<?php

namespace CodeDistortion\Adapt;

use CodeDistortion\Adapt\Adapters\LaravelMySQLAdapter;
use CodeDistortion\Adapt\Adapters\LaravelPostgreSQLAdapter;
use CodeDistortion\Adapt\Adapters\LaravelSQLiteAdapter;
use CodeDistortion\Adapt\DI\DIContainer;
use CodeDistortion\Adapt\DTO\ConfigDTO;
use CodeDistortion\Adapt\DTO\DatabaseMetaInfo;
use CodeDistortion\Adapt\DTO\ResolvedSettingsDTO;
use CodeDistortion\Adapt\DTO\SnapshotMetaInfo;
use CodeDistortion\Adapt\DTO\VersionsDTO;
use CodeDistortion\Adapt\Exceptions\AdaptBuildException;
use CodeDistortion\Adapt\Exceptions\AdaptConfigException;
use CodeDistortion\Adapt\Exceptions\AdaptRemoteBuildException;
use CodeDistortion\Adapt\Exceptions\AdaptSnapshotException;
use CodeDistortion\Adapt\Exceptions\AdaptTransactionException;
use CodeDistortion\Adapt\Support\DatabaseBuilderTraits\ConfigAdapterAndDriverTrait;
use CodeDistortion\Adapt\Support\DatabaseBuilderTraits\DatabaseTrait;
use CodeDistortion\Adapt\Support\DatabaseBuilderTraits\LogTrait;
use CodeDistortion\Adapt\Support\DatabaseBuilderTraits\OnlyExecuteOnceTrait;
use CodeDistortion\Adapt\Support\DatabaseBuilderTraits\ReuseJournalTrait;
use CodeDistortion\Adapt\Support\DatabaseBuilderTraits\ReuseTransactionTrait;
use CodeDistortion\Adapt\Support\DatabaseBuilderTraits\VerificationTrait;
use CodeDistortion\Adapt\Support\HasConfigDTOTrait;
use CodeDistortion\Adapt\Support\Hasher;
use CodeDistortion\Adapt\Support\Settings;
use CodeDistortion\Adapt\Support\VersionSupport;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Build a database ready for use in tests.
 */
class DatabaseBuilder
{
    use ConfigAdapterAndDriverTrait;
    use DatabaseTrait;
    use HasConfigDTOTrait;
    use LogTrait;
    use OnlyExecuteOnceTrait;
    use ReuseTransactionTrait;
    use ReuseJournalTrait;
    use VerificationTrait;



    /** @var string The framework currently being used. */
    protected $framework;

    /** @var string[][] The available database adapters. */
    private $availableDBAdapters = [
        'laravel' => [
            'mysql' => LaravelMySQLAdapter::class,
            'sqlite' => LaravelSQLiteAdapter::class,
            'pgsql' => LaravelPostgreSQLAdapter::class,
        ],
    ];

    /** @var DIContainer The dependency-injection container to use. */
    private $di;

    /** @var callable The closure to call to get the driver for a connection. */
    private $pickDriverClosure;

    /** @var Hasher Builds and checks checksums. */
    private $hasher;



    /** @var ResolvedSettingsDTO|null The build-settings when they've been resolved. */
    private $resolvedSettingsDTO;



    /**
     * Constructor.
     *
     * @param string      $framework         The framework currently being used.
     * @param DIContainer $di                The dependency-injection container to use.
     * @param ConfigDTO   $configDTO         A DTO containing the settings to use.
     * @param Hasher      $hasher            The Hasher object to use.
     * @param callable    $pickDriverClosure A closure that will return the driver for the given connection.
     */
    public function __construct(
        string $framework,
        DIContainer $di,
        ConfigDTO $configDTO,
        Hasher $hasher,
        callable $pickDriverClosure
    ) {
        $this->framework = $framework;
        $this->di = $di;
        $this->configDTO = $configDTO;
        $this->hasher = $hasher;
        $this->pickDriverClosure = $pickDriverClosure;

        $this->recordDriverAbilities();
    }





    /**
     * Build a database ready to test with - migrate and seed the database etc (only if it's not ready-to-go already).
     *
     * @return static
     * @throws Throwable When something goes wrong.
     */
    public function execute(): self
    {
        $logTimer = $this->di->log->newTimer();

        $this->onlyExecuteOnce();
        $this->resetDbAdapter();
        $this->recordDriverAbilities();
        $this->prePreparationChecks();
        $this->prepareDB();

        $this->logTotalTimeTaken($logTimer);

        return $this;
    }

    /**
     * Add a line to the log showing how long the total preparation time was.
     *
     * @param integer $logTimer The timer measuring the overall time taken.
     * @return void
     */
    private function logTotalTimeTaken(int $logTimer)
    {
        // for phpstan - it will exist
        if (!$this->resolvedSettingsDTO) {
            return;
        }

        $database = $this->configDTO->database;
        $connection = $this->configDTO->connection;
        $existed = $this->resolvedSettingsDTO->databaseExistedBefore;
        $reused = $this->resolvedSettingsDTO->databaseWasReused;
        $reusing = $reused ? "Reusing" : ($existed ? "Rebuilt" : "Built");
        $emoji = $this->resolvedSettingsDTO->databaseWasReused ? '😎' : '🏗️ ';

        if ($this->di->log->currentVerbosity() == 0) {
            $message = "$reusing $connection database \"$database\" $emoji";
            $this->di->log->debug($message, $logTimer);
        } else {
            $message = "$reusing database \"$database\" - total preparation time $emoji";
            $this->di->log->vDebug($message, $logTimer, true);
        }
    }

    /**
     * Perform things after building, but BEFORE the test has run.
     *
     * @return void
     */
    public function runPostBuildSteps()
    {
        $this->recordVerificationStart();
        $this->recordJournalingStart();
        $this->startTransaction();
    }

    /**
     * Perform things after the TEST has run.
     *
     * @param boolean $isLast Whether this is the last Builder to run or not.
     * @return void
     * @throws AdaptTransactionException When the re-use transaction was committed.
     */
    public function runPostTestSteps($isLast)
    {
        // work out when the new-line should be added
        $d = $isLast && $this->configDTO->shouldVerifyData();
        $c = $isLast && $this->configDTO->shouldUseJournal() && !$d;
        $b = $isLast && $this->configDTO->shouldVerifyStructure() && !$c && !$d;
        $a = $isLast && $this->configDTO->shouldUseTransaction() && !$b && !$c && !$d;

        $logTimer = $this->di->log->newTimer();

        $this->checkForRolledBackTransaction();
        $this->rollBackTransaction();
        $this->checkForCommittedTransaction($logTimer, $a);

        $this->verifyDatabaseStructure($b);

        $this->reverseJournal($c);

        $this->verifyDatabaseData($d);
        $this->recordVerificationStop();
    }



    /**
     * Record extra details about the driver's abilities.
     *
     * @return void
     * @throws AdaptConfigException
     */
    private function recordDriverAbilities()
    {
        // update $configDTO with some extra settings now that the driver is known
        $this->configDTO->dbAdapterSupport($this->dbAdapter()->build->supportsReuse(), $this->dbAdapter()->snapshot->supportsSnapshots(), $this->dbAdapter()->build->supportsScenarios(), $this->dbAdapter()->reuseTransaction->supportsTransactions(), $this->dbAdapter()->reuseJournal->supportsJournaling(), $this->dbAdapter()->verifier->supportsVerification());
    }

    /**
     * Perform any checks that that need to happen before building a database.
     *
     * @return void
     */
    private function prePreparationChecks()
    {
        $this->configDTO->ensureThatSessionDriversMatch();
    }



    /**
     * Reuse the existing database, populate it from a snapshot or build it from scratch - whatever is necessary.
     *
     * @return void
     */
    private function prepareDB()
    {
        $logTimer = $this->di->log->newTimer();

        $reusedLocally = $this->reuseRemotelyBuiltDBLocallyIfPossible($logTimer);
        if ($reusedLocally) {
            return;
        }

        $this->logTitle();

        $forceRebuild = ($reusedLocally === false); // false means it was built before, but couldn't be reused

        $this->configDTO->shouldBuildRemotely()
            ? $this->buildDBRemotely($forceRebuild, $logTimer)
            : $this->buildDBLocally($forceRebuild);
    }





    /**
     * Check if the database was built remotely, earlier in the test-run. Then re-use it if it can be.
     *
     * (This avoids needing to make a remote request to build when the checksums have already been calculated).
     *
     * @param integer $logTimer The timer, started a little earlier.
     * @return boolean|null Null = irrelevant (just build it), false = it exists but can't be reused (force-rebuild).
     */
    private function reuseRemotelyBuiltDBLocallyIfPossible(int $logTimer)
    {
        $reuseLocally = $this->checkLocallyIfRemotelyBuiltDBCanBeReused();

        if ($reuseLocally) {
            $this->reuseRemotelyBuiltDB($logTimer);
        }

        return $reuseLocally;
    }

    /**
     * Check if the current database was built remotely earlier in the test-run, and can be re-used now.
     *
     * @return boolean|null Null = irrelevant (just build it), false = it exists but can't be reused (force-rebuild).
     * @throws Throwable When something goes wrong.
     */
    private function checkLocallyIfRemotelyBuiltDBCanBeReused()
    {
        try {

            if (!$this->configDTO->shouldBuildRemotely()) {
                return null;
            }

            $resolvedSettingsDTO = $this->getTheRelevantPreviousResolvedSettingsDTO();
            if (!$resolvedSettingsDTO) {
                return null;
            }

            $origDatabase = $this->getCurrentDatabase();
            $this->silentlyUseDatabase((string) $resolvedSettingsDTO->database);

            $return = $this->canReuseDB(
                $resolvedSettingsDTO->buildChecksum,
                $resolvedSettingsDTO->scenarioChecksum,
                (string) $resolvedSettingsDTO->database,
                false,
                null
            );

            // restore it back so it can be officially changed later
            $this->silentlyUseDatabase((string) $origDatabase);

            return $return;

        } catch (Throwable $e) {
            throw $this->transformAnAccessDeniedException($e);
        }
    }

    /**
     * Grab the previously built ResolvedSettingsDTO (if it exists).
     *
     * @return ResolvedSettingsDTO|null
     */
    private function getTheRelevantPreviousResolvedSettingsDTO()
    {
        if (!$this->configDTO->connectionExists) {
            return null;
        }

        return Settings::getResolvedSettingsDTO($this->hasher->currentScenarioChecksum());
    }

    /**
     * Reuse the database that was built remotely.
     *
     * @param integer $logTimer The timer, started a little earlier.
     * @return void
     * @throws Throwable When something goes wrong.
     */
    private function reuseRemotelyBuiltDB(int $logTimer)
    {
        try {

            if ($this->configDTO->shouldInitialise()) {
                $this->initialise();
            }

            $this->resolvedSettingsDTO = $this->getTheRelevantPreviousResolvedSettingsDTO();
            ($resolvedSettingsDTO = $this->resolvedSettingsDTO) ? $resolvedSettingsDTO->databaseWasReused(true) : null; // it was re-used this time

            $connection = $this->configDTO->connection;
            $database = (string) (($resolvedSettingsDTO = $this->resolvedSettingsDTO) ? $resolvedSettingsDTO->database : null);
            $buildChecksum = ($resolvedSettingsDTO = $this->resolvedSettingsDTO) ? $resolvedSettingsDTO->buildChecksum : null;
            $this->configDTO->remoteBuildUrl = null; // stop debug output from showing that it's being built remotely
            $this->hasher->buildChecksumWasPreCalculated($buildChecksum);

            $this->logTitle();
            $this->logHttpRequestWasSaved($database, $logTimer);

            if (!$this->configDTO->shouldInitialise()) {
                $this->di->log->vDebug("Not using connection \"$connection\" locally");
                return;
            }

            $this->logTheUsedSettings();
            $this->useDatabase($database);
            $this->di->log->vDebug("The existing database \"$database\" can be reused");
            $this->updateMetaTable();

        } catch (Throwable $e) {
            throw $this->transformAnAccessDeniedException($e);
        }
    }





    /**
     * Perform the process of building (or reuse an existing) database - locally.
     *
     * @param boolean $forceRebuild Should the database be rebuilt anyway (no need to double-check)?.
     * @return void
     * @throws AdaptConfigException When building failed.
     */
    private function buildDBLocally(bool $forceRebuild)
    {
        $this->initialise();

        // is only used when not null
        $this->hasher->buildChecksumWasPreCalculated($this->configDTO->preCalculatedBuildChecksum);

        $this->resolvedSettingsDTO = $this->buildResolvedSettingsDTO($this->pickDatabaseName());

        $this->logTheUsedSettings();

        $this->pickDatabaseNameAndUse();

        $reused = $this->buildOrReuseDBLocally($forceRebuild);

        ($resolvedSettingsDTO = $this->resolvedSettingsDTO) ? $resolvedSettingsDTO->databaseWasReused($reused) : null;
    }

    /**
     * Initialise this object ready for running.
     *
     * @return void
     * @throws AdaptConfigException When the connection doesn't exist.
     * @throws AdaptBuildException When the database isn't compatible with browser tests.
     */
    private function initialise()
    {
        if (!$this->configDTO->connectionExists) {
            throw AdaptConfigException::invalidConnection($this->configDTO->connection);
        }

        if (($this->configDTO->isBrowserTest) && (!$this->dbAdapter()->build->isCompatibleWithBrowserTests())) {
            throw AdaptBuildException::databaseNotCompatibleWithBrowserTests((string) $this->configDTO->driver);
        }
    }

    /**
     * Build or reuse an existing database - locally.
     *
     * @param boolean $forceRebuild Should the database be rebuilt anyway (no need to double-check)?.
     * @return boolean Returns true if the database was reused.
     * @throws Throwable When the database couldn't be used.
     */
    private function buildOrReuseDBLocally(bool $forceRebuild): bool
    {
//        $this->di->log->vDebug("Preparing database \"{$this->configDTO->database}\"…");

        try {
            $logTimer = $this->di->log->newTimer();

            $canReuse = $this->canReuseDB(
                $this->hasher->getBuildChecksum(),
                $this->hasher->currentScenarioChecksum(),
                (string) $this->configDTO->database,
                $forceRebuild,
                $logTimer
            );

            $canReuse ? $this->updateMetaTable() : $this->buildDBFresh();

            return $canReuse;

        } catch (Throwable $e) {
            throw $this->transformAnAccessDeniedException($e);
        }
    }



    /**
     * Check if the current database can be re-used.
     *
     * @param string|null  $buildChecksum    The current build-checksum.
     * @param string|null  $scenarioChecksum The current scenario-checksum.
     * @param string       $database         The current database to check.
     * @param boolean      $forceRebuild     Should the database be rebuilt anyway (no need to double-check)?.
     * @param integer|null $logTimer         The timer, started a little earlier.
     * @return boolean
     */
    private function canReuseDB(
        $buildChecksum,
        $scenarioChecksum,
        string $database,
        bool $forceRebuild,
        $logTimer
    ): bool {

        if ($forceRebuild) {
            return false;
        }

        if (!$this->configDTO->reusingDB()) {
            return false;
        }

        if ($this->configDTO->forceRebuild) {
            return false;
        }

        $isReusable = $this->dbAdapter()->reuseMetaData->dbIsCleanForReuse(
            $buildChecksum,
            $scenarioChecksum,
            $this->configDTO->projectName,
            $database
        );

        if (!$logTimer) {
            return $isReusable;
        }
        if (!$isReusable && !$this->di->db->currentDatabaseExists()) {
            return false;
        }

        if ($isReusable) {
            $this->di->log->vDebug("The existing database \"$database\" can be reused", $logTimer);
        } else {
            $reason = $this->dbAdapter()->reuseMetaData->getCantReuseReason();
            $this->di->log->vDebug("Database \"$database\" cannot be reused", $logTimer);
            $this->di->log->vDebug("(Reason: $reason)");
        }

        return $isReusable;
    }

    /**
     * Create the re-use meta-data table - only when snapshot files are simply copied.
     *
     * @return void
     */
    private function createReuseMetaDataTableIfImportFilesAreSimplyCopied()
    {
        if (!$this->dbAdapter()->snapshot->snapshotFilesAreSimplyCopied()) {
            return;
        }

        $this->createReuseMetaDataTable();
    }

    /**
     * Create the re-use meta-data table - only when snapshot files are NOT simply copied.
     *
     * @return void
     */
    private function createReuseMetaDataTableIfImportFilesAreNotSimplyCopied()
    {
        if ($this->dbAdapter()->snapshot->snapshotFilesAreSimplyCopied()) {
            return;
        }

        $this->createReuseMetaDataTable();
    }

    /**
     * Create the re-use meta-data table.
     *
     * @return void
     */
    private function createReuseMetaDataTable()
    {
        // don't bother if the database simply disappears afterwards
        if ($this->dbAdapter()->build->databaseIsEphemeral()) {
            return;
        }

        $logTimer = $this->di->log->newTimer();

        $this->dbAdapter()->reuseMetaData->createReuseMetaDataTable(
            $this->origDBName(),
            $this->hasher->getBuildChecksum(),
            $this->hasher->currentSnapshotChecksum(),
            $this->hasher->currentScenarioChecksum()
        );

        $this->configDTO->dbSupportsReUse
            ? $this->di->log->vDebug("Set up the re-use meta-data", $logTimer)
            : $this->di->log->vDebug("Set up the meta-data", $logTimer);
    }

    /**
     * Update the last-used field in the meta-table.
     *
     * @return void
     */
    private function updateMetaTable()
    {
        $logTimer = $this->di->log->newTimer();

        $this->dbAdapter()->reuseMetaData->updateMetaTable($this->hasher->currentScenarioChecksum());

        $this->di->log->vDebug("Refreshed the re-use meta-data", $logTimer);
    }

    /**
     * Build the database fresh.
     *
     * @return void
     */
    private function buildDBFresh()
    {
        $this->resetDBIfSnapshotsFilesAreNotSimplyCopied();

        $this->canUseSnapshots()
            ? $this->buildDBFromSnapshot()
            : $this->buildDBFromScratch();
    }

    /**
     * Check if snapshots can be used here.
     *
     * @return boolean
     */
    private function canUseSnapshots(): bool
    {
        return $this->configDTO->snapshotsAreEnabled() && $this->dbAdapter()->snapshot->supportsSnapshots();
    }

    /**
     * Reset the database ready to build into - only when snapshot files are simply copied.
     *
     * @return void
     */
    private function resetDBIfSnapshotFilesAreSimplyCopied()
    {
        if (!$this->dbAdapter()->snapshot->snapshotFilesAreSimplyCopied()) {
            return;
        }

        $this->resetDB();
    }

    /**
     * Reset the database ready to build into - only when snapshot files are NOT simply copied.
     *
     * @return void
     */
    private function resetDBIfSnapshotsFilesAreNotSimplyCopied()
    {
        if ($this->dbAdapter()->snapshot->snapshotFilesAreSimplyCopied()) {
            return;
        }

        $this->resetDB();
    }

    /**
     * Reset the database ready to build into.
     *
     * @return void
     */
    private function resetDB()
    {
        $exists = $this->di->db->currentDatabaseExists();
        $this->dbAdapter()->build->resetDB($exists);

        ($resolvedSettingsDTO = $this->resolvedSettingsDTO) ? $resolvedSettingsDTO->databaseExistedBefore($exists) : null;
    }

    /**
     * Build the database fresh, loading from a snapshot if available.
     *
     * @return void
     */
    private function buildDBFromSnapshot()
    {
        $seeders = $this->configDTO->pickSeedersToInclude();
        $seedersLeftToRun = [];

        if ($this->trySnapshots($seeders, $seedersLeftToRun)) {
            $this->updateMetaTable();
            if (count($seedersLeftToRun)) {
                $this->seed($seedersLeftToRun);
                $this->takeSnapshotAfterSeeders();
            }
            $this->setUpVerification();
            $this->setUpJournaling();
        } else {
            $this->buildDBFromScratch();
        }
    }

    /**
     * Build the database fresh (no import from snapshot).
     *
     * @return void
     */
    private function buildDBFromScratch()
    {
        // the db may have been reset above in buildDBFresh(),
        // if it wasn't, do it now to make sure it exists and is empty
        $this->resetDBIfSnapshotFilesAreSimplyCopied();

        $this->createReuseMetaDataTableIfImportFilesAreNotSimplyCopied();
        $this->importInitialImports();
        $this->createReuseMetaDataTableIfImportFilesAreSimplyCopied();
        $this->migrate();
        $this->takeSnapshotAfterMigrations();
        $this->seed();
        $this->takeSnapshotAfterSeeders();
        $this->setUpVerification();
        $this->setUpJournaling();
    }



    /**
     * Import the database dumps needed before the migrations run.
     *
     * @return void
     * @throws AdaptSnapshotException When snapshots aren't allowed for this type of database.
     */
    private function importInitialImports()
    {
        $initialImports = $this->configDTO->pickInitialImports();
        if (!count($initialImports)) {
            return;
        }

        if (!$this->dbAdapter()->snapshot->supportsSnapshots()) {
            throw AdaptSnapshotException::importsNotAllowed(
                (string) $this->configDTO->driver,
                (string) $this->configDTO->database
            );
        }

        foreach ($initialImports as $path) {
            $logTimer = $this->di->log->newTimer();
            // will throw exception if the file doesn't exist
            $this->dbAdapter()->snapshot->importSnapshot($path, true);
            $this->di->log->vDebug('Initial import: "' . $path . '" - successful', $logTimer);
        }
    }



    /**
     * Run the migrations.
     *
     * @return void
     * @throws AdaptConfigException When the migration path isn't valid.
     */
    private function migrate()
    {
        $migrationsPath = is_string($this->configDTO->migrations)
            ? $this->configDTO->migrations
            : (bool) $this->configDTO->migrations;

        if (!mb_strlen((string) $migrationsPath)) {
            return;
        }

        if (is_string($migrationsPath)) {
            if (!$this->di->filesystem->dirExists((string) realpath($migrationsPath))) {
                throw AdaptConfigException::migrationsPathInvalid($migrationsPath);
            }
        } else {
            $migrationsPath = null;
        }

        $this->dbAdapter()->build->migrate($migrationsPath);
    }



    /**
     * Run the seeders.
     *
     * @param string[]|null $seeders The seeders to run - will run all if not passed.
     * @return void
     */
    private function seed(array $seeders = null)
    {
        if (!$this->configDTO->seedingIsAllowed()) {
            return;
        }

        if (is_null($seeders)) {
            $seeders = $this->configDTO->pickSeedersToInclude();
        }

        if (!count($seeders)) {
            return;
        }

        $this->dbAdapter()->build->seed($seeders);
    }



    /**
     * Take the snapshot that would be taken after the migrations have been run.
     *
     * @return void
     */
    private function takeSnapshotAfterMigrations()
    {
        if (!$this->configDTO->shouldTakeSnapshotAfterMigrations()) {
            return;
        }

        $seedersRun = []; // i.e. no seeders
        $this->takeDBSnapshot($seedersRun);
    }

    /**
     * Take the snapshot that would be taken after the seeders have been run.
     *
     * @return void
     */
    private function takeSnapshotAfterSeeders()
    {
        if (!$this->configDTO->shouldTakeSnapshotAfterSeeders()) {
            return;
        }

        $seedersRun = $this->configDTO->pickSeedersToInclude(); // i.e. all seeders
        $this->takeDBSnapshot($seedersRun);
    }



    /**
     * Take a snapshot (dump) of the current database.
     *
     * @param string[] $seeders The seeders that are included in this database.
     * @return void
     */
    private function takeDBSnapshot(array $seeders)
    {
        if (!$this->canUseSnapshots()) {
            return;
        }

        $logTimer = $this->di->log->newTimer();

        $snapshotPath = $this->generateSnapshotPath($seeders);
        $this->dbAdapter()->snapshot->takeSnapshot($snapshotPath);

        $this->di->log->vDebug('Snapshot save: "' . $snapshotPath . '" - successful', $logTimer);
    }

    /**
     * Use the snapshot if it exits, trying one less seeder each time until one is found (or exhausted).
     *
     * @param string[] $seeders          The seeders to include.
     * @param string[] $seedersLeftToRun Array that will be populated with seeders that haven't been included.
     * @return boolean
     */
    private function trySnapshots(array $seeders, array &$seedersLeftToRun): bool
    {
        $seedersLeftToRun = [];
        do {
            if ($this->trySnapshot($seeders)) {
                return true;
            }
            if (!count($seeders)) {
                return false;
            }
            array_unshift($seedersLeftToRun, array_pop($seeders));
        } while (true);
    }

    /**
     * Generate the path that will be used for the snapshot.
     *
     * @param string[] $seeders The seeders that are included in the snapshot.
     * @return string
     */
    private function generateSnapshotPath(array $seeders): string
    {
        return $this->dbAdapter()->name->generateSnapshotPath(
            $this->hasher->generateSnapshotFilenameChecksumPart($seeders)
        );
    }

    /**
     * Use the snapshot if it exits.
     *
     * @param string[] $seeders The seeders to include.
     * @return boolean
     */
    private function trySnapshot(array $seeders): bool
    {
        $logTimer = $this->di->log->newTimer();

        $snapshotPath = $this->generateSnapshotPath($seeders);

        if (!$this->di->filesystem->fileExists($snapshotPath)) {
            $this->di->log->vDebug('Snapshot import: "' . $snapshotPath . '" - not found', $logTimer);
            return false;
        }

        if (!$this->dbAdapter()->snapshot->importSnapshot($snapshotPath)) {
            $this->di->log->vDebug('Snapshot import: "' . $snapshotPath . '" - FAILED', $logTimer);
            return false;
        }

        $this->di->filesystem->touch($snapshotPath); // so the stale grace-period starts again

        $this->di->log->vDebug('Snapshot import: "' . $snapshotPath . '" - successful', $logTimer);
        return true;
    }



    /**
     * Perform the process of building (or reuse an existing) database - remotely.
     *
     * @param boolean $forceRebuild Should the database be rebuilt anyway (no need to double-check)?.
     * @param integer $logTimer     The timer, started a little earlier.
     * @return void
     * @throws AdaptRemoteBuildException When the database type isn't allowed to be built remotely.
     */
    private function buildDBRemotely(bool $forceRebuild, int $logTimer)
    {
        if (!$this->dbAdapter()->build->canBeBuiltRemotely()) {
            throw AdaptRemoteBuildException::databaseTypeCannotBeBuiltRemotely((string) $this->configDTO->driver);
        }

        if ($this->configDTO->shouldInitialise()) {
            $this->initialise();
        }



        $this->di->log->vDebug("Building the database remotely…");

        $this->resolvedSettingsDTO = $this->sendBuildRemoteRequest($forceRebuild);
        Settings::storeResolvedSettingsDTO($this->hasher->currentScenarioChecksum(), $this->resolvedSettingsDTO);

        $connection = $this->resolvedSettingsDTO->connection;
        $database = (string) $this->resolvedSettingsDTO->database;

        $message = $this->resolvedSettingsDTO->databaseWasReused
            ? "Database \"$database\" was reused. Remote preparation time"
            : "Database \"$database\" was built. Remote preparation time";
        $this->di->log->vDebug($message, $logTimer);

        $this->resolvedSettingsDTO->remoteVersionsDTO = $this->resolvedSettingsDTO->versionsDTO;
        $this->resolvedSettingsDTO->versionsDTO = $this->buildVersionsDTO();

        if (!$this->configDTO->shouldInitialise()) {
            $this->di->log->vDebug("Not using connection \"$connection\" locally");
            return;
        }

        $this->logTheUsedSettings();
        $this->useDatabase($database);

        if ($this->resolvedSettingsDTO && $this->resolvedSettingsDTO->databaseWasReused) {
            $this->di->log->vDebug("The existing database \"$database\" can be reused");
        }
    }

    /**
     * Send the http request to build the database remotely.
     *
     * @param boolean $forceRebuild Should the database be rebuilt anyway (no need to double-check)?.
     * @return ResolvedSettingsDTO
     * @throws AdaptRemoteBuildException When the database couldn't be built.
     */
    private function sendBuildRemoteRequest(bool $forceRebuild): ResolvedSettingsDTO
    {
        $httpClient = new HttpClient(['timeout' => 60 * 10]);
        $url = $this->buildRemoteUrl();
        $data = ['configDTO' => $this->prepareConfigForRemoteRequest($url, $forceRebuild)->buildPayload()];

        try {
            $response = $httpClient->post(
                $this->buildRemoteUrl(),
                ['form_params' => $data]
            );

            $resolvedSettingsDTO = ResolvedSettingsDTO::buildFromPayload((string) $response->getBody());
            $resolvedSettingsDTO->builtRemotely(true, $url);

            Hasher::rememberRemoteBuildChecksum($url, (string) $resolvedSettingsDTO->buildChecksum);

            return $resolvedSettingsDTO;

        } catch (GuzzleException $e) {
            throw $this->buildAdaptRemoteBuildException($url, $e);
        }
    }

    /**
     * Build a ConfigDTO ready to send in the remote-build request.
     *
     * @param string  $remoteBuildUrl The remote-build url.
     * @param boolean $forceRebuild   Force the database to be rebuilt.
     * @return ConfigDTO
     */
    private function prepareConfigForRemoteRequest(string $remoteBuildUrl, bool $forceRebuild): ConfigDTO
    {
        $configDTO = clone $this->configDTO;

        if ($forceRebuild) {
            $configDTO->forceRebuild = true;
        }

        // save time by telling the remote Adapt installation what the build-checksum was from last time.
        $configDTO->preCalculatedBuildChecksum(Hasher::getRemoteBuildChecksum($remoteBuildUrl));
        return $configDTO;
    }

    /**
     * Build a AdaptRemoteBuildException.
     *
     * @param string          $url The remote-build url.
     * @param GuzzleException $e   The exception that occurred.
     * @return AdaptRemoteBuildException
     */
    private function buildAdaptRemoteBuildException(string $url, GuzzleException $e): AdaptRemoteBuildException
    {
        /** @var ?ResponseInterface $response */
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;

        return AdaptRemoteBuildException::remoteBuildFailed(
            $this->configDTO->connection,
            $url,
            $response,
            $e
        );
    }

    /**
     * Build the url to use when building the database remotely.
     *
     * @return string
     * @throws AdaptRemoteBuildException When the url is invalid.
     */
    private function buildRemoteUrl(): string
    {
        $remoteUrl = $origUrl = (string) $this->configDTO->remoteBuildUrl;
        $pos = mb_strpos($remoteUrl, '?');
        if ($pos !== false) {
            $remoteUrl = mb_substr($remoteUrl, 0, $pos);
        }

        $remoteUrl = "$remoteUrl/" . Settings::REMOTE_BUILD_REQUEST_PATH;

        $parts = parse_url($remoteUrl);
        if (!is_array($parts)) {
            throw AdaptRemoteBuildException::remoteBuildUrlInvalid($origUrl);
        }
        if ((!isset($parts['scheme'])) || (!isset($parts['host']))) {
            throw AdaptRemoteBuildException::remoteBuildUrlInvalid($origUrl);
        }

        $origPath = $parts['path'] ?? '';
        $path = (string) preg_replace('%//+%', '/', $origPath);
        return str_replace($origPath, $path, $remoteUrl);
    }



    /**
     * Build DatabaseMetaInfo objects for the existing databases.
     *
     * @return DatabaseMetaInfo[]
     */
    public function buildDatabaseMetaInfos(): array
    {
        if (!$this->configDTO->dbSupportsReUse) {
            return [];
        }

        return $this->dbAdapter()->find->findDatabases($this->hasher->getBuildChecksum());
    }



    /**
     * Build SnapshotMetaInfo objects for the snapshots in the storage directory.
     *
     * @return SnapshotMetaInfo[]
     */
    public function buildSnapshotMetaInfos(): array
    {
        if (!$this->di->filesystem->dirExists($this->configDTO->storageDir)) {
            return [];
        }

        $logTimer = $this->di->log->newTimer();

        try {
            $filePaths = $this->di->filesystem->filesInDir(Settings::snapshotDir($this->configDTO->storageDir));
            $this->di->log->vvDebug("Retrieved snapshot list", $logTimer);
        } catch (Throwable $e) {
            $this->di->log->vvWarning("Could not retrieve snapshot list", $logTimer);
            return [];
        }

        $logTimer2 = $this->di->log->newTimer();

        $snapshotMetaInfos = [];
        $attemptedCount = 0;
        foreach ($filePaths as $path) {

            // ignore other files
//            $temp = (array) preg_split('/[\\\\\/]+/', $path);
//            $filename = array_pop($temp);
//            if (in_array($filename, ['.gitignore', 'purge-lock'])) {
//                continue;
//            }

            $attemptedCount++;

            $logTimer3 = $this->di->log->newTimer();

            try {
                $snapshotMetaInfo = $this->buildSnapshotMetaInfo($path);
                $snapshotMetaInfos[] = $snapshotMetaInfo;

                $path = $snapshotMetaInfo ? $snapshotMetaInfo->path : $path;
                $usable = $snapshotMetaInfo
                    ? ($snapshotMetaInfo->isValid
                        ? '(usable)'
                        : "(stale" . ($snapshotMetaInfo->shouldPurgeNow() ? '' : ' - within grace period') . ")")
                    : '(not usable - ignoring)';
                $this->di->log->vvDebug("- Found snapshot \"$path\" $usable", $logTimer3);

            } catch (Throwable $e) {
                $this->di->log->vvWarning("Could not read from snapshot \"$path\"", $logTimer3);
            }
        }

        if (!$attemptedCount) {
            $this->di->log->vvDebug("- No snapshots were found", $logTimer2);
        }

        return array_values(array_filter($snapshotMetaInfos));
    }

    /**
     * Build a SnapshotMetaInfo object for a snapshot.
     *
     * @param string $path The file to build a SnapshotMetaInfo object for.
     * @return SnapshotMetaInfo|null
     */
    private function buildSnapshotMetaInfo(string $path)
    {
        $temp = explode(DIRECTORY_SEPARATOR, $path);
        $filename = (string) array_pop($temp);
        $prefix = $this->configDTO->snapshotPrefix;

        if (mb_substr($filename, 0, mb_strlen($prefix)) != $prefix) {
            return null;
        }

        $filename = mb_substr($filename, mb_strlen($prefix));

        $accessTS = fileatime($path);
        $accessDT = new DateTime("@$accessTS");
        $accessDT->setTimezone(new DateTimeZone('UTC'));

        $snapshotMetaInfo = new SnapshotMetaInfo(
            $path,
            $filename,
            $accessDT,
            $this->hasher->filenameHasBuildChecksum($filename),
            function () use ($path) {
                return $this->di->filesystem->size($path);
            },
            $this->configDTO->staleGraceSeconds
        );
        $snapshotMetaInfo->setDeleteCallback(function () use ($snapshotMetaInfo) {
            return $this->removeSnapshotFile($snapshotMetaInfo);
        });
        return $snapshotMetaInfo;
    }

    /**
     * Remove the given snapshot file.
     *
     * @param SnapshotMetaInfo $snapshotMetaInfo The info object representing the snapshot file.
     * @return boolean
     * @throws AdaptSnapshotException When the snapshot can't be removed.
     */
    private function removeSnapshotFile(SnapshotMetaInfo $snapshotMetaInfo): bool
    {
        if (!$this->di->filesystem->fileExists($snapshotMetaInfo->path)) {
            return false;
        }

        try {
            if (!$this->di->filesystem->unlink($snapshotMetaInfo->path)) {
                throw AdaptSnapshotException::deleteFailed($snapshotMetaInfo->path);
            }
            return true;
        } catch (AdaptSnapshotException $e) {
            throw $e; // just rethrow as is
        } catch (Throwable $e) {
            throw AdaptSnapshotException::deleteFailed($snapshotMetaInfo->path, $e);
        }
    }







    /**
     * Throw a custom exception if the given exception is, or contains a PDOException - "access denied" exception.
     *
     * @param Throwable $e The exception to check.
     * @return Throwable
     */
    private function transformAnAccessDeniedException(Throwable $e): Throwable
    {
        $previous = $e;
        do {
            if ($previous instanceof PDOException) {
                if ($previous->getCode() == 1044) {
                    return AdaptBuildException::accessDenied($e);
                }
            }
            $previous = $previous->getPrevious();
        } while ($previous);

        return $e;
    }





    /**
     * Build a ResolvedSettingsDTO from the current preparation.
     *
     * @param string $database The database used.
     * @return ResolvedSettingsDTO
     */
    private function buildResolvedSettingsDTO(string $database): ResolvedSettingsDTO
    {
        $configDTO = $this->configDTO;
        $buildingLocally = !$configDTO->shouldBuildRemotely();

        return (new ResolvedSettingsDTO())
            ->projectName($configDTO->projectName)
            ->testName($configDTO->testName)
            ->connection($configDTO->connection)
            ->driver((string) $configDTO->driver)
            ->host($this->di->db->getHost())
            ->database($database)
            ->storageDir($configDTO->storageDir)
            ->initialImports($configDTO->pickInitialImports())
            ->migrations($configDTO->migrations)
            ->seeders($configDTO->seedingIsAllowed(), $configDTO->seeders)
            ->builtRemotely(
                $configDTO->shouldBuildRemotely(),
                $configDTO->shouldBuildRemotely() ? $this->buildRemoteUrl() : null
            )->snapshotType($configDTO->snapshotType(), is_string($configDTO->useSnapshotsWhenReusingDB) ? $configDTO->useSnapshotsWhenReusingDB : null, is_string($configDTO->useSnapshotsWhenNotReusingDB) ? $configDTO->useSnapshotsWhenNotReusingDB : null)
            ->isBrowserTest($configDTO->isBrowserTest)
            ->isParallelTest($configDTO->isParallelTest)
            ->usingPest($configDTO->usingPest)
            ->sessionDriver($configDTO->sessionDriver)
            ->transactionReusable($configDTO->shouldUseTransaction())
            ->journalReusable($configDTO->shouldUseJournal())
            ->verifyDatabase($configDTO->verifyDatabase)
            ->forceRebuild($configDTO->forceRebuild)
            ->scenarios(
                $configDTO->usingScenarios(),
                $buildingLocally ? $this->hasher->getBuildChecksum() : null,
                $buildingLocally ? $this->hasher->currentSnapshotChecksum() : null,
                $buildingLocally ? $this->hasher->currentScenarioChecksum() : null
            )
            ->databaseWasReused(true)
            ->versionsDTO($this->buildVersionsDTO())
            ->remoteVersionsDTO(null);
    }

    /**
     * Build a VersionsDTO based on the current software.
     *
     * @return VersionsDTO|null
     */
    private function buildVersionsDTO()
    {
        $connection = $this->configDTO->connection;
        $driver = $this->configDTO->driver;

        $versionsDTO =
            Settings::getResolvedVersionsDTO($connection, $driver)
            ?? VersionSupport::buildVersionDTO($this->dbAdapter(), $this->di->log);

        Settings::storeResolvedVersionsDTO($connection, $driver, $versionsDTO);

        return $versionsDTO;
    }

    /**
     * Get the ResolvedSettingsDTO representing the settings that were used.
     *
     * @return ResolvedSettingsDTO|null
     */
    public function getResolvedSettingsDTO()
    {
        return $this->resolvedSettingsDTO;
    }
}

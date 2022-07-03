<?php

namespace CodeDistortion\Adapt\DTO;

use CodeDistortion\Adapt\Exceptions\AdaptRemoteShareException;
use CodeDistortion\Adapt\Support\Settings;

/**
 * Resolves default setting values when needed.
 */
class ConfigDTO extends AbstractDTO
{
    /**
     * The ConfigDTO version. An exception will be thrown when there's a mismatch between installations of Adapt.
     *
     * @var integer
     */
    public int $dtoVersion;

    /** @var string|null The name of the current project. */
    public ?string $projectName;

    /** @var string The name of the current test. */
    public string $testName;



    /** @var string The database connection to prepare. */
    public string $connection;

    /** @var boolean|null Whether this connection should be made the default or not. null = maybe if no others are. */
    public ?bool $isDefaultConnection;

    /** @var boolean Whether the connection exists or not (it's ok to not exist locally when the building remotely). */
    public bool $connectionExists;

    /** @var string|null The database driver to use when building the database ("mysql", "sqlite" etc). */
    public ?string $driver = null;

    /** @var string The name of the database before being altered. */
    public string $origDatabase;

    /** @var string|null The name of the database to use. */
    public ?string $database = null;

    /** @var string A database name modifier (e.g. Paratest adds a TEST_TOKEN env value to make the db unique). */
    public string $databaseModifier = '';



    /** @var string The directory to store database snapshots in. */
    public string $storageDir;

    /** @var string The prefix to add to snapshot filenames. */
    public string $snapshotPrefix;

    /** @var string The prefix to add to database names. */
    public string $databasePrefix;

    /** @var boolean Whether cache-invalidation is enabled or not. */
    public bool $cacheInvalidationEnabled;

    /** @var string|null The method to check source-files for changes - 'content' / 'modified' / null. */
    public ?string $cacheInvalidationMethod;

    /** @var string[] The files and directories to look through. Changes to files will invalidate dbs and snapshots. */
    public array $checksumPaths;

    /** @var string|null The build-checksum if it has already been calculated - passed to remote Adapt installations. */
    public ?string $preCalculatedBuildChecksum;


    /** @var string[]|string[][] The files to import before the migrations are run. */
    public array $initialImports;

    /** @var boolean|string Should the migrations be run? / migrations location - if not, the db will be empty. */
    public bool|string $migrations;

    /** @var string[] The seeders to run after migrating - will only be run if migrations were run. */
    public array $seeders;

    /** @var string|null The remote Adapt installation to send "build" requests to. */
    public ?string $remoteBuildUrl;

    /** @var boolean Is a browser test being run? If so, this will turn off transaction re-use. */
    public bool $isBrowserTest;

    /** @var boolean Is parallel testing being run? Is just for informational purposes. */
    public bool $isParallelTest;

    /** @var boolean Whether Pest is being used for this test or not. */
    public bool $usingPest;

    /** @var boolean Is this process building a db locally for another remote Adapt installation?. */
    public bool $isRemoteBuild;

    /**
     * The session driver being used - will throw and exception when the remote version is different to
     * $remoteCallerSessionDriver.
     *
     * @var string
     */
    public string $sessionDriver;

    /** @var string|null The session driver being used in the caller Adapt installation. */
    public ?string $remoteCallerSessionDriver;



    /** @var boolean Whether the db supports re-use or not - a record of the setting based on the driver. */
    public bool $dbSupportsReUse;

    /** @var boolean Whether the db supports snapshots or not - a record of the setting based on the driver. */
    public bool $dbSupportsSnapshots;

    /** @var boolean Whether the db supports scenarios or not - a record of the setting based on the driver. */
    public bool $dbSupportsScenarios;

    /** @var boolean Whether the db supports transactions or not - a record of the setting based on the driver. */
    public bool $dbSupportsTransactions;

    /** @var boolean Whether the db supports journaling or not - a record of the setting based on the driver. */
    public bool $dbSupportsJournaling;

    /** @var boolean Whether the db supports verification or not - a record of the setting based on the driver. */
    public bool $dbSupportsVerification;



    /** @var boolean When turned on, databases will be reused using a transaction instead of rebuilding them. */
    public bool $reuseTransaction;

    /** @var boolean When turned on, databases will be reused using journaling instead of rebuilding them. */
    public bool $reuseJournal;

    /** @var boolean When turned on, the database structure and content will be checked after each test. */
    public bool $verifyDatabase;

    /** @var boolean When turned on, dbs will be created for each scenario (based on migrations and seeders etc). */
    public bool $scenarios;

    /** @var string|null Enable snapshots, and specify when to take them. */
    public ?string $snapshots;

    /** @var string|null Snapshots when reusing the database. Derived from $snapshots. */
    public ?string $useSnapshotsWhenReusingDB;

    /** @var string|null Snapshots when NOT reusing the database. Derived from $snapshots. */
    public ?string $useSnapshotsWhenNotReusingDB;

    /** @var boolean When turned on, the database will be rebuilt instead of allowing it to be reused. */
    public bool $forceRebuild;



    /** @var string The path to the "mysql" executable. */
    public string $mysqlExecutablePath;

    /** @var string The path to the "mysqldump" executable. */
    public string $mysqldumpExecutablePath;

    /** @var string The path to the "psql" executable. */
    public string $psqlExecutablePath;

    /** @var string The path to the "pg_dump" executable. */
    public string $pgDumpExecutablePath;



    /** @var integer The number of seconds grace-period before stale databases and snapshots are to be deleted. */
    public int $staleGraceSeconds = 0;



    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->dtoVersion(Settings::CONFIG_DTO_VERSION);
    }



    /**
     * Set the ConfigDTO version.
     *
     * @param integer $dtoVersion The ConfigDTO version.
     * @return static
     */
    public function dtoVersion(int $dtoVersion): self
    {
        $this->dtoVersion = $dtoVersion;
        return $this;
    }

    /**
     * Set the project-name.
     *
     * @param string|null $projectName The name of this project.
     * @return static
     */
    public function projectName(?string $projectName): self
    {
        $this->projectName = $projectName;
        return $this;
    }

    /**
     * Set the current test-name.
     *
     * @param string $testName The name of the current test.
     * @return static
     */
    public function testName(string $testName): self
    {
        $this->testName = $testName;
        return $this;
    }


    /**
     * Set the connection to prepare.
     *
     * @param string $connection The database connection to prepare.
     * @return static
     */
    public function connection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Whether this connection should be made the default or not.
     *
     * @param boolean|null $isDefaultConnection Whether to make this connection default or not.
     * @return static
     */
    public function isDefaultConnection(?bool $isDefaultConnection): self
    {
        $this->isDefaultConnection = $isDefaultConnection;
        return $this;
    }

    /**
     * Set the connectionExists value.
     *
     * @param boolean $connectionExists Whether the connection exists or not (it's ok to not exist locally when the
     *                                  building remotely).
     * @return static
     */
    public function connectionExists(bool $connectionExists): self
    {
        $this->connectionExists = $connectionExists;
        return $this;
    }

    /**
     * Set the database driver to use when building the database ("mysql", "sqlite" etc).
     *
     * @param string $driver The database driver to use.
     * @return static
     */
    public function driver(string $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Set the name of the database before being altered.
     *
     * @param string $origDatabase The name of the original database.
     * @return static
     */
    public function origDatabase(string $origDatabase): self
    {
        $this->origDatabase = $origDatabase;
        return $this;
    }

    /**
     * Set the database to use.
     *
     * @param string|null $database The name of the database to use.
     * @return static
     */
    public function database(?string $database): self
    {
        $this->database = $database;
        return $this;
    }

    /**
     * Set the database-modifier to use (e.g. Paratest adds a TEST_TOKEN env value to make the db unique).
     *
     * @param string $databaseModifier The modifier to use.
     * @return static
     */
    public function databaseModifier(string $databaseModifier): self
    {
        $this->databaseModifier = $databaseModifier;
        return $this;
    }

    /**
     * Set the directory to store database snapshots in.
     *
     * @param string $storageDir The storage directory to use.
     * @return static
     */
    public function storageDir(string $storageDir): self
    {
        $this->storageDir = $storageDir;
        return $this;
    }

    /**
     * Set the prefix to add to snapshot filenames.
     *
     * @param string $snapshotPrefix The prefix to use.
     * @return static
     */
    public function snapshotPrefix(string $snapshotPrefix): self
    {
        $this->snapshotPrefix = $snapshotPrefix;
        return $this;
    }

    /**
     * Set the prefix to add to database names.
     *
     * @param string $databasePrefix The prefix to use.
     * @return static
     */
    public function databasePrefix(string $databasePrefix): self
    {
        $this->databasePrefix = $databasePrefix;
        return $this;
    }

    /**
     * Set the cache-invalidation-enabled setting.
     *
     * @param boolean $cacheInvalidationEnabled Whether cache-invalidation is enabled or not.
     * @return static
     */
    public function cacheInvalidationEnabled(bool $cacheInvalidationEnabled): self
    {
        $this->cacheInvalidationEnabled = $cacheInvalidationEnabled;
        return $this;
    }

    /**
     * Set the method to use when checking for source-file changes.
     *
     * @param string $cacheInvalidationMethod The method to use - 'modified' / 'content'.
     * @return static
     */
    public function cacheInvalidationMethod(string $cacheInvalidationMethod): self
    {
        $this->cacheInvalidationMethod = in_array($cacheInvalidationMethod, ['modified', 'content'], true)
            ? $cacheInvalidationMethod
            : 'modified'; //default

        return $this;
    }

    /**
     * Set the list of directories that can invalidate test-databases and snapshots.
     *
     * @param string[] $checksumPaths The files and directories to look through.
     * @return static
     */
    public function checksumPaths(array $checksumPaths): self
    {
        $this->checksumPaths = $checksumPaths;
        return $this;
    }

    /**
     * Set the pre-calculated build-checksum - passed to remote Adapt installations.
     *
     * @param string|null $preCalculatedBuildChecksum The pre-calculated build-checksum.
     * @return static
     */
    public function preCalculatedBuildChecksum(?string $preCalculatedBuildChecksum): self
    {
        $this->preCalculatedBuildChecksum = $preCalculatedBuildChecksum;
        return $this;
    }



    /**
     * Set the details that affect what is being built (i.e. the database-scenario).
     *
     * @param string[]|string[][] $initialImports            The files to import before the migrations are run.
     * @param boolean|string      $migrations                Should the migrations be run? / the path of the migrations
     *                                                       to run.
     * @param string[]            $seeders                   The seeders to run after migrating.
     * @param string|null         $remoteBuildUrl            The remote Adapt installation to send "build" requests to.
     * @param boolean             $isBrowserTest             Is a browser test running?.
     * @param boolean             $isParallelTest            Is parallel testing being run?.
     * @param boolean             $usingPest                 Whether Pest is being used for this test or not.
     * @param boolean             $isRemoteBuild             Is this process building a db for another Adapt
     *                                                       installation?.
     * @param string              $sessionDriver             The session driver being used.
     * @param string|null         $remoteCallerSessionDriver The session driver being used in the caller Adapt
     *                                                       installation.
     * @return static
     */
    public function buildSettings(
        array $initialImports,
        bool|string $migrations,
        array $seeders,
        ?string $remoteBuildUrl,
        bool $isBrowserTest,
        bool $isParallelTest,
        bool $usingPest,
        bool $isRemoteBuild,
        string $sessionDriver,
        ?string $remoteCallerSessionDriver
    ): self {

        $this->initialImports = $initialImports;
        $this->migrations = $migrations;
        $this->seeders = $seeders;
        $this->remoteBuildUrl = $remoteBuildUrl;
        $this->isBrowserTest = $isBrowserTest;
        $this->isParallelTest = $isParallelTest;
        $this->usingPest = $usingPest;
        $this->isRemoteBuild = $isRemoteBuild;
        $this->sessionDriver = $sessionDriver;
        $this->remoteCallerSessionDriver = $remoteCallerSessionDriver;
        return $this;
    }

    /**
     * Specify the database dump files to import before migrations run.
     *
     * @param string[]|string[][] $initialImports The database dump files to import, one per database type.
     * @return static
     */
    public function initialImports(array $initialImports): self
    {
        $this->initialImports = $initialImports;
        return $this;
    }

    /**
     * Turn migrations on or off, or specify the location of the migrations to run.
     *
     * @param boolean|string $migrations Should the migrations be run? / the path of the migrations to run.
     * @return static
     */
    public function migrations($migrations): self
    {
        $this->migrations = false;
        if ((is_string($migrations) && (mb_strlen($migrations))) || (is_bool($migrations))) {
            $this->migrations = $migrations;
        }
        return $this;
    }

    /**
     * Specify the seeders to run.
     *
     * @param string[] $seeders The seeders to run after migrating.
     * @return static
     */
    public function seeders(array $seeders): self
    {
        $this->seeders = $seeders;
        return $this;
    }

    /**
     * Specify the url to send "remote-build" requests to.
     *
     * @param string|null $remoteBuildUrl The remote Adapt installation to send "build" requests to.
     * @return static
     */
    public function remoteBuildUrl(?string $remoteBuildUrl): self
    {
        $this->remoteBuildUrl = $remoteBuildUrl;
        return $this;
    }

    /**
     * Turn the is-browser-test setting on or off.
     *
     * @param boolean $isBrowserTest Is this test a browser-test?.
     * @return static
     */
    public function isBrowserTest(bool $isBrowserTest): self
    {
        $this->isBrowserTest = $isBrowserTest;
        return $this;
    }

    /**
     * Turn the is-parallel-test setting on or off (is just for informational purposes).
     *
     * @param boolean $isParallelTest Is parallel testing being run?.
     * @return static
     */
    public function isParallelTest(bool $isParallelTest): self
    {
        $this->isParallelTest = $isParallelTest;
        return $this;
    }

    /**
     * Turn the using-pest setting on or off (is just for informational purposes).
     *
     * @param boolean $usingPest Whether Pest is being used for this test or not.
     * @return static
     */
    public function usingPest(bool $usingPest): self
    {
        $this->usingPest = $usingPest;
        return $this;
    }

    /**
     * Turn the is-remote-build setting on or off.
     *
     * @param boolean $isRemoteBuild Is this process building a db for another Adapt installation?.
     * @return static
     */
    public function isRemoteBuild(bool $isRemoteBuild): self
    {
        $this->isRemoteBuild = $isRemoteBuild;
        return $this;
    }

    /**
     * Set the session-driver.
     *
     * @param string $sessionDriver The session driver being used.
     * @return static
     */
    public function sessionDriver(string $sessionDriver): self
    {
        $this->sessionDriver = $sessionDriver;
        return $this;
    }

    /**
     * Set the caller Adapt session-driver.
     *
     * @param string|null $remoteCallerSessionDriver The session driver being used.
     * @return static
     */
    public function remoteCallerSessionDriver(?string $remoteCallerSessionDriver): self
    {
        $this->remoteCallerSessionDriver = $remoteCallerSessionDriver;
        return $this;
    }



    /**
     * Turn the db-supports-re-use setting on or off - a record of the setting based on the driver.
     *
     * @param boolean $dbSupportsReUse        Whether the database supports scenarios or not.
     * @param boolean $dbSupportsSnapshots    Whether the database supports snapshots or not.
     * @param boolean $dbSupportsScenarios    Whether the database supports scenarios or not.
     * @param boolean $dbSupportsTransactions Whether the database supports transactions or not.
     * @param boolean $dbSupportsJournaling   Whether the database supports journaling or not.
     * @param boolean $dbSupportsVerification Whether the database supports verification or not.
     * @return static
     */
    public function dbAdapterSupport(
        bool $dbSupportsReUse,
        bool $dbSupportsSnapshots,
        bool $dbSupportsScenarios,
        bool $dbSupportsTransactions,
        bool $dbSupportsJournaling,
        bool $dbSupportsVerification
    ): self {

        $this->dbSupportsReUse = $dbSupportsReUse;
        $this->dbSupportsSnapshots = $dbSupportsSnapshots;
        $this->dbSupportsScenarios = $dbSupportsScenarios;
        $this->dbSupportsTransactions = $dbSupportsTransactions;
        $this->dbSupportsJournaling = $dbSupportsJournaling;
        $this->dbSupportsVerification = $dbSupportsVerification;
        return $this;
    }

    /**
     * Turn the db-supports-re-use setting on or off - a record of the setting based on the driver.
     *
     * @param boolean $dbSupportsReUse Whether the database supports scenarios or not.
     * @return static
     */
    public function dbSupportsReUse(bool $dbSupportsReUse): self
    {
        $this->dbSupportsReUse = $dbSupportsReUse;
        return $this;
    }

    /**
     * Turn the db-supports-snapshots setting on or off - a record of the setting based on the driver.
     *
     * @param boolean $dbSupportsSnapshots Whether the database supports snapshots or not.
     * @return static
     */
    public function dbSupportsSnapshots(bool $dbSupportsSnapshots): self
    {
        $this->dbSupportsSnapshots = $dbSupportsSnapshots;
        return $this;
    }

    /**
     * Turn the db-supports-scenarios setting on or off - a record of the setting based on the driver.
     *
     * @param boolean $dbSupportsScenarios Whether the database supports scenarios or not.
     * @return static
     */
    public function dbSupportsScenarios(bool $dbSupportsScenarios): self
    {
        $this->dbSupportsScenarios = $dbSupportsScenarios;
        return $this;
    }

    /**
     * Turn the db-supports-transactions setting on or off - a record of the setting based on the driver.
     *
     * @param boolean $dbSupportsTransactions Whether the database supports transactions or not.
     * @return static
     */
    public function dbSupportsTransactions(bool $dbSupportsTransactions): self
    {
        $this->dbSupportsTransactions = $dbSupportsTransactions;
        return $this;
    }

    /**
     * Turn the db-supports-journaling setting on or off - a record of the setting based on the driver.
     *
     * @param boolean $dbSupportsJournaling Whether the database supports journaling or not.
     * @return static
     */
    public function dbSupportsJournaling(bool $dbSupportsJournaling): self
    {
        $this->dbSupportsJournaling = $dbSupportsJournaling;
        return $this;
    }

    /**
     * Turn the db-supports-verification setting on or off - a record of the setting based on the driver.
     *
     * @param boolean $dbSupportsVerification Whether the database supports verification or not.
     * @return static
     */
    public function dbSupportsVerification(bool $dbSupportsVerification): self
    {
        $this->dbSupportsVerification = $dbSupportsVerification;
        return $this;
    }



    /**
     * Set the types of cache to use.
     *
     * @param boolean $reuseTransaction Reuse databases with a transaction?.
     * @param boolean $reuseJournal     Reuse databases with a journal?.
     * @param boolean $verifyDatabase   Perform a check of the db structure and content after each test?.
     * @param boolean $scenarios        Create databases as needed for the database-scenario?.
     * @return static
     */
    public function cacheTools(
        bool $reuseTransaction,
        bool $reuseJournal,
        bool $verifyDatabase,
        bool $scenarios
    ): self {
        $this->reuseTransaction = $reuseTransaction;
        $this->reuseJournal = $reuseJournal;
        $this->verifyDatabase = $verifyDatabase;
        $this->scenarios = $scenarios;
        return $this;
    }

    /**
     * Turn the reuse-transaction setting on or off.
     *
     * @param boolean $reuseTransaction Reuse databases with a transactions?.
     * @return static
     */
    public function reuseTransaction(bool $reuseTransaction): self
    {
        $this->reuseTransaction = $reuseTransaction;
        return $this;
    }

    /**
     * Turn the reuse-journal setting on or off.
     *
     * @param boolean $reuseJournal Reuse databases with a journal?.
     * @return static
     */
    public function reuseJournal(bool $reuseJournal): self
    {
        $this->reuseJournal = $reuseJournal;
        return $this;
    }

    /**
     * Turn the verify-database setting on (or off).
     *
     * @param boolean $verifyDatabase Perform a check of the db structure and content after each test?.
     * @return static
     */
    public function verifyDatabase(bool $verifyDatabase): self
    {
        $this->verifyDatabase = $verifyDatabase;
        return $this;
    }

    /**
     * Turn the scenarios setting on or off.
     *
     * @param boolean $scenarios Create databases as needed for the database-scenario?.
     * @return static
     */
    public function scenarios(bool $scenarios): self
    {
        $this->scenarios = $scenarios;
        return $this;
    }

    /**
     * Set the snapshot setting.
     *
     * @param string|boolean|null $snapshots Take and import snapshots when reusing databases?
     *                                       false
     *                                       / "afterMigrations" / "afterSeeders" / "both"
     *                                       / "!afterMigrations" / "!afterSeeders" / "!both"
     * @return static
     */
    public function snapshots(string|bool|null $snapshots): self
    {
        $this->snapshots = $this->cleanSnapshotValue($snapshots);

        $this->useSnapshotsWhenNotReusingDB = $this->snapshotBase($this->snapshots);

        $this->useSnapshotsWhenReusingDB = $this->snapshotIsImportant($this->snapshots)
            ? $this->useSnapshotsWhenNotReusingDB
            : null;

        return $this;
    }

    /**
     * Check that the $snapshots setting is ok.
     *
     * @param string|boolean|null $snapshots The $snapshots setting to check.
     * @return string|bool|null
     */
    private function cleanSnapshotValue(string|bool|null $snapshots): string|bool|null
    {
        $possible = [
            null, 'afterMigrations', 'afterSeeders', 'both', '!afterMigrations', '!afterSeeders', '!both'
        ];

        return in_array($snapshots, $possible, true)
            ? $snapshots
            : null;
    }

    /**
     * Check if $snapshots are important.
     *
     * @param string|boolean|null $snapshots The $snapshots setting to check.
     * @return bool
     */
    private function snapshotIsImportant(string|bool|null $snapshots): bool
    {
        if (!is_string($snapshots)) {
            return false;
        }
        return mb_substr($snapshots, 0, 1) == '!';
    }

    /**
     * Get the snapshot base value.
     *
     * @param string|boolean|null $snapshots The $snapshots setting to check.
     * @return string|boolean|null
     */
    private function snapshotBase(string|bool|null $snapshots): string|bool|null
    {
        return $this->snapshotIsImportant($snapshots)
            ? mb_substr($snapshots, 1)
            : $snapshots;
    }

    /**
     * Turn the force-rebuild setting on or off.
     *
     * @param boolean $forceRebuild Force the database to be rebuilt (or not).
     * @return static
     */
    public function forceRebuild(bool $forceRebuild): self
    {
        $this->forceRebuild = $forceRebuild;
        return $this;
    }

    /**
     * Set the mysql specific details.
     *
     * @param string $mysqlExecutablePath     The path to the "mysql" executable.
     * @param string $mysqldumpExecutablePath The path to the "mysqldump" executable.
     * @return static
     */
    public function mysqlSettings(
        string $mysqlExecutablePath,
        string $mysqldumpExecutablePath
    ): self {
        $this->mysqlExecutablePath = $mysqlExecutablePath;
        $this->mysqldumpExecutablePath = $mysqldumpExecutablePath;
        return $this;
    }

    /**
     * Set the postgres specific details.
     *
     * @param string $psqlExecutablePath   The path to the "psql" executable.
     * @param string $pgDumpExecutablePath The path to the "pg_dump" executable.
     * @return static
     */
    public function postgresSettings(
        string $psqlExecutablePath,
        string $pgDumpExecutablePath
    ): self {
        $this->psqlExecutablePath = $psqlExecutablePath;
        $this->pgDumpExecutablePath = $pgDumpExecutablePath;
        return $this;
    }



    /**
     * Set the number of seconds grace-period before stale databases and snapshots are to be deleted.
     *
     * @param integer $staleGraceSeconds The number of seconds.
     * @return static
     */
    public function staleGraceSeconds(int $staleGraceSeconds): self
    {
        $this->staleGraceSeconds = $staleGraceSeconds;
        return $this;
    }





    /**
     * Determine the seeders that need to be used.
     *
     * @return string[]
     */
    public function pickSeedersToInclude(): array
    {
        return $this->seedingIsAllowed() ? $this->seeders : [];
    }

    /**
     * Pick the database dumps to import before the migrations run.
     *
     * @return string[]
     */
    public function pickInitialImports(): array
    {
        $initialImports = $this->initialImports;
        $driver = $this->driver;

        $usePaths = [];
        if (isset($initialImports[$driver])) {

            $paths = $initialImports[$driver];
            $paths = is_string($paths) ? [$paths] : $paths;

            if (is_array($paths)) {
                foreach ($paths as $path) {
                    if (mb_strlen($path)) {
                        $usePaths[] = $path;
                    }
                }
            }
        }
        return $usePaths;
    }

    /**
     * Check if there are any initial imports for the current connection.
     *
     * @return boolean
     */
    public function hasInitialImports(): bool
    {
        return count($this->pickInitialImports()) > 0;
    }





    /**
     * Check if initialisation is possible.
     *
     * @return boolean
     */
    public function shouldInitialise(): bool
    {
        return $this->connectionExists;
    }

    /**
     * When building remotely & running browser tests, make sure the remote session.driver matches the local one.
     *
     * @return void
     * @throws AdaptRemoteShareException When building remotely, is a browser test, and session.drivers don't match.
     */
    public function ensureThatSessionDriversMatch(): void
    {
        if (!$this->isRemoteBuild) {
            return;
        }

        if (!$this->isBrowserTest) {
            return;
        }

        if ($this->sessionDriver == $this->remoteCallerSessionDriver) {
            return;
        }

        throw AdaptRemoteShareException::sessionDriverMismatch(
            $this->sessionDriver,
            (string) $this->remoteCallerSessionDriver
        );
    }

    /**
     * Resolve whether database re-use is allowed.
     *
     * @return boolean
     */
    public function reusingDB(): bool
    {
        return $this->shouldUseTransaction() || $this->shouldUseJournal();
    }



    /**
     * Resolve whether transactions shall be used.
     *
     * @return boolean
     */
    public function shouldUseTransaction(): bool
    {
        return $this->canUseTransactions();
    }

    /**
     * Resolve whether journaling shall be used.
     *
     * @return boolean
     */
    public function shouldUseJournal(): bool
    {
        // transactions are better so use them if they're enabled
        return $this->canUseJournaling() && !$this->canUseTransactions();
    }



    /**
     * Resolve whether transactions can be used for database re-use.
     *
     * @return boolean
     */
    public function canUseTransactions(): bool
    {
        if (!$this->connectionExists) {
            return false;
        }
        if (!$this->dbSupportsReUse) {
            return false;
        }
        if ($this->isBrowserTest) {
            return false;
        }
        if (!$this->dbSupportsTransactions) {
            return false;
        }
        return $this->reuseTransaction;
    }

    /**
     * Resolve whether journaling can be used for database re-use.
     *
     * @return boolean
     */
    public function canUseJournaling(): bool
    {
        if (!$this->connectionExists) {
            return false;
        }
        if (!$this->dbSupportsReUse) {
            return false;
        }
        if (!$this->dbSupportsJournaling) {
            return false;
        }
        return $this->reuseJournal;
    }



    /**
     * Resolve whether the database should be verified (in some way) or not.
     *
     * @return boolean
     */
    public function shouldVerifyDatabase(): bool
    {
        return $this->shouldVerifyStructure() || $this->shouldVerifyData();
    }

    /**
     * Resolve whether the database structure should be verified or not.
     *
     * @return boolean
     */
    public function shouldVerifyStructure(): bool
    {
        if (!$this->dbSupportsVerification) {
            return false;
        }

        return $this->verifyDatabase; // this setting is applied to both structure and content checking
    }

    /**
     * Resolve whether the database content should be verified or not.
     *
     * @return boolean
     */
    public function shouldVerifyData(): bool
    {
        if (!$this->dbSupportsVerification) {
            return false;
        }

        return $this->verifyDatabase; // this setting is applied to both structure and content checking
    }



    /**
     * Resolve whether scenarios are to be used.
     *
     * @return boolean
     */
    public function usingScenarios(): bool
    {
        return $this->dbSupportsScenarios && $this->scenarios;
    }

    /**
     * Check if the database should be built remotely (instead of locally).
     *
     * @return boolean
     */
    public function shouldBuildRemotely(): bool
    {
        return mb_strlen((string) $this->remoteBuildUrl) > 0;
    }

    /**
     * Resolve whether seeding is allowed.
     *
     * @return boolean
     */
    public function seedingIsAllowed(): bool
    {
        return ($this->hasInitialImports()) || ($this->migrations !== false);
    }

    /**
     * Resolve whether snapshots are enabled or not.
     *
     * @return boolean
     */
    public function snapshotsAreEnabled(): bool
    {
        return !is_null($this->snapshotType());
    }

    /**
     * Check which type of snapshots are being used.
     *
     * @return string|null
     */
    public function snapshotType(): ?string
    {
        if (!$this->dbSupportsSnapshots) {
            return null;
        }

        $snapshotType = $this->reusingDB()
            ? $this->useSnapshotsWhenReusingDB
            : $this->useSnapshotsWhenNotReusingDB;

        return in_array($snapshotType, ['afterMigrations', 'afterSeeders', 'both'], true)
            ? $snapshotType
            : null;
    }

    /**
     * Derive if a snapshot should be taken after the migrations have been run.
     *
     * @return boolean
     */
    public function shouldTakeSnapshotAfterMigrations(): bool
    {
        if (!$this->snapshotsAreEnabled()) {
            return false;
        }

        if ((!$this->hasInitialImports()) && ($this->migrations === false)) {
            return false;
        }

        // take into consideration when there are no seeders to run, but a snapshot should be taken after seeders
        return count($this->pickSeedersToInclude())
            ? in_array($this->snapshotType(), ['afterMigrations', 'both'], true)
            : in_array($this->snapshotType(), ['afterMigrations', 'afterSeeders', 'both'], true);
    }

    /**
     * Derive if a snapshot should be taken after the seeders have been run.
     *
     * @return boolean
     */
    public function shouldTakeSnapshotAfterSeeders(): bool
    {
        if (!$this->snapshotsAreEnabled()) {
            return false;
        }

        if ((!$this->hasInitialImports()) && ($this->migrations === false)) {
            return false;
        }

        if (!$this->seedingIsAllowed()) {
            return false;
        }

        // if there are no seeders, the snapshot will be the same as after migrations
        // so this situation is included in shouldTakeSnapshotAfterMigrations(..) above
        if (!count($this->pickSeedersToInclude())) {
            return false;
        }

        return in_array($this->snapshotType(), ['afterSeeders', 'both'], true);
    }





    /**
     * Build a new ConfigDTO from the data given in a request to build the database remotely.
     *
     * @param string $payload The raw ConfigDTO data from the request.
     * @return self|null
     * @throws AdaptRemoteShareException When the payload couldn't be interpreted or the version doesn't match.
     */
    public static function buildFromPayload(string $payload): ?self
    {
        if (!mb_strlen($payload)) {
            return null;
        }

        $values = json_decode($payload, true);
        if (!is_array($values)) {
            throw AdaptRemoteShareException::couldNotReadConfigDTO();
        }

        $configDTO = static::buildFromArray($values);

        if ($configDTO->dtoVersion != Settings::CONFIG_DTO_VERSION) {
            throw AdaptRemoteShareException::versionMismatch();
        }

        return $configDTO;
    }

    /**
     * Build the value to send in requests.
     *
     * @return string
     */
    public function buildPayload(): string
    {
        return (string) json_encode(get_object_vars($this));
    }
}

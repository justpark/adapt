<?php

namespace CodeDistortion\Adapt\Adapters\LaravelSQLite;

use CodeDistortion\Adapt\Adapters\AbstractClasses\AbstractReuseMetaDataTable;
use CodeDistortion\Adapt\Adapters\Interfaces\ReuseMetaDataTableInterface;
use CodeDistortion\Adapt\Adapters\Traits\InjectTrait;
use CodeDistortion\Adapt\Adapters\Traits\Laravel\LaravelTransactionsTrait;
use CodeDistortion\Adapt\Support\Settings;
use DateTime;
use DateTimeZone;
use stdClass;

/**
 * Database-adapter methods related to managing Laravel/SQLite reuse meta-data.
 */
class LaravelSQLiteReuseMetaDataTable extends AbstractReuseMetaDataTable implements ReuseMetaDataTableInterface
{
    use InjectTrait;
    use LaravelTransactionsTrait;



    /**
     * Insert details to the database to help identify if it can be reused or not.
     *
     * @param string  $origDBName          The name of the database that this test-database is for.
     * @param string  $buildHash           The current build-hash.
     * @param string  $snapshotHash        The current snapshot-hash.
     * @param string  $scenarioHash        The current scenario-hash.
     * @param boolean $transactionReusable Whether this database can be reused because of a transaction or not.
     * @param boolean $journalReusable     Whether this database can be reused because of journaling or not.
     * @param boolean $willVerify          Whether this database will be verified or not.
     * @return void
     */
    public function writeReuseMetaData(
        string $origDBName,
        string $buildHash,
        string $snapshotHash,
        string $scenarioHash,
        bool $transactionReusable,
        bool $journalReusable,
        bool $willVerify
    ): void {

        $this->removeReuseMetaTable();

        $table = Settings::REUSE_TABLE;

        $this->di->db->statement(
            "CREATE TABLE `$table` ("
            . "`project_name` varchar(255), "
            . "`reuse_table_version` varchar(16), "
            . "`orig_db_name` varchar(255) NOT NULL, "
            . "`build_hash` varchar(32) NOT NULL, "
            . "`snapshot_hash` varchar(32) NOT NULL, "
            . "`scenario_hash` varchar(32) NOT NULL, "
            . "`transaction_reusable` tinyint unsigned NULL, "
            . "`journal_reusable` tinyint unsigned NULL, "
            . "`validation_passed` tinyint unsigned NULL, "
            . "`last_used` timestamp"
            . ")"
        );

        $this->di->db->insert(
            "INSERT INTO `$table` ("
                . "`project_name`, "
                . "`reuse_table_version`, "
                . "`orig_db_name`, "
                . "`build_hash`, "
                . "`snapshot_hash`, "
                . "`scenario_hash`, "
                . "`transaction_reusable`, "
                . "`journal_reusable`, "
                . "`validation_passed`, "
                . "`last_used`"
            . ") "
            . "VALUES ("
                . ":projectName, "
                . ":reuseTableVersion, "
                . ":origDBName, "
                . ":buildHash, "
                . ":snapshotHash, "
                . ":scenarioHash, "
                . ":transactionReusable, "
                . ":journalReusable, "
                . ":validationPassed, "
                . ":lastUsed"
            . ")",
            [
                'projectName' => $this->configDTO->projectName,
                'reuseTableVersion' => Settings::REUSE_TABLE_VERSION,
                'origDBName' => $origDBName,
                'buildHash' => $buildHash,
                'snapshotHash' => $snapshotHash,
                'scenarioHash' => $scenarioHash,
                'transactionReusable' => $transactionReusable ? 1 : null,
                'journalReusable' => $journalReusable ? 1 : null,
                'validationPassed' => $willVerify ? 1 : null,
                'lastUsed' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Remove the re-use meta-data table.
     *
     * @return void
     */
    public function removeReuseMetaTable(): void
    {
        $this->di->db->statement("DROP TABLE IF EXISTS `" . Settings::REUSE_TABLE . "`");
    }

    /**
     * Load the reuse details from the meta-data table.
     *
     * @return stdClass|null
     */
    protected function loadReuseInfo(): ?stdClass
    {
        $rows = $this->di->db->select("SELECT * FROM `" . Settings::REUSE_TABLE . "` LIMIT 0, 1");
        return $rows[0] ?? null;
    }
}

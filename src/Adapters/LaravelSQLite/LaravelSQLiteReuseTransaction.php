<?php

namespace CodeDistortion\Adapt\Adapters\LaravelSQLite;

use CodeDistortion\Adapt\Adapters\Interfaces\ReuseTransactionInterface;
use CodeDistortion\Adapt\Adapters\Traits\InjectTrait;
use CodeDistortion\Adapt\Adapters\Traits\Laravel\LaravelTransactionsTrait;
use CodeDistortion\Adapt\Adapters\Traits\SQLite\SQLiteHelperTrait;
use CodeDistortion\Adapt\Support\Settings;
use stdClass;
use Throwable;

/**
 * Database-adapter methods related to managing Laravel/SQLite reuse through transactions.
 */
class LaravelSQLiteReuseTransaction implements ReuseTransactionInterface
{
    use InjectTrait;
    use LaravelTransactionsTrait;
    use SQLiteHelperTrait;



    /**
     * Determine if a transaction can be used on this database (for database re-use).
     *
     * @return boolean
     */
    public function supportsTransactions(): bool
    {
        // the database connection is closed between tests,
        // which causes :memory: databases to disappear,
        // so transactions can't be used on them between tests
        return !$this->isMemoryDatabase();
    }



    /**
     * Start the transaction that the test will be encapsulated in.
     *
     * @return void
     */
    public function applyTransaction()
    {
        $this->di->db->update("UPDATE `" . Settings::REUSE_TABLE . "` SET `transaction_reusable` = 1");
        $this->laravelApplyTransaction();
        $this->di->db->update("UPDATE `" . Settings::REUSE_TABLE . "` SET `transaction_reusable` = 0");
    }

    /**
     * Check if the transaction was committed.
     *
     * @return boolean
     */
    public function wasTransactionCommitted(): bool
    {
        try {
            $rows = $this->di->db->select(
                "SELECT `transaction_reusable` FROM `" . Settings::REUSE_TABLE . "` LIMIT 0, 1"
            );

            /** @var stdClass|null $reuseInfo */
            $reuseInfo = $rows[0] ?? null;

            return ($reuseInfo->transaction_reusable ?? null) === 0;

        } catch (Throwable $e) {
            return false;
        }
    }
}

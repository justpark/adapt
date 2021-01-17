<?php

namespace CodeDistortion\Adapt\Boot;

use CodeDistortion\Adapt\DTO\PropBagDTO;

/**
 * Bootstrap Adapt for tests.
 */
interface BootTestInterface
{
    /**
     * Set the name of the test being run.
     *
     * @param string $testName The name of the test being run.
     * @return static
     */
    public function testName(string $testName);

    /**
     * Specify the properties that were present in the test-class.
     *
     * @param PropBagDTO $propBag A populated PropBagDTO.
     * @return static
     */
    public function props(PropBagDTO $propBag);

    /**
     * Specify the closure to call to start a db transaction.
     *
     * @param callable $transactionClosure The closure to use.
     * @return static
     */
    public function transactionClosure(callable $transactionClosure);

    /**
     * Specify the callback closure to call that will initialise the DatabaseBuilder/s.
     *
     * @param callable|null $initCallback The closure to use.
     * @return static
     */
    public function initCallback($initCallback);

//    /**
//     * Specify the DIContainer to use.
//     *
//     * @param DIContainer $di The DIContainer to use.
//     * @return static
//     */
//    public function setDI(DIContainer $di);



    /**
     * Run the process to build the databases.
     *
     * @return void
     */
    public function run();

    /**
     * Perform any clean-up needed after the test has finished.
     *
     * @return void
     */
    public function cleanUp();

    /**
     * Remove any old (ie. orphaned) temporary config files.
     *
     * @return void
     */
    public function removeOldTempConfigFiles();
}

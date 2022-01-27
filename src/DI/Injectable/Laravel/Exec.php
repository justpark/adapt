<?php

namespace CodeDistortion\Adapt\DI\Injectable\Laravel;

/**
 * Injectable class to abstract program execution.
 */
class Exec
{
    /**
     * Execute the given command.
     *
     * @param string $command   The command to run.
     * @param mixed  $output    Will be populated with the script's output.
     * @param mixed  $returnVal Will contain the script's exit-code.
     * @return string|boolean
     */
    public function run($command, &$output, &$returnVal)
    {
        return exec($command, $output, $returnVal);
    }

    /**
     * Run the given command and see if it completes without error.
     *
     * @param string $command The command to run.
     * @return boolean
     */
    public function commandRuns($command): bool
    {
        exec($command . ' 2>/dev/null', $output, $returnVal); // suppress stderror
        return $returnVal == 0;
    }
}

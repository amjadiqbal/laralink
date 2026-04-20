<?php

namespace Amjadiqbal\Laralink\Contracts;

interface ProcessRunner
{
    /**
     * Run a process command.
     *
     * @param  string[]      $command    The command and its arguments.
     * @param  string|null   $workingDir Working directory, or null for the current directory.
     * @param  callable      $output     Callback receiving (string $type, string $buffer) for live output.
     * @return bool          True when the process exits successfully, false otherwise.
     */
    public function run(array $command, ?string $workingDir, callable $output): bool;
}

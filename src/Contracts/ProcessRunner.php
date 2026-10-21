<?php

namespace AmjadIqbal\Laralink\Contracts;

/**
 * Runs git and composer. Bound in the container so tests can swap in a fake.
 */
interface ProcessRunner
{
    /**
     * Run a command and return its exit code.
     *
     * @param  array<int, string>  $command
     * @param  (callable(string, string): void)|null  $output  receives (type, buffer) as the command writes
     */
    public function run(array $command, string $cwd, ?callable $output = null): int;
}

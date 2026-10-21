<?php

namespace AmjadIqbal\Laralink\Commands\Concerns;

use AmjadIqbal\Laralink\Contracts\ProcessRunner;
use AmjadIqbal\Laralink\Laralink;
use Illuminate\Support\Composer;

trait RunsProcesses
{
    /**
     * Run Composer in the project root, streaming its output, and return true on success.
     *
     * @param  array<int, string>  $arguments
     */
    protected function composer(Laralink $laralink, array $arguments): bool
    {
        $command = array_merge($this->laravel->make(Composer::class)->findComposer(), $arguments);

        $this->line('  <fg=gray>$ composer '.implode(' ', $arguments).'</>');

        return $this->process($laralink, $command);
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function process(Laralink $laralink, array $command, ?string $cwd = null): bool
    {
        return $this->laravel->make(ProcessRunner::class)->run(
            $command,
            $cwd ?? $laralink->getBasePath(),
            function (string $type, string $buffer): void {
                $this->output->write($buffer);
            }
        ) === 0;
    }
}

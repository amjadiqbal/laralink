<?php

namespace AmjadIqbal\Laralink\Tests\Support;

use AmjadIqbal\Laralink\Contracts\ProcessRunner;

/**
 * Records commands instead of running them. `git clone` creates the target folder
 * from $cloneComposer; Composer succeeds unless $composerExitCode says otherwise.
 */
class FakeProcessRunner implements ProcessRunner
{
    /** @var array<int, array{command: array<int, string>, cwd: string}> */
    public array $commands = [];

    /** @var array<string, mixed>|null */
    public ?array $cloneComposer = null;

    public int $cloneExitCode = 0;

    public int $composerExitCode = 0;

    public string $gitStatus = '';

    public function run(array $command, string $cwd, ?callable $output = null): int
    {
        $this->commands[] = ['command' => $command, 'cwd' => $cwd];

        if ($command[0] === 'git' && $command[1] === 'clone') {
            if ($this->cloneExitCode === 0) {
                $target = end($command);
                mkdir($target.'/.git', 0755, true);
                file_put_contents($target.'/composer.json', json_encode($this->cloneComposer ?? []));
            }

            return $this->cloneExitCode;
        }

        if ($command[0] === 'git' && $command[1] === 'status') {
            $output && $output('out', $this->gitStatus);

            return 0;
        }

        return $this->composerExitCode;
    }

    /**
     * The arguments of every Composer call, as one string each.
     *
     * @return array<int, string>
     */
    public function composerCalls(): array
    {
        $calls = [];

        foreach ($this->commands as $call) {
            $index = array_search('require', $call['command'], true);
            $index = $index === false ? array_search('reinstall', $call['command'], true) : $index;

            if ($call['command'][0] !== 'git' && $index !== false) {
                $calls[] = implode(' ', array_slice($call['command'], $index));
            }
        }

        return $calls;
    }
}

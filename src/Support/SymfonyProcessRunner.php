<?php

namespace AmjadIqbal\Laralink\Support;

use AmjadIqbal\Laralink\Contracts\ProcessRunner;
use Symfony\Component\Process\Process;

class SymfonyProcessRunner implements ProcessRunner
{
    public function run(array $command, string $cwd, ?callable $output = null): int
    {
        $process = new Process($command, $cwd, ['COMPOSER_NO_INTERACTION' => '1']);
        $process->setTimeout(900);

        return $process->run($output);
    }
}

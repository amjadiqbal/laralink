<?php

namespace Amjadiqbal\Laralink\Support;

use Amjadiqbal\Laralink\Contracts\ProcessRunner;
use Symfony\Component\Process\Process;

class SymfonyProcessRunner implements ProcessRunner
{
    public function run(array $command, ?string $workingDir, callable $output): bool
    {
        $process = new Process($command, $workingDir);
        $process->setTimeout(300);
        $process->run($output);

        return $process->isSuccessful();
    }
}

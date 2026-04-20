<?php

namespace Amjadiqbal\Laralink\Tests\Support;

use Amjadiqbal\Laralink\Contracts\ProcessRunner;

class FakeProcessRunner implements ProcessRunner
{
    /** @var list<array{command: string[], workingDir: string|null}> */
    private array $recorded = [];

    public function __construct(private bool $shouldSucceed = true) {}

    public function run(array $command, ?string $workingDir, callable $output): bool
    {
        $this->recorded[] = ['command' => $command, 'workingDir' => $workingDir];

        return $this->shouldSucceed;
    }

    /**
     * Return all recorded process invocations.
     *
     * @return list<array{command: string[], workingDir: string|null}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function reset(): void
    {
        $this->recorded = [];
    }
}

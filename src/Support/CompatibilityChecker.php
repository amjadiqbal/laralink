<?php

namespace AmjadIqbal\Laralink\Support;

use Composer\Semver\Semver;
use UnexpectedValueException;

/**
 * Checks a package's own composer.json against the PHP and Laravel versions this app runs,
 * so an obvious mismatch is reported in one line instead of a page of solver output.
 */
class CompatibilityChecker
{
    /** Requirements that pin the Laravel version, in the order they are checked. */
    private const LARAVEL_REQUIREMENTS = ['laravel/framework', 'illuminate/support', 'illuminate/contracts', 'illuminate/console'];

    public function __construct(private string $phpVersion, private string $laravelVersion) {}

    /**
     * @param  array<string, mixed>  $packageComposer
     * @return array<int, array{check: string, constraint: string|null, current: string, ok: bool}>
     */
    public function check(array $packageComposer): array
    {
        $require = $packageComposer['require'] ?? [];
        $results = [$this->result('PHP', $require['php'] ?? null, $this->phpVersion)];

        $laravel = null;

        foreach (self::LARAVEL_REQUIREMENTS as $name) {
            if (isset($require[$name])) {
                $laravel = $require[$name];
                break;
            }
        }

        $results[] = $this->result('Laravel', $laravel, $this->laravelVersion);

        return $results;
    }

    /**
     * @return array{check: string, constraint: string|null, current: string, ok: bool}
     */
    private function result(string $check, mixed $constraint, string $current): array
    {
        if (! is_string($constraint) || $constraint === '') {
            return ['check' => $check, 'constraint' => null, 'current' => $current, 'ok' => true];
        }

        try {
            $ok = Semver::satisfies($this->normalize($current), $constraint);
        } catch (UnexpectedValueException) {
            // A constraint Composer itself would reject is Composer's to report, not ours.
            $ok = true;
        }

        return ['check' => $check, 'constraint' => $constraint, 'current' => $current, 'ok' => $ok];
    }

    private function normalize(string $version): string
    {
        return preg_match('/^\d+\.\d+\.\d+/', $version, $m) ? $m[0] : $version;
    }
}

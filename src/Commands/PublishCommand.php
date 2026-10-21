<?php

namespace AmjadIqbal\Laralink\Commands;

use AmjadIqbal\Laralink\Commands\Concerns\RunsProcesses;
use AmjadIqbal\Laralink\Contracts\ProcessRunner;
use AmjadIqbal\Laralink\Laralink;
use Illuminate\Console\Command;
use RuntimeException;

class PublishCommand extends Command
{
    use RunsProcesses;

    protected $signature = 'laralink:publish
                            {package? : The package name (vendor/package)}
                            {--delete : Also remove ./packages/vendor/package (a symlink is only unlinked)}
                            {--backup : With --delete, copy the files to ./packages/.backups first}
                            {--force : Skip the confirmation and delete even with uncommitted changes}';

    protected $description = 'Switch a linked package back to its released version and optionally remove the local copy';

    public function handle(Laralink $laralink): int
    {
        try {
            $name = $this->resolveName($laralink);

            return $name === null ? self::FAILURE : $this->publish($laralink, $name);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveName(Laralink $laralink): ?string
    {
        if ($this->argument('package')) {
            return implode('/', $laralink->parseName((string) $this->argument('package')));
        }

        $linked = array_values(array_filter(
            $laralink->linkedPackages(),
            fn ($name) => $laralink->hasPathRepository(...explode('/', $name, 2))
        ));

        if ($linked === []) {
            $this->components->info('No linked packages.');

            return null;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Pass the package name when running without interaction.');

            return null;
        }

        return $this->choice('Which package do you want to switch back?', $linked, 0);
    }

    private function publish(Laralink $laralink, string $name): int
    {
        ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($name);

        if (! $laralink->hasPathRepository($vendor, $package)) {
            $this->components->error("{$name} is not linked (composer.json has no path repository for ./packages/{$name}).");

            return self::FAILURE;
        }

        $state = $laralink->state()[$name] ?? [];
        $previous = $state['previous'] ?? null;
        $constraint = is_array($previous) && ! str_contains((string) ($previous['constraint'] ?? ''), 'dev')
            ? (string) $previous['constraint']
            : null;
        $dev = ($previous['section'] ?? ($laralink->requirement($name)['section'] ?? null)) === 'require-dev';
        $path = $laralink->localPath($vendor, $package);
        $isSymlink = is_link($path);

        $this->components->twoColumnDetail('Package', $name);
        $this->components->twoColumnDetail('Switch to', $constraint ?? 'the latest stable release');
        $this->components->twoColumnDetail('Local files', match (true) {
            ! $this->option('delete') => 'kept in ./packages/'.$name,
            $isSymlink => 'symlink removed (the source folder is not touched)',
            $this->option('backup') => 'backed up, then deleted',
            default => '<fg=red>deleted</>',
        });

        if ($this->option('delete') && ! $isSymlink && ! $this->option('backup') && ! $this->option('force') && $this->hasUncommittedWork($path)) {
            $this->components->error("./packages/{$name} has uncommitted changes. Add --backup, commit them, or use --force to delete anyway.");

            return self::FAILURE;
        }

        if (! $this->option('force') && $this->input->isInteractive() && ! $this->confirm('Continue?', true)) {
            return self::SUCCESS;
        }

        $before = $laralink->readComposerJson();
        $laralink->removePathRepository($vendor, $package);

        $arguments = ['require', $constraint === null ? $name : "{$name}:{$constraint}", '--with-all-dependencies'];

        if ($dev) {
            $arguments[] = '--dev';
        }

        if (! $this->composer($laralink, $arguments)) {
            $laralink->writeComposerJson($before);
            $this->newLine();
            $this->components->error("Composer could not install a released version of {$name} (is it published yet?). composer.json has been restored and the package is still linked.");

            return self::FAILURE;
        }

        $laralink->rememberLink($name, null);

        if ($this->option('delete')) {
            if (! $isSymlink && $this->option('backup')) {
                $this->components->twoColumnDetail('Backup', str_replace($laralink->getBasePath(), '.', $laralink->backupLocalCopy($vendor, $package)));
            }

            $laralink->removeLocalCopy($vendor, $package);
        }

        $version = $laralink->installedVersions()[$name] ?? 'the released version';
        $this->newLine();
        $this->components->info("{$name} is now installed from its repository ({$version}).");

        return self::SUCCESS;
    }

    private function hasUncommittedWork(string $path): bool
    {
        if (! is_dir($path.DIRECTORY_SEPARATOR.'.git')) {
            return false;
        }

        $changes = '';
        $this->laravel->make(ProcessRunner::class)->run(['git', 'status', '--porcelain'], $path, function ($type, $buffer) use (&$changes) {
            $changes .= $buffer;
        });

        return trim($changes) !== '';
    }
}

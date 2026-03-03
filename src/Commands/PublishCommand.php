<?php

namespace Amjadiqbal\Laralink\Commands;

use Amjadiqbal\Laralink\Laralink;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class PublishCommand extends Command
{
    protected $signature = 'laralink:publish
                            {package? : The package name in vendor/package format}
                            {--delete : Also delete the local source code from ./packages}';

    protected $description = 'Publish a package by removing the local symlink and installing the official version from Packagist';

    public function handle(Laralink $laralink, Filesystem $files): int
    {
        $packageName = $this->argument('package');

        if (! empty($packageName)) {
            return $this->publishPackage($laralink, $files, $packageName);
        }

        $linked = $laralink->linkedPackages();

        if (empty($linked)) {
            $this->info('No locally linked packages found.');
            return self::SUCCESS;
        }

        $packageName = $this->choice(
            'Which package do you want to publish?',
            $linked
        );

        if (empty($packageName)) {
            $this->error('Package name is required.');
            return self::FAILURE;
        }

        return $this->publishPackage($laralink, $files, $packageName);
    }

    private function publishPackage(Laralink $laralink, Filesystem $files, string $packageName): int
    {
        try {
            ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($packageName);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (! $laralink->isLinked($vendor, $package)) {
            $this->error("Package <comment>{$vendor}/{$package}</comment> is not linked locally.");
            return self::FAILURE;
        }

        $this->info("Publishing <comment>{$vendor}/{$package}</comment>...");

        $this->info('Removing path repository from <comment>composer.json</comment>...');
        $laralink->removePathRepository($vendor, $package);
        $this->line('  <info>✓</info> Path repository removed.');

        if (! $this->runComposerRequire($laralink, $vendor, $package)) {
            return self::FAILURE;
        }

        if ($this->option('delete') || $this->confirm('Delete local source code from ./packages?', false)) {
            $localPath = $laralink->localPath($vendor, $package);
            $files->deleteDirectory($localPath);
            $this->line("  <info>✓</info> Deleted <comment>./packages/{$vendor}/{$package}</comment>.");
        }

        $this->line('');
        $this->info("✓ Package <comment>{$vendor}/{$package}</comment> is now using the official Packagist version.");

        return self::SUCCESS;
    }

    private function runComposerRequire(Laralink $laralink, string $vendor, string $package): bool
    {
        $this->info("Running <comment>composer require {$vendor}/{$package}</comment>...");

        $process = new Process(['composer', 'require', "{$vendor}/{$package}"], $laralink->getBasePath());
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->warn('Composer require did not complete successfully. You may need to run it manually.');
            $this->line("  Try: <comment>composer require {$vendor}/{$package}</comment>");
            return false;
        }

        $this->line('  <info>✓</info> Official package installed from Packagist.');
        return true;
    }
}

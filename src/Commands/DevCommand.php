<?php

namespace Amjadiqbal\Laralink\Commands;

use Amjadiqbal\Laralink\Laralink;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DevCommand extends Command
{
    protected $signature = 'laralink:dev {package? : The package name in vendor/package format}';

    protected $description = 'Link a package for local development by cloning it into ./packages and updating composer.json';

    public function handle(Laralink $laralink): int
    {
        $packageName = $this->argument('package');

        if (empty($packageName)) {
            $packageName = $this->ask('Enter the package name (vendor/package)');
        }

        if (empty($packageName)) {
            $this->error('Package name is required.');
            return self::FAILURE;
        }

        try {
            ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($packageName);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Setting up local development for <comment>{$vendor}/{$package}</comment>...");

        if ($laralink->isLinked($vendor, $package)) {
            $this->warn("Package <comment>{$vendor}/{$package}</comment> is already linked at ./packages/{$vendor}/{$package}.");

            if (! $this->confirm('Do you want to re-link it and update composer.json?', false)) {
                return self::SUCCESS;
            }
        } else {
            if (! $this->clonePackage($laralink, $vendor, $package)) {
                return self::FAILURE;
            }
        }

        $this->info('Updating <comment>composer.json</comment> with path repository...');
        $laralink->addPathRepository($vendor, $package);
        $this->line('  <info>✓</info> Path repository added.');

        return $this->runComposerRequire($laralink, $vendor, $package);
    }

    private function clonePackage(Laralink $laralink, string $vendor, string $package): bool
    {
        $localPath = $laralink->localPath($vendor, $package);

        $this->info("Package <comment>{$vendor}/{$package}</comment> is not available locally.");

        $gitUrl = $this->askForGitUrl($vendor, $package);

        if (empty($gitUrl)) {
            $this->error('A Git URL is required to clone the package.');
            return false;
        }

        $this->info("Cloning <comment>{$gitUrl}</comment> into <comment>./packages/{$vendor}/{$package}</comment>...");

        if (! is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0755, true);
        }

        $process = new Process(['git', 'clone', $gitUrl, $localPath]);
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Failed to clone the repository. Please check the Git URL and try again.');
            return false;
        }

        $this->line('  <info>✓</info> Repository cloned successfully.');
        return true;
    }

    private function askForGitUrl(string $vendor, string $package): string
    {
        $suggestedUrl = "https://github.com/{$vendor}/{$package}.git";

        $gitUrl = $this->ask(
            "Enter the Git URL to clone (press Enter to use suggested URL)",
            $suggestedUrl
        );

        return trim((string) $gitUrl);
    }

    private function runComposerRequire(Laralink $laralink, string $vendor, string $package): int
    {
        $this->info("Running <comment>composer require {$vendor}/{$package}:@dev</comment>...");

        $process = new Process(['composer', 'require', "{$vendor}/{$package}:@dev"], $laralink->getBasePath());
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->warn('Composer require did not complete successfully. You may need to run it manually.');
            $this->line("  Try: <comment>composer require {$vendor}/{$package}:@dev</comment>");
            return self::FAILURE;
        }

        $this->line('');
        $this->info("✓ Package <comment>{$vendor}/{$package}</comment> is now linked for local development.");
        $this->line("  Source code: <comment>./packages/{$vendor}/{$package}</comment>");
        $this->line("  Run <comment>php artisan laralink:list</comment> to see all linked packages.");

        return self::SUCCESS;
    }
}

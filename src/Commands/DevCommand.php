<?php

namespace AmjadIqbal\Laralink\Commands;

use AmjadIqbal\Laralink\Commands\Concerns\RunsProcesses;
use AmjadIqbal\Laralink\Laralink;
use AmjadIqbal\Laralink\Support\CompatibilityChecker;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class DevCommand extends Command
{
    use RunsProcesses;

    protected $signature = 'laralink:dev
                            {package? : The package name (vendor/package)}
                            {--source= : A local path or a Git URL (HTTPS, SSH or file://)}
                            {--branch= : Git branch or tag to clone (default: the repository default branch)}
                            {--copy : Copy a local package into ./packages instead of symlinking it}
                            {--skip-version-check : Do not check the package PHP and Laravel constraints}
                            {--force : Replace an existing symlink and skip the version check}';

    protected $description = 'Link a package for local development from a local path or a Git repository';

    private const LOCAL = 'A local path';

    private const GIT = 'A Git repository';

    private const MANUAL = 'Enter a path';

    public function handle(Laralink $laralink, Filesystem $files): int
    {
        try {
            return $this->link($laralink, $files);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function link(Laralink $laralink, Filesystem $files): int
    {
        $name = $this->argument('package') ? $this->normalizedName($laralink, (string) $this->argument('package')) : null;
        $source = $this->option('source');

        // Files already in ./packages but not linked (e.g. after laralink:publish without --delete): link them in place.
        if ($name !== null && ! $source) {
            ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($name);

            if ($laralink->isLinked($vendor, $package)) {
                if ($laralink->hasPathRepository($vendor, $package) && ! $this->option('force')) {
                    $this->components->info("{$name} is already linked at ./packages/{$name}.");

                    return self::SUCCESS;
                }

                return $this->requireLinked($laralink, $name, $laralink->state()[$name] ?? ['kind' => 'existing'], null);
            }
        }

        if (! $source) {
            $source = $this->askForSource($laralink, $name);
        }

        if ($source === null) {
            return self::FAILURE;
        }

        $local = $this->localDirectory($source);

        return $local !== null
            ? $this->linkLocal($laralink, $local, $name)
            : $this->linkGit($laralink, $files, $this->assertGitUrl($source), $name);
    }

    private function linkLocal(Laralink $laralink, string $path, ?string $expected): int
    {
        $name = $this->packageName($laralink, $laralink->readPackageComposerJson($path), $expected);
        ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($name);

        if (realpath($laralink->localPath($vendor, $package)) === $path) {
            return $this->requireLinked($laralink, $name, $laralink->state()[$name] ?? ['kind' => 'existing'], null);
        }

        if (! $this->makeRoom($laralink, $vendor, $package) || ! $this->compatible($laralink->readPackageComposerJson($path))) {
            return self::FAILURE;
        }

        $kind = $this->option('copy') ? 'copy' : 'symlink';

        if ($kind === 'copy') {
            $laralink->copyPackageDirectory($path, $vendor, $package);
        } else {
            $laralink->symlinkPackageDirectory($path, $vendor, $package);
        }

        $this->components->twoColumnDetail(ucfirst($kind), "./packages/{$name} <fg=gray>→</> {$path}");

        return $this->requireLinked($laralink, $name, ['kind' => $kind, 'source' => $path], fn () => $laralink->removeLocalCopy($vendor, $package));
    }

    private function linkGit(Laralink $laralink, Filesystem $files, string $url, ?string $expected): int
    {
        if ($expected !== null) {
            ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($expected);

            if (! $this->makeRoom($laralink, $vendor, $package)) {
                return self::FAILURE;
            }
        }

        $branch = $this->option('branch') ?: null;
        $files->ensureDirectoryExists($laralink->packagesPath());
        $temp = $laralink->packagesPath().DIRECTORY_SEPARATOR.'.clone-'.bin2hex(random_bytes(4));

        $this->components->info('Cloning '.$url.($branch ? " ({$branch})" : ''));
        $command = array_merge(['git', 'clone'], $branch ? ['--branch', $branch] : [], [$url, $temp]);

        if (! $this->process($laralink, $command)) {
            $files->deleteDirectory($temp);
            $this->components->error('git clone failed. Check the URL, the branch and your access to the repository.');

            return self::FAILURE;
        }

        try {
            $composer = $laralink->readPackageComposerJson($temp);
            $name = $this->packageName($laralink, $composer, $expected);
            ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($name);
        } catch (RuntimeException $e) {
            $files->deleteDirectory($temp);
            throw $e;
        }

        if (! $this->makeRoom($laralink, $vendor, $package) || ! $this->compatible($composer)) {
            $files->deleteDirectory($temp);

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($laralink->localPath($vendor, $package)));
        $files->moveDirectory($temp, $laralink->localPath($vendor, $package));
        $this->components->twoColumnDetail('Cloned', "./packages/{$name}");

        return $this->requireLinked(
            $laralink,
            $name,
            array_filter(['kind' => 'clone', 'source' => $url, 'branch' => $branch]),
            fn () => $laralink->removeLocalCopy($vendor, $package)
        );
    }

    /**
     * Add the path repository and run composer require. On failure, put composer.json back and undo what this run created.
     *
     * @param  array<string, mixed>  $entry
     */
    private function requireLinked(Laralink $laralink, string $name, array $entry, ?callable $undo): int
    {
        ['vendor' => $vendor, 'package' => $package] = $laralink->parseName($name);

        $before = $laralink->readComposerJson();
        $known = $laralink->state()[$name] ?? null;
        $previous = is_array($known) && array_key_exists('previous', $known) ? $known['previous'] : $laralink->requirement($name);

        $laralink->addPathRepository($vendor, $package);
        $laralink->rememberLink($name, $entry + ['previous' => $previous, 'linked_at' => date('c')]);

        $arguments = ['require', "{$name}:@dev", '--with-all-dependencies'];

        if (($previous['section'] ?? null) === 'require-dev') {
            $arguments[] = '--dev';
        }

        if (! $this->composer($laralink, $arguments)) {
            $laralink->writeComposerJson($before);
            $laralink->rememberLink($name, $known);

            if ($undo !== null) {
                $undo();
            }

            $this->newLine();
            $this->components->error("Composer could not install {$name} from ./packages/{$name}. composer.json has been restored.");
            $this->line('  See why: <comment>composer why-not '.$name.' @dev</comment>');

            return self::FAILURE;
        }

        $version = $laralink->installedVersions()[$name] ?? '@dev';
        $this->newLine();
        $this->components->info("{$name} ({$version}) is now installed from ./packages/{$name}.");
        $this->components->bulletList([
            "Edit the code in ./packages/{$name}; vendor/{$name} is a symlink to it, so changes show up immediately.",
            'php artisan laralink:list shows every linked package.',
            "php artisan laralink:publish {$name} switches back to the released version.",
        ]);

        return self::SUCCESS;
    }

    /**
     * Make sure ./packages/vendor/package is free. --force replaces a symlink; real directories are never overwritten.
     */
    private function makeRoom(Laralink $laralink, string $vendor, string $package): bool
    {
        if (! $laralink->isLinked($vendor, $package)) {
            return true;
        }

        $path = $laralink->localPath($vendor, $package);

        if (is_link($path) && $this->option('force')) {
            $laralink->removeLocalCopy($vendor, $package);

            return true;
        }

        $this->components->error(is_link($path)
            ? "./packages/{$vendor}/{$package} is already linked to ".readlink($path).'. Use --force to point it somewhere else.'
            : "./packages/{$vendor}/{$package} already exists. Run laralink:dev {$vendor}/{$package} to link it as it is, or laralink:publish {$vendor}/{$package} --delete first.");

        return false;
    }

    /**
     * @param  array<string, mixed>  $composer
     */
    private function compatible(array $composer): bool
    {
        if ($this->option('skip-version-check') || $this->option('force')) {
            return true;
        }

        $checker = new CompatibilityChecker(PHP_VERSION, $this->laravel->version());
        $ok = true;

        foreach ($checker->check($composer) as $result) {
            $ok = $ok && $result['ok'];
            $this->components->twoColumnDetail(
                $result['check'].' <fg=gray>'.($result['constraint'] ?? 'no constraint').'</>',
                ($result['ok'] ? '<fg=green>OK</>' : '<fg=red>NOT MET</>').' <fg=gray>(you have '.$result['current'].')</>'
            );
        }

        if (! $ok) {
            $this->components->error('This package does not support this app\'s PHP or Laravel version. Use --skip-version-check to link it anyway.');
        }

        return $ok;
    }

    private function askForSource(Laralink $laralink, ?string $name): ?string
    {
        if (! $this->input->isInteractive()) {
            if ($name === null) {
                $this->components->error('Pass a package name or --source when running without interaction.');

                return null;
            }

            return "https://github.com/{$name}.git";
        }

        if ($this->choice('Where is the package?', [self::LOCAL, self::GIT], 0) === self::GIT) {
            $name ??= $this->normalizedName($laralink, (string) $this->ask('Package name (vendor/package)'));

            if (! $this->option('branch') && ($branch = $this->ask('Branch or tag (leave empty for the default branch)'))) {
                $this->input->setOption('branch', $branch);
            }

            return (string) $this->ask('Git URL', "https://github.com/{$name}.git");
        }

        $found = $this->detectLocalPackages($laralink);

        if ($found !== []) {
            $options = array_map(fn ($path, $package) => "{$package} ({$path})", array_keys($found), $found);
            $picked = $this->choice('Which package?', array_merge($options, [self::MANUAL]), 0);

            if ($picked !== self::MANUAL) {
                return array_keys($found)[array_search($picked, $options, true)];
            }
        }

        return (string) $this->ask('Path to the package (the folder with its composer.json)');
    }

    /**
     * Packages next to this app (../*, ../*\/*) and in ~/projects, keyed by path.
     *
     * @return array<string, string>
     */
    private function detectLocalPackages(Laralink $laralink): array
    {
        $roots = [dirname($laralink->getBasePath())];
        $home = getenv('HOME');

        if ($home && is_dir($home.'/projects')) {
            $roots[] = $home.'/projects';
        }

        $found = [];
        $self = realpath($laralink->getBasePath());

        foreach ($roots as $root) {
            foreach (array_merge(glob($root.'/*/composer.json') ?: [], glob($root.'/*/*/composer.json') ?: []) as $file) {
                $path = realpath(dirname($file));
                $data = json_decode((string) file_get_contents($file), true);

                if (! $path || $path === $self || str_starts_with($path, $self.DIRECTORY_SEPARATOR)
                    || ! is_array($data) || empty($data['name']) || ($data['type'] ?? 'library') === 'project') {
                    continue;
                }

                $found[$path] = $data['name'];
            }
        }

        asort($found);

        return $found;
    }

    private function localDirectory(string $source): ?string
    {
        if (str_starts_with($source, '~/') && getenv('HOME')) {
            $source = getenv('HOME').substr($source, 1);
        }

        $path = realpath($source);

        return $path !== false && is_dir($path) ? $path : null;
    }

    private function assertGitUrl(string $source): string
    {
        if (preg_match('#^(https?|ssh|git|file)://#', $source) || preg_match('#^[\w.-]+@[\w.-]+:#', $source) || str_ends_with($source, '.git')) {
            return $source;
        }

        throw new RuntimeException("'{$source}' is neither a directory nor a Git URL.");
    }

    /**
     * @param  array<string, mixed>  $composer
     */
    private function packageName(Laralink $laralink, array $composer, ?string $expected): string
    {
        if (empty($composer['name']) || ! is_string($composer['name'])) {
            throw new RuntimeException('The package composer.json has no "name".');
        }

        $name = $this->normalizedName($laralink, $composer['name']);

        if ($expected !== null && $expected !== $name) {
            throw new RuntimeException("The package is named {$name} in its composer.json, not {$expected}.");
        }

        return $name;
    }

    private function normalizedName(Laralink $laralink, string $name): string
    {
        return implode('/', $laralink->parseName($name));
    }
}

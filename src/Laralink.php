<?php

namespace AmjadIqbal\Laralink;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * The project-side model: the ./packages directory, the path repositories in composer.json,
 * and the small state file that remembers how each package was linked.
 */
class Laralink
{
    public const STATE_FILE = '.laralink.json';

    private ?string $basePath = null;

    public function __construct(protected Filesystem $files) {}

    /**
     * Override the project root (used by tests).
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public function getBasePath(): string
    {
        return $this->basePath ?? base_path();
    }

    public function packagesPath(): string
    {
        return $this->getBasePath().DIRECTORY_SEPARATOR.'packages';
    }

    public function localPath(string $vendor, string $package): string
    {
        return $this->packagesPath().DIRECTORY_SEPARATOR.$vendor.DIRECTORY_SEPARATOR.$package;
    }

    /**
     * The path repository URL Laralink writes for a package, relative to the project root.
     */
    public function repositoryUrl(string $vendor, string $package): string
    {
        return './packages/'.$vendor.'/'.$package;
    }

    /**
     * True when ./packages/vendor/package exists, as a directory or as a symlink (even a broken one).
     */
    public function isLinked(string $vendor, string $package): bool
    {
        $path = $this->localPath($vendor, $package);

        return is_link($path) || $this->files->isDirectory($path);
    }

    /**
     * @return array{vendor: string, package: string}
     */
    public function parseName(string $name): array
    {
        $name = strtolower(trim($name));

        if (! preg_match('#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$#', $name)) {
            throw new RuntimeException("Invalid package name '{$name}'. Expected format: vendor/package");
        }

        [$vendor, $package] = explode('/', $name, 2);

        return ['vendor' => $vendor, 'package' => $package];
    }

    /**
     * @return array<string, mixed>
     */
    public function readComposerJson(): array
    {
        return $this->readJsonFile($this->getBasePath().DIRECTORY_SEPARATOR.'composer.json', 'composer.json not found in project root.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function writeComposerJson(array $data): void
    {
        $this->writeJsonFile($this->getBasePath().DIRECTORY_SEPARATOR.'composer.json', $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function readPackageComposerJson(string $sourcePath): array
    {
        return $this->readJsonFile(
            rtrim($sourcePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json',
            "No composer.json found in {$sourcePath}."
        );
    }

    /**
     * Copy a package directory into ./packages, skipping .git, vendor and node_modules.
     */
    public function copyPackageDirectory(string $sourcePath, string $vendor, string $package): void
    {
        $this->copyDirectoryFiltered($sourcePath, $this->localPath($vendor, $package));
    }

    /**
     * Point ./packages/vendor/package at a directory elsewhere on disk.
     */
    public function symlinkPackageDirectory(string $sourcePath, string $vendor, string $package): void
    {
        $target = $this->localPath($vendor, $package);
        $this->files->ensureDirectoryExists(dirname($target));

        if (! @symlink($sourcePath, $target)) {
            throw new RuntimeException("Could not create a symlink at {$target}.");
        }
    }

    /**
     * Remove ./packages/vendor/package. A symlink is unlinked and its target is never touched.
     */
    public function removeLocalCopy(string $vendor, string $package): void
    {
        $path = $this->localPath($vendor, $package);

        if (is_link($path)) {
            $this->files->delete($path);
        } elseif ($this->files->isDirectory($path)) {
            $this->files->deleteDirectory($path);
        }

        $vendorDir = dirname($path);

        if ($this->files->isDirectory($vendorDir) && $this->files->isEmptyDirectory($vendorDir)) {
            $this->files->deleteDirectory($vendorDir);
        }
    }

    /**
     * Copy ./packages/vendor/package to ./packages/.backups/vendor/package-<timestamp> and return that path.
     */
    public function backupLocalCopy(string $vendor, string $package): string
    {
        $backup = $this->packagesPath().DIRECTORY_SEPARATOR.'.backups'.DIRECTORY_SEPARATOR.$vendor
            .DIRECTORY_SEPARATOR.$package.'-'.date('Ymd-His');

        $this->copyDirectoryFiltered($this->localPath($vendor, $package), $backup, ['vendor', 'node_modules']);

        return $backup;
    }

    public function hasPathRepository(string $vendor, string $package): bool
    {
        $url = $this->repositoryUrl($vendor, $package);

        foreach ($this->readComposerJson()['repositories'] ?? [] as $repo) {
            if (is_array($repo) && ($repo['type'] ?? null) === 'path' && ($repo['url'] ?? null) === $url) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a symlinked path repository for the package, first in the list so it wins over any other source.
     */
    public function addPathRepository(string $vendor, string $package): void
    {
        if ($this->hasPathRepository($vendor, $package)) {
            return;
        }

        $data = $this->readComposerJson();
        $repositories = $data['repositories'] ?? [];

        // composer.json also allows an object keyed by name; Composer reads it in order, so prepend either way.
        $entry = ['type' => 'path', 'url' => $this->repositoryUrl($vendor, $package), 'options' => ['symlink' => true]];
        $data['repositories'] = array_is_list($repositories)
            ? array_merge([$entry], $repositories)
            : ['laralink-'.$vendor.'-'.$package => $entry] + $repositories;

        $this->writeComposerJson($data);
    }

    public function removePathRepository(string $vendor, string $package): void
    {
        $data = $this->readComposerJson();

        if (empty($data['repositories'])) {
            return;
        }

        $url = $this->repositoryUrl($vendor, $package);
        $isList = array_is_list($data['repositories']);
        $kept = array_filter(
            $data['repositories'],
            fn ($repo) => ! (is_array($repo) && ($repo['type'] ?? null) === 'path' && ($repo['url'] ?? null) === $url)
        );

        if ($kept === []) {
            unset($data['repositories']);
        } else {
            $data['repositories'] = $isList ? array_values($kept) : $kept;
        }

        $this->writeComposerJson($data);
    }

    /**
     * Every vendor/package under ./packages (dot directories such as .backups are skipped).
     *
     * @return array<int, string>
     */
    public function linkedPackages(): array
    {
        $packagesPath = $this->packagesPath();

        if (! $this->files->isDirectory($packagesPath)) {
            return [];
        }

        $linked = [];

        foreach ($this->files->directories($packagesPath) as $vendorDir) {
            $vendor = basename($vendorDir);

            if (str_starts_with($vendor, '.')) {
                continue;
            }

            foreach (scandir($vendorDir) ?: [] as $entry) {
                $path = $vendorDir.DIRECTORY_SEPARATOR.$entry;

                if (! str_starts_with($entry, '.') && (is_link($path) || is_dir($path))) {
                    $linked[] = $vendor.'/'.$entry;
                }
            }
        }

        sort($linked);

        return $linked;
    }

    /**
     * Packages required by the project, with require taking precedence over require-dev.
     *
     * @return array<string, string>
     */
    public function requiredPackages(): array
    {
        $data = $this->readComposerJson();

        return array_merge($data['require-dev'] ?? [], $data['require'] ?? []);
    }

    /**
     * Where and how a package is required, or null when it is not.
     *
     * @return array{section: string, constraint: string}|null
     */
    public function requirement(string $name): ?array
    {
        $data = $this->readComposerJson();

        foreach (['require', 'require-dev'] as $section) {
            if (isset($data[$section][$name])) {
                return ['section' => $section, 'constraint' => (string) $data[$section][$name]];
            }
        }

        return null;
    }

    /**
     * Installed versions from composer.lock, keyed by package name.
     *
     * @return array<string, string>
     */
    public function installedVersions(): array
    {
        $lock = $this->getBasePath().DIRECTORY_SEPARATOR.'composer.lock';

        if (! $this->files->exists($lock)) {
            return [];
        }

        $data = json_decode($this->files->get($lock), true);
        $versions = [];

        foreach (array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []) as $package) {
            if (isset($package['name'], $package['version'])) {
                $versions[$package['name']] = $package['version'];
            }
        }

        return $versions;
    }

    /**
     * How each package was linked, kept in ./packages/.laralink.json.
     *
     * @return array<string, array<string, mixed>>
     */
    public function state(): array
    {
        $path = $this->packagesPath().DIRECTORY_SEPARATOR.self::STATE_FILE;

        if (! $this->files->exists($path)) {
            return [];
        }

        $data = json_decode($this->files->get($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>|null  $entry  null removes the package from the state file
     */
    public function rememberLink(string $name, ?array $entry): void
    {
        $state = $this->state();

        if ($entry === null) {
            unset($state[$name]);
        } else {
            $state[$name] = $entry;
        }

        $path = $this->packagesPath().DIRECTORY_SEPARATOR.self::STATE_FILE;

        if ($state === []) {
            $this->files->delete($path);

            return;
        }

        ksort($state);
        $this->files->ensureDirectoryExists($this->packagesPath());
        $this->writeJsonFile($path, $state);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path, string $missingMessage): array
    {
        if (! $this->files->exists($path)) {
            throw new RuntimeException($missingMessage);
        }

        $decoded = json_decode($this->files->get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Failed to parse {$path}: ".json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJsonFile(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException("Failed to encode {$path}: ".json_last_error_msg());
        }

        $this->files->put($path, $json.PHP_EOL);
    }

    /**
     * @param  array<int, string>  $exclude
     */
    private function copyDirectoryFiltered(string $source, string $destination, array $exclude = ['.git', 'vendor', 'node_modules']): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);

        $this->files->ensureDirectoryExists($destination);

        foreach ($this->files->directories($source) as $dir) {
            if (! in_array(basename($dir), $exclude, true)) {
                $this->copyDirectoryFiltered($dir, $destination.DIRECTORY_SEPARATOR.basename($dir), $exclude);
            }
        }

        foreach ($this->files->files($source, true) as $file) {
            $this->files->copy($file->getPathname(), $destination.DIRECTORY_SEPARATOR.$file->getFilename());
        }
    }
}

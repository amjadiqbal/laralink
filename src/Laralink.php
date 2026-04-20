<?php

namespace Amjadiqbal\Laralink;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class Laralink
{
    private ?string $basePath = null;

    public function __construct(protected Filesystem $files) {}

    /**
     * Override the base path (useful for testing).
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = $path;
    }

    /**
     * Return the base path of the project.
     */
    public function getBasePath(): string
    {
        return $this->basePath ?? base_path();
    }

    /**
     * Return the absolute path to the local packages directory.
     */
    public function packagesPath(): string
    {
        return $this->getBasePath() . DIRECTORY_SEPARATOR . 'packages';
    }

    /**
     * Return the local path for a given vendor/package.
     */
    public function localPath(string $vendor, string $package): string
    {
        return $this->packagesPath() . DIRECTORY_SEPARATOR . $vendor . DIRECTORY_SEPARATOR . $package;
    }

    /**
     * Check whether a package has been linked locally.
     */
    public function isLinked(string $vendor, string $package): bool
    {
        return $this->files->isDirectory($this->localPath($vendor, $package));
    }

    /**
     * Parse vendor and package name from a "vendor/package" string.
     *
     * @return array{vendor: string, package: string}
     */
    public function parseName(string $name): array
    {
        $parts = explode('/', $name, 2);

        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            throw new RuntimeException("Invalid package name '{$name}'. Expected format: vendor/package");
        }

        return ['vendor' => $parts[0], 'package' => $parts[1]];
    }

    /**
     * Read and decode the root composer.json file.
     *
     * @return array<string, mixed>
     */
    public function readComposerJson(): array
    {
        $path = $this->getBasePath() . DIRECTORY_SEPARATOR . 'composer.json';

        if (! $this->files->exists($path)) {
            throw new RuntimeException('composer.json not found in project root.');
        }

        $contents = $this->files->get($path);
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse composer.json: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Write an array back to the root composer.json with pretty formatting.
     *
     * @param array<string, mixed> $data
     */
    public function writeComposerJson(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Failed to encode composer.json: ' . json_last_error_msg());
        }

        $path    = $this->getBasePath() . DIRECTORY_SEPARATOR . 'composer.json';
        $tmpPath = $path . '.tmp';

        $this->files->put($tmpPath, $json . PHP_EOL);
        $this->files->move($tmpPath, $path);
    }

    /**
     * Read and decode a composer.json file at the given path.
     *
     * @return array<string, mixed>
     */
    public function readPackageComposerJson(string $sourcePath): array
    {
        $path = rtrim($sourcePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';

        if (! $this->files->exists($path)) {
            throw new RuntimeException('No composer.json found at the provided path.');
        }

        $contents = $this->files->get($path);
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse composer.json: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Copy a package directory to the local packages folder, excluding .git and vendor directories.
     */
    public function copyPackageDirectory(string $sourcePath, string $vendor, string $package): void
    {
        $destinationPath = $this->localPath($vendor, $package);

        if ($this->files->isDirectory($destinationPath)) {
            $this->files->deleteDirectory($destinationPath);
        }

        $this->files->makeDirectory($destinationPath, 0755, true);

        $this->copyDirectoryFiltered($sourcePath, $destinationPath);
    }

    /**
     * Recursively copy a directory, excluding .git and vendor directories.
     */
    private function copyDirectoryFiltered(string $source, string $destination): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);

        if (! $this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0755, true);
        }

        $excludeDirs = ['.git', 'vendor'];

        foreach ($this->files->directories($source) as $dir) {
            $dirName = basename($dir);

            if (in_array($dirName, $excludeDirs, true)) {
                continue;
            }

            $this->copyDirectoryFiltered($dir, $destination . DIRECTORY_SEPARATOR . $dirName);
        }

        foreach ($this->files->files($source) as $file) {
            $this->files->copy($file, $destination . DIRECTORY_SEPARATOR . basename($file));
        }
    }

    /**
     * Add a path repository entry for the given local package to composer.json.
     */
    public function addPathRepository(string $vendor, string $package, bool $symlink = false): void
    {
        $data = $this->readComposerJson();
        $url  = './packages/' . $vendor . '/' . $package;

        $data['repositories'] = $data['repositories'] ?? [];

        foreach ($data['repositories'] as $repo) {
            if (isset($repo['type'], $repo['url']) && $repo['type'] === 'path' && $repo['url'] === $url) {
                return;
            }
        }

        $entry = [
            'type' => 'path',
            'url'  => $url,
        ];

        if ($symlink) {
            $entry['options'] = ['symlink' => true];
        }

        $data['repositories'][] = $entry;

        $this->writeComposerJson($data);
    }

    /**
     * Remove the path repository entry for the given local package from composer.json.
     */
    public function removePathRepository(string $vendor, string $package): void
    {
        $data = $this->readComposerJson();
        $url  = './packages/' . $vendor . '/' . $package;

        if (empty($data['repositories'])) {
            return;
        }

        $data['repositories'] = array_values(array_filter(
            $data['repositories'],
            fn ($repo) => ! (isset($repo['type'], $repo['url']) && $repo['type'] === 'path' && $repo['url'] === $url)
        ));

        if (empty($data['repositories'])) {
            unset($data['repositories']);
        }

        $this->writeComposerJson($data);
    }

    /**
     * Return all locally linked packages by scanning the packages directory.
     *
     * @return array<string> List of "vendor/package" strings.
     */
    public function linkedPackages(): array
    {
        $packagesPath = $this->packagesPath();

        if (! $this->files->isDirectory($packagesPath)) {
            return [];
        }

        $linked = [];

        foreach ($this->files->directories($packagesPath) as $vendorDir) {
            $vendorName = basename($vendorDir);
            foreach ($this->files->directories($vendorDir) as $packageDir) {
                $linked[] = $vendorName . '/' . basename($packageDir);
            }
        }

        return $linked;
    }

    /**
     * Return all packages currently required in composer.json.
     * Packages in 'require' take precedence over 'require-dev' when the same
     * package appears in both sections.
     *
     * @return array<string, string>
     */
    public function requiredPackages(): array
    {
        $data = $this->readComposerJson();

        // Merge require-dev first so that require entries take precedence
        return array_merge(
            $data['require-dev'] ?? [],
            $data['require'] ?? []
        );
    }
}

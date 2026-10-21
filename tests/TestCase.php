<?php

namespace AmjadIqbal\Laralink\Tests;

use AmjadIqbal\Laralink\Contracts\ProcessRunner;
use AmjadIqbal\Laralink\Laralink;
use AmjadIqbal\Laralink\LaralinkServiceProvider;
use AmjadIqbal\Laralink\Tests\Support\FakeProcessRunner;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /** A throwaway Laravel project root with its own composer.json. */
    protected string $project;

    /** A folder next to the project for package sources. */
    protected string $sources;

    public FakeProcessRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $root = sys_get_temp_dir().'/laralink-'.bin2hex(random_bytes(4));
        $this->project = $root.'/app';
        $this->sources = $root.'/src';
        mkdir($this->project, 0755, true);
        mkdir($this->sources, 0755, true);
        $this->project = (string) realpath($this->project);
        $this->sources = (string) realpath($this->sources);

        $this->writeJson($this->project.'/composer.json', [
            'name' => 'acme/app',
            'type' => 'project',
            'require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'],
            'require-dev' => ['acme/dev-tool' => '^2.0'],
        ]);

        $this->app->make(Laralink::class)->setBasePath($this->project);
        $this->runner = new FakeProcessRunner;
        $this->app->instance(ProcessRunner::class, $this->runner);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(dirname($this->project));

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [LaralinkServiceProvider::class];
    }

    /**
     * Create a package source folder and return its real path.
     *
     * @param  array<string, mixed>  $composer
     */
    public function makePackage(string $name, array $composer = []): string
    {
        $path = $this->sources.'/'.str_replace('/', '-', $name);
        mkdir($path.'/src', 0755, true);
        file_put_contents($path.'/src/Hello.php', "<?php\n");
        $this->writeJson($path.'/composer.json', ['name' => $name] + $composer);

        return (string) realpath($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function composerJson(): array
    {
        return json_decode((string) file_get_contents($this->project.'/composer.json'), true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}

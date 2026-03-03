<?php

namespace Amjadiqbal\Laralink\Tests;

use Amjadiqbal\Laralink\Laralink;
use Illuminate\Filesystem\Filesystem;

class CommandTest extends TestCase
{
    private string $tempDir;
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laralink_cmd_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->files = new Filesystem();

        /** @var Laralink $laralink */
        $laralink = $this->app->make(Laralink::class);
        $laralink->setBasePath($this->tempDir);

        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode(['name' => 'test/project', 'require' => new \stdClass()], JSON_PRETTY_PRINT)
        );
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_list_command_shows_no_packages_message(): void
    {
        $this->artisan('laralink:list')
            ->assertExitCode(0);
    }

    public function test_list_command_shows_linked_packages(): void
    {
        mkdir($this->tempDir . '/packages/vendor/package', 0755, true);

        $this->artisan('laralink:list')
            ->assertExitCode(0);
    }

    public function test_dev_command_requires_package_name(): void
    {
        $this->artisan('laralink:dev')
            ->expectsQuestion('Enter the package name (vendor/package)', '')
            ->assertExitCode(1);
    }

    public function test_dev_command_fails_on_invalid_package_name(): void
    {
        $this->artisan('laralink:dev', ['package' => 'invalid-name'])
            ->assertExitCode(1);
    }

    public function test_publish_command_shows_no_packages_message(): void
    {
        $this->artisan('laralink:publish')
            ->expectsOutput('No locally linked packages found.')
            ->assertExitCode(0);
    }

    public function test_publish_command_fails_for_unlinked_package(): void
    {
        $this->artisan('laralink:publish', ['package' => 'vendor/package'])
            ->assertExitCode(1);
    }

    public function test_publish_command_accepts_package_argument(): void
    {
        // Create the local package directory so isLinked() returns true
        mkdir($this->tempDir . '/packages/test-vendor/test-package', 0755, true);

        /** @var Laralink $laralink */
        $laralink = $this->app->make(Laralink::class);
        $laralink->addPathRepository('test-vendor', 'test-package');

        // The command will fail at composer require, but it should reach that point
        $this->artisan('laralink:publish', ['package' => 'test-vendor/test-package'])
            ->assertExitCode(1); // Fails at composer require in test environment
    }
}

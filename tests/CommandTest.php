<?php

namespace Amjadiqbal\Laralink\Tests;

use Amjadiqbal\Laralink\Contracts\ProcessRunner;
use Amjadiqbal\Laralink\Laralink;
use Amjadiqbal\Laralink\Tests\Support\FakeProcessRunner;
use Illuminate\Filesystem\Filesystem;

class CommandTest extends TestCase
{
    private string $tempDir;
    private Filesystem $files;
    private FakeProcessRunner $fakeRunner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laralink_cmd_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->files = new Filesystem();

        // Bind a fake process runner so commands never invoke real git/composer.
        $this->fakeRunner = new FakeProcessRunner();
        $this->app->bind(ProcessRunner::class, fn () => $this->fakeRunner);

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

    // ── laralink:list ─────────────────────────────────────────────────────────

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

    // ── laralink:dev – git mode ───────────────────────────────────────────────

    public function test_dev_command_requires_package_name(): void
    {
        $this->artisan('laralink:dev')
            ->expectsChoice('Install from', 'Git Repository', ['Local Path', 'Git Repository'])
            ->expectsQuestion('Enter the package name (vendor/package)', '')
            ->assertExitCode(1);
    }

    public function test_dev_command_fails_on_invalid_package_name(): void
    {
        $this->artisan('laralink:dev', ['package' => 'invalid-name'])
            ->expectsChoice('Install from', 'Git Repository', ['Local Path', 'Git Repository'])
            ->assertExitCode(1);
    }

    public function test_dev_command_git_mode_requires_package_name(): void
    {
        $this->artisan('laralink:dev')
            ->expectsChoice('Install from', 'Git Repository', ['Local Path', 'Git Repository'])
            ->expectsQuestion('Enter the package name (vendor/package)', '')
            ->assertExitCode(1);
    }

    public function test_dev_command_git_mode_fails_on_invalid_package_name(): void
    {
        $this->artisan('laralink:dev')
            ->expectsChoice('Install from', 'Git Repository', ['Local Path', 'Git Repository'])
            ->expectsQuestion('Enter the package name (vendor/package)', 'invalid-name')
            ->assertExitCode(1);
    }

    public function test_dev_command_git_mode_succeeds(): void
    {
        $this->artisan('laralink:dev', ['package' => 'vendor/my-package'])
            ->expectsChoice('Install from', 'Git Repository', ['Local Path', 'Git Repository'])
            ->expectsQuestion(
                'Enter the Git URL to clone (press Enter to use suggested URL)',
                'https://github.com/vendor/my-package.git'
            )
            ->assertExitCode(0);

        /** @var Laralink $laralink */
        $laralink = $this->app->make(Laralink::class);
        $data = $laralink->readComposerJson();

        $this->assertArrayHasKey('repositories', $data);
        $this->assertCount(1, $data['repositories']);
        $this->assertSame('./packages/vendor/my-package', $data['repositories'][0]['url']);
    }

    // ── laralink:dev – local path mode ────────────────────────────────────────

    public function test_dev_command_local_path_fails_when_empty_path(): void
    {
        $this->artisan('laralink:dev')
            ->expectsChoice('Install from', 'Local Path', ['Local Path', 'Git Repository'])
            ->expectsQuestion('Enter the absolute path to your local package', '')
            ->expectsOutput('A source path is required.')
            ->assertExitCode(1);
    }

    public function test_dev_command_local_path_fails_when_no_composer_json(): void
    {
        $sourcePath = $this->tempDir . '/empty-source';
        mkdir($sourcePath, 0755, true);

        $this->artisan('laralink:dev')
            ->expectsChoice('Install from', 'Local Path', ['Local Path', 'Git Repository'])
            ->expectsQuestion('Enter the absolute path to your local package', $sourcePath)
            ->expectsOutput('No composer.json found at the provided path.')
            ->assertExitCode(1);
    }

    public function test_dev_command_local_path_fails_when_no_package_name(): void
    {
        $sourcePath = $this->tempDir . '/source-pkg';
        mkdir($sourcePath, 0755, true);
        file_put_contents(
            $sourcePath . '/composer.json',
            json_encode(['description' => 'no name'], JSON_PRETTY_PRINT)
        );

        $this->artisan('laralink:dev')
            ->expectsChoice('Install from', 'Local Path', ['Local Path', 'Git Repository'])
            ->expectsQuestion('Enter the absolute path to your local package', $sourcePath)
            ->expectsOutput('Package name not found in composer.json.')
            ->assertExitCode(1);
    }

    public function test_dev_command_local_path_succeeds(): void
    {
        $sourcePath = $this->tempDir . '/source-pkg';
        mkdir($sourcePath . '/src', 0755, true);
        file_put_contents(
            $sourcePath . '/composer.json',
            json_encode(['name' => 'vendor/my-pkg', 'description' => 'Test'], JSON_PRETTY_PRINT)
        );
        file_put_contents($sourcePath . '/src/MyClass.php', '<?php class MyClass {}');

        $this->artisan('laralink:dev')
            ->expectsChoice('Install from', 'Local Path', ['Local Path', 'Git Repository'])
            ->expectsQuestion('Enter the absolute path to your local package', $sourcePath)
            ->assertExitCode(0);

        /** @var Laralink $laralink */
        $laralink = $this->app->make(Laralink::class);
        $this->assertTrue($laralink->isLinked('vendor', 'my-pkg'));

        $data = $laralink->readComposerJson();
        $this->assertArrayHasKey('repositories', $data);
        $this->assertSame('./packages/vendor/my-pkg', $data['repositories'][0]['url']);
        $this->assertSame(['symlink' => true], $data['repositories'][0]['options']);
    }

    // ── laralink:publish ──────────────────────────────────────────────────────

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

    public function test_publish_command_succeeds(): void
    {
        mkdir($this->tempDir . '/packages/test-vendor/test-package', 0755, true);

        /** @var Laralink $laralink */
        $laralink = $this->app->make(Laralink::class);
        $laralink->addPathRepository('test-vendor', 'test-package');

        $this->artisan('laralink:publish', ['package' => 'test-vendor/test-package'])
            ->expectsConfirmation('Delete local source code from ./packages?', 'no')
            ->assertExitCode(0);

        $data = $laralink->readComposerJson();
        $this->assertArrayNotHasKey('repositories', $data);
    }

    public function test_publish_command_rolls_back_composer_json_on_require_failure(): void
    {
        mkdir($this->tempDir . '/packages/test-vendor/test-package', 0755, true);

        /** @var Laralink $laralink */
        $laralink = $this->app->make(Laralink::class);
        $laralink->addPathRepository('test-vendor', 'test-package');

        // Override with a failing runner for this test only.
        $this->app->bind(ProcessRunner::class, fn () => new FakeProcessRunner(false));

        $this->artisan('laralink:publish', ['package' => 'test-vendor/test-package'])
            ->assertExitCode(1);

        // The path repository must have been restored by the rollback.
        $data = $laralink->readComposerJson();
        $this->assertArrayHasKey('repositories', $data);
        $this->assertCount(1, $data['repositories']);
        $this->assertSame('./packages/test-vendor/test-package', $data['repositories'][0]['url']);
    }

    public function test_publish_command_deletes_local_source_with_flag(): void
    {
        mkdir($this->tempDir . '/packages/test-vendor/test-pkg', 0755, true);

        /** @var Laralink $laralink */
        $laralink = $this->app->make(Laralink::class);
        $laralink->addPathRepository('test-vendor', 'test-pkg');

        $this->artisan('laralink:publish', ['package' => 'test-vendor/test-pkg', '--delete' => true])
            ->assertExitCode(0);

        $this->assertFalse($laralink->isLinked('test-vendor', 'test-pkg'));
    }
}

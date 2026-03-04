<?php

namespace Amjadiqbal\Laralink\Tests;

use Amjadiqbal\Laralink\Laralink;
use Illuminate\Filesystem\Filesystem;

class LaralinkTest extends TestCase
{
    private string $tempDir;
    private Filesystem $files;
    private Laralink $laralink;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laralink_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->files = new Filesystem();
        $this->laralink = new Laralink($this->files);
        $this->laralink->setBasePath($this->tempDir);

        // Create a minimal composer.json
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

    public function test_parse_name_returns_vendor_and_package(): void
    {
        $result = $this->laralink->parseName('vendor/package');

        $this->assertSame('vendor', $result['vendor']);
        $this->assertSame('package', $result['package']);
    }

    public function test_parse_name_throws_on_invalid_format(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid package name 'invalidname'");

        $this->laralink->parseName('invalidname');
    }

    public function test_parse_name_throws_on_empty_vendor(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->laralink->parseName('/package');
    }

    public function test_parse_name_throws_on_empty_package(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->laralink->parseName('vendor/');
    }

    public function test_is_linked_returns_false_when_directory_missing(): void
    {
        $this->assertFalse($this->laralink->isLinked('vendor', 'package'));
    }

    public function test_is_linked_returns_true_when_directory_exists(): void
    {
        $localPath = $this->tempDir . '/packages/vendor/package';
        mkdir($localPath, 0755, true);

        $this->assertTrue($this->laralink->isLinked('vendor', 'package'));
    }

    public function test_local_path_returns_correct_path(): void
    {
        $expected = $this->tempDir . '/packages/vendor/package';
        $this->assertSame($expected, $this->laralink->localPath('vendor', 'package'));
    }

    public function test_read_composer_json_returns_array(): void
    {
        $data = $this->laralink->readComposerJson();

        $this->assertIsArray($data);
        $this->assertSame('test/project', $data['name']);
    }

    public function test_read_composer_json_throws_when_file_missing(): void
    {
        unlink($this->tempDir . '/composer.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer.json not found');

        $this->laralink->readComposerJson();
    }

    public function test_add_path_repository_adds_entry(): void
    {
        $this->laralink->addPathRepository('vendor', 'package');

        $data = $this->laralink->readComposerJson();

        $this->assertArrayHasKey('repositories', $data);
        $this->assertCount(1, $data['repositories']);
        $this->assertSame('path', $data['repositories'][0]['type']);
        $this->assertSame('./packages/vendor/package', $data['repositories'][0]['url']);
    }

    public function test_add_path_repository_does_not_duplicate(): void
    {
        $this->laralink->addPathRepository('vendor', 'package');
        $this->laralink->addPathRepository('vendor', 'package');

        $data = $this->laralink->readComposerJson();

        $this->assertCount(1, $data['repositories']);
    }

    public function test_remove_path_repository_removes_entry(): void
    {
        $this->laralink->addPathRepository('vendor', 'package');
        $this->laralink->removePathRepository('vendor', 'package');

        $data = $this->laralink->readComposerJson();

        $this->assertArrayNotHasKey('repositories', $data);
    }

    public function test_remove_path_repository_only_removes_matching(): void
    {
        $this->laralink->addPathRepository('vendor', 'package-one');
        $this->laralink->addPathRepository('vendor', 'package-two');
        $this->laralink->removePathRepository('vendor', 'package-one');

        $data = $this->laralink->readComposerJson();

        $this->assertCount(1, $data['repositories']);
        $this->assertSame('./packages/vendor/package-two', $data['repositories'][0]['url']);
    }

    public function test_remove_path_repository_does_nothing_when_not_present(): void
    {
        $this->laralink->removePathRepository('vendor', 'package');

        $data = $this->laralink->readComposerJson();

        $this->assertArrayNotHasKey('repositories', $data);
    }

    public function test_linked_packages_returns_empty_when_no_packages_dir(): void
    {
        $this->assertSame([], $this->laralink->linkedPackages());
    }

    public function test_linked_packages_returns_all_local_packages(): void
    {
        mkdir($this->tempDir . '/packages/vendor-a/package-one', 0755, true);
        mkdir($this->tempDir . '/packages/vendor-a/package-two', 0755, true);
        mkdir($this->tempDir . '/packages/vendor-b/package-three', 0755, true);

        $linked = $this->laralink->linkedPackages();

        sort($linked);

        $this->assertCount(3, $linked);
        $this->assertContains('vendor-a/package-one', $linked);
        $this->assertContains('vendor-a/package-two', $linked);
        $this->assertContains('vendor-b/package-three', $linked);
    }

    public function test_required_packages_merges_require_and_require_dev(): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode([
                'name'        => 'test/project',
                'require'     => ['vendor/pkg-a' => '^1.0'],
                'require-dev' => ['vendor/pkg-b' => '^2.0'],
            ], JSON_PRETTY_PRINT)
        );

        $required = $this->laralink->requiredPackages();

        $this->assertArrayHasKey('vendor/pkg-a', $required);
        $this->assertArrayHasKey('vendor/pkg-b', $required);
    }

    public function test_write_composer_json_preserves_existing_data(): void
    {
        $data = $this->laralink->readComposerJson();
        $data['extra'] = ['test' => true];
        $this->laralink->writeComposerJson($data);

        $fresh = $this->laralink->readComposerJson();
        $this->assertSame(true, $fresh['extra']['test']);
    }

    public function test_read_package_composer_json_returns_array(): void
    {
        $sourcePath = $this->tempDir . '/source-package';
        mkdir($sourcePath, 0755, true);
        file_put_contents(
            $sourcePath . '/composer.json',
            json_encode(['name' => 'vendor/my-package', 'description' => 'A test package'], JSON_PRETTY_PRINT)
        );

        $data = $this->laralink->readPackageComposerJson($sourcePath);

        $this->assertIsArray($data);
        $this->assertSame('vendor/my-package', $data['name']);
    }

    public function test_read_package_composer_json_throws_when_missing(): void
    {
        $sourcePath = $this->tempDir . '/empty-dir';
        mkdir($sourcePath, 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No composer.json found at the provided path.');

        $this->laralink->readPackageComposerJson($sourcePath);
    }

    public function test_copy_package_directory_copies_files(): void
    {
        $sourcePath = $this->tempDir . '/source-package';
        mkdir($sourcePath . '/src', 0755, true);
        file_put_contents($sourcePath . '/composer.json', '{}');
        file_put_contents($sourcePath . '/src/MyClass.php', '<?php class MyClass {}');

        $this->laralink->copyPackageDirectory($sourcePath, 'vendor', 'my-package');

        $destPath = $this->laralink->localPath('vendor', 'my-package');
        $this->assertFileExists($destPath . '/composer.json');
        $this->assertFileExists($destPath . '/src/MyClass.php');
    }

    public function test_copy_package_directory_excludes_git_and_vendor(): void
    {
        $sourcePath = $this->tempDir . '/source-package';
        mkdir($sourcePath . '/.git', 0755, true);
        mkdir($sourcePath . '/vendor', 0755, true);
        mkdir($sourcePath . '/src', 0755, true);
        file_put_contents($sourcePath . '/.git/config', 'git config');
        file_put_contents($sourcePath . '/vendor/autoload.php', '<?php');
        file_put_contents($sourcePath . '/src/MyClass.php', '<?php class MyClass {}');
        file_put_contents($sourcePath . '/composer.json', '{}');

        $this->laralink->copyPackageDirectory($sourcePath, 'vendor', 'filtered-pkg');

        $destPath = $this->laralink->localPath('vendor', 'filtered-pkg');
        $this->assertDirectoryDoesNotExist($destPath . '/.git');
        $this->assertDirectoryDoesNotExist($destPath . '/vendor');
        $this->assertFileExists($destPath . '/src/MyClass.php');
        $this->assertFileExists($destPath . '/composer.json');
    }

    public function test_add_path_repository_with_symlink_option(): void
    {
        $this->laralink->addPathRepository('vendor', 'package', true);

        $data = $this->laralink->readComposerJson();

        $this->assertArrayHasKey('repositories', $data);
        $this->assertCount(1, $data['repositories']);
        $this->assertSame('path', $data['repositories'][0]['type']);
        $this->assertSame('./packages/vendor/package', $data['repositories'][0]['url']);
        $this->assertSame(['symlink' => true], $data['repositories'][0]['options']);
    }

    public function test_add_path_repository_without_symlink_has_no_options(): void
    {
        $this->laralink->addPathRepository('vendor', 'package');

        $data = $this->laralink->readComposerJson();

        $this->assertArrayHasKey('repositories', $data);
        $this->assertArrayNotHasKey('options', $data['repositories'][0]);
    }
}

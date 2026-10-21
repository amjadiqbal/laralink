<?php

function linkForPublish($test, string $name, array $extra = []): string
{
    $source = $test->makePackage($name);
    $test->artisan('laralink:dev', ['--source' => $source] + $extra)->assertSuccessful();
    $test->runner->commands = [];

    return $source;
}

it('removes the path repository and restores the previous constraint', function () {
    $source = linkForPublish($this, 'acme/dev-tool');

    $this->artisan('laralink:publish', ['package' => 'acme/dev-tool', '--force' => true])->assertSuccessful();

    expect($this->composerJson())->not->toHaveKey('repositories')
        ->and($this->runner->composerCalls())->toBe(['require acme/dev-tool:^2.0 --with-all-dependencies --dev'])
        ->and(file_exists($this->project.'/packages/.laralink.json'))->toBeFalse()
        ->and(is_link($this->project.'/packages/acme/dev-tool'))->toBeTrue();
});

it('requires the latest release when the package was not required before', function () {
    linkForPublish($this, 'acme/hello');

    $this->artisan('laralink:publish', ['package' => 'acme/hello', '--force' => true])->assertSuccessful();

    expect($this->runner->composerCalls())->toBe(['require acme/hello --with-all-dependencies']);
});

it('only unlinks a symlink with --delete and never touches the source folder', function () {
    $source = linkForPublish($this, 'acme/hello');

    $this->artisan('laralink:publish', ['package' => 'acme/hello', '--delete' => true, '--force' => true])->assertSuccessful();

    expect(is_link($this->project.'/packages/acme/hello'))->toBeFalse()
        ->and(file_exists($source.'/src/Hello.php'))->toBeTrue();
});

it('backs up a package, Git history included, before deleting it', function () {
    $this->runner->cloneComposer = ['name' => 'acme/hello'];
    $this->artisan('laralink:dev', ['--source' => 'https://github.com/acme/hello.git'])->assertSuccessful();
    mkdir($this->project.'/packages/acme/hello/src');
    file_put_contents($this->project.'/packages/acme/hello/src/Hello.php', '<?php');
    file_put_contents($this->project.'/packages/acme/hello/.git/HEAD', 'ref: refs/heads/main');

    $this->artisan('laralink:publish', ['package' => 'acme/hello', '--delete' => true, '--backup' => true, '--force' => true])
        ->expectsOutputToContain('.backups')
        ->assertSuccessful();

    $backups = glob($this->project.'/packages/.backups/acme/hello-*');
    expect($backups)->toHaveCount(1)
        ->and(file_exists($backups[0].'/src/Hello.php'))->toBeTrue()
        ->and(file_get_contents($backups[0].'/.git/HEAD'))->toBe('ref: refs/heads/main')
        ->and(file_exists($this->project.'/packages/acme/hello'))->toBeFalse();
});

it('refuses to delete a clone with uncommitted changes unless backed up or forced', function () {
    $this->runner->cloneComposer = ['name' => 'acme/remote'];
    $this->artisan('laralink:dev', ['--source' => 'https://github.com/acme/remote.git'])->assertSuccessful();
    $this->runner->gitStatus = " M src/Hello.php\n";

    $this->artisan('laralink:publish', ['package' => 'acme/remote', '--delete' => true, '--no-interaction' => true])
        ->expectsOutputToContain('uncommitted changes')
        ->assertFailed();

    expect(is_dir($this->project.'/packages/acme/remote'))->toBeTrue()
        ->and($this->composerJson()['repositories'][0]['url'])->toBe('./packages/acme/remote');
});

it('keeps the package linked when Composer cannot install a release', function () {
    linkForPublish($this, 'acme/hello');
    $before = $this->composerJson();
    $this->runner->composerExitCode = 2;

    $this->artisan('laralink:publish', ['package' => 'acme/hello', '--delete' => true, '--force' => true])
        ->expectsOutputToContain('still linked')
        ->assertFailed();

    expect($this->composerJson())->toBe($before)
        ->and(is_link($this->project.'/packages/acme/hello'))->toBeTrue()
        ->and(json_decode(file_get_contents($this->project.'/packages/.laralink.json'), true))->toHaveKey('acme/hello');
});

it('asks which linked package to publish', function () {
    linkForPublish($this, 'acme/hello');

    $this->artisan('laralink:publish')
        ->expectsChoice('Which package do you want to switch back?', 'acme/hello', ['acme/hello'])
        ->expectsConfirmation('Continue?', 'yes')
        ->assertSuccessful();

    expect($this->runner->composerCalls())->toBe(['require acme/hello --with-all-dependencies']);
});

it('says so when nothing is linked', function () {
    $this->artisan('laralink:publish')->expectsOutputToContain('No linked packages')->assertFailed();
});

it('fails for a package that is not linked', function () {
    $this->artisan('laralink:publish', ['package' => 'acme/nothing', '--force' => true])
        ->expectsOutputToContain('is not linked')
        ->assertFailed();
});

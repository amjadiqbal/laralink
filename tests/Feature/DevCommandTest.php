<?php

it('symlinks a local package, adds a path repository and requires it at @dev', function () {
    $source = $this->makePackage('acme/hello', ['require' => ['php' => '^8.3']]);

    $this->artisan('laralink:dev', ['--source' => $source])->assertSuccessful();

    $link = $this->project.'/packages/acme/hello';
    expect(is_link($link))->toBeTrue()
        ->and(readlink($link))->toBe($source)
        ->and($this->composerJson()['repositories'][0])->toBe([
            'type' => 'path', 'url' => './packages/acme/hello', 'options' => ['symlink' => true],
        ])
        ->and($this->runner->composerCalls())->toBe(['require acme/hello:@dev --with-all-dependencies'])
        ->and($this->runner->commands[0]['cwd'])->toBe($this->project);

    $state = json_decode(file_get_contents($this->project.'/packages/.laralink.json'), true);
    expect($state['acme/hello'])->toMatchArray(['kind' => 'symlink', 'source' => $source, 'previous' => null]);
});

it('copies instead of symlinking with --copy, skipping .git and vendor', function () {
    $source = $this->makePackage('acme/hello');
    mkdir($source.'/.git');
    mkdir($source.'/vendor');
    mkdir($source.'/.github');
    file_put_contents($source.'/.github/funding.yml', 'x');
    symlink('src/Hello.php', $source.'/hello-link.php');

    $this->artisan('laralink:dev', ['--source' => $source, '--copy' => true])->assertSuccessful();

    $copy = $this->project.'/packages/acme/hello';
    expect(is_link($copy))->toBeFalse()
        ->and(file_exists($copy.'/src/Hello.php'))->toBeTrue()
        ->and(file_exists($copy.'/.git'))->toBeFalse()
        ->and(file_exists($copy.'/vendor'))->toBeFalse()
        ->and(file_exists($copy.'/.github/funding.yml'))->toBeTrue()
        ->and(readlink($copy.'/hello-link.php'))->toBe('src/Hello.php');
});

it('clones a Git repository into ./packages under the name from its composer.json', function () {
    $this->runner->cloneComposer = ['name' => 'acme/remote'];

    $this->artisan('laralink:dev', ['--source' => 'git@github.com:acme/remote.git', '--branch' => 'develop'])
        ->assertSuccessful();

    $clone = $this->runner->commands[0]['command'];
    expect(array_slice($clone, 0, 5))->toBe(['git', 'clone', '--branch', 'develop', 'git@github.com:acme/remote.git'])
        ->and(is_dir($this->project.'/packages/acme/remote/.git'))->toBeTrue()
        ->and(glob($this->project.'/packages/.clone-*'))->toBe([])
        ->and($this->runner->composerCalls())->toBe(['require acme/remote:@dev --with-all-dependencies']);
});

it('defaults to the GitHub URL for a package name when not interactive', function () {
    $this->runner->cloneComposer = ['name' => 'acme/remote'];

    $this->artisan('laralink:dev', ['package' => 'acme/remote', '--no-interaction' => true])->assertSuccessful();

    expect($this->runner->commands[0]['command'])->toContain('https://github.com/acme/remote.git');
});

it('refuses a clone whose composer.json names a different package and cleans up', function () {
    $this->runner->cloneComposer = ['name' => 'other/thing'];

    $this->artisan('laralink:dev', ['package' => 'acme/remote', '--no-interaction' => true])
        ->expectsOutputToContain('named other/thing')
        ->assertFailed();

    expect(glob($this->project.'/packages/{,.}*', GLOB_BRACE))->not->toContain($this->project.'/packages/other');
    expect(glob($this->project.'/packages/.clone-*'))->toBe([]);
});

it('reports a failed clone', function () {
    $this->runner->cloneExitCode = 128;

    $this->artisan('laralink:dev', ['--source' => 'https://github.com/acme/missing.git'])
        ->expectsOutputToContain('git clone failed')
        ->assertFailed();

    expect($this->runner->composerCalls())->toBe([]);
});

it('stops when the package does not support this PHP or Laravel version', function () {
    $source = $this->makePackage('acme/old', ['require' => ['php' => '^7.4', 'illuminate/support' => '^8.0']]);

    $this->artisan('laralink:dev', ['--source' => $source])
        ->expectsOutputToContain('NOT MET')
        ->assertFailed();

    expect(file_exists($this->project.'/packages/acme/old'))->toBeFalse()
        ->and($this->runner->composerCalls())->toBe([]);
});

it('links an incompatible package with --skip-version-check', function () {
    $source = $this->makePackage('acme/old', ['require' => ['php' => '^7.4']]);

    $this->artisan('laralink:dev', ['--source' => $source, '--skip-version-check' => true])->assertSuccessful();

    expect(is_link($this->project.'/packages/acme/old'))->toBeTrue();
});

it('restores composer.json and removes the link when Composer fails', function () {
    $source = $this->makePackage('acme/hello');
    $before = file_get_contents($this->project.'/composer.json');
    $this->runner->composerExitCode = 2;

    $this->artisan('laralink:dev', ['--source' => $source])
        ->expectsOutputToContain('composer.json has been restored')
        ->assertFailed();

    expect(json_decode(file_get_contents($this->project.'/composer.json'), true))->toBe(json_decode($before, true))
        ->and(file_exists($this->project.'/packages/acme/hello'))->toBeFalse()
        ->and(is_link($this->project.'/packages/acme/hello'))->toBeFalse()
        ->and(file_exists($this->project.'/packages/.laralink.json'))->toBeFalse()
        ->and(file_exists($source.'/src/Hello.php'))->toBeTrue();
});

it('remembers an existing require-dev constraint and keeps the package in require-dev', function () {
    $source = $this->makePackage('acme/dev-tool');

    $this->artisan('laralink:dev', ['--source' => $source])->assertSuccessful();

    $state = json_decode(file_get_contents($this->project.'/packages/.laralink.json'), true);
    expect($state['acme/dev-tool']['previous'])->toBe(['section' => 'require-dev', 'constraint' => '^2.0'])
        ->and($this->runner->composerCalls())->toBe(['require acme/dev-tool:@dev --with-all-dependencies --dev']);
});

it('does nothing when the package is already linked', function () {
    $source = $this->makePackage('acme/hello');
    $this->artisan('laralink:dev', ['--source' => $source])->assertSuccessful();

    $this->artisan('laralink:dev', ['package' => 'acme/hello'])
        ->expectsOutputToContain('already linked')
        ->assertSuccessful();

    expect($this->runner->composerCalls())->toHaveCount(1);
});

it('will not replace a symlink without --force, and never replaces a real folder', function () {
    $first = $this->makePackage('acme/hello');
    $this->artisan('laralink:dev', ['--source' => $first])->assertSuccessful();

    $second = $this->sources.'/elsewhere';
    mkdir($second);
    file_put_contents($second.'/composer.json', json_encode(['name' => 'acme/hello']));
    $second = realpath($second);

    $this->artisan('laralink:dev', ['--source' => $second])->expectsOutputToContain('--force')->assertFailed();
    $this->artisan('laralink:dev', ['--source' => $second, '--force' => true])->assertSuccessful();
    expect(readlink($this->project.'/packages/acme/hello'))->toBe($second);

    $copySource = $this->makePackage('acme/copied');
    $this->artisan('laralink:dev', ['--source' => $copySource, '--copy' => true])->assertSuccessful();
    $this->artisan('laralink:dev', ['--source' => $copySource, '--force' => true])->expectsOutputToContain('already exists')->assertFailed();
    expect(is_link($this->project.'/packages/acme/copied'))->toBeFalse();
});

it('reinstalls when vendor/ still points at the previous source after a forced re-link', function () {
    $first = $this->makePackage('acme/hello');
    $this->artisan('laralink:dev', ['--source' => $first])->assertSuccessful();
    mkdir($this->project.'/vendor/acme', 0755, true);
    symlink($first, $this->project.'/vendor/acme/hello');

    $second = $this->sources.'/second';
    mkdir($second);
    file_put_contents($second.'/composer.json', json_encode(['name' => 'acme/hello']));

    $this->artisan('laralink:dev', ['--source' => $second, '--force' => true])->assertSuccessful();

    expect(array_slice($this->runner->composerCalls(), 1))->toBe([
        'require acme/hello:@dev --with-all-dependencies',
        'reinstall acme/hello',
    ]);
});

it('re-links files already in ./packages in place', function () {
    mkdir($this->project.'/packages/acme/kept', 0755, true);
    file_put_contents($this->project.'/packages/acme/kept/composer.json', json_encode(['name' => 'acme/kept']));

    $this->artisan('laralink:dev', ['package' => 'acme/kept'])->assertSuccessful();

    expect($this->composerJson()['repositories'][0]['url'])->toBe('./packages/acme/kept')
        ->and($this->runner->composerCalls())->toBe(['require acme/kept:@dev --with-all-dependencies']);
});

it('asks where the package is and offers packages found next to the app', function () {
    $source = $this->makePackage('acme/nearby');

    $this->artisan('laralink:dev')
        ->expectsChoice('Where is the package?', 'A local path', ['A local path', 'A Git repository'])
        ->expectsChoice('Which package?', "acme/nearby ({$source})", ["acme/nearby ({$source})", 'Enter a path'])
        ->assertSuccessful();

    expect(readlink($this->project.'/packages/acme/nearby'))->toBe($source);
});

it('asks for the name, branch and URL of a Git repository', function () {
    $this->runner->cloneComposer = ['name' => 'acme/remote'];

    $this->artisan('laralink:dev')
        ->expectsChoice('Where is the package?', 'A Git repository', ['A local path', 'A Git repository'])
        ->expectsQuestion('Package name (vendor/package)', 'acme/remote')
        ->expectsQuestion('Branch or tag (leave empty for the default branch)', 'v2')
        ->expectsQuestion('Git URL', 'https://gitlab.com/acme/remote.git')
        ->assertSuccessful();

    expect(array_slice($this->runner->commands[0]['command'], 0, 5))
        ->toBe(['git', 'clone', '--branch', 'v2', 'https://gitlab.com/acme/remote.git']);
});

it('needs a package or --source without interaction', function () {
    $this->artisan('laralink:dev', ['--no-interaction' => true])
        ->expectsOutputToContain('Pass a package name or --source')
        ->assertFailed();
});

it('rejects a source that is neither a folder nor a Git URL', function () {
    $this->artisan('laralink:dev', ['--source' => '/no/such/folder'])
        ->expectsOutputToContain('neither a directory nor a Git URL')
        ->assertFailed();
});

it('rejects an invalid package name', function () {
    $this->artisan('laralink:dev', ['package' => 'not-a-name', '--no-interaction' => true])
        ->expectsOutputToContain('Invalid package name')
        ->assertFailed();
});

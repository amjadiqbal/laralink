<?php

use AmjadIqbal\Laralink\Laralink;

beforeEach(fn () => $this->laralink = $this->app->make(Laralink::class));

it('parses and validates package names', function () {
    expect($this->laralink->parseName('Acme/Hello-World'))->toBe(['vendor' => 'acme', 'package' => 'hello-world']);
    expect(fn () => $this->laralink->parseName('acme'))->toThrow(RuntimeException::class);
    expect(fn () => $this->laralink->parseName('acme/../etc'))->toThrow(RuntimeException::class);
});

it('prepends one path repository and removes only its own', function () {
    $composer = $this->composerJson();
    $composer['repositories'] = [['type' => 'vcs', 'url' => 'https://github.com/acme/hello']];
    $this->writeJson($this->project.'/composer.json', $composer);

    $this->laralink->addPathRepository('acme', 'hello');
    $this->laralink->addPathRepository('acme', 'hello');

    expect($this->composerJson()['repositories'])->toHaveCount(2)
        ->and($this->composerJson()['repositories'][0]['url'])->toBe('./packages/acme/hello');

    $this->laralink->removePathRepository('acme', 'hello');

    expect($this->composerJson()['repositories'])->toBe([['type' => 'vcs', 'url' => 'https://github.com/acme/hello']]);
});

it('handles repositories written as an object', function () {
    $composer = $this->composerJson();
    $composer['repositories'] = ['private' => ['type' => 'composer', 'url' => 'https://repo.example.com']];
    $this->writeJson($this->project.'/composer.json', $composer);

    $this->laralink->addPathRepository('acme', 'hello');
    expect(array_keys($this->composerJson()['repositories']))->toBe(['laralink-acme-hello', 'private']);

    $this->laralink->removePathRepository('acme', 'hello');
    expect($this->composerJson()['repositories'])->toBe(['private' => ['type' => 'composer', 'url' => 'https://repo.example.com']]);
});

it('lists linked packages, symlinks included, skipping dot folders', function () {
    mkdir($this->project.'/packages/acme/one', 0755, true);
    mkdir($this->project.'/packages/.backups/acme/old', 0755, true);
    symlink($this->sources, $this->project.'/packages/acme/two');

    expect($this->laralink->linkedPackages())->toBe(['acme/one', 'acme/two']);
});

it('reads requirements and installed versions', function () {
    $this->writeJson($this->project.'/composer.lock', ['packages' => [['name' => 'laravel/framework', 'version' => 'v13.1.0']]]);

    expect($this->laralink->requirement('acme/dev-tool'))->toBe(['section' => 'require-dev', 'constraint' => '^2.0'])
        ->and($this->laralink->requirement('acme/none'))->toBeNull()
        ->and($this->laralink->installedVersions())->toBe(['laravel/framework' => 'v13.1.0']);
});

it('reports a missing or broken composer.json', function () {
    unlink($this->project.'/composer.json');
    expect(fn () => $this->laralink->readComposerJson())->toThrow(RuntimeException::class, 'composer.json not found');

    file_put_contents($this->project.'/composer.json', '{nope');
    expect(fn () => $this->laralink->readComposerJson())->toThrow(RuntimeException::class, 'Failed to parse');
});

<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $source = $this->makePackage('acme/hello');
    $this->artisan('laralink:dev', ['--source' => $source])->assertSuccessful();

    $composer = $this->composerJson();
    $composer['require']['acme/hello'] = '@dev';
    $this->writeJson($this->project.'/composer.json', $composer);

    mkdir($this->project.'/packages/acme/leftover', 0755, true);

    $this->writeJson($this->project.'/composer.lock', ['packages' => [
        ['name' => 'laravel/framework', 'version' => 'v13.1.0'],
        ['name' => 'acme/hello', 'version' => 'dev-main'],
    ], 'packages-dev' => [['name' => 'acme/dev-tool', 'version' => '2.3.0']]]);
});

it('lists required and local packages with their status as JSON', function () {
    $this->withoutMockingConsoleOutput();

    expect(Artisan::call('laralink:list', ['--json' => true]))->toBe(0);
    $rows = collect(json_decode(Artisan::output(), true))->keyBy('name');

    expect($rows->keys()->all())->toBe(['acme/hello', 'acme/leftover', 'acme/dev-tool', 'laravel/framework'])
        ->and($rows['acme/hello'])->toMatchArray(['status' => 'linked', 'local' => true, 'installed' => 'dev-main', 'location' => './packages/acme/hello'])
        ->and($rows['acme/leftover']['status'])->toBe('unlinked')
        ->and($rows['acme/dev-tool'])->toMatchArray(['status' => 'remote', 'section' => 'require-dev', 'constraint' => '^2.0', 'installed' => '2.3.0'])
        ->and($rows['laravel/framework']['location'])->toBe('vendor/laravel/framework');
});

it('filters to local or remote packages', function () {
    $this->withoutMockingConsoleOutput();

    Artisan::call('laralink:list', ['--json' => true, '--filter' => 'local']);
    $local = array_column(json_decode(Artisan::output(), true), 'name');

    Artisan::call('laralink:list', ['--json' => true, '--filter' => 'remote']);
    $remote = array_column(json_decode(Artisan::output(), true), 'name');

    expect($local)->toBe(['acme/hello', 'acme/leftover'])
        ->and($remote)->toBe(['acme/dev-tool', 'laravel/framework']);
});

it('renders a table with the totals', function () {
    $this->artisan('laralink:list')
        ->expectsOutputToContain('acme/hello')
        ->expectsOutputToContain('Linked locally')
        ->assertSuccessful();
});

it('rejects an unknown filter', function () {
    $this->artisan('laralink:list', ['--filter' => 'nope'])->assertFailed();
});

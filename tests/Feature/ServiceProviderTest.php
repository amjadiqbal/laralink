<?php

use AmjadIqbal\Laralink\Facades\Laralink;
use Illuminate\Support\Facades\Artisan;

it('registers the commands and the facade', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('laralink:dev', 'laralink:list', 'laralink:publish')
        ->and(Laralink::packagesPath())->toBe($this->project.'/packages');
});

it('adds a passive entry to php artisan about', function () {
    $this->artisan('about', ['--only' => 'laralink'])
        ->expectsOutputToContain('amjad.com.pk/contact')
        ->assertSuccessful();
});

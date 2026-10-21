<?php

namespace AmjadIqbal\Laralink;

use AmjadIqbal\Laralink\Commands\DevCommand;
use AmjadIqbal\Laralink\Commands\ListCommand;
use AmjadIqbal\Laralink\Commands\PublishCommand;
use AmjadIqbal\Laralink\Contracts\ProcessRunner;
use AmjadIqbal\Laralink\Support\SymfonyProcessRunner;
use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class LaralinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Laralink::class);
        $this->app->bindIf(ProcessRunner::class, SymfonyProcessRunner::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DevCommand::class,
            ListCommand::class,
            PublishCommand::class,
        ]);

        AboutCommand::add('Laralink', fn () => [
            'Version' => InstalledVersions::isInstalled('amjadiqbal/laralink')
                ? (string) InstalledVersions::getPrettyVersion('amjadiqbal/laralink')
                : 'unknown',
            'Support' => 'https://amjad.com.pk/contact?utm_source=artisan&utm_medium=about&utm_campaign=laralink',
        ]);
    }
}

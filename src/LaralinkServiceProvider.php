<?php

namespace AmjadIqbal\Laralink;

use AmjadIqbal\Laralink\Commands\DevCommand;
use AmjadIqbal\Laralink\Commands\ListCommand;
use AmjadIqbal\Laralink\Commands\PublishCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class LaralinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Laralink::class, function ($app) {
            return new Laralink($app->make(Filesystem::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DevCommand::class,
                ListCommand::class,
                PublishCommand::class,
            ]);
        }
    }
}

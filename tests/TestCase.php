<?php

namespace AmjadIqbal\Laralink\Tests;

use AmjadIqbal\Laralink\LaralinkServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaralinkServiceProvider::class,
        ];
    }
}

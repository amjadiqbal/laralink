<?php

namespace Amjadiqbal\Laralink\Tests;

use Amjadiqbal\Laralink\LaralinkServiceProvider;
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

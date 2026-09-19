<?php

namespace AmjadIqbal\Laralink\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string packagesPath()
 * @method static string localPath(string $vendor, string $package)
 * @method static bool isLinked(string $vendor, string $package)
 * @method static array parseName(string $name)
 * @method static array linkedPackages()
 * @method static array requiredPackages()
 *
 * @see \AmjadIqbal\Laralink\Laralink
 */
class Laralink extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AmjadIqbal\Laralink\Laralink::class;
    }
}

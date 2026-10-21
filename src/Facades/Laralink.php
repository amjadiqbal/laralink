<?php

namespace AmjadIqbal\Laralink\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string packagesPath()
 * @method static string localPath(string $vendor, string $package)
 * @method static bool isLinked(string $vendor, string $package)
 * @method static bool hasPathRepository(string $vendor, string $package)
 * @method static array{vendor: string, package: string} parseName(string $name)
 * @method static array<int, string> linkedPackages()
 * @method static array<string, string> requiredPackages()
 * @method static array<string, string> installedVersions()
 * @method static array<string, array<string, mixed>> state()
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

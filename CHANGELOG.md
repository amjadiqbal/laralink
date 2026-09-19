# Changelog

All notable changes to the Laralink package will be documented in this file.

## [1.1.1] - 2026-09-19

Found and fixed during manual integration testing against a fresh Laravel 13 / PHP 8.5 install
(before publishing to Laravel News). Reproduced by requiring the package into a real app and
running every artisan command it ships.

### Fixed
- **`composer require amjadiqbal/laralink` failed on the current Laravel release.** `composer.json`
  capped `illuminate/support`, `illuminate/console` and `illuminate/filesystem` at `^10.0|^11.0`.
  A fresh Laravel 13 app pulls in `illuminate/*` `^13.x`, so resolution failed outright
  ("these were not loaded, likely because it conflicts with another require"). The package only
  uses stable `Illuminate\Filesystem\Filesystem` and `Illuminate\Console\Command` APIs, so this was
  a stale constraint, not a real incompatibility. Widened to also accept `^12.0|^13.0`.
- **Package discovery silently failed to register the facade alias (and would break entirely on a
  case-sensitive filesystem, i.e. every real Linux server).** `composer.json`'s PSR-4 map and
  `extra.laravel.providers`/`aliases` all reference the `AmjadIqbal\Laralink\...` namespace
  (capital I), but `src/Laralink.php`, `src/LaralinkServiceProvider.php`, and all three test files
  declared `namespace Amjadiqbal\Laralink...` (lowercase i). This worked by accident on this
  machine's case-insensitive filesystem but would fail to autoload at all on a case-sensitive one.
  Renamed every declaration to match the case Composer and Laravel's package discovery actually
  look for.
- **The `Laralink` facade registered in `composer.json` didn't exist.** `extra.laravel.aliases`
  declares `"Laralink": "AmjadIqbal\\Laralink\\Facades\\Laralink"`, but there was no `Facades/`
  directory in `src/` at all — calling the facade would throw `Class "...Facades\Laralink" not
  found`. Added `src/Facades/Laralink.php`, a standard `Illuminate\Support\Facades\Facade`
  subclass resolving to the already-bound `Laralink::class` singleton.

Manually verified after the fixes: `composer require` against a real Laravel 13 project,
`laralink:list`, `laralink:dev acme/hello --source=<local path>` (real path-repository symlink
install, confirmed via `composer.json`'s `repositories` entry and a real `packages/acme/hello`
directory on disk), and `laralink:publish acme/hello` (cleanly removed the path repository and
switched the require back) — all worked end to end. The facade was exercised directly via
`AmjadIqbal\Laralink\Facades\Laralink::packagesPath()`.

**Not touched in this pass:** `src/Commands/DevCommand.php`, `src/Commands/ListCommand.php`,
`src/Commands/PublishCommand.php`, and the new `src/Support/DependencyChecker.php` had
uncommitted local changes already in progress from earlier work when this testing session found
them. Per instruction, those are left exactly as found — not reviewed, not modified, not
committed here. They were exercised as-is above (with their in-progress code) since that's what a
real `composer require` would pull in from this working tree, and no problems surfaced in that
exercise, but they still need their own author to finish and commit them.

## [1.0.0] - Initial release

- `laralink:dev` — link a local or Git-sourced package for development with automatic path
  repository setup.
- `laralink:list` — show every required package's link status.
- `laralink:publish` — switch a linked package back to its Packagist version.

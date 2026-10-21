# Changelog

All notable changes to `amjadiqbal/laralink` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased] - planned as 2.0.0

The three commands were rebuilt. 1.x was never on Packagist; its tagged commands did not have the options
its README described (`--source`, `--branch`, `--json`, `--filter`, `--backup`), `laralink:publish --delete`
removed the files of a locally linked package, and nothing was undone when Composer failed.

### Added

- `laralink:dev --source=` takes a folder (absolute, relative or `~/`) or any Git URL (`https://`,
  `git@host:`, `ssh://`, `file://`); `--branch=` clones a branch or tag; `--copy` copies instead of
  symlinking; `--skip-version-check`; `--force` re-points an existing symlink.
- A PHP and Laravel compatibility check against the package's own `composer.json` (Composer semver rules).
- Interactive mode lists packages found next to the app (`../*`, `../*/*`) and in `~/projects`.
- `laralink:list --json` and `--filter=local|remote`, with the constraint, the installed version from
  `composer.lock`, and a status (linked, local files not linked, linked but not required, remote).
- `laralink:publish --backup` (copies to `./packages/.backups/`, Git history included) and `--force`.
- `./packages/.laralink.json` remembers how each package was linked and the constraint it had before.
- `php artisan about` shows the installed version and a support link.

### Changed

- Requires PHP 8.3+ and Laravel 12 or 13.
- The path repository is added first in `composer.json`, so it wins over other repositories.
- `laralink:dev` keeps a package that was in `require-dev` in `require-dev`.
- `laralink:publish` restores the constraint the package had before it was linked (or requires the latest
  release) instead of always running a bare `composer require`.

### Fixed

- `laralink:publish --delete` on a symlinked package only removes the symlink; the source folder is never
  touched. A clone with uncommitted changes is not deleted without `--backup` or `--force`.
- If Composer fails, `laralink:dev` restores `composer.json` and removes the link or clone it created, and
  `laralink:publish` restores `composer.json` and leaves the package linked.
- After a forced re-link to another folder, `vendor/` now points at the new folder (`composer reinstall`).
- `--copy` and backups keep dot folders such as `.git` and `.github`.
- The command classes declared `Amjadiqbal\Laralink` (lowercase i) while Composer autoloads
  `AmjadIqbal\Laralink`.
- `composer.json` no longer has a hard-coded `version` field or `minimum-stability: dev`.

### Removed

- The `--symlink` option (symlinking is the default for folders; use `--copy` to copy).
- The `laralink-config` publish tag mentioned in the old README; there was never a config file.

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

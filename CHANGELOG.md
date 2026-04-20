# Changelog

All notable changes to Laralink are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-04-20

### Added

- `laralink:dev` command – link a package for local development from a local path or a Git repository.
- `laralink:list` command – show all packages with **Linked (local)** vs **Remote (Packagist)** status.
- `laralink:publish` command – remove the local path repository and switch a package back to its official Packagist version.
- Automatic `composer.json` path-repository management (add / remove entries).
- **Atomic writes** for `composer.json` – changes are written to a temporary file and renamed into place to prevent corruption on failure.
- **Rollback** in `laralink:publish` – if `composer require` fails, `composer.json` is automatically restored to its pre-publish state.
- **Clean re-copy** – re-linking a local-path package now deletes the stale destination before copying, preventing ghost files from a previous copy.
- `ProcessRunner` contract (`Amjadiqbal\Laralink\Contracts\ProcessRunner`) and `SymfonyProcessRunner` implementation to decouple process execution from command logic and enable deterministic testing.
- Support for Laravel 9, 10, 11, and 12.
- GitHub Actions CI matrix covering PHP 8.0–8.3 × Laravel 9–12.
- Tag-triggered GitHub Release workflow.
- `CONTRIBUTING.md`, `SECURITY.md`, PR template, and GitHub Issue templates.

[Unreleased]: https://github.com/amjadiqbal/laralink/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/amjadiqbal/laralink/releases/tag/v0.1.0

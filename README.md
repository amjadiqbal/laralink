# Laralink

**Local-First Package Manager for Laravel**

Laralink automates the local package development workflow. Instead of manually editing `composer.json` and running `git clone`, Laralink handles it all with a single interactive command.

---

## How It Works

```
laravel-project/
├── app/
├── packages/              ← Managed by Laralink
│   └── vendor/
│       └── my-package/    ← Cloned source code
├── composer.json          ← Auto-updated with path repositories
└── vendor/                ← Standard Composer vendor directory
```

---

## Installation

```bash
composer require amjadiqbal/laralink
```

The service provider is auto-discovered. No additional setup is needed.

---

## Commands

### `laralink:dev {package?}`

Link a package for local development. Laralink will:

1. Check if `./packages/vendor/package` exists locally.
2. If not, prompt for a Git URL (defaulting to the GitHub URL) and clone it.
3. Add a Composer `path` repository to your `composer.json`.
4. Run `composer require vendor/package:@dev` automatically.

```bash
php artisan laralink:dev vendor/package
# or interactively:
php artisan laralink:dev
```

### `laralink:list`

Show all packages with their status — **Linked (local)** vs **Remote (Packagist)**.

```bash
php artisan laralink:list
```

Output example:
```
+---------------------+--------------------+---------+
| Package             | Status             | Version |
+---------------------+--------------------+---------+
| vendor/my-package   | Linked (local)     | @dev    |
| laravel/framework   | Remote (Packagist) | ^11.0   |
+---------------------+--------------------+---------+
```

### `laralink:publish {package?}`

Remove the local path repository and install the official version from Packagist. Optionally delete the local source code.

```bash
php artisan laralink:publish vendor/package
# or interactively (shows a list to choose from):
php artisan laralink:publish

# Also delete local source code:
php artisan laralink:publish vendor/package --delete
```

---

## Typical Workflow

```bash
# 1. Start local development
php artisan laralink:dev vendor/my-package
# → Clones to ./packages/vendor/my-package
# → Updates composer.json
# → Runs composer require vendor/my-package:@dev

# 2. Make changes in ./packages/vendor/my-package

# 3. See what's linked
php artisan laralink:list

# 4. When done, switch back to Packagist
php artisan laralink:publish vendor/my-package
```

---

## License

MIT


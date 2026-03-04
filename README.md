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

1. Ask you to choose between installing from a **Local Path** or a **Git Repository**.
2. **Local Path mode**: Provide the path to your local package. Laralink will verify the `composer.json`, auto-detect the package name, copy files (excluding `.git/` and `vendor/`), and link it.
3. **Git Repository mode**: Provide the package name (or pass it as an argument). Laralink will prompt for a Git URL (defaulting to the GitHub URL) and clone it.
4. Add a Composer `path` repository to your `composer.json`.
5. Run `composer require vendor/package:@dev` automatically.

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

## Local-First Development

You can install a package directly from a local folder on your machine for testing and development.

### Steps

1. Run the command:
   ```bash
   php artisan laralink:dev
   ```

2. Select **Local Path** when prompted:
   ```
   Install from:
     [0] Local Path
     [1] Git Repository
   ```

3. Provide the absolute path to your package folder:
   ```
   Enter the absolute path to your local package:
   > /home/user/projects/my-package
   ```

4. The tool will automatically:
   - **Verify** the `composer.json` in the source directory.
   - **Detect** the package name (e.g., `vendor/my-package`) from the source `composer.json`.
   - **Copy** the files to the project's `./packages` directory (excluding `.git/` and `vendor/` folders).
   - **Link** it via a Composer path repository with the `symlink` option enabled.
   - **Run** `composer require` to install the local package.

> **Warning:** If you are not using the symlink option, changes made in the original local source will not be reflected automatically. You will need to re-run the command or use `composer update` to pick up changes.

---

## License

MIT


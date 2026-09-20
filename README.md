# 🔗 Laralink

**Local-First Package Manager for Laravel**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amjadiqbal/laralink.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/laralink)
[![Total Downloads](https://img.shields.io/packagist/dt/amjadiqbal/laralink.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/laralink)
[![License](https://img.shields.io/packagist/l/amjadiqbal/laralink.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/laralink)
[![PHP Version Require](https://img.shields.io/packagist/php-v/amjadiqbal/laralink?style=flat-square)](https://packagist.org/packages/amjadiqbal/laralink)
[![Laravel Version](https://shields.io|10.x|11.x-red?style=flat-square)](https://laravel.com)

*Streamline your Laravel package development workflow with intelligent local package management*

---

## ✨ Why Laralink?

Developing Laravel packages locally shouldn't be a hassle. Laralink transforms the tedious process of cloning repositories, manually editing `composer.json`, and configuring path repositories into a single, intelligent command.

### 🎯 Key Benefits

- **⚡ Lightning Fast Setup**: Link any package in seconds, not minutes.
- **🧠 Smart Detection**: Automatically finds packages on your machine.
- **🔗 Seamless Integration**: Works with Git repositories and local paths.
- **🔄 Instant Reflection**: Changes appear immediately with automatic symlinks.
- **📦 Clean Workflow**: Organized `./packages` directory keeps your project tidy.
- **🛡️ Error-Proof**: Intelligent error handling with actionable solutions.

---

## 📦 Installation

You can install the package via composer:

```bash
composer require amjadiqbal/laralink
```

The service provider is automatically discovered by Laravel. No additional setup is required.

### Optional: Publish Configuration
If you want to customize Laralink's behavior, publish the configuration file:

```bash
php artisan vendor:publish --tag=laralink-config
```

This will create a `config/laralink.php` file where you can modify default settings.

### Requirements
- **PHP**: 8.1 or higher
- **Laravel**: 9.0, 10.0, or 11.0
- **Composer**: 2.0 or higher

---

## 🚀 Quick Start
Here's how to get started with Laralink in 3 simple steps:

### 1. Link a Package
```bash
# Interactive mode (recommended for beginners)
php artisan laralink:dev

# Direct installation from GitHub
php artisan laralink:dev vendor/package-name
```

### 2. Check Status
```bash
# View all packages and their status
php artisan laralink:list
```

### 3. Publish When Done
```bash
# Switch back to Packagist version
php artisan laralink:publish vendor/package-name
```

---

## 📖 Available Commands

### `laralink:dev` - Link Package for Development
Link a package for local development with smart detection and automatic setup.

**Signature:**
```bash
php artisan laralink:dev [package-name] [options]
```

**Options:**
- `--source=` - Direct source (Git URL or local path)
- `--branch=main` - Specify Git branch
- `--symlink` - Use symlink instead of copying files
- `--no-interaction` - Skip all interactive prompts

**Examples:**
```bash
# Interactive mode with smart detection
php artisan laralink:dev

# Quick link from GitHub
php artisan laralink:dev vendor/package-name

# Link specific branch
php artisan laralink:dev vendor/package --branch=develop

# Direct Git URL
php artisan laralink:dev vendor/package --source=https://github.com/vendor/package.git

# Use SSH format
php artisan laralink:dev vendor/package --source=git@github.com:vendor/package.git

# Link local package
php artisan laralink:dev --source=/path/to/local/package
```

**What it does:**
- 🧠 Automatically detects local packages in common directories.
- 🌐 Normalizes Git URLs (HTTPS, SSH, shorthand formats).
- 🌿 Clones specific branches for testing.
- 📋 Verifies `composer.json` and detects package name.
- 📁 Copies or symlinks files to `./packages/vendor/package`.
- 🔗 Adds path repository to your `composer.json`.
- 📦 Runs `composer require vendor/package:@dev`.

---

### `laralink:list` - View Package Status
Display all packages with their link status and statistics.

**Signature:**
```bash
php artisan laralink:list [options]
```

**Options:**
- `--json` - Output as JSON (for CI/CD pipelines)
- `--filter=` - Filter by status (`local`|`remote`|`all`)

**Examples:**
```bash
# Standard view
php artisan laralink:list

# JSON output for scripting
php artisan laralink:list --json

# Filter linked packages only
php artisan laralink:list --filter=local
```

**Sample Output:**
```text
📊 Package Status

+---------------------+--------------------+---------+-----------------------------+

| Package             | Status             | Version | Location                    |
+---------------------+--------------------+---------+-----------------------------+

| 🔗 vendor/my-package| Linked (local)     | @dev    | ./packages/vendor/my-package|
| 🌐 laravel/framework| Remote (Packagist) | ^11.0   | vendor/                     |
+---------------------+--------------------+---------+-----------------------------+

📈 Statistics:
  📦 Total packages: 2
  🔗 Linked locally: 1
  🌐 Remote (Packagist): 1
```

---

### `laralink:publish` - Switch to Packagist Version
Remove local linking and switch back to the official Packagist version.

**Signature:**
```bash
php artisan laralink:publish [package-name] [options]
```

**Options:**
- `--delete` - Remove local source code after publishing
- `--force` - Skip confirmation prompts
- `--backup` - Create backup before deleting local files

**Examples:**
```bash
# Interactive selection
php artisan laralink:publish

# Publish specific package
php artisan laralink:publish vendor/my-package

# Publish and cleanup with backup
php artisan laralink:publish vendor/my-package --delete --backup
```

**What it does:**
- 🔍 Shows list of linked packages to choose from.
- 🗑️ Removes path repository from `composer.json`.
- 📦 Runs `composer update` to fetch Packagist version.
- 💾 Optionally creates backup before deletion.
- 🧹 Optionally removes local source code.

---

## 📁 How It Works

```text
your-laravel-project/
├── app/
├── packages/                    ← Managed by Laralink
│   └── vendor/
│       ├── my-package/          ← Cloned/linked source code
│       └── another-package/     ← Symlinked to local path
├── composer.json                ← Auto-updated with path repositories
└── vendor/                      ← Standard Composer vendor directory
```

When you run `laralink:dev`, Laralink:
1. Creates the package directory in `./packages/vendor/package-name`.
2. Adds a path repository entry in your project's `composer.json`.
3. Installs the package with `composer require`.
4. Enables symlink by default for instant change reflection.

---

## 🔄 Typical Workflow

### Starting Local Development
```bash
php artisan laralink:dev vendor/my-package
```

### Making Changes
- Edit files directly in `./packages/vendor/my-package`.
- Changes are immediately reflected in your Laravel app.
- No need to run `composer update`!

### Monitoring Packages
```bash
php artisan laralink:list
```

### Publishing to Production
```bash
# Switch back to Packagist version
php artisan laralink:publish vendor/my-package

# Or cleanup completely
php artisan laralink:publish vendor/my-package --delete --backup
```

---

## 🎨 Local-First Development

### Linking from a Local Path
```bash
# Run the interactive command
php artisan laralink:dev
```
1. Select **"Local Path"** when prompted.
2. Provide the absolute path to your package: `/home/user/projects/my-package`

Laralink will automatically:
- ✅ Verify the `composer.json` exists and is valid.
- 🔍 Detect the package name from `composer.json`.
- 📋 Copy files to `./packages` (excluding `.git/` and `vendor/`).
- 🔗 Create a Composer path repository with symlink option.
- 📦 Run `composer require` to install the package.

### Symlink Benefits
When using symlinks (default for local paths):
- 🔄 Changes reflect immediately in your Laravel application.
- ⚡ No need to run `composer update` after each change.
- 🧪 Perfect for rapid development and testing.
- 💾 No duplicate files taking up disk space.

> 💡 **Note**: If you modify the path repository to disable symlinks, you will need to run `composer update` to pick up changes from the original directory source.

## ⚙️ Configuration

Publish the configuration file to customize Laralink:

```bash
php artisan vendor:publish --tag=laralink-config
```

### Available Configuration Options

```php
return [
    'defaults' => [
        'branch' => 'main',              // Default Git branch
        'packages_path' => 'packages',   // Where packages are stored
        'symlink' => true,               // Use symlinks by default
        'auto_validate' => true,         // Validate packages automatically
    ],
    
    'ui' => [
        'show_progress_bars' => true,    // Show progress indicators
        'enable_colors' => true,         // Use colored output
        'show_emojis' => true,           // Show emoji indicators
    ],
    
    'validation' => [
        'require_composer_json' => true,
        'check_php_compatibility' => true,
    ],
];
```

---

## 🛠️ Common Use Cases

### Team Development
Each developer can link packages locally with different branches:

```bash
# Developer A
php artisan laralink:dev company/core-package --branch=feature/new-api

# Developer B
php artisan laralink:dev company/core-package --branch=bugfix/auth-fix
```

### Monorepo Setup
Link multiple packages from a monorepo structure:

```bash
php artisan laralink:dev --source=./../monorepo/packages/auth
php artisan laralink:dev --source=./../monorepo/packages/payments
php artisan laralink:dev --source=./../monorepo/packages/notifications
```

### Package Testing
Test packages against different versions:

```bash
# Test beta version
php artisan laralink:dev vendor/package --branch=v2.0-beta

# Run your tests
php artisan test

# Switch back when done
php artisan laralink:publish vendor/package
```

### CI/CD Integration
Automate package management in your CI pipeline:

```bash
# Link package from CI variables
php artisan laralink:dev vendor/package \
  --source=\$CI_REPOSITORY_URL \
  --branch=\$CI_COMMIT_BRANCH \
  --no-interaction

# Verify setup
php artisan laralink:list --json

# Cleanup after tests
php artisan laralink:publish vendor/package --force --delete
```

---

## ❗ Troubleshooting

### Permission Denied Errors
```bash
# Fix directory permissions
chmod -R 755 ./packages

# Fix ownership (Linux/Mac)
sudo chown -R \(USER:\)USER ./packages
```

### Git Clone Failures
```bash
# Test repository access
git ls-remote https://github.com/vendor/package.git

# Try SSH instead of HTTPS
php artisan laralink:dev vendor/package --source=git@github.com:vendor/package.git

# Check your Git credentials
git config --list
```

### Composer Issues
```bash
# Clear composer cache
composer clear-cache

# Run diagnostics
composer diagnose

# Update composer itself
composer self-update

# Update with verbose output for debugging
composer update -vvv
```

### Package Not Found
```bash
# Verify package exists on Packagist
curl -s https://repo.packagist.org/p2/vendor/package.json

# Check if package name matches composer.json
cat packages/vendor/package/composer.json | grep name
```

> 💡 **Note**: Ensure your main project's `composer.json` allows development packages by adding `"minimum-stability": "dev"`.

---

## 🧪 Testing

```bash
# Run the test suite
composer test

# With coverage report
composer test-coverage

# Static analysis
composer analyse
```

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup
```bash
# Clone the repository
git clone https://github.com/amjadiqbal/laralink.git

# Navigate to directory
cd laralink

# Install dependencies
composer install

# Run tests
composer test
```

### Guidelines
- Follow PSR-12 coding standards.
- Add tests for new features.
- Update documentation as needed.
- Keep PRs focused and atomic.
- Write clear commit messages.

---

## 📈 Roadmap

Future plans for Laralink:
- **Package Creation Wizard**: Interactive scaffolding for new packages.
- **Multi-Repository Support**: Link from multiple sources simultaneously.
- **Package Analytics**: Track usage and development metrics.
- **Web Dashboard**: Visual interface for package management.
- **VS Code Extension**: IDE integration for seamless workflow.
- **Package Templates**: Pre-configured templates for quick starts.

---

## 📄 License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.

---

## 👨‍💻 Author

**Amjad Iqbal**
- GitHub: [@amjadiqbal](https://github.com/amjadiqbal)

---

## ⭐ Support

If you find Laralink useful, please consider:
- Starring the repository ⭐
- Sharing with your network 🗣️
- Contributing to development 🛠️
- Reporting issues and suggesting features 💡

---

## 📞 Contact

For security issues, please email `your-email@example.com` instead of using the public issue tracker.

---

<p align="center">Made with ❤️ for Laravel developers</p>
<p align="center"><em>Simplify your package development workflow with Laralink</em></p>

## Support & Community

### Custom Development
[Hire me on Upwork](https://www.upwork.com/freelancers/amjadkhatri) for:
- Package integration
- Custom feature development
- Technical consultation
- Project implementation

### Community Support
- [Discord Community](https://discord.com/channels/1352854772859932702/1352854916690874388)
- [GitHub Issues](https://github.com/amjadiqbal/laralink/issues)

For priority support and enterprise solutions, please reach out via Upwork for direct assistance.

## Author

**Amjad Iqbal** — [amjad.com.pk](https://amjad.com.pk) · [hi@amjad.com.pk](mailto:hi@amjad.com.pk) · [GitHub](https://github.com/AmjadIqbal)

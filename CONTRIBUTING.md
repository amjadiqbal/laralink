# Contributing to Laralink

Thank you for considering contributing! Every improvement – whether it is a bug fix, a new feature, documentation, or a test – is welcome.

---

## Development Setup

```bash
# 1. Fork and clone the repository
git clone https://github.com/your-username/laralink.git
cd laralink

# 2. Install dependencies
composer install

# 3. Run the test suite
composer test
```

---

## Workflow

1. **Open an issue first** for anything non-trivial so we can discuss the approach before you invest time writing code.
2. **Fork** the repository and create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Write tests** for every change. Pull requests without tests will not be merged.
4. **Keep commits focused** – one logical change per commit.
5. **Update `CHANGELOG.md`** under the `[Unreleased]` section.
6. Open a **Pull Request** against `main` and fill in the PR template.

---

## Coding Style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/).
- No trailing whitespace, Unix line endings.
- Methods and properties use `camelCase`; classes use `PascalCase`.
- Keep methods short and focused. Extract private helpers freely.

---

## Testing

```bash
# Run the full suite
composer test

# Run a single test file
vendor/bin/phpunit tests/LaralinkTest.php

# Run a specific test method
vendor/bin/phpunit --filter test_add_path_repository_adds_entry
```

Tests live in `tests/`. Unit tests go in `LaralinkTest.php`; command integration tests go in `CommandTest.php`. Use `FakeProcessRunner` from `tests/Support/` to stub `git` and `composer` calls – never invoke real network operations in tests.

---

## Reporting Security Issues

Please see [SECURITY.md](SECURITY.md) before filing a public issue about a vulnerability.

---

## License

By contributing you agree that your code will be released under the [MIT License](LICENSE).

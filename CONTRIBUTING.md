# Contributing to php-upload-helper

Thank you for considering contributing to `phatnt93/php-upload-helper`! We welcome bug fixes, documentation improvements, new features, and translations.

---

## Development Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/phatnt93/php-upload-helper.git
   cd php-upload-helper
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Verify your local environment:**
   - PHP: 8.1 or higher
   - Extensions: `ext-gd`, `ext-fileinfo`, `ext-mbstring`, `ext-zip`

---

## Quality & Testing Workflow

Before submitting a pull request, ensure all checks pass:

```bash
# 1. Validate composer.json strictly
composer validate --strict

# 2. Check and fix code style (PER-CS 2.0 / PSR-12)
composer cs:check
composer cs:fix

# 3. Static analysis (PHPStan level 8)
composer analyse

# 4. Run PHPUnit test suite
composer test

# 5. Check test line coverage (must be >= 90%)
composer test:coverage

# 6. Run standalone examples
for f in examples/*.php; do php "$f"; done
```

---

## Coding Standards & Conventions

- **Strict Types:** Every PHP file must declare `declare(strict_types=1);` as the first statement.
- **Naming Conventions:** PSR-4 under `UploadHelper\`. Interfaces belong in `UploadHelper\Contracts\`.
- **Exception Messages:** All developer exception messages must be in English with appropriate named constructors (`UploadException::forCode(...)`, `UploadException::named(...)`).
- **Security First:**
  - Never trust client-supplied MIME or extension metadata.
  - Every regex using `$` must include `D` flag or `\z` to reject trailing newlines.
  - Keep SSRF guards strict; never follow unvetted HTTP redirects automatically.

---

## Adding a New Translation (Locale)

1. Copy `src/I18n/en.php` to `src/I18n/{locale}.php` (e.g. `fr.php`, `ja.php`).
2. Translate all message strings while preserving exact `{placeholder}` keys.
3. Add the locale code to `tests/Unit/I18n/LocaleParityTest.php` in `localeProvider()`.
4. Run `composer test` to confirm 100% key and placeholder parity.
5. Update `llms-full.txt` and `README.md` to list the newly supported locale.

---

## Commit Message Convention

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:
- `feat:` A new feature or capability.
- `fix:` A bug fix.
- `test:` Adding or updating tests.
- `docs:` Documentation additions or updates.
- `refactor:` Code restructuring without functional changes.
- `style:` Formatting or whitespace changes.
- `chore:` Maintenance, build, or dependency changes.

---

## Reporting Vulnerabilities

Please **do not** open public GitHub issues for security vulnerabilities. Review [SECURITY.md](SECURITY.md) for our private disclosure process.

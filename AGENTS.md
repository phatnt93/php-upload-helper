# AI Agent & Contributor Guide — php-upload-helper

This guide defines core conventions, architecture, and verification commands for contributors and AI coding agents working on `phatnt93/php-upload-helper`.

## Project Overview

- **Package Name:** `phatnt93/php-upload-helper`
- **Target Runtime:** PHP `^8.1` (tested across 8.1, 8.2, 8.3, 8.4)
- **Primary Goal:** Modern, security-first PHP file upload and image processing library with first-class multi-file and nested array normalization, binary magic-byte MIME validation, and atomic batch rollback.
- **Reference Standards:** PER Coding Style 2.0 / PSR-12, PSR-4 autoloading (`src/` and `tests/`).

## Architecture Map

```
src/
├── Contracts/        # Core interfaces (Action, ImageDriver, Scanner, Storage, Validator)
├── Core/             # Normalization, UploadedFile, ProcessedFile, Builders, Pipeline
├── Drivers/          # Image drivers (GdDriver default, Imagick ready)
├── Exceptions/       # Domain exceptions with named constructors and English messages
├── I18n/             # Translator and dictionaries (en default, vi)
├── Remote/           # SSRF-safe UrlDownloader with IP pinning and per-hop checks
├── Results/          # SingleResult, BatchResult
├── Storage/          # LocalStorage, DirectoryHardener, RollbackManager
├── Validation/       # Binary MimeSniffer, ExtensionGuard, SvgSanitizer, Guards
└── Upload.php        # Fluent factory entry point
```

## Core Rules & Conventions

1. **Strict Typing:** Every PHP file in `src/`, `tests/`, and `examples/` MUST start with `declare(strict_types=1);`.
2. **Coding Style:** PER-CS 2.0 enforced by PHP-CS-Fixer (`.php-cs-fixer.dist.php`).
3. **Static Analysis:** PHPStan at level 8 (`phpstan.neon.dist`, `phpVersion: 80100`). Keep code 100% clean of PHPStan errors.
4. **Test Coverage:** Maintain line coverage ≥ 90% (`tests/coverage-check.php` enforces this threshold).
5. **Security-First Rules:**
   - Never trust `$_FILES['field']['type']`; always detect via `MimeSniffer` (magic bytes + `finfo`).
   - Extension validation must check dangerous extensions, double extensions (`file.php.jpg`), and null bytes (`\0`, `%00`).
   - All regexes with end anchor `$` must use `D` flag (`/pattern$/D`) or `\z` to reject trailing `\n`.
   - Prevent SSRF by checking scheme (`http`/`https`), disallowing private/loopback/cloud metadata IPs, avoiding automatic cURL redirect following, and pinning validated IP addresses.
   - Public APIs must gracefully handle wrong input types (arrays, objects, integers, null) without throwing `TypeError`.
6. **Internationalization (i18n):**
   - Default locale is `en`, with fallback to `en`.
   - All developer exception messages must be in English.
   - User-facing error messages are resolved via `Translator` keys.
   - Whenever an error key is added, update both `src/I18n/en.php` and `src/I18n/vi.php` and verify via `LocaleParityTest`.
7. **Examples:**
   - Every script in `examples/` must be self-contained, create its own temporary data, execute with `php examples/xx.php`, and exit with code 0.

## Verification Commands

Run all verification steps locally before committing:

```bash
# 1. Validate composer.json
composer validate --strict

# 2. Check code style (PER-CS 2.0)
composer cs:check
# To auto-fix: composer cs:fix

# 3. Static analysis (PHPStan level 8)
composer analyse

# 4. Unit and integration tests
composer test

# 5. Test coverage gate (>= 90%)
composer test:coverage

# 6. Verify standalone examples
for f in examples/*.php; do php "$f"; done
```

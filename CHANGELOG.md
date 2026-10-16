# Changelog

All notable changes to `phatnt93/php-upload-helper` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-16

### Added
- **First-class multi-file and nested array normalization:**
  - Automatic recursive parsing of complex PHP `$_FILES` matrices (flat `files[]` and nested `items[photos][]`).
  - Fluent batch processing via `Upload::files()` and `Upload::fromGlobals()` with `each()` and `rollbackOnError()`.
- **Factory inputs:**
  - `Upload::file()` for single uploads.
  - `Upload::fromPath()` for local files on disk.
  - `Upload::fromBase64()` for Data URIs and raw base64 payloads.
  - `Upload::fromUrl()` for remote downloads with SSRF protection and IP pinning.
  - `Upload::fromPsr7()` for PSR-7 `UploadedFileInterface` objects and arrays.
- **Security & Validation Layer:**
  - `ExtensionGuard`: Case-insensitive whitelist, hard block on dangerous extensions (`.php`, `.phtml`, `.phar`, `.cgi`, etc.), double-extension detector (`.php.jpg`), and null-byte injection prevention.
  - `MimeSniffer`: Deep binary inspection using magic bytes (JPEG, PNG, GIF, WebP, AVIF, PDF, BMP, ZIP) and `finfo`, ignoring untrusted client headers.
  - `SizeValidator`: Flexible size limits with human units (`'2M'`, `'500K'`, `'1G'`).
  - `DimensionValidator`: Aspect ratio parsing, min/max/exact dimensions.
  - `PixelFloodGuard`: Decompression bomb protection via megapixels and memory estimation.
  - `SvgSanitizer`: Stored-XSS prevention stripping `<script>`, `<iframe>`, `<object>`, inline `on*` handlers, and `javascript:` links.
  - `DocumentSecurityValidator`: Blocking PDF JavaScript, Launch actions, and Office VBA macros.
  - `SsrfGuard`: Scheme whitelisting (`http`/`https`), private/loopback/cloud metadata IP blocking, per-hop redirect verification, and DNS rebinding prevention via IP pinning.
  - `ClamAvScanner`: Pluggable antivirus socket integration.
- **Image Processing Pipeline (GD Driver):**
  - Resizing with multiple modes (`contain`, `cover`, `fill`, `pad`, `crop`).
  - Precise cropping, arbitrary rotation, horizontal/vertical flipping.
  - Text and image watermarks with position, opacity, and offset controls.
  - Format conversions: WebP, JPEG, PNG, AVIF with quality parameters.
  - Color filters: grayscale, brightness, contrast, blur, sharpen, invert, sepia, pixelate.
  - Metadata sanitization: `stripExif()` and EXIF `autoOrient()`.
- **Multi-Variant Generation & Hooks:**
  - `variant()` pipeline for generating thumbnails, previews, and responsive variants from one source file.
  - Lifecycle callbacks: `beforeProcess`, `afterProcess`, `onSuccess`, `onError`.
- **Storage & Naming:**
  - Naming strategies: `slugName`, `uuidName`, `randomName`, `timestampName`, `hashName`, `sha256Name`, `prefix`, `suffix`, and custom closures.
  - Collision handling: `autoIncrement()`, `overwrite()`.
  - Directory hardening: `.htaccess` generation in destination folder.
- **Internationalization (i18n):**
  - Full support for English (`en`, default) and Vietnamese (`vi`).
  - `LocaleParityTest` ensuring 100% key and placeholder parity across dictionaries.
- **Developer Experience & AI Readiness:**
  - Static analysis: PHPStan level 8 clean.
  - Code style: PER-CS 2.0 / PSR-12 enforced by PHP-CS-Fixer.
  - Machine-readable context: `llms.txt` and `llms-full.txt`.
  - Standalone executable examples in `examples/`.
  - CI matrix testing PHP 8.1 through 8.4 with lowest and highest dependencies.

[1.0.0]: https://github.com/phatnt93/php-upload-helper/releases/tag/v1.0.0

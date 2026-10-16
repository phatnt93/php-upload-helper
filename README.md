# PHP Upload Helper

[📖 Bản Tiếng Việt (Vietnamese Documentation)](README.vn.md)

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/phatnt93/php-upload-helper)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Tests Passing](https://img.shields.io/badge/tests-59%20passed%20(185%20assertions)-brightgreen.svg)](tests)

A modern, secure, and fluent PHP file upload and image processing library with **first-class multi-file array support**, inspired by the legendary [verot/class.upload.php](https://github.com/verot/class.upload.php).

---

## Table of Contents

1. [Why Use PHP Upload Helper?](#1-why-use-php-upload-helper)
2. [Key Advantages Over class.upload.php](#2-key-advantages-over-classuploadphp)
3. [System Requirements & Setup](#3-system-requirements-setup)
4. [Installation](#4-installation)
5. [Quick Start](#5-quick-start)
   - [Single File Upload](#single-file-upload)
   - [Multi-File Array Upload (First-Class Support)](#multi-file-array-upload-first-class-support)
   - [Deeply Nested Multi-Dimensional Form Upload](#deeply-nested-multi-dimensional-form-upload)
6. [Real-World Practical Examples](#6-real-world-practical-examples)
   - [Example 1: User Profile Avatar with Automatic Crop & WebP](#example-1-user-profile-avatar-with-automatic-crop-webp)
   - [Example 2: E-Commerce Product Gallery with Atomic Batch Rollback](#example-2-e-commerce-product-gallery-with-atomic-batch-rollback)
   - [Example 3: Secure Document (PDF/Docx) Storage with Slug Naming](#example-3-secure-document-pdfdocx-storage-with-slug-naming)
   - [Example 4: Sanitized SVG Vector Upload (Stored-XSS Immunity)](#example-4-sanitized-svg-vector-upload-stored-xss-immunity)
   - [Example 5: REST API / SPA Upload via Base64 or PSR-7](#example-5-rest-api-spa-upload-via-base64-or-psr-7)
7. [Comprehensive Action Pipeline & API Reference](#7-comprehensive-action-pipeline-api-reference)
   - [Upload Factory API](#upload-factory-api)
   - [Security & Validation Layer](#security-validation-layer)
   - [File Naming Strategies](#file-naming-strategies)
   - [Storage Engine & Collision Handling](#storage-engine-collision-handling)
   - [Image Manipulation (Resize Modes, Crop, Rotate, Watermark)](#image-manipulation-resize-modes-crop-rotate-watermark)
   - [Format Conversion & Compression](#format-conversion-compression)
   - [Color Filters & Visual Effects](#color-filters-visual-effects)
   - [SingleResult & ProcessedFile API](#singleresult-processedfile-api)
   - [BatchUploadBuilder & BatchResult API](#batchuploadbuilder-batchresult-api)
   - [Multi-Variant Pipeline (Thumbnails & Previews)](#multi-variant-pipeline-thumbnails-previews)
   - [Lifecycle Hooks & Event Callbacks](#lifecycle-hooks-event-callbacks)
8. [Framework Integration Guides](#8-framework-integration-guides)
   - [Vanilla PHP Web Form](#vanilla-php-web-form)
   - [Laravel Integration](#laravel-integration)
   - [Symfony Integration](#symfony-integration)
   - [Phalcon Integration](#phalcon-integration)
   - [Slim / PSR-7 Frameworks](#slim-psr-7-frameworks)
9. [Internationalization & Error Codes Catalog](#9-internationalization-error-codes-catalog)
10. [Security Best Practices & Server Hardening](#10-security-best-practices-server-hardening)
11. [Feature Comparison with verot/class.upload.php](#11-feature-comparison-with-verotclassuploadphp)
12. [FAQ & Troubleshooting](#12-faq-troubleshooting)
13. [Automated Testing](#13-automated-testing)
14. [License & Credits](#14-license-credits)

---

## 1. Why Use PHP Upload Helper?

Handling file uploads in native PHP is notorious for friction and security hazards:
- **Chaotic Array Transposition:** Native `$_FILES` splits multiple files into fragmented sub-arrays (`$_FILES['photos']['name'][$i]`), forcing developers to write repetitive matrix restructuring loops.
- **Client Header Trust Vulnerabilities:** Checking `$_FILES['file']['type']` is insecure because it is an untrusted client header trivially spoofed with cURL or Burp Suite.
- **Dangerous Extensions & Code Execution:** Attackers easily bypass naive blacklists with `.phtml`, `.php5`, `.phar`, double extensions (`shell.php.png`), or null-byte injections (`shell.php\0.jpg`).
- **Stored XSS in SVGs:** Uploaded SVGs containing `<script>` or inline `onload` handlers execute scripts in users' browsers when rendered directly.
- **Orphan File Pollution:** In batch uploads, if 9 out of 10 files succeed and the 10th fails, native PHP leaves orphan files cluttering storage.
- **Verbose GD Syntax:** Resizing or watermarking images using procedural GD functions requires dozens of boilerplate lines.

`php-upload-helper` provides an **all-in-one, modern, type-safe Fluent Pipeline** that encapsulates input normalization, deep binary sniffing, SVG sanitization, image processing, atomic storage, and batch rollback into elegant, chainable PHP 8.1+ syntax.

---

## 2. Key Advantages Over `class.upload.php`

| Feature | `verot/class.upload.php` | `phatnt93/php-upload-helper` |
|---|---|---|
| **Multi-File Array Handling** | ❌ None (Requires manual matrix loops) | 🟢 **First-class native support** for flat & nested arrays |
| **PHP Compatibility** | Legacy PHP 4/5/7/8 procedural class | 🟢 **PHP 8.1 - 8.4+** with strict types and PSR-4 |
| **Architecture** | Monolithic (~6,000 lines single file) | 🟢 **Modular Action Pipeline** (SRP, clean SOLID design) |
| **Security Strategy** | Historical Blacklist (bypass risks) | 🟢 **Strict Whitelist & Magic Bytes Sniffing** |
| **SVG Sanitization** | ❌ Vulnerable to Stored XSS | 🟢 **Built-in XML/SVG script sanitizer** |
| **Batch Rollback** | ❌ None (Leaves orphan files on failure) | 🟢 **All-or-nothing rollback** on failure (`rollbackOnError`) |
| **Alternative Inputs** | Limited | 🟢 `$_FILES`, PSR-7, Base64 URI, Remote URL, Local path |
| **Image Drivers** | Hardcoded GD calls | 🟢 **Driver Abstraction** (GD default, Imagick ready) |

---

## 3. System Requirements & Setup

- **PHP Compatibility:** PHP `>= 8.1` (tested on PHP 8.1, 8.2, 8.3, and 8.4 64-bit).
- **Core Extensions:**
  - `ext-fileinfo`: Required for magic bytes binary MIME type inspection.
  - `ext-mbstring`: Required for multibyte string manipulation, Vietnamese slug conversion, and UTF-8 font text rendering.
  - `ext-gd`: Required for the default image manipulation engine.
- **Optional Extensions:**
  - `ext-imagick`: For advanced image manipulation and vector rendering.
  - `ext-exif`: For automatic mobile photo orientation based on EXIF camera tags.

---

## 4. Installation

Install via Composer into your project:

```bash
composer require phatnt93/php-upload-helper
```

Ensure your `php.ini` has adequate upload boundaries:

```ini
file_uploads = On
upload_max_filesize = 50M
post_max_size = 60M
max_file_uploads = 50
memory_limit = 256M
```

---

## 5. Quick Start

### Single File Upload
```php
use UploadHelper\Upload;

// Process a single file from $_FILES['avatar']
$result = Upload::file($_FILES['avatar'])
    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
    ->maxSize('5M')
    ->slugName()
    ->resize(400, 400, mode: 'crop')
    ->toWebp(quality: 85)
    ->saveTo('/var/www/uploads/avatars');

if ($result->isSuccess()) {
    echo "Saved to: " . $result->getFilePath();
    echo "Filename: " . $result->getFilename();
    echo "File size: " . $result->getFormattedSize();
} else {
    echo "Upload failed: " . $result->getErrorMessage();
}
```

---

### Multi-File Array Upload (First-Class Support)
Say goodbye to manual `foreach` matrix restructuring for `<input type="file" name="photos[]" multiple>`!

```php
use UploadHelper\Upload;

// Feed the raw $_FILES array directly:
$batch = Upload::files($_FILES['photos'])
    ->allowedExtensions(['jpg', 'png', 'webp'])
    ->maxSize('10M')
    ->each(function ($builder, $index) {
        $builder->randomName(16)
                ->resize(1200, 800, mode: 'fit')
                ->watermarkImage('/path/to/watermark.png', position: 'bottom-right', opacity: 70)
                ->saveTo('/var/www/uploads/gallery');
    })
    ->rollbackOnError(true) // Automatically deletes saved files if any file in the batch fails
    ->process();

if ($batch->allSuccessful()) {
    echo "All " . count($batch->getSuccessful()) . " files uploaded successfully!";
    foreach ($batch->getSuccessful() as $file) {
        echo "- " . $file->getFilename() . " (" . $file->getFormattedSize() . ")\n";
    }
} else {
    echo "Batch failed with " . count($batch->getFailed()) . " error(s):\n";
    foreach ($batch->getFailed() as $failed) {
        echo "- " . $failed->getOriginalName() . ": " . $failed->getErrorMessage() . "\n";
    }
}
```

---

### Deeply Nested Multi-Dimensional Form Upload
Supports complex form inputs like `<input type="file" name="catalog[category][items][0][attachments][]">`:

```php
use UploadHelper\Upload;

// Automatically flattens and normalizes any nested upload array:
$batch = Upload::files($_FILES['catalog'])
    ->allowedExtensions(['pdf', 'docx', 'jpg', 'png'])
    ->maxSize('20M')
    ->saveTo('/var/www/uploads/catalog')
    ->process();

foreach ($batch->getSuccessful() as $processed) {
    echo "Field: " . $processed->getFieldKey() . "\n";
    echo "Original: " . $processed->getOriginalName() . " -> Saved: " . $processed->getFilename() . "\n";
}
```

---

## 6. Real-World Practical Examples

### Example 1: User Profile Avatar with Automatic Crop & WebP
Enforce a clean square avatar, strip harmful metadata, resize to 300x300, and save in modern WebP:

```php
use UploadHelper\Upload;

$result = Upload::file($_FILES['avatar'])
    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
    ->allowedMimes(['image/jpeg', 'image/png', 'image/webp'])
    ->maxSize('2M')
    ->dimensionBetween(minW: 100, minH: 100, maxW: 4000, maxH: 4000)
    ->autoOrient()                                // Fix orientation for mobile camera selfies
    ->resize(300, 300, mode: 'crop')             // Smart center crop
    ->toWebp(quality: 85)                        // High-efficiency WebP compression
    ->name('user_' . $userId . '_avatar')
    ->overwrite(true)                            // Update existing avatar
    ->saveTo('/var/www/app/public/uploads/avatars');

if ($result->isSuccess()) {
    $avatarUrl = '/uploads/avatars/' . $result->getFilename();
    // Update database with $avatarUrl
}
```

---

### Example 2: E-Commerce Product Gallery with Atomic Batch Rollback
Upload product photos in batch, apply watermarks, convert to progressive JPEG, and rollback if any photo is invalid:

```php
use UploadHelper\Upload;

$batch = Upload::files($_FILES['product_images'])
    ->allowedExtensions(['jpg', 'jpeg', 'png'])
    ->maxSize('8M')
    ->each(function ($builder, $i) use ($productId) {
        $builder->name("product_{$productId}_img_" . ($i + 1))
                ->resize(1200, 1200, mode: 'fit')
                ->watermarkImage(
                    watermarkPath: '/assets/img/watermark-logo.png',
                    position: 'bottom-right',
                    opacity: 75,
                    offsetX: 20,
                    offsetY: 20
                )
                ->toJpeg(quality: 88)
                ->saveTo("/var/www/app/storage/products/{$productId}");
    })
    ->rollbackOnError(true) // Crucial: clean disk if any file exceeds size or is corrupt
    ->process();

if ($batch->allSuccessful()) {
    foreach ($batch->getSuccessful() as $item) {
        $db->insertProductImage($productId, $item->getFilename());
    }
} else {
    // Zero disk pollution: all previously saved files were deleted automatically!
    $errors = $batch->getErrors();
    $_SESSION['flash_error'] = "Upload failed: " . implode(', ', array_column($errors, 'message'));
}
```

---

### Example 3: Secure Document (PDF/Docx) Storage with Slug Naming
Safely accept resumes or contracts, sanitize filenames (including Vietnamese accents), and auto-increment duplicates:

```php
use UploadHelper\Upload;

$result = Upload::file($_FILES['resume'])
    ->allowedExtensions(['pdf', 'docx', 'doc'])
    ->allowedMimes([
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
    ])
    ->maxSize('15M')
    ->slugName() // "Báo cáo tài chính quý 3.pdf" -> "bao-cao-tai-chinh-quy-3.pdf"
    ->autoIncrementDuplicate(true) // If exists: "bao-cao-tai-chinh-quy-3_1.pdf"
    ->saveTo('/var/www/app/storage/documents', chmod: 0750);

if ($result->isSuccess()) {
    echo "Saved safely at: " . $result->getFilePath();
}
```

---

### Example 4: Sanitized SVG Vector Upload (Stored-XSS Immunity)
SVGs can carry malicious `<script>` tags. With `sanitizeSvg(true)`, the parser strips all scripts, event handlers (`onload`, `onclick`), and `javascript:` URIs before saving:

```php
use UploadHelper\Upload;

$result = Upload::file($_FILES['company_logo'])
    ->allowedExtensions(['svg'])
    ->allowedMimes(['image/svg+xml'])
    ->maxSize('1M')
    ->sanitizeSvg(true) // Strips <script>, <foreignObject>, onload, onerror, javascript:
    ->uuidName()        // Generates UUID v4 name
    ->saveTo('/var/www/app/public/logos');

if ($result->isSuccess()) {
    echo "Clean, XSS-free SVG saved: " . $result->getFilename();
}
```

---

### Example 5: REST API / SPA Upload via Base64 or PSR-7
Accept image uploads from mobile apps, Vue/React Canvas, or PSR-7 HTTP requests:

```php
use UploadHelper\Upload;

// 1. Mobile / Web Canvas Base64 Data URI:
$base64String = $requestPayload['avatar_base64']; // "data:image/png;base64,iVBORw0KGgo..."
$result = Upload::fromBase64($base64String, filename: 'canvas_drawing.png')
    ->maxSize('3M')
    ->resize(500, 500, mode: 'fit')
    ->toWebp(quality: 80)
    ->saveTo('/uploads/drawings');

// 2. PSR-7 UploadedFile (Slim, Laminas, Nyholm, Guzzle):
$psr7File = $request->getUploadedFiles()['profile_picture'];
$result = Upload::fromPsr7($psr7File)
    ->allowedExtensions(['jpg', 'png'])
    ->saveTo('/uploads/profiles');

// 3. Download from Remote URL:
$result = Upload::fromUrl('https://images.unsplash.com/photo-1579783900882-c0d3dad7b119')
    ->resize(800, 600)
    ->saveTo('/uploads/unsplash');
```

---

## 7. Comprehensive Action Pipeline & API Reference

### Upload Factory API
Entry points from the `UploadHelper\Upload` facade:

```php
// Standard Web Form Input
Upload::file(mixed $input): UploadBuilder           // Single $_FILES element
Upload::files(mixed $input): BatchUploadBuilder     // Multi-file $_FILES element
Upload::fromGlobals(?array $files = null): BatchUploadBuilder // All $_FILES at once

// Alternative Sources
Upload::fromPath(string $localFilePath, ?string $filename = null): UploadBuilder
Upload::fromBase64(string $base64String, ?string $filename = null): UploadBuilder
Upload::fromUrl(
    string $url,
    ?string $filename = null,
    int $timeout = 15,
    int $maxDownloadBytes = 52_428_800,
    bool $allowPrivateIp = false,
    int $maxRedirects = 3          // every redirect hop is re-checked against SSRF rules
): UploadBuilder
Upload::fromPsr7(object|array $psr7Input): UploadBuilder
```

---

### Security & Validation Layer
All validation methods are available on `UploadBuilder` and `BatchUploadBuilder`:

| Method | Description | Example |
|---|---|---|
| `allowedExtensions(array $exts)` | Strict whitelist of allowed extensions (lowercase). | `->allowedExtensions(['jpg', 'png', 'pdf'])` |
| `allowedMimes(array $mimes)` | Whitelist of MIME types validated via magic bytes. | `->allowedMimes(['image/jpeg', 'image/png'])` |
| `maxSize(int\|string $maxSize)` | Maximum file size in bytes or human string (`K`, `M`, `G`). | `->maxSize('10M')` or `->maxSize(10485760)` |
| `minSize(int\|string $minSize)` | Minimum file size in bytes or human string. | `->minSize('5K')` |
| `dimensionBetween(?int $minW, ?int $minH, ?int $maxW, ?int $maxH)` | Enforce bounding box constraints on images. | `->dimensionBetween(minW: 200, maxW: 1920)` |
| `sanitizeSvg(bool $sanitize = true)` | Enable deep DOM XML sanitization to prevent Stored XSS. | `->sanitizeSvg(true)` |
| `maxPixels(int $maxPixels)` | Prevent Decompression Bomb DoS by limiting max megapixels. | `->maxPixels(40_000_000)` |
| `maxImageMemory(int\|string $maxMemory)` | Limit estimated uncompressed RGBA RAM before GD loading. | `->maxImageMemory('128M')` |
| `blockPdfJavaScript(bool $block = true)` | Block active JavaScript, /Launch, and /EmbeddedFiles in PDF. | `->blockPdfJavaScript(true)` |
| `blockOfficeMacros(bool $block = true)` | Block VBA Macros (vbaProject.bin) in Office documents. | `->blockOfficeMacros(true)` |
| `scanner(ScannerInterface $scanner)` | Real-time Antivirus scan via ClamAV daemon socket. | `->scanner(new ClamAvScanner())` |

---

### File Naming Strategies

| Method | Behavior | Example Output |
|---|---|---|
| `slugName()` | Converts original name to safe ASCII slug (supports Vietnamese). | `Ảnh_đẹp 2026.png` -> `anh-dep-2026.png` |
| `uuidName()` | Generates standard UUID v4. | `550e8400-e29b-41d4-a716-446655440000.png` |
| `randomName(int $len = 16)` | Cryptographically secure hex string. | `a9b3f021e78c45d2.png` |
| `timestampName()` | Date and microsecond timestamp. | `20261015_153045_8912.png` |
| `hashName(string $algo = 'sha256', int $len = 64)` | Content-addressable hash name of file body. | `e3b0c44298fc1c14...png` |
| `sha256Name(int $len = 64)` | Shortcut for SHA-256 hash naming. | `a8b3c4...png` |
| `name(string\|Closure $name)` | Set fixed name or compute dynamic name via Closure. | `->name('cover_image')` |
| `prefix(string $prefix)` | Prepends prefix to filename. | `->prefix('thumb_')` |
| `suffix(string $suffix)` | Appends suffix before extension. | `->suffix('_optimized')` |

---

### Storage Engine & Collision Handling

| Method | Description |
|---|---|
| `saveTo(string $destinationDir, int $chmod = 0755)` | Sets target directory, creates recursively with `$chmod`, and executes save. |
| `overwrite(bool $overwrite = true)` | Replaces destination file if it already exists. |
| `autoIncrementDuplicate(bool $auto = true)` | Automatically appends sequential suffix (`_1`, `_2`) if destination exists. |
| `hardenDestination(bool $harden = true)` | Automatically generates `.htaccess`, `web.config`, and `index.html` to block script execution. |

---

### Image Manipulation (Resize Modes, Crop, Rotate, Watermark)

#### 1. Resize Modes
```php
$builder->resize(int $width, int $height, string $mode = 'fit', string $background = '#FFFFFF');
```
- **`fit` (Default):** Proportional scaling so the whole image fits inside `$width` × `$height`. Never distorts aspect ratio.
- **`crop`:** Scales and centers the image to completely fill the `$width` × `$height` box, cropping excess parts.
- **`pad`:** Fits the image proportionally inside the box and fills the outer space with `$background` (e.g. `'#FFFFFF'`).
- **`stretch`:** Distorts the image to match the exact dimensions.
- **`withoutEnlarging(true)`:** Prevents upscaling if original image is smaller than target dimensions.

#### 2. Cropping, Rotating & Orientation
```php
$builder->crop(int $width, int $height, int $x = 0, int $y = 0); // Freeform rectangle
$builder->rotate(float $angle, string $background = '#000000');   // Custom angle rotation
$builder->flipHorizontal();                                      // Horizontal mirror
$builder->flipVertical();                                        // Vertical mirror
$builder->autoOrient();                                          // Read EXIF camera orientation
$builder->stripExif(keepOrientation: true);                     // Strip GPS and camera metadata for user privacy
```

#### 3. Watermarking
```php
// Image Overlay:
$builder->watermarkImage(
    string $watermarkPath,
    string $position = 'bottom-right', // 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'
    int $opacity = 100,               // 0 to 100%
    int $offsetX = 10,
    int $offsetY = 10
);

// TrueType Text Watermark:
$builder->watermarkText(
    string $text,
    string $fontPath = '',            // Path to .ttf font file
    int $size = 14,
    string $color = '#FFFFFF',        // Hex color
    string $position = 'bottom-right',
    ?string $background = '#000000',  // Background padding block
    int $backgroundOpacity = 50,
    int $offsetX = 10,
    int $offsetY = 10
);
```

---

### Format Conversion & Compression

| Method | Output Format | Key Options |
|---|---|---|
| `toWebp(int $quality = 85)` | WebP | `$quality` (1 - 100). Highly recommended for modern web. |
| `toJpeg(int $quality = 85)` | JPEG | `$quality` (1 - 100). Automatically saves in progressive format. |
| `toPng()` | PNG | Lossless compression with alpha channel preservation. |
| `toAvif(int $quality = 80)` | AVIF | Next-gen image format (requires PHP 8.1+ with AVIF GD support). |

---

### Color Filters & Visual Effects

```php
$builder->grayscale();          // Convert to black and white
$builder->brightness(int $lvl); // Brightness: -127 to +127
$builder->contrast(int $lvl);   // Contrast: -127 to +127
$builder->blur();               // Gaussian blur
$builder->sharpen();            // Unsharp mask filter
```

---

### SingleResult & ProcessedFile API
Calling `->saveTo()` or `->process()` on `UploadBuilder` returns a `SingleResult`:

```php
$result = Upload::file($_FILES['photo'])->saveTo('/uploads');

if ($result->isSuccess()) {
    $file = $result->getFile(); // Instance of ProcessedFile
    
    echo $file->getOriginalName();      // e.g. "My Picture.png"
    echo $file->getFilename();          // e.g. "my-picture.webp"
    echo $file->getSavedPath();         // e.g. "/uploads/my-picture.webp"
    echo $file->getFilePath();          // Alias to getSavedPath()
    echo $file->getMime();              // e.g. "image/webp"
    echo $file->getExtension();          // e.g. "webp"
    echo $file->getSize();              // Size in bytes
    echo $file->getFormattedSize();     // e.g. "1.42 MB"
    echo $file->getWidth();             // e.g. 1200
    echo $file->getHeight();            // e.g. 800
    print_r($file->getDimensions());    // ['width' => 1200, 'height' => 800]
    print_r($file->toArray());          // Export array
} else {
    echo $result->getErrorMessage();    // Translated error message
    echo $result->getErrorCode();       // e.g. "ERR_FILE_TOO_LARGE"
    print_r($result->getErrors());      // ['code' => ..., 'message' => ..., 'context' => ...]

    // Technical detail for logs only (English, may contain paths) - never show it to end users:
    error_log($result->getErrors()['context']['reason'] ?? '');
}
```

---

### BatchUploadBuilder & BatchResult API
Calling `->process()` on `BatchUploadBuilder` returns a `BatchResult`:

```php
$batch = Upload::files($_FILES['photos'])
    ->saveTo('/uploads/gallery')
    ->rollbackOnError(true)
    ->process();

$batch->allSuccessful();    // Returns true if all files succeeded
$batch->hasFailures();      // Returns true if one or more files failed
$batch->count();            // Total processed files count

// Access lists:
$batch->getSuccessful();    // Array of ProcessedFile objects
$batch->getFailed();        // Array of SingleResult error objects
$batch->getErrors();        // Array of error details keyed by original name
```

---

### Multi-Variant Pipeline (Thumbnails & Previews)
Generate multiple derivative sizes, formats, or watermarked variants from a single uploaded file without re-uploading:

```php
use UploadHelper\Upload;

$result = Upload::file($_FILES['avatar'])
    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
    ->slugName()
    ->destination('/var/www/uploads/avatars/original') // Main/Original file destination
    // Variant 1: Square thumbnail for profile lists
    ->variant('thumb', function ($v) {
        $v->resize(150, 150, mode: 'crop')
          ->toWebp(quality: 80)
          ->saveTo('/var/www/uploads/avatars/thumbs');
    })
    // Variant 2: Mid-size preview for profile header
    ->variant('preview', function ($v) {
        $v->resize(600, 400, mode: 'fit')
          ->toWebp(quality: 85)
          ->saveTo('/var/www/uploads/avatars/previews');
    })
    ->process();

if ($result->isSuccess()) {
    echo "Original: " . $result->getFilePath() . "\n";
    
    // Access variants easily:
    $thumb = $result->getVariant('thumb');
    echo "Thumbnail: " . $thumb->getSavedPath() . " (" . $thumb->getWidth() . "x" . $thumb->getHeight() . ")\n";
    
    $preview = $result->getVariant('preview');
    echo "Preview: " . $preview->getSavedPath() . "\n";
    
    // Access all variants:
    foreach ($result->getVariants() as $name => $variantFile) {
        echo "- Variant '{$name}': " . $variantFile->getFilename() . "\n";
    }
}
```

---

### Lifecycle Hooks & Event Callbacks
Attach hooks to intercept and react during the upload lifecycle:

```php
Upload::file($_FILES['contract'])
    ->beforeProcess(function ($uploadedFile) {
        // Runs before validation & processing
        Log::info("Starting upload for: " . $uploadedFile->getOriginalName());
    })
    ->onSuccess(function ($processedFile, $result) {
        // Runs only when upload succeeded
        Database::recordUpload($processedFile->getFilename(), $processedFile->getSize());
        Notification::send("Upload complete!");
    })
    ->onError(function (string $errorMessage, string $errorCode, $result) {
        // Runs when validation or processing failed
        Log::warning("Upload failed [{$errorCode}]: {$errorMessage}");
    })
    ->afterProcess(function ($result) {
        // Always runs after process finishes (success or failure)
        Metrics::increment('uploads.attempted');
    })
    ->saveTo('/uploads/contracts');
```


---

## 8. Framework Integration Guides

### Vanilla PHP Web Form
```html
<!-- index.html -->
<form action="upload.php" method="POST" enctype="multipart/form-data">
    <label>Select Photos:</label>
    <input type="file" name="photos[]" multiple required>
    <button type="submit">Upload Gallery</button>
</form>
```

```php
// upload.php
require_once __DIR__ . '/vendor/autoload.php';

use UploadHelper\Upload;

$batch = Upload::files($_FILES['photos'])
    ->allowedExtensions(['jpg', 'png', 'webp'])
    ->maxSize('5M')
    ->slugName()
    ->resize(1200, 800)
    ->toWebp(85)
    ->saveTo(__DIR__ . '/public/uploads')
    ->rollbackOnError(true)
    ->process();

if ($batch->allSuccessful()) {
    header('Location: /gallery.php?success=1');
} else {
    echo "Errors: " . json_encode($batch->getErrors());
}
```

---

### Laravel Integration
In your Laravel Controller:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use UploadHelper\Upload;

class MediaController extends Controller
{
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|file']);

        // Feed Symfony/Laravel UploadedFile directly via path:
        $file = $request->file('avatar');
        
        $result = Upload::fromPath($file->getRealPath(), $file->getClientOriginalName())
            ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
            ->maxSize('5M')
            ->resize(400, 400, mode: 'crop')
            ->toWebp()
            ->saveTo(public_path('uploads/avatars'));

        if (!$result->isSuccess()) {
            return back()->withErrors(['avatar' => $result->getErrorMessage()]);
        }

        auth()->user()->update(['avatar' => $result->getFilename()]);
        return back()->with('status', 'Avatar updated!');
    }
}
```

---

### Symfony Integration
In your Symfony Controller:

```php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UploadHelper\Upload;

class UploadController extends AbstractController
{
    public function handleUpload(Request $request): Response
    {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('document');

        $result = Upload::fromPath($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalName())
            ->allowedExtensions(['pdf', 'docx'])
            ->maxSize('10M')
            ->slugName()
            ->saveTo($this->getParameter('kernel.project_dir') . '/var/uploads');

        if ($result->isSuccess()) {
            return $this->json(['status' => 'success', 'filename' => $result->getFilename()]);
        }

        return $this->json(['error' => $result->getErrorMessage()], 422);
    }
}
```

---

### Phalcon Integration
In your Phalcon Controller:

```php
namespace App\Controllers;

use Phalcon\Mvc\Controller;
use UploadHelper\Upload;

class FileController extends Controller
{
    public function uploadAction()
    {
        if ($this->request->hasFiles()) {
            $batch = Upload::fromGlobals($_FILES)
                ->allowedExtensions(['jpg', 'png', 'webp'])
                ->maxSize('8M')
                ->saveTo($this->config->application->uploadDir)
                ->process();

            if ($batch->allSuccessful()) {
                $this->flash->success("Uploaded " . count($batch->getSuccessful()) . " files!");
            } else {
                $this->flash->error("Failed to upload some files.");
            }
        }
    }
}
```

---

### Slim / PSR-7 Frameworks
```php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use UploadHelper\Upload;

$app->post('/api/upload', function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();
    $avatar = $uploadedFiles['avatar'] ?? null;

    if (!$avatar) {
        $response->getBody()->write(json_encode(['error' => 'No file uploaded']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $result = Upload::fromPsr7($avatar)
        ->allowedExtensions(['jpg', 'png', 'webp'])
        ->maxSize('2M')
        ->resize(300, 300, mode: 'crop')
        ->toWebp()
        ->saveTo(__DIR__ . '/public/uploads');

    $response->getBody()->write(json_encode([
        'success'  => $result->isSuccess(),
        'filename' => $result->getFilename(),
        'error'    => $result->getErrorMessage(),
    ]));
    
    return $response->withHeader('Content-Type', 'application/json');
});
```

---

## 9. Internationalization & Error Codes Catalog

### Setting the Active Locale
The default locale is English (`en`); Vietnamese (`vi`) ships with the library. Missing keys fall back to the active locale → `en` → the error code itself.

```php
use UploadHelper\I18n\Translator;

// Switch the locale globally (format: "xx", "xxx" or "xx_YY"; anything else throws InvalidLocaleException):
Translator::setLocale('vi');

// Register or override translations for any locale:
Translator::addTranslations('es', [
    'ERR_FILE_TOO_LARGE'        => 'El archivo ({formatted_actual}) supera el máximo permitido ({formatted_max}).',
    'ERR_EXTENSION_NOT_ALLOWED' => 'La extensión .{extension} no está permitida.',
]);
Translator::setLocale('es');

Translator::has('ERR_FILE_TOO_LARGE', 'es'); // true
```

Messages returned by `SingleResult::getErrorMessage()` are translated with the active locale. Exceptions thrown inside the library always carry an English message; it is kept in `getErrors()['context']['reason']` for logging. Unexpected internal errors are reported as `ERR_INTERNAL` with a generic message so that paths and internals never leak to end users.

### Standardized Error Codes

The full message catalog lives in [`src/I18n/en.php`](src/I18n/en.php) and [`src/I18n/vi.php`](src/I18n/vi.php); both always contain the same keys (enforced by tests). Placeholders such as `{extension}` are filled from the error context.

| Group | Error codes |
|---|---|
| PHP upload errors | `ERR_UPLOAD_INI_SIZE`, `ERR_UPLOAD_FORM_SIZE`, `ERR_UPLOAD_PARTIAL`, `ERR_UPLOAD_NO_FILE`, `ERR_UPLOAD_NO_TMP_DIR`, `ERR_UPLOAD_CANT_WRITE`, `ERR_UPLOAD_EXTENSION`, `ERR_UPLOAD_UNKNOWN`, `ERR_NO_FILE` |
| Extension & MIME | `ERR_EXTENSION_NOT_ALLOWED`, `ERR_MIME_NOT_ALLOWED`, `ERR_SECURITY_DANGEROUS_EXTENSION`, `ERR_SECURITY_DOUBLE_EXTENSION`, `ERR_SECURITY_NULL_BYTE`, `ERR_PATH_TRAVERSAL` |
| Size & dimensions | `ERR_FILE_TOO_LARGE`, `ERR_FILE_TOO_SMALL`, `ERR_NOT_AN_IMAGE`, `ERR_DIMENSION_MIN_WIDTH`, `ERR_DIMENSION_MAX_WIDTH`, `ERR_DIMENSION_MIN_HEIGHT`, `ERR_DIMENSION_MAX_HEIGHT`, `ERR_DIMENSION_EXACT_WIDTH`, `ERR_DIMENSION_EXACT_HEIGHT`, `ERR_DIMENSION_RATIO`, `ERR_IMAGE_PIXEL_FLOOD` |
| Content security | `ERR_INVALID_SVG_XML`, `ERR_PDF_JAVASCRIPT_DETECTED`, `ERR_PDF_LAUNCH_DETECTED`, `ERR_PDF_EMBEDDED_FILES_DETECTED`, `ERR_OFFICE_MACRO_DETECTED`, `ERR_VIRUS_DETECTED`, `ERR_SCANNER_ERROR` |
| Remote URL (SSRF) | `ERR_SSRF_INVALID_URL`, `ERR_SSRF_DISALLOWED_SCHEME`, `ERR_SSRF_DISALLOWED_PORT`, `ERR_SSRF_DNS_RESOLVE_FAILED`, `ERR_SSRF_BLOCKED`, `ERR_URL_DOWNLOAD_FAILED`, `ERR_URL_DOWNLOAD_TOO_LARGE`, `ERR_URL_TOO_MANY_REDIRECTS` |
| Storage & processing | `ERR_FILE_EXISTS`, `ERR_VARIANT_FAILED`, `ERR_PROCESS_FAILED`, `ERR_VALIDATION`, `ERR_INTERNAL` |

---

## 10. Security Best Practices & Server Hardening

1. **Store Uploads Outside the Web Root:**
   Always store private files outside the public document root (e.g., `/storage/uploads` instead of `/public/uploads`) and serve them via a secure streaming controller with access authorization.

2. **Disable Script Execution in Upload Folders:**
   If serving directly from Nginx, disable PHP execution in the public upload directory:
   ```nginx
   # Nginx server block
   location ^~ /uploads/ {
       location ~ \.(php|phtml|phar|sh|pl|py|cgi)$ {
           deny all;
           return 403;
       }
   }
   ```

   For Apache, place an `.htaccess` file inside your upload directory:
   ```apache
   # Prevent executing any scripts in the uploads folder
   php_flag engine off
   <FilesMatch "\.(php|phtml|phar|sh|pl|cgi)$">
       Order Deny,Allow
       Deny from all
   </FilesMatch>
   ```

3. **Always Whitelist Extensions:**
   Never use blacklists. Attackers continuously discover new executable suffixes (e.g., `.php7`, `.pht`, `.shtml`, `.inc`). Always pass explicit whitelists via `->allowedExtensions(['jpg', 'png', 'pdf'])`.

---

## 11. Feature Comparison with `verot/class.upload.php`

| `verot/class.upload.php` Property | `UploadHelper` Fluent Action |
|---|---|
| `$foo->file_safe_name = true;` | `->slugName()` |
| `$foo->file_new_name_body = 'id';` | `->name('id')` |
| `$foo->file_name_body_pre = 'pre_';` | `->prefix('pre_')` |
| `$foo->file_name_body_add = '_post';` | `->suffix('_post')` |
| `$foo->file_auto_rename = true;` | `->autoIncrementDuplicate()` |
| `$foo->file_overwrite = true;` | `->overwrite(true)` |
| `$foo->file_max_size = 5242880;` | `->maxSize('5M')` |
| `$foo->allowed = ['image/*'];` | `->allowedMimes(['image/jpeg', 'image/png'])` |
| `$foo->image_resize = true;` | `->resize($w, $h, mode: 'fit')` |
| `$foo->image_ratio_crop = true;` | `->resize($w, $h, mode: 'crop')` |
| `$foo->image_ratio_fill = true;` | `->resize($w, $h, mode: 'pad')` |
| `$foo->image_ratio_no_zoom_in = true;` | `->withoutEnlarging()` |
| `$foo->image_watermark = 'wm.png';` | `->watermarkImage('/path/wm.png')` |
| `$foo->image_text = 'Copyright';` | `->watermarkText('Copyright')` |
| `$foo->image_convert = 'webp';` | `->toWebp()` |
| `$foo->image_greyscale = true;` | `->grayscale()` |
| `$foo->image_brightness = 20;` | `->brightness(20)` |
| `$foo->image_contrast = -10;` | `->contrast(-10)` |
| `$foo->dir_auto_create = true;` | Handled automatically in `->saveTo()` |

---

## 12. FAQ & Troubleshooting

### Q: Why do I get an empty `$_FILES` or immediate `UPLOAD_ERR_INI_SIZE`?
**A:** The file exceeds either `upload_max_filesize` or `post_max_size` in your active `php.ini`. Note that `post_max_size` must always be larger than or equal to `upload_max_filesize`. For multi-file uploads, `post_max_size` must be large enough to contain the sum of all files in the batch request.

### Q: Why does `toWebp()` or `toAvif()` fail on my server?
**A:** Check if your PHP GD build was compiled with WebP or AVIF support:
```bash
php -r "var_dump(function_exists('imagewebp'));"
php -r "var_dump(function_exists('imageavif'));"
```
If `false`, install `libwebp-dev` or `libavif-dev` and recompile/install the PHP GD extension.

### Q: How do I create both a large preview and a small thumbnail from the same uploaded file?
**A:** Call `saveTo()` sequentially or execute actions in pipeline:
```php
$uploader = Upload::file($_FILES['photo'])
    ->allowedExtensions(['jpg', 'png', 'webp']);

// 1. Save web preview (800x600):
$previewResult = $uploader
    ->prefix('preview_')
    ->resize(800, 600, mode: 'fit')
    ->saveTo('/uploads/gallery');

// 2. Save thumbnail (150x150 crop):
$thumbResult = $uploader
    ->prefix('thumb_')
    ->resize(150, 150, mode: 'crop')
    ->saveTo('/uploads/gallery');
```

---

## 13. Automated Testing

Run the automated test suite via PHPUnit:

```bash
# Run all unit and integration tests
php codes/lib/vendor/bin/phpunit -c codes/lib/phpunit.xml.dist

# Run with testdox format
php codes/lib/vendor/bin/phpunit -c codes/lib/phpunit.xml.dist --testdox
```

---

## 14. License & Credits

- **License:** Licensed under the [MIT License](LICENSE).
- **Author:** [Phat Nguyen](https://github.com/phatnt93) (`phatnt93@gmail.com`).
- **Inspiration:** Special thanks to Colin Verot for the historical [verot/class.upload.php](https://github.com/verot/class.upload.php).

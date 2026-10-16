# Thư Viện PHP Upload Helper

[📖 English Documentation](README.md)

[![Phiên bản mới nhất](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/phatnt93/php-upload-helper)
[![Phiên bản PHP](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)
[![Giấy phép: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Kiểm thử vượt qua](https://img.shields.io/badge/tests-59%20passed%20(185%20assertions)-brightgreen.svg)](tests)

Thư viện PHP xử lý tải lên tệp tin và thao tác hình ảnh hiện đại, an toàn và mượt mà với **hỗ trợ First-Class cho mảng upload nhiều tệp tin (Multi-File Arrays)**, lấy cảm hứng từ thư viện kinh điển [verot/class.upload.php](https://github.com/verot/class.upload.php).

---

## Mục Lục

1. [Tại sao nên chọn PHP Upload Helper?](#1-tại-sao-nên-chọn-php-upload-helper)
2. [Ưu Điểm Vượt Trội So Với class.upload.php](#2-ưu-điểm-vượt-trội-so-với-classuploadphp)
3. [Yêu Cầu Hệ Thống & Cài Đặt](#3-yêu-cầu-hệ-thống-cài-đặt)
4. [Cài Đặt Qua Composer](#4-cài-đặt-qua-composer)
5. [Hướng Dẫn Nhanh (Quick Start)](#5-hướng-dẫn-nhanh-quick-start)
   - [Tải lên 1 tệp đơn giản](#tải-lên-1-tệp-đơn-giản)
   - [Tải lên mảng nhiều tệp (First-Class Multi-File)](#tải-lên-mảng-nhiều-tệp-first-class-multi-file)
   - [Tải lên mảng lồng nhau nhiều chiều phức tạp](#tải-lên-mảng-lồng-nhau-nhiều-chiều-phức-tạp)
6. [Ví Dụ Thực Tế Từ Cơ Bản Đến Nâng Cao](#6-ví-dụ-thực-tế-từ-cơ-bản-đến-nâng-cao)
   - [Ví dụ 1: Upload Avatar người dùng (Crop thông minh & WebP)](#ví-dụ-1-upload-avatar-người-dùng-crop-thông-minh-webp)
   - [Ví dụ 2: Thư viện ảnh sản phẩm thương mại điện tử với Rollback an toàn](#ví-dụ-2-thư-viện-ảnh-sản-phẩm-thương-mại-điện-tử-với-rollback-an-toàn)
   - [Ví dụ 3: Lưu trữ tài liệu an toàn (PDF/Word) với tên file Slug tiếng Việt](#ví-dụ-3-lưu-trữ-tài-liệu-an-toàn-pdfword-với-tên-file-slug-tiếng-việt)
   - [Ví dụ 4: Upload file vector SVG (Khử mã độc Stored-XSS)](#ví-dụ-4-upload-file-vector-svg-khử-mã-độc-stored-xss)
   - [Ví dụ 5: Tích hợp REST API / SPA Upload qua Base64 hoặc PSR-7](#ví-dụ-5-tích-hợp-rest-api-spa-upload-qua-base64-hoặc-psr-7)
7. [Pipeline Hành Động Toàn Diện & Tra Cứu API](#7-pipeline-hành-động-toàn-diện-tra-cứu-api)
   - [Upload Factory API](#upload-factory-api)
   - [Lớp Bảo Mật & Validation](#lớp-bảo-mật-validation)
   - [Chiến Lược Đổi Tên Tệp](#chiến-lược-đổi-tên-tệp)
   - [Lưu Trữ & Kiểm Soát Trùng Lặp](#lưu-trữ-kiểm-soát-trùng-lặp)
   - [Xử Lý Hình Ảnh (Resize, Crop, Rotate, Watermark)](#xử-lý-hình-ảnh-resize-crop-rotate-watermark)
   - [Chuyển Đổi Định Dạng & Nén Ảnh](#chuyển-đổi-định-dạng-nén-ảnh)
   - [Bộ Lọc Màu & Hiệu Ứng Thị Giác](#bộ-lọc-màu-hiệu-ứng-thị-giác)
   - [Tra Cứu SingleResult & ProcessedFile](#tra-cứu-singleresult-processedfile)
   - [Tra Cứu BatchUploadBuilder & BatchResult](#tra-cứu-batchuploadbuilder-batchresult)
   - [Pipeline Đa Biến Thể (Multi-Variant Thumbnails)](#pipeline-đa-biến-thể-multi-variant-thumbnails)
   - [Vòng Đời Sự Kiện & Lifecycle Hooks](#vòng-đời-sự-kiện-lifecycle-hooks)
8. [Hướng Dẫn Tích Hợp Framework](#8-hướng-dẫn-tích-hợp-framework)
   - [PHP thuần (Vanilla PHP Web Form)](#php-thuần-vanilla-php-web-form)
   - [Tích hợp Laravel Framework](#tích-hợp-laravel-framework)
   - [Tích hợp Symfony Framework](#tích-hợp-symfony-framework)
   - [Tích hợp Phalcon Framework](#tích-hợp-phalcon-framework)
   - [Tích hợp Slim / PSR-7 Frameworks](#tích-hợp-slim-psr-7-frameworks)
9. [Đa Ngôn Ngữ (I18n) & Bảng Mã Lỗi Chuẩn Hóa](#9-đa-ngôn-ngữ-i18n-bảng-mã-lỗi-chuẩn-hóa)
10. [Tiêu Chuẩn Bảo Mật Máy Chủ & Phòng Chống Mã Độc](#10-tiêu-chuẩn-bảo-mật-máy-chủ-phòng-chống-mã-độc)
11. [Bảng Đối Chiếu Tính Năng Với verot/class.upload.php](#11-bảng-đối-chiếu-tính-năng-với-verotclassuploadphp)
12. [Các Câu Hỏi Thường Gặp & Xử Lý Sự Cố (FAQ)](#12-các-câu-hỏi-thường-gặp-xử-lý-sự-cố-faq)
13. [Kiểm Thử Tự Động](#13-kiểm-thử-tự-động)
14. [Giấy Phép & Tác Giả](#14-giấy-phép-tác-giả)
---

## 1. Tại sao nên chọn PHP Upload Helper?

Xử lý tải lên tệp tin trong PHP thuần luôn là bài toán phức tạp và tiềm ẩn nhiều nguy cơ bảo mật nghiêm trọng:
- **Ma trận `$_FILES` bị phân mảnh:** Khi gửi `<input type="file" name="photos[]" multiple>`, PHP phân chia dữ liệu thành các mảng ngang rời rạc (`$_FILES['photos']['name'][$i]`), buộc lập trình viên phải tự viết các vòng lặp đảo ma trận phức tạp.
- **Nguy cơ tin cậy Header Client:** Thuộc tính `$_FILES['file']['type']` do trình duyệt gửi lên hoàn toàn có thể bị giả mạo dễ dàng bằng cURL hoặc Burp Suite.
- **Rủi ro thực thi mã độc Web Shell:** Kẻ tấn công dễ dàng vượt qua các bộ lọc blacklist thô sơ bằng đuôi mở rộng `.phtml`, `.php5`, `.phar`, double extension (`shell.php.png`), hoặc null-byte injection (`shell.php\0.jpg`).
- **Lỗ hổng Stored XSS trong SVG:** File ảnh vector SVG bản chất là XML. Nếu chứa mã JavaScript trong thẻ `<script>` hoặc sự kiện `onload`, nó sẽ thực thi mã độc trực tiếp trên trình duyệt của người dùng khi được mở.
- **Rác ổ cứng khi upload hàng loạt:** Trong mảng 10 file upload, nếu 9 file đầu lưu thành công nhưng file thứ 10 bị lỗi, PHP thuần không tự thu hồi, khiến server tích tụ các file mồ côi (orphan files).
- **Cú pháp GD cồng kềnh:** Để cắt cúp, đổi kích thước hoặc đóng dấu mờ (watermark) một bức ảnh bằng GD nguyên bản, bạn phải viết hàng chục dòng code thủ tục dài dòng và dễ rò rỉ bộ nhớ.

`php-upload-helper` mang đến **một chuỗi Pipeline mượt mà (Fluent Pipeline)**: từ chuẩn hóa cấu trúc mảng, xác thực nhị phân magic bytes, làm sạch mã độc SVG, đến cắt cúp ảnh, nén WebP và cơ chế hoàn tác tự động (atomic rollback).

---

## 2. Ưu Điểm Vượt Trội So Với `class.upload.php`

| Tính năng | `verot/class.upload.php` | `phatnt93/php-upload-helper` |
|---|---|---|
| **Xử lý mảng Upload nhiều file** | ❌ Không có (Phải tự viết vòng lặp đảo ma trận) | 🟢 **Hỗ trợ First-Class native** cho mọi dạng mảng phẳng & lồng nhau |
| **Tương thích PHP** | Class nguyên khối thời PHP 4/5/7/8 | 🟢 **PHP 8.1 - 8.4+** strict types, PER CS / PSR-12 |
| **Kiến trúc mã nguồn** | Monolithic (~6,000 dòng trong 1 file duy nhất) | 🟢 **Action Pipeline hướng đối tượng**, phân tách SRP, SOLID chuẩn mực |
| **Chiến lược bảo mật** | Blacklist lịch sử (dễ bị bypass CVE) | 🟢 **Strict Whitelist & Quét Magic Bytes nhị phân** |
| **Làm sạch file SVG** | ❌ Dễ dính lỗ hổng Stored XSS | 🟢 **Tích hợp bộ lọc DOM XML khử script tự động** |
| **Hoàn tác mẻ upload (Rollback)** | ❌ Không có (Để lại file rác khi có file lỗi) | 🟢 **Tự động xóa sạch file rác** khi batch gặp lỗi (`rollbackOnError`) |
| **Nguồn dữ liệu đầu vào** | Hạn chế | 🟢 `$_FILES`, PSR-7, Base64 URI, Remote URL, File nội bộ |
| **Trừu tượng hóa đồ họa** | Gọi trực tiếp hàm GD | 🟢 **Driver Abstraction** (GD mặc định, sẵn sàng Imagick) |

---

## 3. Yêu Cầu Hệ Thống & Cài Đặt

- **Phiên bản PHP:** PHP `>= 8.1` (Đã kiểm thử thực tế trên PHP 8.1, 8.2, 8.3 và 8.4 64-bit).
- **Phần mở rộng cốt lõi (Core Extensions):**
  - `ext-fileinfo`: Bắt buộc để nhận diện định dạng tệp qua Magic Bytes nhị phân thực tế.
  - `ext-mbstring`: Bắt buộc cho việc xử lý chuỗi UTF-8, chuyển đổi slug tiếng Việt và vẽ chữ watermark.
  - `ext-gd`: Bắt buộc cho việc xử lý và biến đổi hình ảnh mặc định.
- **Phần mở rộng tùy chọn (Optional Extensions):**
  - `ext-imagick`: Cho hiệu năng xử lý đồ họa nâng cao.
  - `ext-exif`: Bắt buộc nếu cần tự động xoay ảnh chụp từ điện thoại dựa trên thẻ xoay máy ảnh EXIF.

---

## 4. Cài Đặt Qua Composer

Cài đặt gói thư viện thông qua Composer:

```bash
composer require phatnt93/php-upload-helper
```

Đảm bảo cấu hình `php.ini` có các giới hạn dung lượng phù hợp cho ứng dụng của bạn:

```ini
file_uploads = On
upload_max_filesize = 50M
post_max_size = 60M
max_file_uploads = 50
memory_limit = 256M
```

---

## 5. Hướng Dẫn Nhanh (Quick Start)

### Tải lên 1 tệp đơn giản
```php
use UploadHelper\Upload;

// Xử lý tệp đơn lẻ từ $_FILES['avatar']
$result = Upload::file($_FILES['avatar'])
    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
    ->maxSize('5M')
    ->slugName()
    ->resize(400, 400, mode: 'crop')
    ->toWebp(quality: 85)
    ->saveTo('/var/www/uploads/avatars');

if ($result->isSuccess()) {
    echo "Đã lưu tại: " . $result->getFilePath();
    echo "Tên tệp: " . $result->getFilename();
    echo "Dung lượng: " . $result->getFormattedSize();
} else {
    echo "Tải lên thất bại: " . $result->getErrorMessage();
}
```

---

### Tải lên mảng nhiều tệp (First-Class Multi-File)
Không cần viết vòng lặp `foreach` đảo ma trận thủ công cho `<input type="file" name="photos[]" multiple>`!

```php
use UploadHelper\Upload;

// Truyền trực tiếp mảng $_FILES thô:
$batch = Upload::files($_FILES['photos'])
    ->allowedExtensions(['jpg', 'png', 'webp'])
    ->maxSize('10M')
    ->each(function ($builder, $index) {
        $builder->randomName(16)
                ->resize(1200, 800, mode: 'fit')
                ->watermarkImage('/path/to/watermark.png', position: 'bottom-right', opacity: 70)
                ->saveTo('/var/www/uploads/gallery');
    })
    ->rollbackOnError(true) // Tự động xóa sạch các file đã lưu nếu có bất kỳ file nào trong mẻ bị lỗi
    ->process();

if ($batch->allSuccessful()) {
    echo "Tải lên thành công toàn bộ " . count($batch->getSuccessful()) . " tệp!";
    foreach ($batch->getSuccessful() as $file) {
        echo "- " . $file->getFilename() . " (" . $file->getFormattedSize() . ")\n";
    }
} else {
    echo "Mẻ tải lên có " . count($batch->getFailed()) . " tệp thất bại:\n";
    foreach ($batch->getFailed() as $failed) {
        echo "- " . $failed->getOriginalName() . ": " . $failed->getErrorMessage() . "\n";
    }
}
```

---

### Tải lên mảng lồng nhau nhiều chiều phức tạp
Hỗ trợ đầy đủ các biểu mẫu form lồng nhau như `<input type="file" name="catalog[category][items][0][attachments][]">`:

```php
use UploadHelper\Upload;

// Tự động giải mã và chuẩn hóa đệ quy mọi cấp độ mảng:
$batch = Upload::files($_FILES['catalog'])
    ->allowedExtensions(['pdf', 'docx', 'jpg', 'png'])
    ->maxSize('20M')
    ->saveTo('/var/www/uploads/catalog')
    ->process();

foreach ($batch->getSuccessful() as $processed) {
    echo "Trường: " . $processed->getFieldKey() . "\n";
    echo "Tệp gốc: " . $processed->getOriginalName() . " -> Đã lưu: " . $processed->getFilename() . "\n";
}
```

---

## 6. Ví Dụ Thực Tế Từ Cơ Bản Đến Nâng Cao

### Ví dụ 1: Upload Avatar người dùng (Crop thông minh & WebP)
Ép buộc ảnh đại diện vuông vức, xoay theo góc chụp điện thoại, cắt từ tâm 300x300, chuyển đổi sang định dạng WebP hiện đại và ghi đè ảnh cũ:

```php
use UploadHelper\Upload;

$result = Upload::file($_FILES['avatar'])
    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
    ->allowedMimes(['image/jpeg', 'image/png', 'image/webp'])
    ->maxSize('2M')
    ->dimensionBetween(minW: 100, minH: 100, maxW: 4000, maxH: 4000)
    ->autoOrient()                                // Tự động xoay ảnh chụp từ điện thoại
    ->resize(300, 300, mode: 'crop')             // Cắt vuông từ tâm
    ->toWebp(quality: 85)                        // Nén WebP chất lượng cao
    ->name('user_' . $userId . '_avatar')
    ->overwrite(true)                            // Cho phép ghi đè avatar cũ
    ->saveTo('/var/www/app/public/uploads/avatars');

if ($result->isSuccess()) {
    $avatarUrl = '/uploads/avatars/' . $result->getFilename();
    // Cập nhật đường dẫn avatar vào cơ sở dữ liệu
}
```

---

### Ví dụ 2: Thư viện ảnh sản phẩm thương mại điện tử với Rollback an toàn
Xử lý đồng thời nhiều ảnh sản phẩm, đóng dấu logo mờ, xuất ảnh JPEG progressive và thu hồi toàn bộ nếu có ảnh lỗi:

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
    ->rollbackOnError(true) // Cực kỳ quan trọng: dọn sạch đĩa nếu có file hỏng hoặc vượt dung lượng
    ->process();

if ($batch->allSuccessful()) {
    foreach ($batch->getSuccessful() as $item) {
        $db->insertProductImage($productId, $item->getFilename());
    }
} else {
    // Không để lại rác trên ổ cứng: các file đã lưu trước đó đều đã được xóa tự động!
    $errors = $batch->getErrors();
    $_SESSION['flash_error'] = "Tải ảnh thất bại: " . implode(', ', array_column($errors, 'message'));
}
```

---

### Ví dụ 3: Lưu trữ tài liệu an toàn (PDF/Word) với tên file Slug tiếng Việt
Tiếp nhận hồ sơ tuyển dụng hoặc hợp đồng, làm sạch tên tiếng Việt có dấu, tự động thêm số thứ tự nếu trùng lặp:

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
    ->autoIncrementDuplicate(true) // Nếu trùng: "bao-cao-tai-chinh-quy-3_1.pdf"
    ->saveTo('/var/www/app/storage/documents', chmod: 0750);

if ($result->isSuccess()) {
    echo "Đã lưu tài liệu an toàn tại: " . $result->getFilePath();
}
```

---

### Ví dụ 4: Upload file vector SVG (Khử mã độc Stored-XSS)
File SVG rất dễ bị chèn thẻ script độc hại. Tùy chọn `sanitizeSvg(true)` sẽ phân tích cây XML DOM và loại bỏ triệt để mọi đoạn mã kịch bản:

```php
use UploadHelper\Upload;

$result = Upload::file($_FILES['company_logo'])
    ->allowedExtensions(['svg'])
    ->allowedMimes(['image/svg+xml'])
    ->maxSize('1M')
    ->sanitizeSvg(true) // Bóc tách sạch sẽ <script>, <foreignObject>, onload, onerror, javascript:
    ->uuidName()        // Sinh tên định danh UUID v4
    ->saveTo('/var/www/app/public/logos');

if ($result->isSuccess()) {
    echo "File SVG sạch mã độc đã lưu: " . $result->getFilename();
}
```

---

### Ví dụ 5: Tích hợp REST API / SPA Upload qua Base64 hoặc PSR-7
Xử lý dữ liệu tải lên từ ứng dụng di động, React/Vue Canvas, hoặc PSR-7 request:

```php
use UploadHelper\Upload;

// 1. Chuỗi Base64 Data URI từ Mobile / Web Canvas:
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

// 3. Tải từ xa qua HTTP Stream an toàn:
$result = Upload::fromUrl('https://images.unsplash.com/photo-1579783900882-c0d3dad7b119')
    ->resize(800, 600)
    ->saveTo('/uploads/unsplash');
```

---

## 7. Pipeline Hành Động Toàn Diện & Tra Cứu API

### Upload Factory API
Các điểm khởi tạo tĩnh từ Facade `UploadHelper\Upload`:

```php
// Dữ liệu từ HTML Form
Upload::file(mixed $input): UploadBuilder           // Xử lý 1 phần tử $_FILES
Upload::files(mixed $input): BatchUploadBuilder     // Xử lý mảng nhiều phần tử $_FILES
Upload::fromGlobals(?array $files = null): BatchUploadBuilder // Đọc toàn bộ siêu biến $_FILES

// Các nguồn dữ liệu thay thế
Upload::fromPath(string $localFilePath, ?string $filename = null): UploadBuilder
Upload::fromBase64(string $base64String, ?string $filename = null): UploadBuilder
Upload::fromUrl(
    string $url,
    ?string $filename = null,
    int $timeout = 15,
    int $maxDownloadBytes = 52_428_800,
    bool $allowPrivateIp = false,
    int $maxRedirects = 3          // mỗi lần chuyển hướng đều được kiểm tra lại chống SSRF
): UploadBuilder
Upload::fromPsr7(object|array $psr7Input): UploadBuilder
```

---

### Lớp Bảo Mật & Validation
Các phương thức xác thực khả dụng trên cả `UploadBuilder` và `BatchUploadBuilder`:

| Phương thức | Chức năng | Ví dụ |
|---|---|---|
| `allowedExtensions(array $exts)` | Whitelist nghiêm ngặt các phần mở rộng cho phép. | `->allowedExtensions(['jpg', 'png', 'pdf'])` |
| `allowedMimes(array $mimes)` | Whitelist định dạng MIME xác thực qua magic bytes. | `->allowedMimes(['image/jpeg', 'image/png'])` |
| `maxSize(int\|string $maxSize)` | Dung lượng tối đa (hỗ trợ số bytes hoặc chuỗi `K`, `M`, `G`). | `->maxSize('10M')` hoặc `->maxSize(10485760)` |
| `minSize(int\|string $minSize)` | Dung lượng tối thiểu cho phép tải lên. | `->minSize('5K')` |
| `dimensionBetween(?int $minW, ?int $minH, ?int $maxW, ?int $maxH)` | Kiểm tra kích thước khung hình ảnh tối thiểu / tối đa. | `->dimensionBetween(minW: 200, maxW: 1920)` |
| `sanitizeSvg(bool $sanitize = true)` | Kích hoạt bộ lọc DOM XML khử mã độc Stored XSS trong SVG. | `->sanitizeSvg(true)` |
| `maxPixels(int $maxPixels)` | Chống Decompression Bomb DoS bằng cách giới hạn Megapixels. | `->maxPixels(40_000_000)` |
| `maxImageMemory(int\|string $maxMemory)` | Ước tính lượng RAM giải nén RGBA trước khi nạp vào GD. | `->maxImageMemory('128M')` |
| `blockPdfJavaScript(bool $block = true)` | Chặn mã JavaScript nhúng, /Launch và /EmbeddedFiles trong PDF. | `->blockPdfJavaScript(true)` |
| `blockOfficeMacros(bool $block = true)` | Chặn mã Macro VBA (vbaProject.bin) trong tài liệu Office. | `->blockOfficeMacros(true)` |
| `scanner(ScannerInterface $scanner)` | Quét virus thời gian thực qua socket daemon ClamAV. | `->scanner(new ClamAvScanner())` |

---

### Chiến Lược Đổi Tên Tệp

| Phương thức | Cơ chế hoạt động | Kết quả mẫu |
|---|---|---|
| `slugName()` | Khử dấu tiếng Việt, ký tự đặc biệt thành ASCII slug. | `Ảnh đại diện 2026.png` -> `anh-dai-dien-2026.png` |
| `uuidName()` | Sinh định danh ngẫu nhiên chuẩn UUID v4. | `550e8400-e29b-41d4-a716-446655440000.png` |
| `randomName(int $len = 16)` | Chuỗi ký tự ngẫu nhiên mã hóa an toàn. | `a9b3f021e78c45d2.png` |
| `timestampName()` | Đặt tên theo thời gian chi tiết đến microsecond. | `20261015_153045_8912.png` |
| `hashName(string $algo = 'sha256', int $len = 64)` | Đặt tên bằng chuỗi băm mật mã theo nội dung tệp. | `e3b0c44298fc1c14...png` |
| `sha256Name(int $len = 64)` | Đặt tên theo mã băm SHA-256. | `a8b3c4...png` |
| `name(string\|Closure $name)` | Đặt tên cố định hoặc tính toán động qua Closure. | `->name('cover_image')` |
| `prefix(string $prefix)` | Thêm tiền tố vào đầu tên tệp. | `->prefix('thumb_')` |
| `suffix(string $suffix)` | Thêm hậu tố vào trước phần mở rộng. | `->suffix('_optimized')` |

---

### Lưu Trữ & Kiểm Soát Trùng Lặp

| Phương thức | Chức năng |
|---|---|
| `saveTo(string $destinationDir, int $chmod = 0755)` | Chỉ định thư mục lưu trữ, tự động tạo đệ quy với `$chmod`, và thực thi ghi đĩa. |
| `overwrite(bool $overwrite = true)` | Cho phép ghi đè tệp tin nếu đã tồn tại ở thư mục đích. |
| `autoIncrementDuplicate(bool $auto = true)` | Tự động thêm hậu tố tăng dần (`_1`, `_2`) nếu tên tệp đích đã tồn tại. |
| `hardenDestination(bool $harden = true)` | Tự động sinh `.htaccess`, `web.config` và `index.html` khóa thực thi script. |

---

### Xử Lý Hình Ảnh (Resize, Crop, Rotate, Watermark)

#### 1. Các chế độ thay đổi kích thước (Resize Modes)
```php
$builder->resize(int $width, int $height, string $mode = 'fit', string $background = '#FFFFFF');
```
- **`fit` (Mặc định):** Thu phóng tỷ lệ sao cho bức ảnh nằm vừa trọn bên trong khung `$width` × `$height`. Không bao giờ làm méo tỷ lệ ảnh.
- **`crop`:** Thu phóng và cắt từ tâm ảnh để phủ kín hoàn toàn khung `$width` × `$height`, loại bỏ phần rìa thừa.
- **`pad`:** Thu nhỏ vừa vặn bên trong khung và lấp đầy các khoảng viền trống thừa bằng màu nền `$background` (ví dụ `'#FFFFFF'`).
- **`stretch`:** Kéo giãn hình ảnh ép buộc đúng chính xác kích thước.
- **`withoutEnlarging(true)`:** Không phóng to nếu kích thước ảnh gốc nhỏ hơn kích thước đích cần resize.

#### 2. Cắt cúp, Xoay & Hướng chụp
```php
$builder->crop(int $width, int $height, int $x = 0, int $y = 0); // Cắt cúp tự do
$builder->rotate(float $angle, string $background = '#000000');   // Xoay ảnh theo góc độ
$builder->flipHorizontal();                                      // Lật ảnh theo gương ngang
$builder->flipVertical();                                        // Lật ảnh theo gương dọc
$builder->autoOrient();                                          // Tự động xoay theo EXIF điện thoại
$builder->stripExif(keepOrientation: true);                     // Khử GPS và camera metadata bảo vệ quyền riêng tư
```

#### 3. Đóng dấu bản quyền (Watermark)
```php
// Đóng dấu bằng ảnh logo mờ:
$builder->watermarkImage(
    string $watermarkPath,
    string $position = 'bottom-right', // 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'
    int $opacity = 100,               // 0 đến 100%
    int $offsetX = 10,
    int $offsetY = 10
);

// Đóng dấu bằng chữ TrueType:
$builder->watermarkText(
    string $text,
    string $fontPath = '',            // Đường dẫn đến file font .ttf
    int $size = 14,
    string $color = '#FFFFFF',        // Màu chữ mã hex
    string $position = 'bottom-right',
    ?string $background = '#000000',  // Khối viền nền đệm cho chữ
    int $backgroundOpacity = 50,
    int $offsetX = 10,
    int $offsetY = 10
);
```

---

### Chuyển Đổi Định Dạng & Nén Ảnh

| Phương thức | Định dạng xuất | Tùy chọn cốt lõi |
|---|---|---|
| `toWebp(int $quality = 85)` | WebP | `$quality` (1 - 100). Định dạng tối ưu nhất cho web hiện đại. |
| `toJpeg(int $quality = 85)` | JPEG | `$quality` (1 - 100). Tự động lưu ở dạng Progressive JPEG. |
| `toPng()` | PNG | Nén không suy hao dữ liệu và giữ nguyên kênh trong suốt. |
| `toAvif(int $quality = 80)` | AVIF | Định dạng nén thế hệ mới (yêu cầu PHP 8.1+ hỗ trợ AVIF trong GD). |

---

### Bộ Lọc Màu & Hiệu Ứng Thị Giác

```php
$builder->grayscale();          // Chuyển ảnh sang trắng đen
$builder->brightness(int $lvl); // Độ sáng: -127 đến +127
$builder->contrast(int $lvl);   // Độ tương phản: -127 đến +127
$builder->blur();               // Làm mờ ảnh (Gaussian blur)
$builder->sharpen();            // Tăng độ sắc nét của ảnh
```

---

### Tra Cứu SingleResult & ProcessedFile
Khi gọi `->saveTo()` hoặc `->process()` trên `UploadBuilder`, kết quả trả về là một instance của `SingleResult`:

```php
$result = Upload::file($_FILES['photo'])->saveTo('/uploads');

if ($result->isSuccess()) {
    $file = $result->getFile(); // Instance của ProcessedFile
    
    echo $file->getOriginalName();      // Tên file gốc: ví dụ "Anh Dep.png"
    echo $file->getFilename();          // Tên file đã lưu: ví dụ "anh-dep.webp"
    echo $file->getSavedPath();         // Đường dẫn tuyệt đối: "/uploads/anh-dep.webp"
    echo $file->getFilePath();          // Alias tương đương getSavedPath()
    echo $file->getMime();              // Ví dụ: "image/webp"
    echo $file->getExtension();          // Ví dụ: "webp"
    echo $file->getSize();              // Dung lượng tính bằng bytes
    echo $file->getFormattedSize();     // Ví dụ: "1.42 MB"
    echo $file->getWidth();             // Ví dụ: 1200
    echo $file->getHeight();            // Ví dụ: 800
    print_r($file->getDimensions());    // ['width' => 1200, 'height' => 800]
    print_r($file->toArray());          // Xuất mảng dữ liệu đầy đủ
} else {
    echo $result->getErrorMessage();    // Thông báo lỗi đã được dịch
    echo $result->getErrorCode();       // Mã lỗi: ví dụ "ERR_FILE_TOO_LARGE"
    print_r($result->getErrors());      // ['code' => ..., 'message' => ..., 'context' => ...]

    // Chi tiết kỹ thuật chỉ dùng để ghi log (tiếng Anh, có thể chứa đường dẫn) - không hiển thị cho người dùng:
    error_log($result->getErrors()['context']['reason'] ?? '');
}
```

---

### Tra Cứu BatchUploadBuilder & BatchResult
Khi gọi `->process()` trên `BatchUploadBuilder`, kết quả trả về là một instance của `BatchResult`:

```php
$batch = Upload::files($_FILES['photos'])
    ->saveTo('/uploads/gallery')
    ->rollbackOnError(true)
    ->process();

$batch->allSuccessful();    // Trả về true nếu 100% file trong mẻ thành công
$batch->hasFailures();      // Trả về true nếu có từ 1 file trở lên thất bại
$batch->count();            // Tổng số lượng file đã xử lý

// Danh sách kết quả:
$batch->getSuccessful();    // Mảng các đối tượng ProcessedFile thành công
$batch->getFailed();        // Mảng các đối tượng SingleResult thất bại
$batch->getErrors();        // Mảng chi tiết lỗi theo tên file gốc
```

---

### Pipeline Đa Biến Thể (Multi-Variant Thumbnails)
Tự động tạo nhiều phiên bản kích thước, định dạng hoặc đóng dấu mờ khác nhau (như ảnh thumbnail, ảnh preview) từ cùng một tệp upload mà không cần người dùng tải lên nhiều lần:

```php
use UploadHelper\Upload;

$result = Upload::file($_FILES['avatar'])
    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
    ->slugName()
    ->destination('/var/www/uploads/avatars/original') // Thư mục lưu bản gốc
    // Biến thể 1: Ảnh vuông thumbnail cho danh sách thành viên
    ->variant('thumb', function ($v) {
        $v->resize(150, 150, mode: 'crop')
          ->toWebp(quality: 80)
          ->saveTo('/var/www/uploads/avatars/thumbs');
    })
    // Biến thể 2: Ảnh xem trước kích thước vừa cho trang cá nhân
    ->variant('preview', function ($v) {
        $v->resize(600, 400, mode: 'fit')
          ->toWebp(quality: 85)
          ->saveTo('/var/www/uploads/avatars/previews');
    })
    ->process();

if ($result->isSuccess()) {
    echo "Ảnh gốc: " . $result->getFilePath() . "\n";
    
    // Truy xuất từng biến thể dễ dàng:
    $thumb = $result->getVariant('thumb');
    echo "Thumbnail: " . $thumb->getSavedPath() . " (" . $thumb->getWidth() . "x" . $thumb->getHeight() . ")\n";
    
    $preview = $result->getVariant('preview');
    echo "Preview: " . $preview->getSavedPath() . "\n";
    
    // Duyệt qua toàn bộ danh sách biến thể:
    foreach ($result->getVariants() as $name => $variantFile) {
        echo "- Biến thể '{$name}': " . $variantFile->getFilename() . "\n";
    }
}
```

---

### Vòng Đời Sự Kiện & Lifecycle Hooks
Gắn các hàm callback chặn (intercept) vào các mốc trong vòng đời xử lý tệp tin:

```php
Upload::file($_FILES['contract'])
    ->beforeProcess(function ($uploadedFile) {
        // Chạy trước khi tiến hành kiểm tra & xử lý
        Log::info("Bắt đầu xử lý tải lên: " . $uploadedFile->getOriginalName());
    })
    ->onSuccess(function ($processedFile, $result) {
        // Chỉ chạy khi toàn bộ quá trình upload thành công
        Database::recordUpload($processedFile->getFilename(), $processedFile->getSize());
        Notification::send("Tải lên thành công!");
    })
    ->onError(function (string $errorMessage, string $errorCode, $result) {
        // Chạy khi vi phạm validation hoặc lỗi ghi đĩa
        Log::warning("Tải lên thất bại [{$errorCode}]: {$errorMessage}");
    })
    ->afterProcess(function ($result) {
        // Luôn luôn chạy khi kết thúc (dù thành công hay thất bại)
        Metrics::increment('uploads.attempted');
    })
    ->saveTo('/uploads/contracts');
```


---

## 8. Hướng Dẫn Tích Hợp Framework

### PHP thuần (Vanilla PHP Web Form)
```html
<!-- index.html -->
<form action="upload.php" method="POST" enctype="multipart/form-data">
    <label>Chọn bộ ảnh:</label>
    <input type="file" name="photos[]" multiple required>
    <button type="submit">Tải lên thư viện ảnh</button>
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
    echo "Lỗi upload: " . json_encode($batch->getErrors());
}
```

---

### Tích hợp Laravel Framework
Trong Controller của Laravel:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use UploadHelper\Upload;

class MediaController extends Controller
{
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|file']);

        // Truyền đường dẫn thật của file từ Symfony UploadedFile:
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
        return back()->with('status', 'Ảnh đại diện đã được cập nhật thành công!');
    }
}
```

---

### Tích hợp Symfony Framework
Trong Controller của Symfony:

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

### Tích hợp Phalcon Framework
Trong Controller của Phalcon:

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
                $this->flash->success("Đã tải lên thành công " . count($batch->getSuccessful()) . " tệp!");
            } else {
                $this->flash->error("Có lỗi xảy ra khi tải lên tệp tin.");
            }
        }
    }
}
```

---

### Tích hợp Slim / PSR-7 Frameworks
```php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use UploadHelper\Upload;

$app->post('/api/upload', function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();
    $avatar = $uploadedFiles['avatar'] ?? null;

    if (!$avatar) {
        $response->getBody()->write(json_encode(['error' => 'Chưa có file nào được chọn']));
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

## 9. Đa Ngôn Ngữ (I18n) & Bảng Mã Lỗi Chuẩn Hóa

### Thiết lập ngôn ngữ hoạt động
Ngôn ngữ mặc định là Tiếng Anh (`en`); Tiếng Việt (`vi`) có sẵn trong thư viện. Khóa nào thiếu sẽ lùi về ngôn ngữ đang dùng → `en` → chính mã lỗi.

```php
use UploadHelper\I18n\Translator;

// Đổi ngôn ngữ toàn cục (định dạng "xx", "xxx" hoặc "xx_YY"; sai định dạng sẽ ném InvalidLocaleException):
Translator::setLocale('vi');

// Đăng ký hoặc ghi đè bản dịch cho bất kỳ ngôn ngữ nào:
Translator::addTranslations('ja', [
    'ERR_FILE_TOO_LARGE'        => 'ファイルサイズ ({formatted_actual}) が上限 ({formatted_max}) を超えています。',
    'ERR_EXTENSION_NOT_ALLOWED' => '拡張子 .{extension} は許可されていません。',
]);
Translator::setLocale('ja');

Translator::has('ERR_FILE_TOO_LARGE', 'ja'); // true
```

Thông báo trả về từ `SingleResult::getErrorMessage()` được dịch theo ngôn ngữ đang dùng. Exception ném ra bên trong thư viện luôn mang thông điệp tiếng Anh; thông điệp này được giữ ở `getErrors()['context']['reason']` để ghi log. Lỗi nội bộ không lường trước được trả về mã `ERR_INTERNAL` với thông báo chung, để đường dẫn và chi tiết nội bộ không bị lộ ra người dùng.

### Bảng mã lỗi chuẩn hóa

Toàn bộ thông điệp nằm trong [`src/I18n/en.php`](src/I18n/en.php) và [`src/I18n/vi.php`](src/I18n/vi.php); hai file luôn có cùng tập khóa (được kiểm tra bằng test). Các placeholder như `{extension}` được điền từ context của lỗi.

| Nhóm | Mã lỗi |
|---|---|
| Lỗi upload của PHP | `ERR_UPLOAD_INI_SIZE`, `ERR_UPLOAD_FORM_SIZE`, `ERR_UPLOAD_PARTIAL`, `ERR_UPLOAD_NO_FILE`, `ERR_UPLOAD_NO_TMP_DIR`, `ERR_UPLOAD_CANT_WRITE`, `ERR_UPLOAD_EXTENSION`, `ERR_UPLOAD_UNKNOWN`, `ERR_NO_FILE` |
| Extension & MIME | `ERR_EXTENSION_NOT_ALLOWED`, `ERR_MIME_NOT_ALLOWED`, `ERR_SECURITY_DANGEROUS_EXTENSION`, `ERR_SECURITY_DOUBLE_EXTENSION`, `ERR_SECURITY_NULL_BYTE`, `ERR_PATH_TRAVERSAL` |
| Dung lượng & kích thước ảnh | `ERR_FILE_TOO_LARGE`, `ERR_FILE_TOO_SMALL`, `ERR_NOT_AN_IMAGE`, `ERR_DIMENSION_MIN_WIDTH`, `ERR_DIMENSION_MAX_WIDTH`, `ERR_DIMENSION_MIN_HEIGHT`, `ERR_DIMENSION_MAX_HEIGHT`, `ERR_DIMENSION_EXACT_WIDTH`, `ERR_DIMENSION_EXACT_HEIGHT`, `ERR_DIMENSION_RATIO`, `ERR_IMAGE_PIXEL_FLOOD` |
| An toàn nội dung | `ERR_INVALID_SVG_XML`, `ERR_PDF_JAVASCRIPT_DETECTED`, `ERR_PDF_LAUNCH_DETECTED`, `ERR_PDF_EMBEDDED_FILES_DETECTED`, `ERR_OFFICE_MACRO_DETECTED`, `ERR_VIRUS_DETECTED`, `ERR_SCANNER_ERROR` |
| URL từ xa (SSRF) | `ERR_SSRF_INVALID_URL`, `ERR_SSRF_DISALLOWED_SCHEME`, `ERR_SSRF_DISALLOWED_PORT`, `ERR_SSRF_DNS_RESOLVE_FAILED`, `ERR_SSRF_BLOCKED`, `ERR_URL_DOWNLOAD_FAILED`, `ERR_URL_DOWNLOAD_TOO_LARGE`, `ERR_URL_TOO_MANY_REDIRECTS` |
| Lưu trữ & xử lý | `ERR_FILE_EXISTS`, `ERR_VARIANT_FAILED`, `ERR_PROCESS_FAILED`, `ERR_VALIDATION`, `ERR_INTERNAL` |

---

## 10. Tiêu Chuẩn Bảo Mật Máy Chủ & Phòng Chống Mã Độc

1. **Lưu trữ ngoài thư mục công khai (Web Root):**
   Luôn ưu tiên lưu các tệp tin quan trọng bên ngoài thư mục public (ví dụ `/storage/uploads` thay vì `/public/uploads`) và phân phối qua Controller bảo mật có xác thực quyền truy cập.

2. **Chặn thực thi mã kịch bản trong thư mục upload:**
   Nếu bắt buộc phải phục vụ trực tiếp qua Nginx, hãy vô hiệu hóa việc thực thi file PHP trong thư mục upload:
   ```nginx
   # Cấu hình Nginx
   location ^~ /uploads/ {
       location ~ \.(php|phtml|phar|sh|pl|py|cgi)$ {
           deny all;
           return 403;
       }
   }
   ```

   Với máy chủ Apache, đặt một file `.htaccess` bên trong thư mục upload:
   ```apache
   # Vô hiệu hóa thực thi script
   php_flag engine off
   <FilesMatch "\.(php|phtml|phar|sh|pl|cgi)$">
       Order Deny,Allow
       Deny from all
   </FilesMatch>
   ```

3. **Luôn sử dụng Strict Whitelist:**
   Không bao giờ dựa vào blacklist. Kẻ tấn công luôn tìm ra các extension thực thi mới (như `.pht`, `.shtml`, `.inc`, `.phar`). Hãy luôn khai báo rõ ràng whitelist thông qua `->allowedExtensions(['jpg', 'png', 'pdf'])`.

---

## 11. Bảng Đối Chiếu Tính Năng Với `verot/class.upload.php`

| Thuộc tính trong `verot/class.upload.php` | Phương thức trong `UploadHelper` |
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
| `$foo->dir_auto_create = true;` | Đã xử lý tự động trong `->saveTo()` |

---

## 12. Các Câu Hỏi Thường Gặp & Xử Lý Sự Cố (FAQ)

### Q: Tại sao siêu biến `$_FILES` bị rỗng hoặc gặp lỗi `UPLOAD_ERR_INI_SIZE` ngay lập tức?
**A:** Do tệp tải lên vượt quá giá trị `upload_max_filesize` hoặc `post_max_size` trong file `php.ini`. Lưu ý rằng `post_max_size` luôn phải lớn hơn hoặc bằng `upload_max_filesize`. Khi tải lên nhiều file cùng lúc, `post_max_size` phải đủ lớn để chứa tổng dung lượng của toàn bộ các file trong mẻ.

### Q: Tại sao phương thức `toWebp()` hoặc `toAvif()` báo lỗi trên máy chủ?
**A:** Hãy kiểm tra xem extension GD của PHP trên máy chủ đã được biên dịch kèm hỗ trợ WebP hoặc AVIF hay chưa:
```bash
php -r "var_dump(function_exists('imagewebp'));"
php -r "var_dump(function_exists('imageavif'));"
```
Nếu kết quả trả về `false`, hãy cài đặt thư viện `libwebp-dev` hoặc `libavif-dev` trên hệ điều hành và cài đặt lại extension GD cho PHP.

### Q: Làm thế nào để tạo cả ảnh xem trước lớn và ảnh thu nhỏ (thumbnail) từ cùng 1 file tải lên?
**A:** Gọi phương thức `saveTo()` tuần tự hoặc tái sử dụng builder:
```php
$uploader = Upload::file($_FILES['photo'])
    ->allowedExtensions(['jpg', 'png', 'webp']);

// 1. Lưu ảnh xem trước (800x600 fit):
$previewResult = $uploader
    ->prefix('preview_')
    ->resize(800, 600, mode: 'fit')
    ->saveTo('/uploads/gallery');

// 2. Lưu ảnh thumbnail thu nhỏ (150x150 vuông):
$thumbResult = $uploader
    ->prefix('thumb_')
    ->resize(150, 150, mode: 'crop')
    ->saveTo('/uploads/gallery');
```

---

## 13. Kiểm Thử Tự Động

Chạy toàn bộ bộ kiểm thử tự động thông qua PHPUnit:

```bash
# Chạy toàn bộ các bài test
php codes/lib/vendor/bin/phpunit -c codes/lib/phpunit.xml.dist

# Chạy với định dạng hiển thị chi tiết (testdox)
php codes/lib/vendor/bin/phpunit -c codes/lib/phpunit.xml.dist --testdox
```

---

## 14. Giấy Phép & Tác Giả

- **Giấy phép:** Phát hành theo chuẩn [Giấy phép MIT](LICENSE).
- **Tác giả:** [Phát Nguyễn](https://github.com/phatnt93) (`phatnt93@gmail.com`).
- **Nguồn cảm hứng:** Trân trọng cảm ơn Colin Verot vì thư viện kinh điển [verot/class.upload.php](https://github.com/verot/class.upload.php).

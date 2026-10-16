<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

class MimeSniffer
{
    /**
     * Nhận diện chính xác MIME type thực sự của tệp qua finfo và magic bytes nhị phân
     */
    public static function detect(string $filePath): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return 'application/octet-stream';
        }

        // 1. Đọc magic bytes nhị phân trực tiếp ở đầu tệp
        $magicMime = self::detectFromMagicBytes($filePath);
        if ($magicMime !== null) {
            return $magicMime;
        }

        // 2. Sử dụng PHP Fileinfo extension
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if ($mime !== false && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        // 3. Fallback qua mime_content_type
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($filePath);
            if ($mime !== false && $mime !== '') {
                return strtolower($mime);
            }
        }

        return 'application/octet-stream';
    }

    /**
     * So khớp chuỗi bytes mở đầu (Magic Bytes signature) của các định dạng phổ biến
     */
    public static function detectFromMagicBytes(string $filePath): ?string
    {
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 32);
        fclose($handle);

        if ($header === false || strlen($header) < 4) {
            return null;
        }

        // JPEG: FF D8 FF
        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (str_starts_with($header, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")) {
            return 'image/png';
        }

        // GIF: GIF87a hoặc GIF89a
        if (str_starts_with($header, "GIF87a") || str_starts_with($header, "GIF89a")) {
            return 'image/gif';
        }

        // WEBP: 'RIFF' + 4 bytes size + 'WEBP'
        if (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        // PDF: %PDF-
        if (str_starts_with($header, '%PDF-')) {
            return 'application/pdf';
        }

        // BMP: 'BM' + file size + 4 reserved zero bytes (a bare 'BM' prefix matches arbitrary text)
        if (strlen($header) >= 10 && str_starts_with($header, 'BM') && substr($header, 6, 4) === "\0\0\0\0") {
            return 'image/bmp';
        }

        // AVIF: offset 4 là 'ftyp' kèm brand 'avif' hoặc 'avis'
        if (strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp') {
            $brand = substr($header, 8, 4);
            if ($brand === 'avif' || $brand === 'avis') {
                return 'image/avif';
            }
            if ($brand === 'heic' || $brand === 'heix' || $brand === 'mif1') {
                return 'image/heic';
            }
        }

        // ZIP / DOCX / XLSX / ODT: PK 03 04
        if (str_starts_with($header, "PK\x03\x04")) {
            return 'application/zip';
        }

        return null;
    }
}

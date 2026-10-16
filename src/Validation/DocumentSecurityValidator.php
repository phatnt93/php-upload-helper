<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Exceptions\SecurityException;
use ZipArchive;

class DocumentSecurityValidator
{
    /**
     * Kiểm tra tệp PDF có chứa JavaScript hoặc các hành động thực thi nguy hiểm không
     *
     * @throws SecurityException Nếu phát hiện mã kịch bản / hành động tiềm ẩn rủi ro
     */
    public static function assertSafePdf(
        string $filePath,
        bool $allowJavaScript = false,
        bool $allowLaunch = false,
        bool $allowEmbeddedFiles = false,
    ): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $content = @file_get_contents($filePath);
        if ($content === false || strlen($content) < 5) {
            return;
        }

        // Kiểm tra xem có đúng định dạng PDF (%PDF-) không
        if (!str_starts_with($content, '%PDF-')) {
            return;
        }

        // 1. Kiểm tra JavaScript (/JavaScript hoặc /JS)
        if (!$allowJavaScript) {
            if (preg_match('/\/(?:JavaScript|JS)\b/i', $content)) {
                throw SecurityException::forCode('ERR_PDF_JAVASCRIPT_DETECTED', ['file' => basename($filePath)]);
            }
        }

        // 2. Kiểm tra lệnh khởi chạy ứng dụng bên ngoài (/Launch)
        if (!$allowLaunch) {
            if (preg_match('/\/Launch\b/i', $content)) {
                throw SecurityException::forCode('ERR_PDF_LAUNCH_DETECTED', ['file' => basename($filePath)]);
            }
        }

        // 3. Kiểm tra tệp đính kèm nhúng bên trong (/EmbeddedFiles)
        if (!$allowEmbeddedFiles) {
            if (preg_match('/\/EmbeddedFiles\b/i', $content)) {
                throw SecurityException::forCode('ERR_PDF_EMBEDDED_FILES_DETECTED', ['file' => basename($filePath)]);
            }
        }
    }

    /**
     * Kiểm tra tệp tài liệu Office (DOCX, XLSX, PPTX) có chứa macro VBA độc hại không
     *
     * @throws SecurityException Nếu phát hiện file vbaProject.bin trong gói lưu trữ OOXML
     */
    public static function assertSafeOfficeDocument(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        // 1. Kiểm tra header PK (ZIP format) của định dạng OOXML
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return;
        }
        $header = fread($handle, 4);
        fclose($handle);

        if ($header !== "PK\x03\x04") {
            return;
        }

        // 2. Sử dụng ZipArchive nếu khả dụng
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            $res = $zip->open($filePath, ZipArchive::RDONLY);
            if ($res === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $entryName = strtolower($stat['name'] ?? '');

                    // Tìm kiếm các thành phần VBA macro nguy hiểm
                    if (str_contains($entryName, 'vbaproject.bin') || str_contains($entryName, 'vba/')) {
                        $zip->close();
                        throw SecurityException::forCode('ERR_OFFICE_MACRO_DETECTED', ['file' => basename($filePath), 'entry' => $entryName]);
                    }
                }
                $zip->close();
                return;
            }
        }

        // 3. Fallback: Quét nhị phân chuỗi "vbaProject.bin" trong toàn bộ file
        $content = @file_get_contents($filePath);
        if ($content !== false && stripos($content, 'vbaProject.bin') !== false) {
            throw SecurityException::forCode('ERR_OFFICE_MACRO_DETECTED', ['file' => basename($filePath)]);
        }
    }
}

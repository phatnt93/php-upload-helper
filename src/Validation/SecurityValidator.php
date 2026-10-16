<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Contracts\ScannerInterface;
use UploadHelper\Contracts\ValidatorInterface;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\ValidationException;

class SecurityValidator implements ValidatorInterface
{
    /**
     * @param array<int, string> $allowedExtensions
     * @param array<int, string> $allowedMimes
     * @param array<string, mixed> $dimensionConstraints
     */
    public function __construct(
        private array $allowedExtensions = [],
        private array $allowedMimes = [],
        private int|string|null $maxSize = null,
        private int|string|null $minSize = null,
        private array $dimensionConstraints = [],
        private bool $sanitizeSvg = true,
        private ?int $maxPixels = null,
        private int|string|null $maxImageMemory = null,
        private bool $blockPdfJavaScript = false,
        private bool $blockOfficeMacros = false,
        private ?ScannerInterface $scanner = null,
    ) {}

    /**
     * @param array<int, string> $extensions
     */
    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = $extensions;
        return $this;
    }

    /**
     * @param array<int, string> $mimes
     */
    public function setAllowedMimes(array $mimes): self
    {
        $this->allowedMimes = $mimes;
        return $this;
    }

    public function setMaxSize(int|string|null $maxSize): self
    {
        $this->maxSize = $maxSize;
        return $this;
    }

    public function setMinSize(int|string|null $minSize): self
    {
        $this->minSize = $minSize;
        return $this;
    }

    /**
     * @param array<string, mixed> $constraints
     */
    public function setDimensionConstraints(array $constraints): self
    {
        $this->dimensionConstraints = array_replace($this->dimensionConstraints, $constraints);
        return $this;
    }

    public function setSanitizeSvg(bool $sanitize): self
    {
        $this->sanitizeSvg = $sanitize;
        return $this;
    }

    public function setMaxPixels(?int $maxPixels): self
    {
        $this->maxPixels = $maxPixels;
        return $this;
    }

    public function setMaxImageMemory(int|string|null $maxMemory): self
    {
        $this->maxImageMemory = $maxMemory;
        return $this;
    }

    public function setBlockPdfJavaScript(bool $block = true): self
    {
        $this->blockPdfJavaScript = $block;
        return $this;
    }

    public function setBlockOfficeMacros(bool $block = true): self
    {
        $this->blockOfficeMacros = $block;
        return $this;
    }

    public function setScanner(?ScannerInterface $scanner): self
    {
        $this->scanner = $scanner;
        return $this;
    }

    public function validate(UploadedFile $file): void
    {
        // 1. Kiểm tra mã lỗi upload gốc của PHP
        $error = $file->getErrorCode();
        if ($error !== UPLOAD_ERR_OK) {
            $errorCode = match ($error) {
                UPLOAD_ERR_INI_SIZE   => 'ERR_UPLOAD_INI_SIZE',
                UPLOAD_ERR_FORM_SIZE  => 'ERR_UPLOAD_FORM_SIZE',
                UPLOAD_ERR_PARTIAL    => 'ERR_UPLOAD_PARTIAL',
                UPLOAD_ERR_NO_FILE    => 'ERR_UPLOAD_NO_FILE',
                UPLOAD_ERR_NO_TMP_DIR => 'ERR_UPLOAD_NO_TMP_DIR',
                UPLOAD_ERR_CANT_WRITE => 'ERR_UPLOAD_CANT_WRITE',
                UPLOAD_ERR_EXTENSION  => 'ERR_UPLOAD_EXTENSION',
                default               => 'ERR_UPLOAD_UNKNOWN',
            };

            throw ValidationException::forCode($errorCode, ['filename' => $file->getOriginalName(), 'upload_error' => $error]);
        }

        // 2. Kiểm tra Extension whitelist và các extension thực thi nguy hiểm
        ExtensionGuard::assertAllowed($file->getOriginalName(), $this->allowedExtensions);

        // 3. Kiểm tra MIME thực sự qua magic bytes / finfo (không tin trường type của $_FILES)
        if (!empty($this->allowedMimes)) {
            $detectedMime = MimeSniffer::detect($file->getCurrentPath());
            $allowedMimes = array_map('strtolower', $this->allowedMimes);

            if (!in_array($detectedMime, $allowedMimes, true)) {
                throw ValidationException::forCode('ERR_MIME_NOT_ALLOWED', ['filename' => $file->getOriginalName(), 'detected_mime' => $detectedMime, 'allowed_mimes' => $this->allowedMimes]);
            }
        }

        // 4. Kiểm tra kích thước dung lượng
        SizeValidator::validate($file, $this->maxSize, $this->minSize);

        // 5. Kiểm tra kích thước độ phân giải (nếu có cấu hình)
        if (!empty($this->dimensionConstraints)) {
            DimensionValidator::validate($file, $this->dimensionConstraints);
        }

        // 6. Làm sạch SVG chống Stored XSS
        if ($this->sanitizeSvg && $file->getCurrentExtension() === 'svg' && file_exists($file->getCurrentPath())) {
            SvgSanitizer::sanitize($file->getCurrentPath());
        }

        // 7. Chống Decompression Bomb / Pixel Flood DoS cho ảnh
        if ($file->isImage() && file_exists($file->getCurrentPath())) {
            PixelFloodGuard::assertSafe(
                $file->getCurrentPath(),
                $this->maxPixels,
                SizeValidator::parseToBytes($this->maxImageMemory),
            );
        }

        // 8. Kiểm tra an toàn cho tài liệu PDF (chặn JavaScript / Launch / Embedded)
        if ($this->blockPdfJavaScript && $file->getCurrentExtension() === 'pdf' && file_exists($file->getCurrentPath())) {
            DocumentSecurityValidator::assertSafePdf($file->getCurrentPath());
        }

        // 9. Kiểm tra an toàn cho tài liệu Office (chặn VBA Macros)
        if ($this->blockOfficeMacros && in_array($file->getCurrentExtension(), ['docx', 'xlsx', 'pptx', 'docm', 'xlsm', 'pptm', 'dotm'], true) && file_exists($file->getCurrentPath())) {
            DocumentSecurityValidator::assertSafeOfficeDocument($file->getCurrentPath());
        }

        // 10. Quét Antivirus qua ScannerInterface nếu được kích hoạt
        if ($this->scanner !== null && file_exists($file->getCurrentPath())) {
            $isClean = $this->scanner->scan($file->getCurrentPath());
            if (!$isClean) {
                $threat = $this->scanner->getThreatName() ?? 'Unknown-Malware';
                throw SecurityException::forCode('ERR_VIRUS_DETECTED', ['filename' => $file->getOriginalName(), 'threat' => $threat]);
            }
        }
    }
}

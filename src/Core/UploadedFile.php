<?php

declare(strict_types=1);

namespace UploadHelper\Core;

class UploadedFile
{
    private string $currentName;
    private string $currentExtension;
    private string $currentPath;
    private ?string $detectedMime = null;
    /** @var array{width: int, height: int}|null|false */
    private array|null|false $dimensions = false;
    private bool $isEphemeral = false;

    public function __construct(
        private readonly string $originalName,
        private readonly string $clientMime,
        private readonly int $size,
        private readonly string $tmpPath,
        private readonly int $errorCode = UPLOAD_ERR_OK,
        private readonly ?string $fieldKey = null,
    ) {
        $cleanOriginal = trim(basename($this->originalName), " \t\n\r\0\x0B");
        $this->currentName = pathinfo($cleanOriginal, PATHINFO_FILENAME);
        $this->currentExtension = strtolower(trim(pathinfo($cleanOriginal, PATHINFO_EXTENSION), " \t\n\r\0\x0B"));
        $this->currentPath = $this->tmpPath;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientMime(): string
    {
        return $this->clientMime;
    }

    public function getSize(): int
    {
        if ($this->currentPath !== $this->tmpPath && file_exists($this->currentPath)) {
            $currentSize = filesize($this->currentPath);
            if ($currentSize !== false) {
                return $currentSize;
            }
        }

        if ($this->size > 0) {
            return $this->size;
        }

        if (file_exists($this->currentPath)) {
            $currentSize = filesize($this->currentPath);
            if ($currentSize !== false) {
                return $currentSize;
            }
        }

        return 0;
    }

    public function getTmpPath(): string
    {
        return $this->tmpPath;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function isOk(): bool
    {
        return $this->errorCode === UPLOAD_ERR_OK && !empty($this->currentPath) && file_exists($this->currentPath);
    }

    public function getFieldKey(): ?string
    {
        return $this->fieldKey;
    }

    public function getCurrentName(): string
    {
        return $this->currentName;
    }

    public function setCurrentName(string $name): self
    {
        $this->currentName = trim($name, " \t\n\r\0\x0B");
        return $this;
    }

    public function getCurrentExtension(): string
    {
        return $this->currentExtension;
    }

    public function setCurrentExtension(string $extension): self
    {
        $this->currentExtension = strtolower(trim(ltrim($extension, '.'), " \t\n\r\0\x0B"));
        return $this;
    }

    public function getCurrentFilename(): string
    {
        if ($this->currentExtension === '') {
            return $this->currentName;
        }
        return $this->currentName . '.' . $this->currentExtension;
    }

    public function getCurrentPath(): string
    {
        return $this->currentPath;
    }

    public function setCurrentPath(string $path, bool $isEphemeral = false): self
    {
        if ($this->isEphemeral && $this->currentPath !== $path && file_exists($this->currentPath)) {
            @unlink($this->currentPath);
        }

        $this->currentPath = $path;
        $this->isEphemeral = $isEphemeral;
        $this->detectedMime = null;
        $this->dimensions = false;

        return $this;
    }

    public function getDetectedMime(): string
    {
        if ($this->detectedMime !== null) {
            return $this->detectedMime;
        }

        if (!file_exists($this->currentPath)) {
            return $this->clientMime;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $this->currentPath);
                finfo_close($finfo);
                if ($mime !== false && $mime !== '') {
                    $this->detectedMime = strtolower($mime);
                    return $this->detectedMime;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($this->currentPath);
            if ($mime !== false && $mime !== '') {
                $this->detectedMime = strtolower($mime);
                return $this->detectedMime;
            }
        }

        $this->detectedMime = strtolower($this->clientMime);
        return $this->detectedMime;
    }

    public function isImage(): bool
    {
        $mime = $this->getDetectedMime();
        return str_starts_with($mime, 'image/') || in_array($this->currentExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg'], true);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function getImageDimensions(): ?array
    {
        if ($this->dimensions !== false) {
            return $this->dimensions;
        }

        if (!file_exists($this->currentPath) || !$this->isImage()) {
            $this->dimensions = null;
            return null;
        }

        $info = @getimagesize($this->currentPath);
        if ($info === false) {
            $this->dimensions = null;
            return null;
        }

        $this->dimensions = [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
        ];

        return $this->dimensions;
    }

    public function getFormattedSize(int $precision = 2): string
    {
        $bytes = (float) $this->getSize();
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function getHash(string $algo = 'sha256'): string
    {
        if (file_exists($this->currentPath)) {
            $hash = hash_file($algo, $this->currentPath);
            if ($hash !== false) {
                return $hash;
            }
        }
        return '';
    }

    public function getSha256(): string
    {
        return $this->getHash('sha256');
    }

    public function getMd5(): string
    {
        return $this->getHash('md5');
    }

    public function clean(): void
    {
        if ($this->isEphemeral && file_exists($this->currentPath)) {
            @unlink($this->currentPath);
            $this->isEphemeral = false;
        }
    }

    public function __destruct()
    {
        $this->clean();
    }
}

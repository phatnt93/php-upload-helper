<?php

declare(strict_types=1);

namespace UploadHelper\Core;

class ProcessedFile
{
    /**
     * @param array{width: int, height: int}|null $dimensions
     */
    public function __construct(
        private readonly string $originalName,
        private readonly string $savedPath,
        private readonly string $filename,
        private readonly string $mime,
        private readonly string $extension,
        private readonly int $size,
        private readonly ?array $dimensions = null,
        private readonly ?string $fieldKey = null,
        private ?string $url = null,
        private ?string $sha256 = null,
    ) {}

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getSavedPath(): string
    {
        return $this->savedPath;
    }

    public function getFilePath(): string
    {
        return $this->savedPath;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function getDimensions(): ?array
    {
        return $this->dimensions;
    }

    public function getWidth(): ?int
    {
        return $this->dimensions['width'] ?? null;
    }

    public function getHeight(): ?int
    {
        return $this->dimensions['height'] ?? null;
    }

    public function getFieldKey(): ?string
    {
        return $this->fieldKey;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getFormattedSize(int $precision = 2): string
    {
        $bytes = (float) $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function getHash(string $algo = 'sha256'): string
    {
        if (file_exists($this->savedPath)) {
            $hash = hash_file($algo, $this->savedPath);
            if ($hash !== false) {
                return $hash;
            }
        }
        return '';
    }

    public function getSha256(): string
    {
        if ($this->sha256 === null) {
            $this->sha256 = $this->getHash('sha256');
        }
        return $this->sha256;
    }

    public function getMd5(): string
    {
        return $this->getHash('md5');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original_name' => $this->originalName,
            'saved_path'    => $this->savedPath,
            'filename'      => $this->filename,
            'mime'          => $this->mime,
            'extension'     => $this->extension,
            'size'          => $this->size,
            'sha256'        => $this->getSha256(),
            'formatted_size' => $this->getFormattedSize(),
            'dimensions'    => $this->dimensions,
            'field_key'     => $this->fieldKey,
            'url'           => $this->url,
        ];
    }
}

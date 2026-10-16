<?php

declare(strict_types=1);

namespace UploadHelper\Results;

use UploadHelper\Contracts\ResultInterface;
use UploadHelper\Core\ProcessedFile;
use UploadHelper\Core\UploadedFile;

class SingleResult implements ResultInterface
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, ProcessedFile> $variants
     * @param array<string, string> $variantErrors
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?ProcessedFile $file = null,
        private readonly ?UploadedFile $originalFile = null,
        private readonly ?string $errorMessage = null,
        private readonly ?string $errorCode = null,
        private readonly array $context = [],
        private readonly array $variants = [],
        private readonly array $variantErrors = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getFile(): ?ProcessedFile
    {
        return $this->file;
    }

    public function getOriginalFile(): ?UploadedFile
    {
        return $this->originalFile;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        if ($this->errorMessage === null) {
            return [];
        }

        return [
            'code'           => $this->errorCode,
            'message'        => $this->errorMessage,
            'context'        => $this->context,
            'variant_errors' => $this->variantErrors,
        ];
    }

    public function getFilePath(): ?string
    {
        return $this->file?->getSavedPath();
    }

    public function getFilename(): ?string
    {
        return $this->file?->getFilename();
    }

    public function getUrl(): ?string
    {
        return $this->file?->getUrl();
    }

    public function getFormattedSize(): ?string
    {
        return $this->file?->getFormattedSize();
    }

    /**
     * Lấy tệp biến thể đã xử lý (ví dụ: 'thumb', 'preview')
     */
    public function getVariant(string $name): ?ProcessedFile
    {
        return $this->variants[$name] ?? null;
    }

    /**
     * @return array<string, ProcessedFile>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function hasVariant(string $name): bool
    {
        return isset($this->variants[$name]);
    }

    /**
     * @return array<string, string>
     */
    public function getVariantErrors(): array
    {
        return $this->variantErrors;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'        => $this->success,
            'file'           => $this->file?->toArray(),
            'error'          => $this->errorMessage,
            'code'           => $this->errorCode,
            'variants'       => array_map(fn(ProcessedFile $v) => $v->toArray(), $this->variants),
            'variant_errors' => $this->variantErrors,
        ];
    }
}

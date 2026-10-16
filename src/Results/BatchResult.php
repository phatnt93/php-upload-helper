<?php

declare(strict_types=1);

namespace UploadHelper\Results;

use Countable;
use UploadHelper\Contracts\ResultInterface;
use UploadHelper\Core\ProcessedFile;
use UploadHelper\Core\UploadedFile;

class BatchResult implements ResultInterface, Countable
{
    /**
     * @param array<int, ProcessedFile> $successful
     * @param array<int, array{file: UploadedFile, error: string, code: string, context?: array<string, mixed>}> $failed
     */
    public function __construct(
        private readonly array $successful = [],
        private readonly array $failed = [],
    ) {}

    public function allSuccessful(): bool
    {
        return empty($this->failed);
    }

    public function isSuccess(): bool
    {
        return $this->allSuccessful();
    }

    public function hasFailures(): bool
    {
        return !empty($this->failed);
    }

    /**
     * @return array<int, ProcessedFile>
     */
    public function getSuccessful(): array
    {
        return $this->successful;
    }

    /**
     * @return array<int, array{file: UploadedFile, error: string, code: string, context?: array<string, mixed>}>
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * @return array<string|int, string>
     */
    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->failed as $index => $item) {
            $key = $item['file']->getFieldKey() ?? $index;
            $errors[$key] = $item['error'];
        }
        return $errors;
    }

    public function count(): int
    {
        return count($this->successful) + count($this->failed);
    }
}

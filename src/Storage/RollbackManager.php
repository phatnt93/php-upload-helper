<?php

declare(strict_types=1);

namespace UploadHelper\Storage;

class RollbackManager
{
    /** @var array<int, string> */
    private array $savedFiles = [];

    public function record(string $filePath): self
    {
        if (!in_array($filePath, $this->savedFiles, true)) {
            $this->savedFiles[] = $filePath;
        }
        return $this;
    }

    /**
     * Hoàn tác xóa toàn bộ các tệp tin đã ghi trong phiên
     *
     * @return array<int, string> Danh sách các tệp đã được xóa
     */
    public function rollback(): array
    {
        $deleted = [];
        foreach ($this->savedFiles as $filePath) {
            if (file_exists($filePath)) {
                if (@unlink($filePath)) {
                    $deleted[] = $filePath;
                }
            }
        }
        $this->savedFiles = [];

        return $deleted;
    }

    public function clear(): void
    {
        $this->savedFiles = [];
    }

    /**
     * @return array<int, string>
     */
    public function getSavedFiles(): array
    {
        return $this->savedFiles;
    }

    public function count(): int
    {
        return count($this->savedFiles);
    }
}

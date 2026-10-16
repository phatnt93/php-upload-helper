<?php

declare(strict_types=1);

namespace UploadHelper\Storage;

use UploadHelper\Contracts\StorageInterface;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\UploadException;

class LocalStorage implements StorageInterface
{
    private bool $hardenDirectory = false;

    public function setHardenDirectory(bool $harden = true): self
    {
        $this->hardenDirectory = $harden;
        return $this;
    }
    public function save(
        UploadedFile $file,
        string $destinationDir,
        string $targetFilename,
        bool $overwrite = false,
        int $chmod = 0755,
    ): string {
        // 1. Chống Path Traversal trong targetFilename
        $cleanFilename = basename($targetFilename);
        if ($cleanFilename !== $targetFilename || str_contains($targetFilename, '..')) {
            throw SecurityException::forCode('ERR_PATH_TRAVERSAL', ['filename' => $targetFilename]);
        }

        // 2. Tạo thư mục đích nếu chưa tồn tại
        if (!is_dir($destinationDir)) {
            if (!@mkdir($destinationDir, $chmod, true) && !is_dir($destinationDir)) {
                throw UploadException::directoryNotCreated($destinationDir);
            }
            @chmod($destinationDir, $chmod);
        }

        $realDestDir = realpath($destinationDir);
        if ($realDestDir === false) {
            throw UploadException::invalidDestination($destinationDir);
        }

        if ($this->hardenDirectory) {
            DirectoryHardener::harden($realDestDir);
        }

        $destinationPath = $realDestDir . DIRECTORY_SEPARATOR . $cleanFilename;

        // 3. Kiểm tra trùng lặp tệp
        if (file_exists($destinationPath)) {
            if (!$overwrite) {
                throw UploadException::fileExists($cleanFilename);
            }
        }

        // 4. Di chuyển / Lưu tệp tin
        $sourcePath = $file->getCurrentPath();
        if (!file_exists($sourcePath)) {
            throw UploadException::sourceMissing($sourcePath);
        }

        // Ưu tiên move_uploaded_file nếu là file upload thật qua HTTP SAPI
        $saved = false;
        if (is_uploaded_file($sourcePath)) {
            $saved = @move_uploaded_file($sourcePath, $destinationPath);
        }

        // Fallback: copy qua file tạm nguyên khối rồi rename (Atomic write)
        if (!$saved) {
            $tempDestination = $destinationPath . '.tmp_' . bin2hex(random_bytes(4));
            if (@copy($sourcePath, $tempDestination)) {
                $saved = @rename($tempDestination, $destinationPath);
                if (!$saved && file_exists($tempDestination)) {
                    @unlink($tempDestination);
                }
            }
        }

        if (!$saved) {
            throw UploadException::saveFailed($destinationPath);
        }

        @chmod($destinationPath, $chmod & 0666);

        return $destinationPath;
    }

    public function resolveUniqueFilename(string $dir, string $filename): string
    {
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($path)) {
            return $filename;
        }

        $info = pathinfo($filename);
        $name = $info['filename'];
        $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';

        $counter = 1;
        while (file_exists(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$name}_{$counter}{$ext}")) {
            $counter++;
        }

        return "{$name}_{$counter}{$ext}";
    }

    public function delete(string $filePath): bool
    {
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return false;
    }

    public function exists(string $filePath): bool
    {
        return file_exists($filePath);
    }
}

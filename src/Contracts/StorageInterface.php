<?php

declare(strict_types=1);

namespace UploadHelper\Contracts;

use UploadHelper\Core\UploadedFile;

interface StorageInterface
{
    /**
     * Lưu tệp tin ra đĩa
     *
     * @param UploadedFile $file Tệp tin cần lưu
     * @param string $destinationDir Thư mục đích
     * @param string $targetFilename Tên file đích
     * @param bool $overwrite Cho phép ghi đè
     * @param int $chmod Phân quyền thư mục và tệp
     * @return string Đường dẫn tuyệt đối tới tệp đã lưu
     */
    public function save(
        UploadedFile $file,
        string $destinationDir,
        string $targetFilename,
        bool $overwrite = false,
        int $chmod = 0755,
    ): string;

    /**
     * Xóa một tệp tin đã lưu
     */
    public function delete(string $filePath): bool;

    /**
     * Kiểm tra tệp tin có tồn tại không
     */
    public function exists(string $filePath): bool;
}

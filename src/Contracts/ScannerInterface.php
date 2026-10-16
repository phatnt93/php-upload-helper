<?php

declare(strict_types=1);

namespace UploadHelper\Contracts;

interface ScannerInterface
{
    /**
     * Quét tệp tin tìm kiếm virus, trojan, mã độc
     *
     * @param string $filePath Đường dẫn tệp tin cần quét
     * @return bool True nếu an toàn, False nếu phát hiện mối đe dọa
     */
    public function scan(string $filePath): bool;

    /**
     * Tên loại mã độc / virus phát hiện được (nếu có)
     */
    public function getThreatName(): ?string;
}

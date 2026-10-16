<?php

declare(strict_types=1);

namespace UploadHelper\Contracts;

interface ResultInterface
{
    /**
     * Xác định tác vụ có thành công toàn bộ hay không
     */
    public function isSuccess(): bool;

    /**
     * Lấy danh sách các thông báo lỗi nếu có
     *
     * @return array<string|int, mixed>
     */
    public function getErrors(): array;
}

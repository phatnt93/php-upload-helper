<?php

declare(strict_types=1);

namespace UploadHelper\Contracts;

use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\ValidationException;

interface ValidatorInterface
{
    /**
     * Kiểm tra tính hợp lệ của tệp upload.
     * Ném ra ValidationException nếu tệp không đạt tiêu chuẩn.
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile $file): void;
}

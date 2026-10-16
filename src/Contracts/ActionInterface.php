<?php

declare(strict_types=1);

namespace UploadHelper\Contracts;

use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;

interface ActionInterface
{
    /**
     * Tên định danh của action (ví dụ: 'rename', 'resize', 'watermark')
     */
    public function getName(): string;

    /**
     * Thực thi hành động trên tệp upload
     */
    public function execute(UploadedFile $file, ActionContext $context): UploadedFile;
}

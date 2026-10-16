<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class StripExifAction implements ActionInterface
{
    public function __construct(private readonly bool $keepOrientation = true) {}

    public function getName(): string
    {
        return 'strip_exif';
    }

    public function execute(UploadedFile $file, ActionContext $context): UploadedFile
    {
        if (!$file->isImage()) {
            return $file;
        }

        $driver = $context->getImageDriver() ?? new GdDriver();
        if (!$driver->isAvailable()) {
            return $file;
        }

        $sourcePath = $file->getCurrentPath();

        $resource = $driver->load($sourcePath);

        // 1. Tự động xoay ảnh theo góc EXIF trước khi xóa metadata
        if ($this->keepOrientation) {
            $resource = $driver->autoOrient($resource, $sourcePath);
        }

        // 2. Tái tạo và ghi đè ảnh qua GD/Driver để triệt tiêu toàn bộ EXIF, GPS, IPTC, XMP
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_scrub_' . uniqid('', true) . '.' . $file->getCurrentExtension();

        $driver->save($resource, $tempPath, $file->getCurrentExtension(), 90);

        return $file->setCurrentPath($tempPath, isEphemeral: true);
    }
}

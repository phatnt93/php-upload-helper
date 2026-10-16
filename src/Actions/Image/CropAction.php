<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class CropAction implements ActionInterface
{
    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly int $x = 0,
        private readonly int $y = 0
    ) {
    }

    public function getName(): string
    {
        return 'crop';
    }

    public function execute(UploadedFile $file, ActionContext $context): UploadedFile
    {
        if (!$file->isImage() || !file_exists($file->getCurrentPath())) {
            return $file;
        }

        $driver = $context->getImageDriver() ?? new GdDriver();
        if (!$driver->isAvailable()) {
            return $file;
        }

        $image = $driver->load($file->getCurrentPath());
        $cropped = $driver->crop($image, $this->width, $this->height, $this->x, $this->y);

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_crop_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($cropped, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

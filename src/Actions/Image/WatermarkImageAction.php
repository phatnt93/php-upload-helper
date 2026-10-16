<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class WatermarkImageAction implements ActionInterface
{
    public function __construct(
        private readonly string $watermarkPath,
        private readonly string $position = 'bottom-right',
        private readonly int $opacity = 100,
        private readonly int $offsetX = 10,
        private readonly int $offsetY = 10
    ) {
    }

    public function getName(): string
    {
        return 'watermark_image';
    }

    public function execute(UploadedFile $file, ActionContext $context): UploadedFile
    {
        if (!$file->isImage() || !file_exists($file->getCurrentPath()) || !file_exists($this->watermarkPath)) {
            return $file;
        }

        $driver = $context->getImageDriver() ?? new GdDriver();
        if (!$driver->isAvailable()) {
            return $file;
        }

        $image = $driver->load($file->getCurrentPath());
        $wm = $driver->watermarkImage(
            $image,
            $this->watermarkPath,
            $this->position,
            $this->opacity,
            $this->offsetX,
            $this->offsetY
        );

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_wm_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($wm, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

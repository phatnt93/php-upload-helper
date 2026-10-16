<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class WatermarkTextAction implements ActionInterface
{
    public function __construct(
        private readonly string $text,
        private readonly string $fontPath = '',
        private readonly int $size = 14,
        private readonly string $color = '#FFFFFF',
        private readonly string $position = 'bottom-right',
        private readonly ?string $background = null,
        private readonly int $backgroundOpacity = 0,
        private readonly int $offsetX = 10,
        private readonly int $offsetY = 10
    ) {
    }

    public function getName(): string
    {
        return 'watermark_text';
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
        $wm = $driver->watermarkText(
            $image,
            $this->text,
            $this->fontPath,
            $this->size,
            $this->color,
            $this->position,
            $this->background,
            $this->backgroundOpacity,
            $this->offsetX,
            $this->offsetY
        );

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_wmt_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($wm, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

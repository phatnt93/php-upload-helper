<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class ResizeAction implements ActionInterface
{
    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly string $mode = 'fit',
        private readonly bool $allowEnlarge = false,
        private readonly string $background = '#FFFFFF',
    ) {}

    public function getName(): string
    {
        return 'resize';
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
        $resized = $driver->resize(
            $image,
            $this->width,
            $this->height,
            $this->mode,
            $this->allowEnlarge,
            $this->background,
        );

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_proc_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($resized, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

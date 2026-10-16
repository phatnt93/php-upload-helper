<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class RotateAction implements ActionInterface
{
    public function __construct(
        private readonly float $angle,
        private readonly string $background = '#000000'
    ) {
    }

    public function getName(): string
    {
        return 'rotate';
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
        $rotated = $driver->rotate($image, $this->angle, $this->background);

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_rot_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($rotated, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

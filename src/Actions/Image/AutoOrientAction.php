<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class AutoOrientAction implements ActionInterface
{
    public function getName(): string
    {
        return 'auto_orient';
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
        $oriented = $driver->autoOrient($image, $file->getCurrentPath());

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_orient_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($oriented, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

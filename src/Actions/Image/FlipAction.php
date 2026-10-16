<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class FlipAction implements ActionInterface
{
    public function __construct(private readonly string $direction = 'horizontal') {}

    public function getName(): string
    {
        return 'flip';
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
        $flipped = $driver->flip($image, $this->direction);

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_flip_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($flipped, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

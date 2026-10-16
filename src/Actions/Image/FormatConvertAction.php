<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class FormatConvertAction implements ActionInterface
{
    public function __construct(
        private readonly string $targetFormat,
        private readonly int $quality = 85
    ) {
    }

    public function getName(): string
    {
        return 'convert';
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
        $cleanFormat = strtolower(ltrim($this->targetFormat, '.'));

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_conv_' . uniqid('', true) . '.' . $cleanFormat;
        $driver->save($image, $tempPath, $cleanFormat, $this->quality);

        $file->setCurrentExtension($cleanFormat);
        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

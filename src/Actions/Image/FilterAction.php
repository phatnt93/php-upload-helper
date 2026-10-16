<?php

declare(strict_types=1);

namespace UploadHelper\Actions\Image;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;

class FilterAction implements ActionInterface
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(
        private readonly string $filterType,
        private readonly array $args = [],
    ) {}

    public function getName(): string
    {
        return 'filter';
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
        $filtered = $driver->filter($image, $this->filterType, $this->args);

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_flt_' . uniqid('', true) . '.' . $file->getCurrentExtension();
        $driver->save($filtered, $tempPath, $file->getCurrentExtension());

        $file->setCurrentPath($tempPath, isEphemeral: true);

        return $file;
    }
}

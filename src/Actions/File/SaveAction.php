<?php

declare(strict_types=1);

namespace UploadHelper\Actions\File;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\Storage\LocalStorage;

class SaveAction implements ActionInterface
{
    public function __construct(
        private ?string $destinationDir = null,
        private ?int $chmod = null,
    ) {}

    public function getName(): string
    {
        return 'save';
    }

    public function execute(UploadedFile $file, ActionContext $context): UploadedFile
    {
        $dir = $this->destinationDir ?? $context->getDestinationDir();
        if ($dir === null || $dir === '') {
            throw UploadException::destinationNotConfigured();
        }

        $chmod = $this->chmod ?? $context->getChmod();
        $overwrite = $context->isOverwrite();
        $storage = $context->getStorage() ?? new LocalStorage();

        // Nếu autoIncrement bật, resolve tên trước khi ghi
        if ($context->isAutoIncrement() && !$overwrite) {
            $currentFilename = $file->getCurrentFilename();
            if ($storage instanceof LocalStorage) {
                $uniqueName = $storage->resolveUniqueFilename($dir, $currentFilename);
                $file->setCurrentName(pathinfo($uniqueName, PATHINFO_FILENAME));
            }
        }

        $savedPath = $storage->save(
            $file,
            $dir,
            $file->getCurrentFilename(),
            $overwrite,
            $chmod,
        );

        $file->setCurrentPath($savedPath);

        return $file;
    }
}

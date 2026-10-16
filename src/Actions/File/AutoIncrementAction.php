<?php

declare(strict_types=1);

namespace UploadHelper\Actions\File;

use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Storage\LocalStorage;

class AutoIncrementAction implements ActionInterface
{
    public function getName(): string
    {
        return 'auto_increment';
    }

    public function execute(UploadedFile $file, ActionContext $context): UploadedFile
    {
        $dir = $context->getDestinationDir();
        if ($dir === null || !is_dir($dir)) {
            return $file;
        }

        $filename = $file->getCurrentFilename();
        $targetPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($targetPath) && !$context->isOverwrite()) {
            $storage = $context->getStorage();
            if ($storage instanceof LocalStorage) {
                $uniqueFilename = $storage->resolveUniqueFilename($dir, $filename);
                $newName = pathinfo($uniqueFilename, PATHINFO_FILENAME);
                $file->setCurrentName($newName);
            }
        }

        return $file;
    }
}

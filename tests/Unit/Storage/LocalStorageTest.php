<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Storage;

use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Storage\RollbackManager;
use UploadHelper\Tests\TestCase;

class LocalStorageTest extends TestCase
{
    public function testSaveFileSuccessfully(): void
    {
        $destDir = $this->createTempDir();
        $sourcePath = $this->createTempFile('file content', 'txt');
        $file = new UploadedFile('doc.txt', 'text/plain', 12, $sourcePath);

        $storage = new LocalStorage();
        $savedPath = $storage->save($file, $destDir, 'doc.txt');

        $this->assertFileExists($savedPath);
        $this->assertSame('file content', file_get_contents($savedPath));
    }

    public function testOverwriteDisallowedThrowsException(): void
    {
        $destDir = $this->createTempDir();
        $existingFile = $destDir . DIRECTORY_SEPARATOR . 'doc.txt';
        file_put_contents($existingFile, 'old content');

        $sourcePath = $this->createTempFile('new content', 'txt');
        $file = new UploadedFile('doc.txt', 'text/plain', 11, $sourcePath);

        $storage = new LocalStorage();

        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('already exists');
        $storage->save($file, $destDir, 'doc.txt', overwrite: false);
    }

    public function testOverwriteAllowed(): void
    {
        $destDir = $this->createTempDir();
        $existingFile = $destDir . DIRECTORY_SEPARATOR . 'doc.txt';
        file_put_contents($existingFile, 'old content');

        $sourcePath = $this->createTempFile('new content', 'txt');
        $file = new UploadedFile('doc.txt', 'text/plain', 11, $sourcePath);

        $storage = new LocalStorage();
        $savedPath = $storage->save($file, $destDir, 'doc.txt', overwrite: true);

        $this->assertFileExists($savedPath);
        $this->assertSame('new content', file_get_contents($savedPath));
    }

    public function testResolveUniqueFilename(): void
    {
        $destDir = $this->createTempDir();
        file_put_contents($destDir . DIRECTORY_SEPARATOR . 'photo.jpg', '1');
        file_put_contents($destDir . DIRECTORY_SEPARATOR . 'photo_1.jpg', '2');

        $storage = new LocalStorage();
        $unique = $storage->resolveUniqueFilename($destDir, 'photo.jpg');

        $this->assertSame('photo_2.jpg', $unique);
    }

    public function testPathTraversalIsBlocked(): void
    {
        $destDir = $this->createTempDir();
        $sourcePath = $this->createTempFile('test', 'txt');
        $file = new UploadedFile('test.txt', 'text/plain', 4, $sourcePath);

        $storage = new LocalStorage();

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path traversal');
        $storage->save($file, $destDir, '../escape.txt');
    }

    public function testRollbackManagerDeletesSavedFiles(): void
    {
        $f1 = $this->createTempFile('1', 'txt');
        $f2 = $this->createTempFile('2', 'txt');

        $rollback = new RollbackManager();
        $rollback->record($f1);
        $rollback->record($f2);

        $this->assertSame(2, $rollback->count());

        $deleted = $rollback->rollback();

        $this->assertCount(2, $deleted);
        $this->assertFileDoesNotExist($f1);
        $this->assertFileDoesNotExist($f2);
        $this->assertSame(0, $rollback->count());
    }
}

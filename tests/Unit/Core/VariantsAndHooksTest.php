<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Core\FileCollection;
use UploadHelper\Core\ProcessedFile;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Results\BatchResult;
use UploadHelper\Results\SingleResult;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Tests\TestCase;
use UploadHelper\Upload;

#[RequiresPhpExtension('gd')]
class VariantsAndHooksTest extends TestCase
{
    public function testSingleFileMultiVariant(): void
    {
        $imagePath = $this->createTestImage(400, 300, 'jpeg');
        $uploaded = new UploadedFile('photo.jpg', 'image/jpeg', filesize($imagePath), $imagePath, UPLOAD_ERR_OK);

        $destDir = $this->createTempDir();
        $thumbDir = $this->createTempDir();
        $previewDir = $this->createTempDir();

        $result = Upload::file($uploaded)
            ->slugName()
            ->destination($destDir)
            ->variant('thumb', function ($v) use ($thumbDir) {
                $v->resize(60, 60, mode: 'crop')
                  ->saveTo($thumbDir);
            })
            ->variant('preview', function ($v) use ($previewDir) {
                $v->resize(200, 150, mode: 'fit')
                  ->toWebp(80)
                  ->saveTo($previewDir);
            })
            ->process();

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists((string) $result->getFilePath());

        // Kiểm tra biến thể thumb
        $this->assertTrue($result->hasVariant('thumb'));
        $thumb = $result->getVariant('thumb');
        $this->assertInstanceOf(ProcessedFile::class, $thumb);
        $this->assertFileExists($thumb->getSavedPath());
        $this->assertSame(60, $thumb->getWidth());
        $this->assertSame(60, $thumb->getHeight());

        // Kiểm tra biến thể preview
        $this->assertTrue($result->hasVariant('preview'));
        $preview = $result->getVariant('preview');
        $this->assertInstanceOf(ProcessedFile::class, $preview);
        $this->assertFileExists($preview->getSavedPath());
        $this->assertSame('webp', $preview->getExtension());

        // Kiểm tra danh sách variants
        $variants = $result->getVariants();
        $this->assertCount(2, $variants);
        $this->assertArrayHasKey('thumb', $variants);
        $this->assertArrayHasKey('preview', $variants);

        // Kiểm tra toArray()
        $array = $result->toArray();
        $this->assertArrayHasKey('variants', $array);
        $this->assertCount(2, $array['variants']);
    }

    public function testVariantAutoInheritsDestinationDir(): void
    {
        $imagePath = $this->createTestImage(200, 200, 'jpeg');
        $uploaded = new UploadedFile('avatar.jpg', 'image/jpeg', filesize($imagePath), $imagePath, UPLOAD_ERR_OK);

        $destDir = $this->createTempDir();

        // Không gọi saveTo() bên trong variant, hệ thống tự kế thừa destDir và gắn prefix "thumb_"
        $result = Upload::file($uploaded)
            ->name('user_1')
            ->variant('thumb', function ($v) {
                $v->resize(50, 50, mode: 'crop');
            })
            ->saveTo($destDir);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->hasVariant('thumb'));

        $thumb = $result->getVariant('thumb');
        $this->assertInstanceOf(ProcessedFile::class, $thumb);
        $this->assertFileExists($thumb->getSavedPath());
        $this->assertStringStartsWith('thumb_', $thumb->getFilename());
        $this->assertSame(50, $thumb->getWidth());
        $this->assertSame(50, $thumb->getHeight());
    }

    public function testLifecycleHooksSingleFileSuccess(): void
    {
        $imagePath = $this->createTestImage(100, 100, 'jpeg');
        $uploaded = new UploadedFile('photo.jpg', 'image/jpeg', filesize($imagePath), $imagePath, UPLOAD_ERR_OK);
        $destDir = $this->createTempDir();

        $events = [];

        $result = Upload::file($uploaded)
            ->beforeProcess(function ($file) use (&$events) {
                $events[] = 'before';
                $this->assertInstanceOf(UploadedFile::class, $file);
            })
            ->onSuccess(function ($file, $res) use (&$events) {
                $events[] = 'success';
                $this->assertInstanceOf(ProcessedFile::class, $file);
                $this->assertInstanceOf(SingleResult::class, $res);
            })
            ->onError(function ($err, $code, $res) use (&$events) {
                $events[] = 'error';
            })
            ->afterProcess(function ($res) use (&$events) {
                $events[] = 'after';
                $this->assertInstanceOf(SingleResult::class, $res);
            })
            ->saveTo($destDir);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['before', 'success', 'after'], $events);
    }

    public function testLifecycleHooksSingleFileError(): void
    {
        $filePath = $this->createTempFile('Malicious file', 'php');
        $uploaded = new UploadedFile('shell.php', 'text/x-php', filesize($filePath), $filePath, UPLOAD_ERR_OK);
        $destDir = $this->createTempDir();

        $events = [];
        $capturedError = null;

        $result = Upload::file($uploaded)
            ->allowedExtensions(['jpg', 'png'])
            ->beforeProcess(function ($file) use (&$events) {
                $events[] = 'before';
            })
            ->onSuccess(function () use (&$events) {
                $events[] = 'success';
            })
            ->onError(function ($error, $code) use (&$events, &$capturedError) {
                $events[] = 'error';
                $capturedError = $code;
            })
            ->afterProcess(function ($res) use (&$events) {
                $events[] = 'after';
            })
            ->saveTo($destDir);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['before', 'error', 'after'], $events);
        $this->assertSame('ERR_SECURITY_DANGEROUS_EXTENSION', $capturedError);
    }

    public function testBatchUploadLifecycleHooks(): void
    {
        $img1 = $this->createTestImage(100, 100, 'jpeg');
        $img2 = $this->createTestImage(120, 120, 'jpeg');

        $up1 = new UploadedFile('p1.jpg', 'image/jpeg', filesize($img1), $img1, UPLOAD_ERR_OK);
        $up2 = new UploadedFile('p2.jpg', 'image/jpeg', filesize($img2), $img2, UPLOAD_ERR_OK);

        $destDir = $this->createTempDir();
        $events = [];

        $batch = Upload::files([$up1, $up2])
            ->beforeProcess(function ($collection) use (&$events) {
                $events[] = 'batch_before';
                $this->assertInstanceOf(FileCollection::class, $collection);
            })
            ->onSuccess(function ($res) use (&$events) {
                $events[] = 'batch_success';
                $this->assertInstanceOf(BatchResult::class, $res);
            })
            ->onError(function () use (&$events) {
                $events[] = 'batch_error';
            })
            ->afterProcess(function ($res) use (&$events) {
                $events[] = 'batch_after';
                $this->assertInstanceOf(BatchResult::class, $res);
            })
            ->saveTo($destDir)
            ->process();

        $this->assertTrue($batch->allSuccessful());
        $this->assertSame(['batch_before', 'batch_success', 'batch_after'], $events);
    }

    public function testVariantsSucceedEvenIfOriginalTmpIsMovedOrDeleted(): void
    {
        $imagePath = $this->createTestImage(200, 200, 'jpeg');
        $uploaded = new UploadedFile('photo.jpg', 'image/jpeg', filesize($imagePath), $imagePath, UPLOAD_ERR_OK);

        $destDir = $this->createTempDir();
        $thumbDir = $this->createTempDir();

        // Custom storage mô phỏng move_uploaded_file: lưu tệp vào đích và xóa nguồn tại tmpPath
        $storage = new class extends LocalStorage {
            public function save(UploadedFile $file, string $destinationDir, string $targetFilename, bool $overwrite = false, int $chmod = 0755): string
            {
                $dest = parent::save($file, $destinationDir, $targetFilename, $overwrite, $chmod);
                // Xóa nguồn tại tmpPath mô phỏng move_uploaded_file trong môi trường HTTP thật
                @unlink($file->getTmpPath());
                return $dest;
            }
        };

        $result = Upload::file($uploaded)
            ->storage($storage)
            ->destination($destDir)
            ->variant('thumb', function ($v) use ($thumbDir) {
                $v->resize(60, 60, mode: 'crop')
                  ->saveTo($thumbDir);
            })
            ->process();

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->hasVariant('thumb'));

        $thumb = $result->getVariant('thumb');
        $this->assertInstanceOf(ProcessedFile::class, $thumb);
        $this->assertFileExists($thumb->getSavedPath());
        $this->assertSame(60, $thumb->getWidth());
        $this->assertSame(60, $thumb->getHeight());
    }
}

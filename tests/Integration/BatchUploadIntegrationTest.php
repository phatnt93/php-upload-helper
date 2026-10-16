<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Integration;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Tests\TestCase;
use UploadHelper\Upload;

#[RequiresPhpExtension('gd')]
class BatchUploadIntegrationTest extends TestCase
{
    public function testBatchUploadAllSuccessful(): void
    {
        $destDir = $this->createTempDir();

        $t1 = $this->createTestImage(200, 200, 'jpg');
        $t2 = $this->createTestImage(300, 300, 'png');

        $fakeFiles = [
            'name'     => ['pic1.jpg', 'pic2.png'],
            'type'     => ['image/jpeg', 'image/png'],
            'tmp_name' => [$t1, $t2],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [filesize($t1), filesize($t2)],
        ];

        $batch = Upload::files($fakeFiles)
            ->allowedExtensions(['jpg', 'png'])
            ->maxSize('5M')
            ->slugName()
            ->prefix('gallery_')
            ->saveTo($destDir)
            ->process();

        $this->assertTrue($batch->allSuccessful());
        $this->assertFalse($batch->hasFailures());
        $this->assertCount(2, $batch->getSuccessful());
        $this->assertEmpty($batch->getFailed());

        foreach ($batch->getSuccessful() as $processed) {
            $this->assertFileExists($processed->getSavedPath());
            $this->assertStringStartsWith('gallery_', $processed->getFilename());
        }
    }

    public function testBatchUploadRollbackOnError(): void
    {
        $destDir = $this->createTempDir();

        // File 1: valid image
        $t1 = $this->createTestImage(200, 200, 'jpg');

        // File 2: invalid executable script
        $t2 = $this->createTempFile('<?php evil(); ?>', 'php');

        $fakeFiles = [
            'name'     => ['good.jpg', 'evil.php'],
            'type'     => ['image/jpeg', 'application/x-php'],
            'tmp_name' => [$t1, $t2],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [filesize($t1), 20],
        ];

        $batch = Upload::files($fakeFiles)
            ->allowedExtensions(['jpg'])
            ->saveTo($destDir)
            ->rollbackOnError(true)
            ->process();

        $this->assertFalse($batch->allSuccessful());
        $this->assertTrue($batch->hasFailures());
        $this->assertCount(1, $batch->getFailed());
        $this->assertEmpty($batch->getSuccessful());

        // Kiểm tra xem good.jpg đã được dọn dẹp sạch sẽ khỏi thư mục đích chưa
        $savedFilesOnDisk = glob($destDir . DIRECTORY_SEPARATOR . '*');
        $this->assertEmpty($savedFilesOnDisk, 'Tất cả các tệp đã lưu trước đó phải được hoàn tác xóa sạch khi bật rollbackOnError!');
    }

    public function testBatchUploadPartialModeKeepsSuccessful(): void
    {
        $destDir = $this->createTempDir();

        $t1 = $this->createTestImage(200, 200, 'jpg');
        $t2 = $this->createTempFile('not an image', 'txt');

        $fakeFiles = [
            'name'     => ['good.jpg', 'bad.txt'],
            'type'     => ['image/jpeg', 'text/plain'],
            'tmp_name' => [$t1, $t2],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [filesize($t1), 12],
        ];

        // Mặc định rollbackOnError = false (Partial Mode)
        $batch = Upload::files($fakeFiles)
            ->allowedExtensions(['jpg'])
            ->saveTo($destDir)
            ->rollbackOnError(false)
            ->process();

        $this->assertFalse($batch->allSuccessful());
        $this->assertCount(1, $batch->getSuccessful());
        $this->assertCount(1, $batch->getFailed());

        // File 1 vẫn tồn tại
        $this->assertFileExists($batch->getSuccessful()[0]->getSavedPath());
    }

    public function testEachCustomization(): void
    {
        $destDir = $this->createTempDir();

        $t1 = $this->createTestImage(100, 100, 'jpg');
        $t2 = $this->createTestImage(100, 100, 'jpg');

        $fakeFiles = [
            'name'     => ['a.jpg', 'b.jpg'],
            'type'     => ['image/jpeg', 'image/jpeg'],
            'tmp_name' => [$t1, $t2],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [filesize($t1), filesize($t2)],
        ];

        $batch = Upload::files($fakeFiles)
            ->saveTo($destDir)
            ->each(function ($builder, $index) {
                $builder->prefix("idx_{$index}_");
            })
            ->process();

        $this->assertTrue($batch->allSuccessful());
        $this->assertStringStartsWith('idx_0_', $batch->getSuccessful()[0]->getFilename());
        $this->assertStringStartsWith('idx_1_', $batch->getSuccessful()[1]->getFilename());
    }
}

<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Results;

use UploadHelper\Core\ProcessedFile;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Results\BatchResult;
use UploadHelper\Results\SingleResult;
use UploadHelper\Tests\TestCase;

class BatchResultTest extends TestCase
{
    public function testSingleResult(): void
    {
        $processed = new ProcessedFile(
            originalName: 'pic.jpg',
            savedPath: '/uploads/pic.jpg',
            filename: 'pic.jpg',
            mime: 'image/jpeg',
            extension: 'jpg',
            size: 2048,
            dimensions: ['width' => 100, 'height' => 100],
        );

        $result = new SingleResult(success: true, file: $processed);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('/uploads/pic.jpg', $result->getFilePath());
        $this->assertSame('pic.jpg', $result->getFilename());
        $this->assertSame('2 KB', $result->getFormattedSize());
        $this->assertEmpty($result->getErrors());

        // Failed single result
        $failed = new SingleResult(success: false, errorMessage: 'File too large', errorCode: 'ERR_LARGE');
        $this->assertFalse($failed->isSuccess());
        $this->assertSame('File too large', $failed->getErrorMessage());
        $this->assertSame('ERR_LARGE', $failed->getErrorCode());
        $this->assertNotEmpty($failed->getErrors());
    }

    public function testBatchResult(): void
    {
        $f1 = new ProcessedFile('1.jpg', '/path/1.jpg', '1.jpg', 'image/jpeg', 'jpg', 100);
        $rawFail = new UploadedFile('bad.exe', 'application/x-msdownload', 200, '/tmp/bad', fieldKey: 'user.docs');

        $batch = new BatchResult(
            successful: [$f1],
            failed: [
                ['file' => $rawFail, 'error' => 'File dangerous', 'code' => 'ERR_DANGEROUS'],
            ],
        );

        $this->assertFalse($batch->allSuccessful());
        $this->assertFalse($batch->isSuccess());
        $this->assertTrue($batch->hasFailures());
        $this->assertCount(1, $batch->getSuccessful());
        $this->assertCount(1, $batch->getFailed());
        $this->assertSame(2, $batch->count());

        $errors = $batch->getErrors();
        $this->assertArrayHasKey('user.docs', $errors);
        $this->assertSame('File dangerous', $errors['user.docs']);
    }
}

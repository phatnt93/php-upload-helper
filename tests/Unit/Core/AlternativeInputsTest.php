<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use UploadHelper\Exceptions\UploadException;
use UploadHelper\Tests\TestCase;
use UploadHelper\Upload;

class AlternativeInputsTest extends TestCase
{
    public function testFromPath(): void
    {
        $destDir = $this->createTempDir();
        $source = $this->createTestImage(100, 100, 'jpg');

        $result = Upload::fromPath($source, 'renamed_local.jpg')
            ->prefix('local_')
            ->saveTo($destDir);

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($result->getFilePath());
        $this->assertSame('local_renamed_local.jpg', $result->getFilename());
    }

    public function testFromInvalidPathThrowsException(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('does not exist');
        Upload::fromPath('/non/existent/path/file.txt');
    }

    public function testFromBase64(): void
    {
        $destDir = $this->createTempDir();

        // 1x1 transparent PNG in base64
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $result = Upload::fromBase64($base64, 'pixel.png')
            ->saveTo($destDir);

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($result->getFilePath());
        $this->assertSame('pixel.png', $result->getFilename());
        $this->assertSame('image/png', $result->getFile()?->getMime());
    }

    public function testFromInvalidBase64ThrowsException(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('Base64 payload is invalid');
        Upload::fromBase64('data:image/png;base64,!!!NOT_BASE64!!!');
    }

    public function testFromInvalidUrlThrowsException(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('remote URL is invalid');
        Upload::fromUrl('invalid-url-without-protocol');
    }

    public function testFromPsr7SingleFile(): void
    {
        $destDir = $this->createTempDir();

        $psr7Mock = new class {
            public function getClientFilename(): string { return 'document.pdf'; }
            public function getClientMediaType(): string { return 'application/pdf'; }
            public function getSize(): int { return 15; }
            public function getError(): int { return UPLOAD_ERR_OK; }
            public function getStream(): string { return 'fake pdf stream'; }
        };

        $result = Upload::fromPsr7($psr7Mock)
            ->allowedExtensions(['pdf'])
            ->saveTo($destDir);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('document.pdf', $result->getFilename());
        $this->assertFileExists($result->getFilePath());
    }

    public function testFromPsr7MultipleFiles(): void
    {
        $destDir = $this->createTempDir();

        $psr7_1 = new class {
            public function getClientFilename(): string { return 'f1.txt'; }
            public function getClientMediaType(): string { return 'text/plain'; }
            public function getSize(): int { return 4; }
            public function getError(): int { return UPLOAD_ERR_OK; }
            public function getStream(): string { return 'foo1'; }
        };

        $psr7_2 = new class {
            public function getClientFilename(): string { return 'f2.txt'; }
            public function getClientMediaType(): string { return 'text/plain'; }
            public function getSize(): int { return 4; }
            public function getError(): int { return UPLOAD_ERR_OK; }
            public function getStream(): string { return 'foo2'; }
        };

        $batch = Upload::fromPsr7([$psr7_1, $psr7_2])
            ->saveTo($destDir)
            ->process();

        $this->assertTrue($batch->allSuccessful());
        $this->assertCount(2, $batch->getSuccessful());
    }
}

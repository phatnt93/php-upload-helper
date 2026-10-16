<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Drivers\Gd\GdDriver;
use UploadHelper\Tests\TestCase;

#[RequiresPhpExtension('gd')]
class GdDriverTest extends TestCase
{
    private GdDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new GdDriver();
    }

    public function testLoadAndSaveJpeg(): void
    {
        $img = $this->createTestImage(200, 150, 'jpg', '#FF0000');
        $resource = $this->driver->load($img);

        $this->assertSame(200, $resource->getWidth());
        $this->assertSame(150, $resource->getHeight());

        $savePath = $this->createTempFile('', 'jpg');
        $saved = $this->driver->save($resource, $savePath, 'jpeg', 80);

        $this->assertTrue($saved);
        $this->assertFileExists($savePath);

        $savedInfo = getimagesize($savePath);
        $this->assertSame(200, $savedInfo[0]);
        $this->assertSame(150, $savedInfo[1]);
    }

    public function testResizeFitMode(): void
    {
        // 400x200 (ratio 2:1) resized to max 100x100
        $img = $this->createTestImage(400, 200, 'png');
        $resource = $this->driver->load($img);

        $resized = $this->driver->resize($resource, 100, 100, mode: 'fit');

        // Should fit inside 100x100 -> 100x50
        $this->assertSame(100, $resized->getWidth());
        $this->assertSame(50, $resized->getHeight());
    }

    public function testResizeCropMode(): void
    {
        $img = $this->createTestImage(400, 200, 'png');
        $resource = $this->driver->load($img);

        $cropped = $this->driver->resize($resource, 100, 100, mode: 'crop');

        $this->assertSame(100, $cropped->getWidth());
        $this->assertSame(100, $cropped->getHeight());
    }

    public function testResizePadMode(): void
    {
        $img = $this->createTestImage(400, 200, 'png');
        $resource = $this->driver->load($img);

        $padded = $this->driver->resize($resource, 100, 100, mode: 'pad', background: '#FFFFFF');

        $this->assertSame(100, $padded->getWidth());
        $this->assertSame(100, $padded->getHeight());
    }

    public function testResizeWithoutEnlarging(): void
    {
        // 50x50 requested to resize to 200x200 with allowEnlarge: false
        $img = $this->createTestImage(50, 50, 'jpg');
        $resource = $this->driver->load($img);

        $result = $this->driver->resize($resource, 200, 200, mode: 'fit', allowEnlarge: false);

        $this->assertSame(50, $result->getWidth());
        $this->assertSame(50, $result->getHeight());
    }

    public function testCrop(): void
    {
        $img = $this->createTestImage(200, 200, 'jpg');
        $resource = $this->driver->load($img);

        $cropped = $this->driver->crop($resource, 50, 60, 10, 10);

        $this->assertSame(50, $cropped->getWidth());
        $this->assertSame(60, $cropped->getHeight());
    }

    public function testRotateAndFlip(): void
    {
        $img = $this->createTestImage(200, 100, 'jpg');
        $resource = $this->driver->load($img);

        // Rotating 90 degrees flips width and height
        $rotated = $this->driver->rotate($resource, 90);
        $this->assertSame(100, $rotated->getWidth());
        $this->assertSame(200, $rotated->getHeight());

        // Flip
        $flipped = $this->driver->flip($resource, 'horizontal');
        $this->assertSame(200, $flipped->getWidth());
        $this->assertSame(100, $flipped->getHeight());
    }

    public function testWatermarkImage(): void
    {
        $baseImg = $this->createTestImage(300, 300, 'png', '#0000FF');
        $wmImg = $this->createTransparentPng(50, 50);

        $resource = $this->driver->load($baseImg);
        $watermarked = $this->driver->watermarkImage($resource, $wmImg, position: 'bottom-right', opacity: 70);

        $this->assertSame(300, $watermarked->getWidth());
        $this->assertSame(300, $watermarked->getHeight());
    }

    public function testFilters(): void
    {
        $img = $this->createTestImage(100, 100, 'jpg', '#33CC66');
        $resource = $this->driver->load($img);

        $this->driver->filter($resource, 'grayscale');
        $this->driver->filter($resource, 'brightness', ['level' => 10]);
        $this->driver->filter($resource, 'contrast', ['level' => 5]);
        $this->driver->filter($resource, 'blur');
        $this->driver->filter($resource, 'sharpen');

        $this->assertSame(100, $resource->getWidth());
    }
}

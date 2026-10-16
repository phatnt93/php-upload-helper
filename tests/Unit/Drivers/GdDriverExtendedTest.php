<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Contracts\ImageResourceInterface;
use UploadHelper\Drivers\Gd\GdDriver;
use UploadHelper\Drivers\Gd\GdResource;
use UploadHelper\Exceptions\ImageProcessingException;
use UploadHelper\Tests\TestCase;

#[RequiresPhpExtension('gd')]
final class GdDriverExtendedTest extends TestCase
{
    private GdDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new GdDriver();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formatProvider(): iterable
    {
        yield 'png' => ['png', 'png'];
        yield 'gif' => ['gif', 'gif'];
        yield 'webp' => ['webp', 'webp'];
        yield 'bmp' => ['bmp', 'bmp'];
        yield 'avif' => ['avif', 'avif'];
    }

    #[DataProvider('formatProvider')]
    public function testSaveAndReloadEachFormat(string $saveFormat, string $expectedFormat): void
    {
        $image = $this->driver->load($this->createTestImage(30, 20, 'png'));
        $target = $this->createTempFile('', $saveFormat);

        if (!$this->driver->save($image, $target, $saveFormat)) {
            $this->markTestSkipped("GD build cannot encode {$saveFormat}.");
        }

        $reloaded = $this->driver->load($target);
        $this->assertSame($expectedFormat, $reloaded->getFormat());
        $this->assertSame(30, $reloaded->getWidth());
        $this->assertSame(20, $reloaded->getHeight());
    }

    public function testUnknownSaveFormatFallsBackToJpeg(): void
    {
        $image = $this->driver->load($this->createTestImage(10, 10, 'png'));
        $target = $this->createTempFile('', 'out');

        $this->assertTrue($this->driver->save($image, $target, 'tiff'));
        $this->assertSame('jpeg', $this->driver->load($target)->getFormat());
    }

    public function testLoadMissingFileThrows(): void
    {
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessage('Cannot read image file');

        $this->driver->load(sys_get_temp_dir() . '/uh-does-not-exist.jpg');
    }

    public function testLoadGarbageThrows(): void
    {
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessage('Cannot decode image file');

        $this->driver->load($this->createTempFile('definitely not an image', 'jpg'));
    }

    public function testStretchResizeIgnoresAspectRatio(): void
    {
        $image = $this->driver->load($this->createTestImage(100, 50, 'jpg'));

        $resized = $this->driver->resize($image, 30, 90, 'stretch');

        $this->assertSame([30, 90], [$resized->getWidth(), $resized->getHeight()]);
    }

    public function testResizeToZeroSizeThrows(): void
    {
        $image = $this->driver->load($this->createTestImage(20, 20, 'jpg'));

        $this->expectException(ImageProcessingException::class);
        $this->driver->resize($image, 0, 10, 'stretch');
    }

    public function testCropToZeroSizeThrows(): void
    {
        $image = $this->driver->load($this->createTestImage(20, 20, 'jpg'));

        $this->expectException(ImageProcessingException::class);
        $this->driver->crop($image, 0, 0);
    }

    public function testFlipBothKeepsDimensions(): void
    {
        $image = $this->driver->load($this->createTestImage(40, 20, 'jpg'));

        $flipped = $this->driver->flip($image, 'both');

        $this->assertSame([40, 20], [$flipped->getWidth(), $flipped->getHeight()]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function positionProvider(): iterable
    {
        foreach (['top-left', 'top', 'top-right', 'center', 'bottom-left', 'bottom', 'bottom-right', 'unknown'] as $position) {
            yield $position => [$position];
        }
    }

    #[DataProvider('positionProvider')]
    public function testWatermarkImageAtEveryPosition(string $position): void
    {
        $image = $this->driver->load($this->createTestImage(60, 60, 'jpg', '#000000'));
        $mark = $this->createTestImage(10, 10, 'png', '#FFFFFF');

        $result = $this->driver->watermarkImage($image, $mark, $position, 60, 5, 5);

        $this->assertSame(60, $result->getWidth());
    }

    public function testFallbackTextWatermarkWithBackground(): void
    {
        $image = $this->driver->load($this->createTestImage(200, 60, 'jpg', '#000000'));

        $result = $this->driver->watermarkText($image, 'Hello', '/missing/font.ttf', 12, '#FFF', 'top-left', '#FF0000', 50);

        $handle = $result->getHandle();
        $this->assertInstanceOf(\GdImage::class, $handle);
        // The background box starts 2px before the text at the 10px offset.
        $this->assertNotSame(0, imagecolorat($handle, 9, 9));
    }

    public function testTrueTypeTextWatermark(): void
    {
        $font = self::findTrueTypeFont();
        if ($font === null || !function_exists('imagettfbbox')) {
            $this->markTestSkipped('No TrueType font available on this machine.');
        }

        $image = $this->driver->load($this->createTestImage(200, 80, 'jpg', '#000000'));

        $result = $this->driver->watermarkText($image, 'Hello', $font, 14, '#FFFFFF', 'center', '#336699', 40);

        $this->assertSame(200, $result->getWidth());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function filterProvider(): iterable
    {
        foreach (['greyscale', 'negative', 'blur', 'brightness', 'contrast', 'sharpen', 'unknown-filter'] as $filter) {
            yield $filter => [$filter];
        }
    }

    #[DataProvider('filterProvider')]
    public function testEveryFilterKeepsImageUsable(string $filter): void
    {
        $image = $this->driver->load($this->createTestImage(20, 20, 'jpg'));

        $result = $this->driver->filter($image, $filter, ['level' => 15]);

        $this->assertSame(20, $result->getWidth());
    }

    #[RequiresPhpExtension('exif')]
    public function testAutoOrientRotatesAccordingToExif(): void
    {
        $path = $this->createJpegWithOrientation(100, 50, 6);
        $image = $this->driver->load($path);

        $oriented = $this->driver->autoOrient($image, $path);

        $this->assertSame([50, 100], [$oriented->getWidth(), $oriented->getHeight()]);
    }

    public function testAutoOrientWithoutExifReturnsSameImage(): void
    {
        $path = $this->createTestImage(30, 10, 'jpg');
        $image = $this->driver->load($path);

        $this->assertSame($image, $this->driver->autoOrient($image, $path));
    }

    /**
     * @return iterable<string, array{callable(GdDriver, ImageResourceInterface): mixed}>
     */
    public static function invalidHandleProvider(): iterable
    {
        yield 'resize' => [static fn(GdDriver $d, ImageResourceInterface $i) => $d->resize($i, 10, 10)];
        yield 'crop' => [static fn(GdDriver $d, ImageResourceInterface $i) => $d->crop($i, 10, 10)];
        yield 'rotate' => [static fn(GdDriver $d, ImageResourceInterface $i) => $d->rotate($i, 90)];
        yield 'flip' => [static fn(GdDriver $d, ImageResourceInterface $i) => $d->flip($i, 'h')];
        yield 'watermarkImage' => [static fn(GdDriver $d, ImageResourceInterface $i) => $d->watermarkImage($i, 'x.png')];
        yield 'watermarkText' => [static fn(GdDriver $d, ImageResourceInterface $i) => $d->watermarkText($i, 'x', '', 10, '#000')];
        yield 'filter' => [static fn(GdDriver $d, ImageResourceInterface $i) => $d->filter($i, 'blur')];
    }

    /**
     * @param callable(GdDriver, ImageResourceInterface): mixed $operation
     */
    #[DataProvider('invalidHandleProvider')]
    public function testForeignHandleIsRejected(callable $operation): void
    {
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessage('Invalid image handle');

        $operation($this->driver, new ForeignResource());
    }

    public function testSavingForeignHandleReturnsFalse(): void
    {
        $this->assertFalse($this->driver->save(new ForeignResource(), $this->createTempFile('', 'jpg'), 'jpg'));
    }

    public function testDestroyedResourceReportsZeroSize(): void
    {
        $handle = imagecreatetruecolor(5, 5);
        $this->assertNotFalse($handle);
        $resource = new GdResource($handle, 'png');

        $resource->destroy();

        $this->assertSame(0, $resource->getWidth());
        $this->assertSame(0, $resource->getHeight());
    }

    private function createJpegWithOrientation(int $width, int $height, int $orientation): string
    {
        $jpeg = (string) file_get_contents($this->createTestImage($width, $height, 'jpg'));

        // Minimal little-endian TIFF block holding a single Orientation (0x0112) tag.
        $tiff = 'II' . pack('v', 42) . pack('V', 8)
            . pack('v', 1)
            . pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
            . pack('V', 0);
        $payload = "Exif\0\0" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        $path = $this->createTempFile('', 'jpg');
        file_put_contents($path, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));

        return $path;
    }

    private static function findTrueTypeFont(): ?string
    {
        $candidates = [
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

/**
 * Image resource coming from another driver, to exercise handle validation.
 */
final class ForeignResource implements ImageResourceInterface
{
    public function getWidth(): int
    {
        return 10;
    }

    public function getHeight(): int
    {
        return 10;
    }

    public function getFormat(): string
    {
        return 'png';
    }

    public function getHandle(): mixed
    {
        return 'not-a-gd-image';
    }

    public function destroy(): void {}
}

<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Actions;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Actions\Image\CropAction;
use UploadHelper\Actions\Image\FilterAction;
use UploadHelper\Actions\Image\FlipAction;
use UploadHelper\Actions\Image\FormatConvertAction;
use UploadHelper\Actions\Image\ResizeAction;
use UploadHelper\Actions\Image\RotateAction;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Tests\TestCase;

#[RequiresPhpExtension('gd')]
class ImageActionsTest extends TestCase
{
    public function testResizeAction(): void
    {
        $tmp = $this->createTestImage(400, 200, 'jpg');
        $file = new UploadedFile('photo.jpg', 'image/jpeg', filesize($tmp), $tmp);

        $action = new ResizeAction(100, 100, mode: 'fit');
        $action->execute($file, new ActionContext());

        $dims = $file->getImageDimensions();
        $this->assertNotNull($dims);
        $this->assertSame(100, $dims['width']);
        $this->assertSame(50, $dims['height']);
    }

    public function testCropAction(): void
    {
        $tmp = $this->createTestImage(200, 200, 'png');
        $file = new UploadedFile('photo.png', 'image/png', filesize($tmp), $tmp);

        $action = new CropAction(80, 60, 10, 10);
        $action->execute($file, new ActionContext());

        $dims = $file->getImageDimensions();
        $this->assertNotNull($dims);
        $this->assertSame(80, $dims['width']);
        $this->assertSame(60, $dims['height']);
    }

    public function testFormatConvertAction(): void
    {
        $tmp = $this->createTestImage(100, 100, 'jpg');
        $file = new UploadedFile('original.jpg', 'image/jpeg', filesize($tmp), $tmp);

        $action = new FormatConvertAction('webp', 80);
        $action->execute($file, new ActionContext());

        $this->assertSame('webp', $file->getCurrentExtension());
        $this->assertSame('original.webp', $file->getCurrentFilename());
        $this->assertSame('image/webp', $file->getDetectedMime());
    }

    public function testRotateAndFlipAction(): void
    {
        $tmp = $this->createTestImage(200, 100, 'jpg');
        $file = new UploadedFile('box.jpg', 'image/jpeg', filesize($tmp), $tmp);

        $rotate = new RotateAction(90);
        $rotate->execute($file, new ActionContext());

        $dims = $file->getImageDimensions();
        $this->assertNotNull($dims);
        $this->assertSame(100, $dims['width']);
        $this->assertSame(200, $dims['height']);

        $flip = new FlipAction('horizontal');
        $flip->execute($file, new ActionContext());
        $this->assertTrue($file->isOk());
    }

    public function testFilterAction(): void
    {
        $tmp = $this->createTestImage(100, 100, 'jpg');
        $file = new UploadedFile('scenery.jpg', 'image/jpeg', filesize($tmp), $tmp);

        $filter = new FilterAction('grayscale');
        $filter->execute($file, new ActionContext());

        $this->assertTrue($file->isOk());
    }

    public function testResizeNonSquareModesContainAndCover(): void
    {
        // 1. Landscape fixture (400x200) into 100x100
        $tmpLandscape = $this->createTestImage(400, 200, 'jpeg');

        // 'contain' should preserve ratio -> 100x50
        $fileContain = new UploadedFile('wide.jpg', 'image/jpeg', filesize($tmpLandscape), $tmpLandscape);
        (new ResizeAction(100, 100, mode: 'contain'))->execute($fileContain, new ActionContext());
        $dimsContain = $fileContain->getImageDimensions();
        $this->assertNotNull($dimsContain);
        $this->assertSame(100, $dimsContain['width']);
        $this->assertSame(50, $dimsContain['height']);

        // 'fit' should match 'contain' -> 100x50
        $fileFit = new UploadedFile('wide.jpg', 'image/jpeg', filesize($tmpLandscape), $tmpLandscape);
        (new ResizeAction(100, 100, mode: 'fit'))->execute($fileFit, new ActionContext());
        $dimsFit = $fileFit->getImageDimensions();
        $this->assertNotNull($dimsFit);
        $this->assertSame(100, $dimsFit['width']);
        $this->assertSame(50, $dimsFit['height']);

        // 'cover' should scale and center-crop to fill box -> 100x100
        $fileCover = new UploadedFile('wide.jpg', 'image/jpeg', filesize($tmpLandscape), $tmpLandscape);
        (new ResizeAction(100, 100, mode: 'cover'))->execute($fileCover, new ActionContext());
        $dimsCover = $fileCover->getImageDimensions();
        $this->assertNotNull($dimsCover);
        $this->assertSame(100, $dimsCover['width']);
        $this->assertSame(100, $dimsCover['height']);

        // 'crop' should match 'cover' -> 100x100
        $fileCrop = new UploadedFile('wide.jpg', 'image/jpeg', filesize($tmpLandscape), $tmpLandscape);
        (new ResizeAction(100, 100, mode: 'crop'))->execute($fileCrop, new ActionContext());
        $dimsCrop = $fileCrop->getImageDimensions();
        $this->assertNotNull($dimsCrop);
        $this->assertSame(100, $dimsCrop['width']);
        $this->assertSame(100, $dimsCrop['height']);

        // 2. Portrait fixture (200x400) into 100x100
        $tmpPortrait = $this->createTestImage(200, 400, 'jpeg');

        // 'contain' on portrait -> 50x100
        $filePortContain = new UploadedFile('tall.jpg', 'image/jpeg', filesize($tmpPortrait), $tmpPortrait);
        (new ResizeAction(100, 100, mode: 'contain'))->execute($filePortContain, new ActionContext());
        $dimsPortContain = $filePortContain->getImageDimensions();
        $this->assertNotNull($dimsPortContain);
        $this->assertSame(50, $dimsPortContain['width']);
        $this->assertSame(100, $dimsPortContain['height']);

        // 'cover' on portrait -> 100x100
        $filePortCover = new UploadedFile('tall.jpg', 'image/jpeg', filesize($tmpPortrait), $tmpPortrait);
        (new ResizeAction(100, 100, mode: 'cover'))->execute($filePortCover, new ActionContext());
        $dimsPortCover = $filePortCover->getImageDimensions();
        $this->assertNotNull($dimsPortCover);
        $this->assertSame(100, $dimsPortCover['width']);
        $this->assertSame(100, $dimsPortCover['height']);
    }
}

<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Actions;

use UploadHelper\Actions\Image\CropAction;
use UploadHelper\Actions\Image\FilterAction;
use UploadHelper\Actions\Image\FlipAction;
use UploadHelper\Actions\Image\FormatConvertAction;
use UploadHelper\Actions\Image\ResizeAction;
use UploadHelper\Actions\Image\RotateAction;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Tests\TestCase;

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
}

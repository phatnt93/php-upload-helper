<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Actions;

use UploadHelper\Actions\File\RenameAction;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Tests\TestCase;

class RenameActionTest extends TestCase
{
    public function testSlugifyVietnamese(): void
    {
        $file = new UploadedFile('Ảnh Đại Diện Đẹp & Sang Trọng.PNG', 'image/png', 100, '/tmp/x');
        $action = (new RenameAction())->slug();

        $action->execute($file, new ActionContext());

        $this->assertSame('anh-dai-dien-dep-sang-trong', $file->getCurrentName());
        $this->assertSame('png', $file->getCurrentExtension());
        $this->assertSame('anh-dai-dien-dep-sang-trong.png', $file->getCurrentFilename());
    }

    public function testUuidNaming(): void
    {
        $file = new UploadedFile('test.jpg', 'image/jpeg', 100, '/tmp/x');
        $action = (new RenameAction())->uuid();

        $action->execute($file, new ActionContext());

        $uuid = $file->getCurrentName();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
    }

    public function testRandomNaming(): void
    {
        $file = new UploadedFile('test.jpg', 'image/jpeg', 100, '/tmp/x');
        $action = (new RenameAction())->random(20);

        $action->execute($file, new ActionContext());

        $random = $file->getCurrentName();
        $this->assertSame(20, strlen($random));
    }

    public function testPrefixAndSuffix(): void
    {
        $file = new UploadedFile('banner.jpg', 'image/jpeg', 100, '/tmp/x');
        $action = (new RenameAction())->prefix('thumb_')->suffix('_large');

        $action->execute($file, new ActionContext());

        $this->assertSame('thumb_banner_large', $file->getCurrentName());
        $this->assertSame('thumb_banner_large.jpg', $file->getCurrentFilename());
    }

    public function testCustomName(): void
    {
        $file = new UploadedFile('original.jpg', 'image/jpeg', 100, '/tmp/x');
        $action = (new RenameAction())->custom('fixed_name');

        $action->execute($file, new ActionContext());

        $this->assertSame('fixed_name', $file->getCurrentName());

        // Closure
        $action2 = (new RenameAction())->custom(fn(UploadedFile $f) => 'custom_' . $f->getSize());
        $action2->execute($file, new ActionContext());
        $this->assertSame('custom_100', $file->getCurrentName());
    }
}

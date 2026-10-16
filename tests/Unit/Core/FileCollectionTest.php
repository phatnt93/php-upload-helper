<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use UploadHelper\Core\FileCollection;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Tests\TestCase;

class FileCollectionTest extends TestCase
{
    public function testCollectionOperations(): void
    {
        $f1 = new UploadedFile('a.jpg', 'image/jpeg', 100, '/tmp/a');
        $f2 = new UploadedFile('b.png', 'image/png', 200, '/tmp/b');

        $collection = new FileCollection([$f1, $f2]);

        $this->assertCount(2, $collection);
        $this->assertFalse($collection->isEmpty());
        $this->assertTrue($collection->isNotEmpty());
        $this->assertSame($f1, $collection->first());
        $this->assertSame($f2, $collection->last());

        // Each
        $names = [];
        $collection->each(function (UploadedFile $file, int $index) use (&$names) {
            $names[] = $file->getOriginalName();
        });
        $this->assertSame(['a.jpg', 'b.png'], $names);

        // Map
        $mapped = $collection->map(fn(UploadedFile $f) => $f->getSize());
        $this->assertSame([100, 200], $mapped);

        // Filter
        $filtered = $collection->filter(fn(UploadedFile $f) => $f->getSize() > 150);
        $this->assertCount(1, $filtered);
        $this->assertSame('b.png', $filtered->first()?->getOriginalName());

        // Add
        $f3 = new UploadedFile('c.webp', 'image/webp', 300, '/tmp/c');
        $collection->add($f3);
        $this->assertCount(3, $collection);
        $this->assertSame($f3, $collection->last());
    }

    public function testEmptyCollection(): void
    {
        $collection = new FileCollection([]);
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
        $this->assertFalse($collection->isNotEmpty());
        $this->assertNull($collection->first());
        $this->assertNull($collection->last());
    }
}

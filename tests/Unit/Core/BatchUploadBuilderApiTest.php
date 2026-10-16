<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Core\BatchUploadBuilder;
use UploadHelper\Core\FileCollection;
use UploadHelper\Core\UploadBuilder;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;
use UploadHelper\Results\BatchResult;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Tests\TestCase;

#[RequiresPhpExtension('gd')]
final class BatchUploadBuilderApiTest extends TestCase
{
    public function testAllSharedOptionsAreAppliedToEveryFile(): void
    {
        $dest = $this->createTempDir();
        $batch = new BatchUploadBuilder([$this->imageFile('one.jpg'), $this->imageFile('two.jpg')]);

        $this->assertSame(2, $batch->count());
        $this->assertSame(2, $batch->getCollection()->count());

        $calls = [];
        $result = $batch
            ->allowedExtensions(['jpg'])
            ->allowedMimes(['image/jpeg'])
            ->maxSize('5M')
            ->minSize(10)
            ->dimensionBetween(50, 50, 1000, 1000)
            ->maxPixels(10_000_000)
            ->maxImageMemory('256M')
            ->blockPdfJavaScript()
            ->blockOfficeMacros()
            ->scanner(new CleanScanner())
            ->imageDriver(new GdDriver())
            ->storage(new LocalStorage())
            ->hardenDestination()
            ->stripGps()
            ->resize(100, 100, 'crop')
            ->crop(80, 80)
            ->rotate(180)
            ->grayscale()
            ->toJpeg(80)
            ->slugName()
            ->prefix('b_')
            ->suffix('_x')
            ->overwrite()
            ->variant('thumb', static fn(UploadBuilder $b) => $b->resize(20, 20))
            ->each(static function (UploadBuilder $b, int $index) use (&$calls): void {
                $calls[] = "each{$index}";
            })
            ->beforeProcess(static function (FileCollection $c) use (&$calls): void {
                $calls[] = 'before';
            })
            ->onSuccess(static function (BatchResult $r) use (&$calls): void {
                $calls[] = 'success';
            })
            ->afterProcess(static function (BatchResult $r) use (&$calls): void {
                $calls[] = 'after';
            })
            ->saveTo($dest)
            ->process();

        $this->assertTrue($result->allSuccessful(), json_encode($result->getErrors(), JSON_THROW_ON_ERROR));
        $this->assertSame(['before', 'each0', 'each1', 'success', 'after'], $calls);

        foreach ($result->getSuccessful() as $file) {
            $this->assertMatchesRegularExpression('/^b_(one|two)_x\.jpeg$/D', $file->getFilename());
            $this->assertSame(['width' => 80, 'height' => 80], $file->getDimensions());
        }
        $this->assertFileExists($dest . DIRECTORY_SEPARATOR . '.htaccess');
    }

    /**
     * @return iterable<string, array{callable(BatchUploadBuilder): BatchUploadBuilder, string}>
     */
    public static function namingProvider(): iterable
    {
        yield 'uuid' => [static fn(BatchUploadBuilder $b) => $b->uuidName(), '/^[0-9a-f-]{36}\.jpg$/D'];
        yield 'random' => [static fn(BatchUploadBuilder $b) => $b->randomName(8), '/^[0-9a-f]{8}\.jpg$/D'];
        yield 'timestamp' => [static fn(BatchUploadBuilder $b) => $b->timestampName(), '/^\d{8}_\d{6}_[0-9a-f]{4}\.jpg$/D'];
        yield 'hash' => [static fn(BatchUploadBuilder $b) => $b->hashName('md5', 10), '/^[0-9a-f]{10}\.jpg$/D'];
        yield 'sha256' => [static fn(BatchUploadBuilder $b) => $b->sha256Name(12), '/^[0-9a-f]{12}\.jpg$/D'];
    }

    /**
     * @param callable(BatchUploadBuilder): BatchUploadBuilder $configure
     */
    #[DataProvider('namingProvider')]
    public function testNamingStrategies(callable $configure, string $pattern): void
    {
        $batch = new BatchUploadBuilder(new FileCollection([$this->imageFile('photo.jpg')]));

        $result = $configure($batch)->saveTo($this->createTempDir())->process();

        $this->assertTrue($result->allSuccessful());
        $this->assertMatchesRegularExpression($pattern, $result->getSuccessful()[0]->getFilename());
    }

    public function testConversionToWebpAndAutoIncrement(): void
    {
        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('GD was built without WebP support.');
        }

        $dest = $this->createTempDir();
        file_put_contents($dest . DIRECTORY_SEPARATOR . 'photo.webp', 'existing');

        $result = (new BatchUploadBuilder([$this->imageFile('photo.jpg')]))
            ->toWebp(70)
            ->autoIncrementDuplicate()
            ->saveTo($dest)
            ->process();

        $this->assertTrue($result->allSuccessful(), json_encode($result->getErrors(), JSON_THROW_ON_ERROR));
        $this->assertSame('photo_1.webp', $result->getSuccessful()[0]->getFilename());
    }

    public function testFailuresTriggerErrorHookAndRecordCodes(): void
    {
        $errorHookCalled = false;
        $bad = new UploadedFile('bad.txt', 'text/plain', 3, $this->createTempFile('abc', 'txt'));

        $result = (new BatchUploadBuilder([$this->imageFile('ok.jpg'), $bad]))
            ->allowedExtensions(['jpg'])
            ->onError(static function (BatchResult $r) use (&$errorHookCalled): void {
                $errorHookCalled = true;
            })
            ->process();

        $this->assertTrue($errorHookCalled);
        $this->assertCount(1, $result->getSuccessful());
        $this->assertSame('ERR_EXTENSION_NOT_ALLOWED', $result->getFailed()[0]['code']);
    }

    private function imageFile(string $name): UploadedFile
    {
        $path = $this->createTestImage(200, 150, 'jpg');

        return new UploadedFile($name, 'image/jpeg', (int) filesize($path), $path);
    }
}

<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Contracts\ScannerInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadBuilder;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;
use UploadHelper\I18n\Translator;
use UploadHelper\Results\SingleResult;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Tests\TestCase;
use UploadHelper\Upload;

#[RequiresPhpExtension('gd')]
final class UploadBuilderApiTest extends TestCase
{
    public function testProcessWithoutFileReturnsNoFileError(): void
    {
        $errors = [];
        $result = (new UploadBuilder())
            ->onError(static function (string $message, string $code) use (&$errors): void {
                $errors[] = $code;
            })
            ->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_NO_FILE', $result->getErrorCode());
        $this->assertSame(['ERR_NO_FILE'], $errors);
    }

    public function testFullImagePipelineProducesExpectedOutput(): void
    {
        $dest = $this->createTempDir();
        $source = $this->createTestImage(400, 300, 'jpg');
        $watermark = $this->createTestImage(20, 20, 'png', '#FF0000');

        $builder = Upload::fromPath($source, 'Holiday Photo.jpg');
        $this->assertInstanceOf(UploadedFile::class, $builder->getFile());

        $result = $builder
            ->allowedExtensions(['jpg', 'jpeg'])
            ->allowedMimes(['image/jpeg'])
            ->maxSize('5M')
            ->minSize(10)
            ->dimensionBetween(100, 100, 1000, 1000)
            ->sanitizeSvg()
            ->maxPixels(10_000_000)
            ->maxImageMemory('256M')
            ->blockPdfJavaScript()
            ->blockOfficeMacros()
            ->scanner(new CleanScanner())
            ->imageDriver(new GdDriver())
            ->storage(new LocalStorage())
            ->hardenDestination()
            ->withoutEnlarging()
            ->autoOrient()
            ->stripGps()
            ->resize(200, 200, 'pad', '#000000')
            ->crop(150, 150, 10, 10)
            ->rotate(90)
            ->flipHorizontal()
            ->flipVertical()
            ->grayscale()
            ->brightness(10)
            ->contrast(-10)
            ->blur()
            ->sharpen()
            ->watermarkImage($watermark, 'top-left', 50)
            ->watermarkText('(c) test', '', 12, '#FFFFFF', 'center', '#000000', 50)
            ->toPng()
            ->slugName()
            ->prefix('p_')
            ->suffix('_s')
            ->saveTo($dest);

        $this->assertTrue($result->isSuccess(), (string) $result->getErrorMessage());
        $file = $result->getFile();
        $this->assertNotNull($file);
        $this->assertSame('p_holiday-photo_s.png', $file->getFilename());
        $this->assertSame('png', $file->getExtension());
        $this->assertSame(['width' => 150, 'height' => 150], $file->getDimensions());
        $this->assertFileExists($dest . DIRECTORY_SEPARATOR . '.htaccess');
    }

    /**
     * @return iterable<string, array{callable(UploadBuilder): UploadBuilder, string}>
     */
    public static function namingStrategyProvider(): iterable
    {
        yield 'uuid' => [static fn(UploadBuilder $b) => $b->uuidName(), '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.jpg$/D'];
        yield 'random' => [static fn(UploadBuilder $b) => $b->randomName(10), '/^[0-9a-f]{10}\.jpg$/D'];
        yield 'timestamp' => [static fn(UploadBuilder $b) => $b->timestampName(), '/^\d{8}_\d{6}_[0-9a-f]{4}\.jpg$/D'];
        yield 'hash' => [static fn(UploadBuilder $b) => $b->hashName('md5', 12), '/^[0-9a-f]{12}\.jpg$/D'];
        yield 'sha256' => [static fn(UploadBuilder $b) => $b->sha256Name(16), '/^[0-9a-f]{16}\.jpg$/D'];
        yield 'custom string' => [static fn(UploadBuilder $b) => $b->name('avatar'), '/^avatar\.jpg$/D'];
        yield 'custom closure' => [static fn(UploadBuilder $b) => $b->name(static fn(UploadedFile $f): string => 'from-closure'), '/^from-closure\.jpg$/D'];
    }

    /**
     * @param callable(UploadBuilder): UploadBuilder $configure
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('namingStrategyProvider')]
    public function testNamingStrategies(callable $configure, string $pattern): void
    {
        $dest = $this->createTempDir();
        $builder = Upload::fromPath($this->createTestImage(50, 50, 'jpg'), 'photo.jpg');

        $result = $configure($builder)->saveTo($dest);

        $this->assertTrue($result->isSuccess(), (string) $result->getErrorMessage());
        $this->assertMatchesRegularExpression($pattern, (string) $result->getFilename());
    }

    public function testFormatConversionsChangeExtension(): void
    {
        $dest = $this->createTempDir();

        $jpeg = Upload::fromPath($this->createTestImage(40, 40, 'png'), 'a.png')->toJpeg(80)->saveTo($dest);
        $this->assertSame('jpeg', $jpeg->getFile()?->getExtension());

        if (function_exists('imagewebp')) {
            $webp = Upload::fromPath($this->createTestImage(40, 40, 'png'), 'b.png')->toWebp(70)->saveTo($dest);
            $this->assertSame('webp', $webp->getFile()?->getExtension());
        }

        // AVIF support depends on the GD build; the call itself must never crash.
        $avif = Upload::fromPath($this->createTestImage(40, 40, 'png'), 'c.png')->toAvif()->process();
        $this->assertTrue($avif->isSuccess());
    }

    public function testAutoIncrementKeepsExistingFile(): void
    {
        $dest = $this->createTempDir();
        file_put_contents($dest . DIRECTORY_SEPARATOR . 'photo.jpg', 'existing');

        $result = Upload::fromPath($this->createTestImage(30, 30, 'jpg'), 'photo.jpg')
            ->autoIncrementDuplicate()
            ->saveTo($dest);

        $this->assertTrue($result->isSuccess(), (string) $result->getErrorMessage());
        $this->assertSame('photo_1.jpg', $result->getFilename());
        $this->assertSame('existing', file_get_contents($dest . DIRECTORY_SEPARATOR . 'photo.jpg'));
    }

    public function testExistingFileWithoutOverwriteFailsAndOverwriteReplaces(): void
    {
        $dest = $this->createTempDir();
        file_put_contents($dest . DIRECTORY_SEPARATOR . 'photo.jpg', 'existing');

        $blocked = Upload::fromPath($this->createTestImage(30, 30, 'jpg'), 'photo.jpg')->overwrite(false)->saveTo($dest);
        $this->assertFalse($blocked->isSuccess());
        $this->assertSame('ERR_FILE_EXISTS', $blocked->getErrorCode());

        $replaced = Upload::fromPath($this->createTestImage(30, 30, 'jpg'), 'photo.jpg')->overwrite()->saveTo($dest);
        $this->assertTrue($replaced->isSuccess(), (string) $replaced->getErrorMessage());
        $this->assertNotSame('existing', file_get_contents($dest . DIRECTORY_SEPARATOR . 'photo.jpg'));
    }

    public function testCustomActionAndHooksRunInOrder(): void
    {
        $calls = [];
        $action = new class ($calls) implements ActionInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls) {}

            public function getName(): string
            {
                return 'record';
            }

            public function execute(UploadedFile $file, ActionContext $context): UploadedFile
            {
                $this->calls[] = 'action';
                return $file;
            }
        };

        $result = Upload::fromPath($this->createTestImage(30, 30, 'jpg'), 'x.jpg')
            ->addAction($action)
            ->destination($this->createTempDir())
            ->beforeProcess(static function () use (&$calls): void {
                $calls[] = 'before';
            })
            ->onSuccess(static function () use (&$calls): void {
                $calls[] = 'success';
            })
            ->afterProcess(static function (SingleResult $r) use (&$calls): void {
                $calls[] = 'after';
            })
            ->process();

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['before', 'action', 'success', 'after'], $calls);
    }

    public function testValidationFailureIsLocalizedAndKeepsEnglishReason(): void
    {
        Translator::setLocale('vi');

        $result = Upload::fromPath($this->createTempFile(str_repeat('A', 2048), 'txt'), 'big.txt')
            ->maxSize('1K')
            ->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_FILE_TOO_LARGE', $result->getErrorCode());
        $this->assertStringContainsString('vượt quá', (string) $result->getErrorMessage());
        $this->assertStringContainsString('exceeds', $result->getErrors()['context']['reason']);
    }

    public function testUnexpectedErrorIsReportedAsInternal(): void
    {
        $result = Upload::fromPath($this->createTestImage(30, 30, 'jpg'), 'x.jpg')
            ->addAction(new class implements ActionInterface {
                public function getName(): string
                {
                    return 'boom';
                }

                public function execute(UploadedFile $file, ActionContext $context): UploadedFile
                {
                    throw new \RuntimeException('secret /internal/path');
                }
            })
            ->process();

        $this->assertSame('ERR_INTERNAL', $result->getErrorCode());
        $this->assertStringNotContainsString('/internal/path', (string) $result->getErrorMessage());
        $this->assertSame('secret /internal/path', $result->getErrors()['context']['reason']);
    }

    public function testVariantWithoutDestinationIsProcessedInPlace(): void
    {
        $result = Upload::fromPath($this->createTestImage(120, 120, 'jpg'), 'v.jpg')
            ->variant('thumb', static fn(UploadBuilder $b) => $b->resize(40, 40))
            ->process();

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->hasVariant('thumb'));
        $this->assertSame(40, $result->getVariant('thumb')?->getWidth());
        $this->assertSame([], $result->getVariantErrors());
    }
}

/**
 * Scanner stub that always reports a clean file.
 */
final class CleanScanner implements ScannerInterface
{
    public function scan(string $filePath): bool
    {
        return true;
    }

    public function getThreatName(): ?string
    {
        return null;
    }
}

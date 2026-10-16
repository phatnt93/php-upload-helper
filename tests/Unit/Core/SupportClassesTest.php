<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Actions\File\AutoIncrementAction;
use UploadHelper\Actions\File\RenameAction;
use UploadHelper\Actions\File\SaveAction;
use UploadHelper\Actions\Image\AutoOrientAction;
use UploadHelper\Actions\Image\CropAction;
use UploadHelper\Actions\Image\FilterAction;
use UploadHelper\Actions\Image\FlipAction;
use UploadHelper\Actions\Image\FormatConvertAction;
use UploadHelper\Actions\Image\ResizeAction;
use UploadHelper\Actions\Image\RotateAction;
use UploadHelper\Actions\Image\StripExifAction;
use UploadHelper\Actions\Image\WatermarkImageAction;
use UploadHelper\Actions\Image\WatermarkTextAction;
use UploadHelper\Contracts\ActionInterface;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\ActionPipeline;
use UploadHelper\Core\FileCollection;
use UploadHelper\Core\ProcessedFile;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Core\UploadNormalizer;
use UploadHelper\Exceptions\ImageProcessingException;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\I18n\Translator;
use UploadHelper\Remote\UrlDownloader;
use UploadHelper\Results\SingleResult;
use UploadHelper\Storage\DirectoryHardener;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Storage\RollbackManager;
use UploadHelper\Tests\TestCase;
use UploadHelper\Tests\Unit\Remote\RecordingUrlDownloader;
use UploadHelper\Upload;

final class SupportClassesTest extends TestCase
{
    /**
     * @return iterable<string, array{ActionInterface, string}>
     */
    public static function actionNameProvider(): iterable
    {
        yield 'auto increment' => [new AutoIncrementAction(), 'auto_increment'];
        yield 'rename' => [new RenameAction(), 'rename'];
        yield 'save' => [new SaveAction('/tmp'), 'save'];
        yield 'auto orient' => [new AutoOrientAction(), 'auto_orient'];
        yield 'crop' => [new CropAction(10, 10), 'crop'];
        yield 'filter' => [new FilterAction('blur'), 'filter'];
        yield 'flip' => [new FlipAction('horizontal'), 'flip'];
        yield 'convert' => [new FormatConvertAction('png'), 'convert'];
        yield 'resize' => [new ResizeAction(10, 10), 'resize'];
        yield 'rotate' => [new RotateAction(90), 'rotate'];
        yield 'strip exif' => [new StripExifAction(), 'strip_exif'];
        yield 'watermark image' => [new WatermarkImageAction('wm.png'), 'watermark_image'];
        yield 'watermark text' => [new WatermarkTextAction('x'), 'watermark_text'];
    }

    #[DataProvider('actionNameProvider')]
    public function testActionNames(ActionInterface $action, string $expected): void
    {
        $this->assertSame($expected, $action->getName());
    }

    /**
     * @return iterable<string, array{ActionInterface, string}>
     */
    public static function imageActionProvider(): iterable
    {
        foreach (self::actionNameProvider() as $label => $case) {
            if (!in_array($case[1], ['auto_increment', 'rename', 'save'], true)) {
                yield $label => $case;
            }
        }
    }

    #[DataProvider('imageActionProvider')]
    public function testImageActionsIgnoreNonImages(ActionInterface $action): void
    {
        $file = new UploadedFile('notes.txt', 'text/plain', 4, $this->createTempFile('text', 'txt'));

        $this->assertSame($file->getCurrentPath(), $action->execute($file, new ActionContext())->getCurrentPath());
    }

    public function testSaveActionWithoutDestinationThrows(): void
    {
        $this->expectException(UploadException::class);

        $file = new UploadedFile('a.txt', 'text/plain', 1, $this->createTempFile('a', 'txt'));
        (new SaveAction(''))->execute($file, new ActionContext());
    }

    public function testSaveActionAutoIncrementsWhenTargetExists(): void
    {
        $dir = $this->createTempDir();
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'a.txt', 'old');
        $file = new UploadedFile('a.txt', 'text/plain', 3, $this->createTempFile('new', 'txt'));
        $context = (new ActionContext(storage: new LocalStorage()))->setAutoIncrement(true);

        $saved = (new SaveAction($dir))->execute($file, $context);

        $this->assertSame('a_1.txt', basename($saved->getCurrentPath()));
    }

    public function testAutoIncrementActionWithoutDirectoryIsNoop(): void
    {
        $file = new UploadedFile('a.txt', 'text/plain', 1, $this->createTempFile('a', 'txt'));

        $this->assertSame('a', (new AutoIncrementAction())->execute($file, new ActionContext())->getCurrentName());
    }

    #[RequiresPhpExtension('gd')]
    public function testImageActionsReturnFileWhenDriverIsUnavailable(): void
    {
        $path = $this->createTestImage(20, 20, 'jpg');
        $context = new ActionContext(imageDriver: new UnavailableDriver());

        foreach ([new AutoOrientAction(), new StripExifAction(), new WatermarkTextAction('x'), new WatermarkImageAction($path)] as $action) {
            $file = new UploadedFile('a.jpg', 'image/jpeg', (int) filesize($path), $path);
            $this->assertSame($path, $action->execute($file, $context)->getCurrentPath(), $action->getName());
        }
    }

    public function testRenameRandomRejectsNonPositiveLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RenameAction::generateRandom(0);
    }

    public function testRenameTimestampStrategy(): void
    {
        $file = new UploadedFile('a.txt', 'text/plain', 1, $this->createTempFile('a', 'txt'));

        $renamed = (new RenameAction())->timestamp()->execute($file, new ActionContext());

        $this->assertMatchesRegularExpression('/^\d{8}_\d{6}_[0-9a-f]{4}$/D', $renamed->getCurrentName());
    }

    public function testActionContextAndPipelineAccessors(): void
    {
        $context = (new ActionContext())
            ->setDestinationDir('/uploads')
            ->setChmod(0700)
            ->set('user_id', 42);

        $this->assertSame('/uploads', $context->getDestinationDir());
        $this->assertSame(0700, $context->getChmod());
        $this->assertSame(42, $context->get('user_id'));
        $this->assertSame('fallback', $context->get('missing', 'fallback'));

        $action = new RotateAction(90);
        $this->assertSame([$action], (new ActionPipeline([$action]))->getActions());
    }

    public function testFileCollectionAll(): void
    {
        $file = new UploadedFile('a.txt', 'text/plain', 1, '');

        $this->assertSame([$file], (new FileCollection([$file]))->all());
    }

    public function testProcessedFileAccessors(): void
    {
        $path = $this->createTempFile('hello', 'txt');
        $file = new ProcessedFile('Orig.txt', $path, 'orig.txt', 'text/plain', 'txt', 5, null, 'docs.0');

        $this->assertSame('Orig.txt', $file->getOriginalName());
        $this->assertSame($path, $file->getSavedPath());
        $this->assertSame(5, $file->getSize());
        $this->assertNull($file->getDimensions());
        $this->assertSame('docs.0', $file->getFieldKey());
        $this->assertNull($file->getUrl());
        $this->assertSame('https://cdn/x', $file->setUrl('https://cdn/x')->getUrl());
        $this->assertSame(md5('hello'), $file->getMd5());

        $missing = new ProcessedFile('a', '/missing/file', 'a', 'x', 'x', 0);
        $this->assertSame('', $missing->getHash());
    }

    public function testSingleResultAccessors(): void
    {
        $original = new UploadedFile('a.txt', 'text/plain', 1, '');
        $processed = (new ProcessedFile('a.txt', '/x/a.txt', 'a.txt', 'text/plain', 'txt', 1))->setUrl('https://cdn/a.txt');

        $result = new SingleResult(true, $processed, $original, variantErrors: ['thumb' => 'failed']);

        $this->assertSame($original, $result->getOriginalFile());
        $this->assertSame('https://cdn/a.txt', $result->getUrl());
        $this->assertSame(['thumb' => 'failed'], $result->getVariantErrors());
    }

    public function testUploadedFileFallbacks(): void
    {
        $path = $this->createTempFile('12345', 'txt');

        $noExtension = new UploadedFile('README', 'text/plain', 0, $path);
        $this->assertSame('README', $noExtension->getCurrentFilename());
        $this->assertSame(5, $noExtension->getSize());

        $missing = new UploadedFile('gone.jpg', 'image/jpeg', 0, '/missing/gone.jpg');
        $this->assertSame('image/jpeg', $missing->getDetectedMime());
        $this->assertSame(0, $missing->getSize());
        $this->assertSame('', $missing->getHash());
        $this->assertNull($missing->getImageDimensions());

        $fakeImage = new UploadedFile('fake.png', 'image/png', 4, $this->createTempFile('nope', 'png'));
        $this->assertNull($fakeImage->getImageDimensions());
        $this->assertNull($fakeImage->getImageDimensions(), 'Result is cached.');
    }

    public function testNormalizerHandlesScalarsAndNestedLists(): void
    {
        $this->assertSame([], UploadNormalizer::normalize(42));

        $first = new UploadedFile('a.txt', 'text/plain', 1, '');
        $second = new UploadedFile('b.txt', 'text/plain', 1, '');

        $this->assertSame([$first, $second], UploadNormalizer::normalize([[$first], [[$second]], 'ignored']));
    }

    public function testLocalStorageErrorsAndHelpers(): void
    {
        $storage = (new LocalStorage())->setHardenDirectory(false);
        $dir = $this->createTempDir();

        $this->assertFalse($storage->delete($dir . '/missing.txt'));
        $this->assertFalse($storage->exists($dir . '/missing.txt'));
        $this->assertSame('fresh.txt', $storage->resolveUniqueFilename($dir, 'fresh.txt'));

        $this->expectException(UploadException::class);
        $storage->save(new UploadedFile('x.txt', 'text/plain', 1, $dir . '/missing-source.txt'), $dir, 'x.txt');
    }

    public function testLocalStorageCreatesNestedDirectory(): void
    {
        $base = $this->createTempDir();
        $target = $base . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b';
        $source = new UploadedFile('file.txt', 'text/plain', 4, $this->createTempFile('data', 'txt'));

        $saved = (new LocalStorage())->save($source, $target, 'file.txt');

        $this->assertFileExists($saved);
        $this->assertTrue((new LocalStorage())->exists($saved));
        $this->assertTrue((new LocalStorage())->delete($saved));
    }

    public function testDirectoryHardenerSkipsMissingAndAlreadyHardened(): void
    {
        DirectoryHardener::harden('/missing/dir');

        $dir = $this->createTempDir();
        DirectoryHardener::harden($dir);
        file_put_contents($dir . '/.htaccess', 'custom');
        DirectoryHardener::harden($dir);

        $this->assertSame('custom', file_get_contents($dir . '/.htaccess'));
    }

    public function testRollbackManagerClear(): void
    {
        $manager = (new RollbackManager())->record('/a')->record('/b');
        $this->assertSame(['/a', '/b'], $manager->getSavedFiles());

        $manager->clear();

        $this->assertSame(0, $manager->count());
    }

    public function testTranslatorFormatsBooleansAndSkipsNonScalars(): void
    {
        Translator::addTranslations('en', ['T_KEY' => 'flag={flag} list={list}']);

        $this->assertSame('flag=true list=1, 2', Translator::translate('T_KEY', ['flag' => true, 'list' => [1, 2, new \stdClass()]]));
        $this->assertSame('flag={flag} list={list}', Translator::translate('T_KEY', ['flag' => new \stdClass()]));
    }

    public function testNamedExceptionsCarryCodes(): void
    {
        $this->assertSame('ERR_PROCESS_FAILED', ImageProcessingException::rotateFailed(45)->getErrorCode());
        $this->assertSame('ERR_PROCESS_FAILED', ImageProcessingException::watermarkNotLoaded('x')->getErrorCode());
        $this->assertSame('ERR_PROCESS_FAILED', UploadException::unsupportedPsr7Input()->getErrorCode());
        $this->assertSame('ERR_PROCESS_FAILED', UploadException::tempFileNotCreated('/x')->getErrorCode());
        $this->assertSame('ERR_PROCESS_FAILED', UploadException::directoryNotCreated('/x')->getErrorCode());
        $this->assertSame('ERR_PROCESS_FAILED', UploadException::invalidDestination('/x')->getErrorCode());
        $this->assertSame('ERR_FILE_EXISTS', UploadException::fileExists('a.txt')->getErrorCode());
        $this->assertSame('ERR_PROCESS_FAILED', UploadException::saveFailed('/x')->getErrorCode());
    }

    public function testFromUrlRejectsInvalidAndPrivateTargets(): void
    {
        try {
            Upload::fromUrl('not a url');
            $this->fail('Invalid URL must be rejected.');
        } catch (SecurityException $e) {
            $this->assertSame('ERR_SSRF_INVALID_URL', $e->getErrorCode());
        }

        try {
            Upload::fromUrl('http://127.0.0.1/secret.jpg');
            $this->fail('Loopback URL must be rejected.');
        } catch (SecurityException $e) {
            $this->assertSame('ERR_SSRF_BLOCKED', $e->getErrorCode());
        }
    }

    public function testFromPsr7RejectsUnsupportedObject(): void
    {
        $this->expectException(UploadException::class);

        Upload::fromPsr7(new \stdClass());
    }

    public function testFromGlobalsUsesProvidedArray(): void
    {
        $batch = Upload::fromGlobals(['doc' => ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/a', 'error' => 0, 'size' => 1]]);

        $this->assertSame(1, $batch->count());
    }

    public function testDownloaderRejectsRedirectWithoutLocation(): void
    {
        $downloader = new RecordingUrlDownloader([[['HTTP/1.1 302 Found'], '']], static fn(string $h): array => ['93.184.216.34']);

        try {
            $downloader->downloadTo('http://cdn.example.com/a', $this->createTempFile('', 'bin'));
            $this->fail('Redirect without Location must fail.');
        } catch (UploadException $e) {
            $this->assertSame('ERR_URL_DOWNLOAD_FAILED', $e->getErrorCode());
        }
    }

    public function testDownloaderResolvesProtocolRelativeAndPathRelativeRedirects(): void
    {
        $downloader = new RecordingUrlDownloader([
            [['HTTP/1.1 302 Found', 'Location: //other.example.com/x/y.jpg'], ''],
            [['HTTP/1.1 302 Found', 'Location: z.jpg'], ''],
            [['HTTP/1.1 200 OK'], 'IMG'],
        ], static fn(string $h): array => ['93.184.216.34']);

        $downloader->downloadTo('https://cdn.example.com/a.jpg', $this->createTempFile('', 'bin'));

        $this->assertSame('Host: other.example.com' . "\r\n", $downloader->requests[1][1]['http']['header']);
        $this->assertSame('https://93.184.216.34/x/z.jpg', $downloader->requests[2][0]);
    }

    public function testDownloaderReportsConnectionFailure(): void
    {
        // Port 8080 is allowed by SsrfGuard; nothing listens on it in the test environment.
        $downloader = new UrlDownloader(timeout: 2, allowPrivateIp: true);

        try {
            $downloader->downloadTo('http://127.0.0.1:8080/none.jpg', $this->createTempFile('', 'bin'));
            $this->fail('Connection failure must be reported.');
        } catch (UploadException $e) {
            $this->assertSame('ERR_URL_DOWNLOAD_FAILED', $e->getErrorCode());
        }
    }

    public function testDownloaderFailsWhenDestinationIsNotWritable(): void
    {
        $downloader = new RecordingUrlDownloader([[['HTTP/1.1 200 OK'], 'DATA']], static fn(string $h): array => ['93.184.216.34']);

        $this->expectException(UploadException::class);
        $downloader->downloadTo('http://cdn.example.com/a.jpg', $this->createTempDir() . '/no/such/dir/file.bin');
    }
}

/**
 * Driver stub whose backend extension is missing.
 */
final class UnavailableDriver extends \UploadHelper\Drivers\Gd\GdDriver
{
    public function isAvailable(): bool
    {
        return false;
    }
}

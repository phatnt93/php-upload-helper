<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Actions\File\RenameAction;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\InvalidLocaleException;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\Exceptions\ValidationException;
use UploadHelper\I18n\Translator;
use UploadHelper\Tests\TestCase;
use UploadHelper\Upload;
use UploadHelper\Validation\DimensionValidator;
use UploadHelper\Validation\ExtensionGuard;
use UploadHelper\Validation\SizeValidator;

class RegexNewlineAndTypeSafetyTest extends TestCase
{
    // =========================================================================
    // Checklist #19: Regex trailing-\n and anchor safety
    // =========================================================================

    public function testLocaleRejectsTrailingNewline(): void
    {
        $this->expectException(InvalidLocaleException::class);
        Translator::setLocale("en\n");
    }

    public function testLocaleRejectsCarriageReturn(): void
    {
        $this->expectException(InvalidLocaleException::class);
        Translator::setLocale("vi\r\n");
    }

    public function testDangerousExtensionWithTrailingNewlineIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('forbidden for security reasons');

        ExtensionGuard::assertAllowed("shell.php\n", ['jpg', 'png']);
    }

    public function testDangerousExtensionWithCarriageReturnIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('forbidden for security reasons');

        ExtensionGuard::assertAllowed("shell.php\r\n", ['jpg', 'png']);
    }

    public function testDangerousExtensionWithoutWhitelistWithNewlineIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('forbidden for security reasons');

        ExtensionGuard::assertAllowed("backdoor.phtml\n", []);
    }

    public function testDoubleExtensionWithNewlineInIntermediatePartIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Malicious double extension');

        ExtensionGuard::assertAllowed("avatar.php\n.jpg", ['jpg']);
    }

    public function testDoubleExtensionWithCarriageReturnInIntermediatePartIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Malicious double extension');

        ExtensionGuard::assertAllowed("exploit.phar\r\n.png", ['png']);
    }

    public function testDoubleExtensionWithWhitespaceInIntermediatePartIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Malicious double extension');

        ExtensionGuard::assertAllowed("exploit.php .png", ['png']);
    }

    public function testSafeExtensionWithTrailingNewlinePassesWhitelistAndTrims(): void
    {
        ExtensionGuard::assertAllowed("photo.jpg\n", ['jpg']);
        $this->assertTrue(true);
    }

    public function testUploadedFileStripsTrailingNewlineFromNameAndExtension(): void
    {
        $tmp = $this->createTempFile('dummy', 'jpg');
        $file = new UploadedFile("my_photo.jpg\n", 'image/jpeg', 5, $tmp);

        $this->assertSame('my_photo', $file->getCurrentName());
        $this->assertSame('jpg', $file->getCurrentExtension());
        $this->assertSame('my_photo.jpg', $file->getCurrentFilename());

        $file->setCurrentExtension("png\r\n");
        $this->assertSame('png', $file->getCurrentExtension());

        $file->setCurrentName("renamed\n");
        $this->assertSame('renamed', $file->getCurrentName());
        $this->assertSame('renamed.png', $file->getCurrentFilename());
    }

    public function testFromBase64HandlesTrailingNewlineInDataUri(): void
    {
        $pixelBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $dataUriWithNewline = "data:image/png;base64,{$pixelBase64}\n";

        $builder = Upload::fromBase64($dataUriWithNewline, 'pixel.png');
        $this->assertNotNull($builder->getFile());
        $this->assertSame('pixel.png', $builder->getFile()->getOriginalName());
    }

    public function testSlugifyStripsNewlinesAndSymbols(): void
    {
        $slug = RenameAction::slugify("hello world\r\n");
        $this->assertSame('hello-world', $slug);

        $slugEmpty = RenameAction::slugify("!@#$%^&*()\n\t");
        $this->assertSame('file', $slugEmpty);
    }

    // =========================================================================
    // Checklist #20: Type safety / wrong input types across public APIs
    // =========================================================================

    public function testUploadFileHandlesNullInputGracefully(): void
    {
        $builder = Upload::file(null);
        $result = $builder->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_NO_FILE', $result->getErrorCode());
    }

    public function testUploadFileHandlesIntegerInputGracefully(): void
    {
        $builder = Upload::file(12345);
        $result = $builder->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_NO_FILE', $result->getErrorCode());
    }

    public function testUploadFileHandlesObjectInputGracefully(): void
    {
        $builder = Upload::file(new \stdClass());
        $result = $builder->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_NO_FILE', $result->getErrorCode());
    }

    public function testUploadFileHandlesEmptyArrayGracefully(): void
    {
        $builder = Upload::file([]);
        $result = $builder->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_NO_FILE', $result->getErrorCode());
    }

    public function testUploadFileHandlesIncompletePhpArrayGracefully(): void
    {
        $builder = Upload::file(['name' => 'only_name.jpg']);
        $result = $builder->process();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ERR_NO_FILE', $result->getErrorCode());
    }

    public function testUploadFilesHandlesNullInputGracefully(): void
    {
        $batch = Upload::files(null);
        $result = $batch->process();

        $this->assertSame(0, $result->count());
        $this->assertTrue($result->allSuccessful());
    }

    public function testUploadFilesHandlesScalarAndObjectInputsGracefully(): void
    {
        $batch = Upload::files([null, 42, 'string', new \stdClass()]);
        $result = $batch->process();

        $this->assertSame(0, $result->count());
    }

    public function testUploadFromPathThrowsUploadExceptionForNonExistentPath(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        Upload::fromPath('/non/existent/path/for/sure_' . uniqid());
    }

    public function testUploadFromBase64ThrowsUploadExceptionForInvalidBase64(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('Base64 payload is invalid');

        Upload::fromBase64('!@#$%^&*()_not_base_64');
    }

    public function testUploadFromPsr7ThrowsUploadExceptionForUnsupportedObject(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('Input is not a PSR-7');

        Upload::fromPsr7(new \stdClass());
    }

    public function testUploadFromPsr7HandlesMixedArraySafely(): void
    {
        $batch = Upload::fromPsr7([new \stdClass(), 123, 'test']);
        $this->assertSame(0, $batch->process()->count());
    }

    public function testUploadFromUrlThrowsSecurityExceptionForInvalidUrl(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('The remote URL is invalid');

        Upload::fromUrl('///bad-url');
    }

    public function testUploadFromUrlThrowsSecurityExceptionForDisallowedScheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('URL scheme is not allowed');

        Upload::fromUrl('ftp://example.com/file.jpg');
    }

    public function testUploadFromUrlThrowsSecurityExceptionForPrivateIp(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('private or restricted network address');

        Upload::fromUrl('http://127.0.0.1/file.jpg');
    }

    public function testSizeValidatorParseToBytesHandlesVariousTypesSafely(): void
    {
        $this->assertNull(SizeValidator::parseToBytes(null));
        $this->assertNull(SizeValidator::parseToBytes(''));
        $this->assertSame(1024, SizeValidator::parseToBytes(1024));
        $this->assertSame(1048576, SizeValidator::parseToBytes('1M'));
        $this->assertSame(0, SizeValidator::parseToBytes('invalid_size_format'));
    }

    #[RequiresPhpExtension('gd')]
    public function testDimensionValidatorHandlesMalformedRatioSafely(): void
    {
        $tmp = $this->createTestImage(100, 100, 'jpg');
        $file = new UploadedFile('photo.jpg', 'image/jpeg', filesize($tmp), $tmp);

        // Malformed ratio string with 3 parts should be ignored, not crash
        DimensionValidator::validate($file, ['ratio' => '16:9:4']);
        // Non-numeric ratio string should be ignored, not crash
        DimensionValidator::validate($file, ['ratio' => 'not-a-ratio']);

        $this->assertTrue(true);
    }

    #[RequiresPhpExtension('gd')]
    public function testDimensionValidatorPassesOnValidRatioWithTolerance(): void
    {
        $tmp = $this->createTestImage(160, 90, 'jpg');
        $file = new UploadedFile('widescreen.jpg', 'image/jpeg', filesize($tmp), $tmp);

        DimensionValidator::validate($file, ['ratio' => '16:9', 'ratio_tolerance' => 0.05]);
        $this->assertTrue(true);
    }

    public function testUniversalTypeSafetyMatrix(): void
    {
        $nonStringInputs = [
            'array'   => ['some', 'data'],
            'object'  => new \stdClass(),
            'null'    => null,
            'integer' => 12345,
            'float'   => 3.14,
            'bool'    => false,
        ];

        foreach ($nonStringInputs as $type => $input) {
            // 1. Upload::fromBase64
            try {
                Upload::fromBase64($input);
                $this->fail("Expected UploadException for fromBase64({$type})");
            } catch (UploadException $e) {
                $this->assertStringContainsString('Base64 payload is invalid', $e->getMessage());
            } catch (\TypeError $e) {
                $this->fail("fromBase64({$type}) threw TypeError instead of UploadException: " . $e->getMessage());
            }

            // 2. Upload::fromPath
            try {
                Upload::fromPath($input);
                $this->fail("Expected UploadException for fromPath({$type})");
            } catch (UploadException $e) {
                $this->assertStringContainsString('not readable', $e->getMessage());
            } catch (\TypeError $e) {
                $this->fail("fromPath({$type}) threw TypeError instead of UploadException: " . $e->getMessage());
            }

            // 3. Upload::fromUrl
            try {
                Upload::fromUrl($input);
                $this->fail("Expected SecurityException for fromUrl({$type})");
            } catch (SecurityException $e) {
                $this->assertStringContainsString('invalid', $e->getMessage());
            } catch (\TypeError $e) {
                $this->fail("fromUrl({$type}) threw TypeError instead of SecurityException: " . $e->getMessage());
            }

            // 4. SizeValidator::parseToBytes
            try {
                $bytes = SizeValidator::parseToBytes($input);
                $this->assertTrue($bytes === null || is_int($bytes));
            } catch (\TypeError $e) {
                $this->fail("SizeValidator::parseToBytes({$type}) threw TypeError: " . $e->getMessage());
            }
            // 5. ExtensionGuard::assertAllowed
            try {
                ExtensionGuard::assertAllowed($input, ['jpg']);
                $this->fail("Expected ValidationException for assertAllowed({$type})");
            } catch (ValidationException $e) {
                $this->assertTrue(true);
            } catch (\TypeError $e) {
                $this->fail("ExtensionGuard::assertAllowed({$type}) threw TypeError: " . $e->getMessage());
            }

            // 6. Upload::fromPsr7
            try {
                $psrResult = Upload::fromPsr7($input);
                $this->assertInstanceOf(\UploadHelper\Core\BatchUploadBuilder::class, $psrResult);
            } catch (UploadException $e) {
                $this->assertStringContainsString('Input is not a PSR-7', $e->getMessage());
            } catch (\TypeError $e) {
                $this->fail("fromPsr7({$type}) threw TypeError instead of UploadException: " . $e->getMessage());
            }

            // 7. Upload::file and Upload::files
            try {
                $singleResult = Upload::file($input)->process();
                $this->assertFalse($singleResult->isSuccess());
                $this->assertSame('ERR_NO_FILE', $singleResult->getErrorCode());

                $batchResult = Upload::files($input)->process();
                $this->assertSame(0, $batchResult->count());
            } catch (\TypeError $e) {
                $this->fail("Upload::file/files({$type}) threw TypeError: " . $e->getMessage());
            }
        }
    }

    #[RequiresPhpExtension('gd')]
    public function testDimensionValidatorHandlesWrongTypeConstraintValuesGracefully(): void
    {
        $tmp = $this->createTestImage(100, 100, 'jpg');
        $file = new UploadedFile('photo.jpg', 'image/jpeg', filesize($tmp), $tmp);

        $badValues = [[], new \stdClass(), null, 'not_a_number'];

        foreach ($badValues as $bad) {
            // Neither ratio nor width/height constraints should throw TypeError
            DimensionValidator::validate($file, ['ratio' => $bad]);
            DimensionValidator::validate($file, ['min_width' => $bad]);
            DimensionValidator::validate($file, ['max_width' => $bad]);
            DimensionValidator::validate($file, ['exact_width' => $bad]);
            DimensionValidator::validate($file, ['min_height' => $bad]);
            DimensionValidator::validate($file, ['max_height' => $bad]);
            DimensionValidator::validate($file, ['exact_height' => $bad]);
            DimensionValidator::validate($file, ['ratio_tolerance' => $bad]);
        }

        $this->assertTrue(true);
    }
}

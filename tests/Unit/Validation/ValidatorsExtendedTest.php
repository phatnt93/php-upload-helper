<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\ValidationException;
use UploadHelper\Tests\TestCase;
use UploadHelper\Validation\DimensionValidator;
use UploadHelper\Validation\DocumentSecurityValidator;
use UploadHelper\Validation\ExtensionGuard;
use UploadHelper\Validation\MimeSniffer;
use UploadHelper\Validation\PixelFloodGuard;
use UploadHelper\Validation\SecurityValidator;
use UploadHelper\Validation\SizeValidator;
use UploadHelper\Validation\SsrfGuard;
use UploadHelper\Validation\SvgSanitizer;

final class ValidatorsExtendedTest extends TestCase
{
    /**
     * @return iterable<string, array{int|string|null, int|null}>
     */
    public static function sizeProvider(): iterable
    {
        yield 'null' => [null, null];
        yield 'empty' => ['', null];
        yield 'int' => [1234, 1234];
        yield 'plain digits' => ['2048', 2048];
        yield 'kilobytes' => ['2K', 2048];
        yield 'megabytes' => ['1.5M', 1_572_864];
        yield 'gigabytes' => ['1G', 1_073_741_824];
        yield 'terabytes' => ['1T', 1_099_511_627_776];
        yield 'lowercase unit' => ['3k', 3072];
    }

    #[DataProvider('sizeProvider')]
    public function testParseToBytes(int|string|null $input, ?int $expected): void
    {
        $this->assertSame($expected, SizeValidator::parseToBytes($input));
    }

    public function testFileSmallerThanMinimumIsRejected(): void
    {
        $file = $this->fileWithContent('abc', 'tiny.txt');

        try {
            SizeValidator::validate($file, null, '1K');
            $this->fail('Too small file must be rejected.');
        } catch (ValidationException $e) {
            $this->assertSame('ERR_FILE_TOO_SMALL', $e->getErrorCode());
            $this->assertSame('1 KB', $e->getContext()['formatted_min']);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function dimensionFailureProvider(): iterable
    {
        yield 'max width' => [['max_width' => 50], 'ERR_DIMENSION_MAX_WIDTH'];
        yield 'min height' => [['min_height' => 200], 'ERR_DIMENSION_MIN_HEIGHT'];
        yield 'max height' => [['max_height' => 10], 'ERR_DIMENSION_MAX_HEIGHT'];
        yield 'exact width' => [['exact_width' => 99], 'ERR_DIMENSION_EXACT_WIDTH'];
        yield 'exact height' => [['exact_height' => 51], 'ERR_DIMENSION_EXACT_HEIGHT'];
        yield 'ratio colon' => [['ratio' => '16:9'], 'ERR_DIMENSION_RATIO'];
        yield 'ratio slash' => [['ratio' => '1/1'], 'ERR_DIMENSION_RATIO'];
        yield 'ratio float' => [['ratio' => 1.0], 'ERR_DIMENSION_RATIO'];
        yield 'ratio numeric string' => [['ratio' => '3'], 'ERR_DIMENSION_RATIO'];
    }

    /**
     * @param array<string, mixed> $constraints
     */
    #[RequiresPhpExtension('gd')]
    #[DataProvider('dimensionFailureProvider')]
    public function testDimensionConstraintFailures(array $constraints, string $expectedCode): void
    {
        $path = $this->createTestImage(100, 50, 'png');
        $file = new UploadedFile('img.png', 'image/png', (int) filesize($path), $path);

        try {
            DimensionValidator::validate($file, $constraints);
            $this->fail("Constraint must fail with {$expectedCode}.");
        } catch (ValidationException $e) {
            $this->assertSame($expectedCode, $e->getErrorCode());
        }
    }

    #[RequiresPhpExtension('gd')]
    public function testMatchingDimensionsAndRatioPass(): void
    {
        $path = $this->createTestImage(160, 90, 'png');
        $file = new UploadedFile('img.png', 'image/png', (int) filesize($path), $path);

        DimensionValidator::validate($file, [
            'exact_width'  => 160,
            'exact_height' => 90,
            'ratio'        => '16:9',
        ]);
        DimensionValidator::validate($file, ['ratio' => 'garbage']);
        DimensionValidator::validate($file, []);

        $this->addToAssertionCount(1);
    }

    public function testDimensionCheckOnNonImageFails(): void
    {
        $this->expectException(ValidationException::class);

        DimensionValidator::validate($this->fileWithContent('text', 'a.txt'), ['min_width' => 1]);
    }

    public function testPdfChecks(): void
    {
        $launch = $this->createTempFile("%PDF-1.7\n/Launch /Win", 'pdf');
        $embedded = $this->createTempFile("%PDF-1.7\n/EmbeddedFiles []", 'pdf');
        $clean = $this->createTempFile("%PDF-1.7\nplain content", 'pdf');

        $this->assertSecurityCode('ERR_PDF_LAUNCH_DETECTED', static fn() => DocumentSecurityValidator::assertSafePdf($launch));
        $this->assertSecurityCode('ERR_PDF_EMBEDDED_FILES_DETECTED', static fn() => DocumentSecurityValidator::assertSafePdf($embedded));

        // Allowed features, non-PDF content, tiny or missing files are ignored.
        DocumentSecurityValidator::assertSafePdf($launch, allowLaunch: true);
        DocumentSecurityValidator::assertSafePdf($embedded, allowEmbeddedFiles: true);
        DocumentSecurityValidator::assertSafePdf($clean);
        DocumentSecurityValidator::assertSafePdf($this->createTempFile('not a pdf /JavaScript', 'pdf'));
        DocumentSecurityValidator::assertSafePdf($this->createTempFile('%P', 'pdf'));
        DocumentSecurityValidator::assertSafePdf('/missing/file.pdf');
    }

    #[RequiresPhpExtension('zip')]
    public function testOfficeMacroInsideZipIsDetected(): void
    {
        $macro = $this->createZip(['word/document.xml' => '<w/>', 'word/vbaProject.bin' => 'VBA']);
        $clean = $this->createZip(['word/document.xml' => '<w/>']);

        $this->assertSecurityCode('ERR_OFFICE_MACRO_DETECTED', static fn() => DocumentSecurityValidator::assertSafeOfficeDocument($macro));

        DocumentSecurityValidator::assertSafeOfficeDocument($clean);
        DocumentSecurityValidator::assertSafeOfficeDocument($this->createTempFile('not a zip', 'docx'));
        DocumentSecurityValidator::assertSafeOfficeDocument('/missing/file.docx');
    }

    public function testOfficeMacroFallbackScanOnUnreadableZip(): void
    {
        // Valid ZIP signature but corrupt archive: falls back to a raw byte scan.
        $path = $this->createTempFile("PK\x03\x04garbage vbaProject.bin garbage", 'docm');

        $this->assertSecurityCode('ERR_OFFICE_MACRO_DETECTED', static fn() => DocumentSecurityValidator::assertSafeOfficeDocument($path));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function magicBytesProvider(): iterable
    {
        yield 'gif' => ["GIF89a\x01\x00\x01\x00", 'image/gif'];
        yield 'webp' => ['RIFF' . "\x24\x00\x00\x00" . 'WEBPVP8 ', 'image/webp'];
        yield 'pdf' => ['%PDF-1.4 rest', 'application/pdf'];
        yield 'bmp' => ['BM' . "\x46\x00\x00\x00" . "\0\0\0\0" . "\x36\x00", 'image/bmp'];
        yield 'text starting with BM' => ['BMW is a car brand', null];
        yield 'avif' => ["\x00\x00\x00\x1Cftypavif\x00\x00", 'image/avif'];
        yield 'heic' => ["\x00\x00\x00\x18ftypheic\x00\x00", 'image/heic'];
        yield 'zip' => ["PK\x03\x04\x14\x00", 'application/zip'];
        yield 'too short' => ['ab', null];
        yield 'unknown' => ['hello world', null];
    }

    #[DataProvider('magicBytesProvider')]
    public function testMagicBytesDetection(string $content, ?string $expected): void
    {
        $this->assertSame($expected, MimeSniffer::detectFromMagicBytes($this->createTempFile($content, 'bin')));
    }

    public function testMimeDetectionFallbacks(): void
    {
        $this->assertSame('application/octet-stream', MimeSniffer::detect('/missing/file.bin'));
        $this->assertSame('text/plain', MimeSniffer::detect($this->createTempFile('just some text', 'txt')));
        $this->assertNull(MimeSniffer::detectFromMagicBytes('/missing/file.bin'));
    }

    #[RequiresPhpExtension('gd')]
    public function testPixelFloodGuard(): void
    {
        $small = $this->createTestImage(20, 20, 'png');

        PixelFloodGuard::assertSafe($small);
        PixelFloodGuard::assertSafe('/missing/file.png');
        PixelFloodGuard::assertSafe($this->createTempFile('not an image', 'png'));

        $this->assertSecurityCode('ERR_IMAGE_PIXEL_FLOOD', static fn() => PixelFloodGuard::assertSafe($small, maxPixels: 100));
        $this->assertSecurityCode('ERR_IMAGE_PIXEL_FLOOD', static fn() => PixelFloodGuard::assertSafe($small, maxMemoryBytes: 100));
    }

    public function testPixelFloodGuardRejectsHugeDeclaredAxis(): void
    {
        // PNG header declaring 70000x1 pixels; no pixel data is needed for getimagesize().
        $ihdr = 'IHDR' . pack('NN', 70000, 1) . "\x08\x02\x00\x00\x00";
        $png = "\x89PNG\r\n\x1A\n" . pack('N', 13) . $ihdr . pack('N', crc32($ihdr));

        $this->assertSecurityCode(
            'ERR_IMAGE_PIXEL_FLOOD',
            fn() => PixelFloodGuard::assertSafe($this->createTempFile($png, 'png')),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function uploadErrorProvider(): iterable
    {
        yield 'form size' => [UPLOAD_ERR_FORM_SIZE, 'ERR_UPLOAD_FORM_SIZE'];
        yield 'partial' => [UPLOAD_ERR_PARTIAL, 'ERR_UPLOAD_PARTIAL'];
        yield 'no file' => [UPLOAD_ERR_NO_FILE, 'ERR_UPLOAD_NO_FILE'];
        yield 'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR, 'ERR_UPLOAD_NO_TMP_DIR'];
        yield 'cant write' => [UPLOAD_ERR_CANT_WRITE, 'ERR_UPLOAD_CANT_WRITE'];
        yield 'extension' => [UPLOAD_ERR_EXTENSION, 'ERR_UPLOAD_EXTENSION'];
        yield 'unknown' => [99, 'ERR_UPLOAD_UNKNOWN'];
    }

    #[DataProvider('uploadErrorProvider')]
    public function testPhpUploadErrorsMapToCodes(int $phpError, string $expectedCode): void
    {
        $file = new UploadedFile('a.jpg', 'image/jpeg', 0, '', $phpError);

        try {
            (new SecurityValidator())->validate($file);
            $this->fail('Upload error must be reported.');
        } catch (ValidationException $e) {
            $this->assertSame($expectedCode, $e->getErrorCode());
        }
    }

    public function testSecurityValidatorSettersEnableDocumentChecks(): void
    {
        $validator = (new SecurityValidator())
            ->setAllowedExtensions(['pdf', 'docx'])
            ->setAllowedMimes(['application/pdf', 'application/zip'])
            ->setMinSize(1)
            ->setMaxSize('1M')
            ->setDimensionConstraints([])
            ->setSanitizeSvg(false)
            ->setMaxPixels(null)
            ->setMaxImageMemory(null)
            ->setBlockPdfJavaScript()
            ->setBlockOfficeMacros()
            ->setScanner(null);

        $pdf = $this->createTempFile("%PDF-1.7\n/JavaScript (alert)", 'pdf');
        $this->assertSecurityCode(
            'ERR_PDF_JAVASCRIPT_DETECTED',
            static fn() => $validator->validate(new UploadedFile('doc.pdf', 'application/pdf', 30, $pdf)),
        );

        $docx = $this->createTempFile("PK\x03\x04broken vbaProject.bin", 'docx');
        $this->assertSecurityCode(
            'ERR_OFFICE_MACRO_DETECTED',
            static fn() => $validator->validate(new UploadedFile('doc.docx', 'application/zip', 30, $docx)),
        );
    }

    public function testSvgSanitizerEdgeCases(): void
    {
        SvgSanitizer::sanitize('/missing/file.svg');
        SvgSanitizer::sanitize($this->createTempFile('   ', 'svg'));

        $onlyHandler = $this->createTempFile('<svg xmlns="http://www.w3.org/2000/svg"><rect onclick="x()"/></svg>', 'svg');
        SvgSanitizer::sanitize($onlyHandler);
        $this->assertStringNotContainsString('onclick', (string) file_get_contents($onlyHandler));

        $broken = $this->createTempFile('<svg><script>alert(1)</svg', 'svg');
        $this->assertSecurityCode('ERR_INVALID_SVG_XML', static fn() => SvgSanitizer::sanitize($broken));
    }

    public function testSvgSanitizerDoesNotResolveExternalEntities(): void
    {
        $secret = $this->createTempFile('TOP-SECRET-VALUE', 'txt');
        $uri = 'file:///' . ltrim(str_replace('\\', '/', $secret), '/');
        $svg = "<?xml version=\"1.0\"?>\n<!DOCTYPE svg [<!ENTITY xxe SYSTEM \"{$uri}\">]>\n"
            . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text><script>x</script></svg>';

        $cleaned = SvgSanitizer::cleanSvgXml($svg);

        $this->assertStringNotContainsString('TOP-SECRET-VALUE', $cleaned);
        $this->assertStringNotContainsString('<script', $cleaned);
    }

    public function testAlternateDataStreamSuffixIsStripped(): void
    {
        $this->assertSame('php', ExtensionGuard::cleanExtension('PHP::$DATA'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function ipProvider(): iterable
    {
        yield 'public v4' => ['93.184.216.34', false];
        yield 'loopback v4' => ['127.0.0.5', true];
        yield 'private A' => ['10.1.2.3', true];
        yield 'private B' => ['172.20.0.1', true];
        yield 'private C' => ['192.168.1.1', true];
        yield 'metadata' => ['169.254.169.254', true];
        yield 'zero network' => ['0.1.2.3', true];
        yield 'carrier grade NAT' => ['100.64.0.1', true];
        yield 'public v6' => ['2606:4700:4700::1111', false];
        yield 'loopback v6' => ['::1', true];
        yield 'unspecified v6' => ['::', true];
        yield 'unique local v6' => ['fd00::1', true];
        yield 'link local v6' => ['fe80::1', true];
        yield 'mapped loopback' => ['::ffff:127.0.0.1', true];
        yield 'mapped public' => ['::ffff:93.184.216.34', false];
        yield 'not an ip' => ['not-an-ip', true];
    }

    #[DataProvider('ipProvider')]
    public function testIpClassification(string $ip, bool $isPrivate): void
    {
        $this->assertSame($isPrivate, SsrfGuard::isPrivateOrReservedIp($ip));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsafeUrlProvider(): iterable
    {
        yield 'no scheme' => ['example.com/a.jpg', 'ERR_SSRF_INVALID_URL'];
        yield 'gopher' => ['gopher://example.com/', 'ERR_SSRF_DISALLOWED_SCHEME'];
        yield 'no host' => ['http:///a.jpg', 'ERR_SSRF_INVALID_URL'];
        yield 'port' => ['http://example.com:22/a.jpg', 'ERR_SSRF_DISALLOWED_PORT'];
        yield 'localhost' => ['http://localhost/a.jpg', 'ERR_SSRF_BLOCKED'];
        yield 'ipv6 loopback' => ['http://[::1]/a.jpg', 'ERR_SSRF_BLOCKED'];
    }

    #[DataProvider('unsafeUrlProvider')]
    public function testUnsafeUrlsAreRejected(string $url, string $expectedCode): void
    {
        $this->assertSecurityCode($expectedCode, static fn() => SsrfGuard::assertSafeUrl($url, false, static fn(string $h): array => ['93.184.216.34']));
    }

    public function testUnresolvableHostIsRejected(): void
    {
        $this->assertSecurityCode(
            'ERR_SSRF_DNS_RESOLVE_FAILED',
            static fn() => SsrfGuard::assertSafeUrl('http://nowhere.example/', false, static fn(string $h): array => []),
        );
    }

    public function testPrivateTargetsAllowedWhenExplicitlyEnabled(): void
    {
        $this->assertSame(['127.0.0.1'], SsrfGuard::assertSafeUrl('http://127.0.0.1/a.jpg', true));
        $this->assertSame(['127.0.0.1'], SsrfGuard::assertSafeUrl('http://localhost/a.jpg', true, static fn(string $h): array => ['127.0.0.1']));
    }

    public function testResolveHostIps(): void
    {
        $this->assertSame(['10.0.0.1'], SsrfGuard::resolveHostIps('10.0.0.1'));
        $this->assertContains('127.0.0.1', SsrfGuard::resolveHostIps('localhost'));
    }

    /**
     * @param callable(): mixed $operation
     */
    private function assertSecurityCode(string $expectedCode, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected SecurityException {$expectedCode}.");
        } catch (SecurityException $e) {
            $this->assertSame($expectedCode, $e->getErrorCode());
        }
    }

    private function fileWithContent(string $content, string $name): UploadedFile
    {
        $path = $this->createTempFile($content, pathinfo($name, PATHINFO_EXTENSION));

        return new UploadedFile($name, 'text/plain', strlen($content), $path);
    }

    /**
     * @param array<string, string> $entries
     */
    private function createZip(array $entries): string
    {
        $path = $this->createTempFile('', 'docx');
        @unlink($path);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE));
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }
}

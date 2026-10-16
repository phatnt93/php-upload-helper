<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Validation;

use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\ValidationException;
use UploadHelper\Tests\TestCase;
use UploadHelper\Validation\SecurityValidator;
use UploadHelper\Validation\SvgSanitizer;

class SecurityValidatorTest extends TestCase
{
    public function testValidFilePasses(): void
    {
        $tmp = $this->createTestImage(200, 200, 'jpg');
        $file = new UploadedFile('photo.jpg', 'image/jpeg', filesize($tmp), $tmp);

        $validator = new SecurityValidator(
            allowedExtensions: ['jpg', 'png'],
            allowedMimes: ['image/jpeg', 'image/png'],
            maxSize: '2M'
        );

        $validator->validate($file);
        $this->assertTrue($file->isOk());
    }

    public function testDangerousExtensionIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('is forbidden for security reasons');

        $tmp = $this->createTempFile('<?php phpinfo(); ?>', 'php');
        $file = new UploadedFile('shell.php', 'application/x-php', 50, $tmp);

        $validator = new SecurityValidator();
        $validator->validate($file);
    }

    public function testNullByteInjectionIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('null-byte');

        $tmp = $this->createTempFile('payload', 'jpg');
        $file = new UploadedFile("avatar.php\0.jpg", 'image/jpeg', 20, $tmp);

        $validator = new SecurityValidator();
        $validator->validate($file);
    }

    public function testDoubleExtensionIsBlocked(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Malicious double extension');

        $tmp = $this->createTempFile('fake img', 'png');
        $file = new UploadedFile('exploit.php.png', 'image/png', 30, $tmp);

        $validator = new SecurityValidator();
        $validator->validate($file);
    }

    public function testExtensionNotInWhitelistIsBlocked(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is not allowed');

        $tmp = $this->createTempFile('content', 'txt');
        $file = new UploadedFile('readme.txt', 'text/plain', 20, $tmp);

        $validator = new SecurityValidator(allowedExtensions: ['jpg', 'png']);
        $validator->validate($file);
    }

    public function testFileExceedingMaxSizeIsBlocked(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds the maximum allowed limit');

        $tmp = $this->createTempFile(str_repeat('A', 5000), 'txt');
        $file = new UploadedFile('big.txt', 'text/plain', 5000, $tmp);

        $validator = new SecurityValidator(maxSize: '2K');
        $validator->validate($file);
    }

    public function testSpoofedMimeIsBlockedByMagicBytes(): void
    {
        $tmp = $this->createTempFile('<?php echo "not an image"; ?>', 'jpg');
        // Client-supplied type claims JPEG; the real content is not.
        $file = new UploadedFile('photo.jpg', 'image/jpeg', 30, $tmp);

        $validator = new SecurityValidator(
            allowedExtensions: ['jpg'],
            allowedMimes: ['image/jpeg']
        );

        try {
            $validator->validate($file);
            $this->fail('A file whose content is not JPEG must be rejected.');
        } catch (ValidationException $e) {
            $this->assertSame('ERR_MIME_NOT_ALLOWED', $e->getErrorCode());
            $this->assertNotSame('image/jpeg', $e->getContext()['detected_mime']);
        }
    }

    public function testMimeWhitelistIsCaseInsensitive(): void
    {
        $tmp = $this->createTestImage(50, 50, 'png');
        $file = new UploadedFile('icon.png', 'image/png', filesize($tmp), $tmp);

        $validator = new SecurityValidator(allowedMimes: ['IMAGE/PNG']);
        $validator->validate($file);

        $this->assertTrue($file->isOk());
    }

    public function testDimensionConstraints(): void
    {
        $tmp = $this->createTestImage(100, 100, 'jpg');
        $file = new UploadedFile('small.jpg', 'image/jpeg', filesize($tmp), $tmp);

        $validator = new SecurityValidator(
            dimensionConstraints: ['min_width' => 200]
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('less than the minimum required width');
        $validator->validate($file);
    }

    public function testSvgSanitizerStripsDangerousElements(): void
    {
        $dirtySvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" onload="alert('XSS')">
    <script>alert('Steal Cookie');</script>
    <circle cx="50" cy="50" r="40" fill="red" />
    <a href="javascript:alert(1)">Click</a>
</svg>
SVG;
        $tmp = $this->createTempFile($dirtySvg, 'svg');
        $file = new UploadedFile('vector.svg', 'image/svg+xml', strlen($dirtySvg), $tmp);

        $validator = new SecurityValidator(sanitizeSvg: true);
        $validator->validate($file);

        $cleanContent = file_get_contents($tmp);
        $this->assertStringNotContainsString('<script', $cleanContent);
        $this->assertStringNotContainsString('onload', $cleanContent);
        $this->assertStringNotContainsString('javascript:', $cleanContent);
        $this->assertStringContainsString('<circle', $cleanContent);
    }

    public function testUploadErrorCodeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('upload_max_filesize');

        $file = new UploadedFile('huge.zip', 'application/zip', 0, '', UPLOAD_ERR_INI_SIZE);
        $validator = new SecurityValidator();
        $validator->validate($file);
    }
}

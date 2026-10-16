<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Validation;

use UploadHelper\Actions\File\RenameAction;
use UploadHelper\Actions\Image\StripExifAction;
use UploadHelper\Core\ActionContext;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Drivers\Gd\GdDriver;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Storage\DirectoryHardener;
use UploadHelper\Storage\LocalStorage;
use UploadHelper\Tests\TestCase;
use UploadHelper\Validation\DocumentSecurityValidator;
use UploadHelper\Validation\PixelFloodGuard;
use UploadHelper\Validation\Scanners\ClamAvScanner;
use UploadHelper\Validation\SecurityValidator;
use UploadHelper\Validation\SsrfGuard;

class AdvancedSecurityTest extends TestCase
{
    // =========================================================================
    // Phase P0: Pixel Flood & SSRF Tests
    // =========================================================================

    public function testPixelFloodPassesForNormalImage(): void
    {
        $file = $this->createTestImage(200, 200, 'jpeg');
        PixelFloodGuard::assertSafe($file, maxPixels: 1_000_000, maxMemoryBytes: 10 * 1024 * 1024);
        $this->assertTrue(true);
    }

    public function testPixelFloodThrowsWhenPixelsExceedLimit(): void
    {
        $file = $this->createTestImage(500, 500, 'jpeg'); // 250,000 pixels

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('exceeds safe limits');
        PixelFloodGuard::assertSafe($file, maxPixels: 100_000);
    }

    public function testPixelFloodThrowsWhenMemoryExceedsLimit(): void
    {
        $file = $this->createTestImage(400, 400, 'jpeg'); // 160,000 px * 4 = 640 KB

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('exceeds safe limits');
        PixelFloodGuard::assertSafe($file, maxMemoryBytes: 300 * 1024); // 300 KB limit
    }

    public function testSsrfGuardBlocksDisallowedScheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('URL scheme is not allowed');
        SsrfGuard::assertSafeUrl('file:///etc/passwd');
    }

    public function testSsrfGuardBlocksDisallowedPort(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('URL port is restricted');
        SsrfGuard::assertSafeUrl('http://example.com:22/test.jpg');
    }

    public function testSsrfGuardBlocksLoopbackIpv4(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('SSRF blocked');
        SsrfGuard::assertSafeUrl('http://127.0.0.1:8080/image.png');
    }

    public function testSsrfGuardBlocksAwsMetadata(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('SSRF blocked');
        SsrfGuard::assertSafeUrl('http://169.254.169.254/latest/meta-data/');
    }

    public function testSsrfGuardBlocksPrivateClassC(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('SSRF blocked');
        SsrfGuard::assertSafeUrl('http://192.168.1.100/secret.jpg');
    }

    public function testSsrfGuardBlocksLocalhostDomain(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('SSRF blocked');
        SsrfGuard::assertSafeUrl('http://localhost/admin.jpg');
    }

    // =========================================================================
    // Phase P1: Directory Hardener, Exif Scrub & Checksums
    // =========================================================================

    public function testDirectoryHardenerGeneratesProtectionFiles(): void
    {
        $destDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_harden_' . uniqid('', true);
        mkdir($destDir, 0755, true);
        $this->tempFiles[] = $destDir;

        DirectoryHardener::harden($destDir);

        $htaccess = $destDir . DIRECTORY_SEPARATOR . '.htaccess';
        $webConfig = $destDir . DIRECTORY_SEPARATOR . 'web.config';
        $index = $destDir . DIRECTORY_SEPARATOR . 'index.html';

        $this->assertFileExists($htaccess);
        $this->assertFileExists($webConfig);
        $this->assertFileExists($index);

        $this->assertStringContainsString('php_flag engine off', (string) file_get_contents($htaccess));
        $this->assertStringContainsString('<clear />', (string) file_get_contents($webConfig));
        $this->assertStringContainsString('Directory listing is forbidden', (string) file_get_contents($index));
    }

    public function testStripExifActionScrubsImage(): void
    {
        $file = $this->createTestImage(100, 100, 'jpeg');
        $uploaded = new UploadedFile('photo.jpg', 'image/jpeg', filesize($file), $file, UPLOAD_ERR_OK);

        $action = new StripExifAction(keepOrientation: true);
        $context = new ActionContext(imageDriver: new GdDriver(), storage: new LocalStorage());

        $resultFile = $action->execute($uploaded, $context);

        $this->assertFileExists($resultFile->getCurrentPath());
        $this->assertGreaterThan(0, $resultFile->getSize());
    }

    public function testSha256ChecksumAndHashRename(): void
    {
        $file = $this->createTempFile('Test file content for SHA256 checksum verification', 'txt');
        $uploaded = new UploadedFile('document.txt', 'text/plain', filesize($file), $file, UPLOAD_ERR_OK);

        $expectedHash = hash('sha256', 'Test file content for SHA256 checksum verification');
        $this->assertSame($expectedHash, $uploaded->getSha256());
        $this->assertSame(md5('Test file content for SHA256 checksum verification'), $uploaded->getMd5());

        $action = new RenameAction();
        $action->hash('sha256', 32);
        $context = new ActionContext(imageDriver: new GdDriver(), storage: new LocalStorage());

        $renamed = $action->execute($uploaded, $context);
        $this->assertSame(substr($expectedHash, 0, 32), $renamed->getCurrentName());
    }

    // =========================================================================
    // Phase P2: Document Security & Antivirus Scanner
    // =========================================================================

    public function testSafePdfPasses(): void
    {
        $pdfPath = $this->createTempFile("%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF", 'pdf');
        DocumentSecurityValidator::assertSafePdf($pdfPath);
        $this->assertTrue(true);
    }

    public function testPdfWithJavaScriptThrowsSecurityException(): void
    {
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Action /S /JavaScript /JS (app.alert('XSS')) >>\nendobj\n%%EOF";
        $pdfPath = $this->createTempFile($pdfContent, 'pdf');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Embedded JavaScript detected inside PDF');
        DocumentSecurityValidator::assertSafePdf($pdfPath);
    }

    public function testPdfWithLaunchThrowsSecurityException(): void
    {
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Action /S /Launch /F (cmd.exe) >>\nendobj\n%%EOF";
        $pdfPath = $this->createTempFile($pdfContent, 'pdf');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('External launch action (/Launch)');
        DocumentSecurityValidator::assertSafePdf($pdfPath);
    }

    public function testOfficeDocumentWithVbaMacroThrowsSecurityException(): void
    {
        // Giả lập tệp DOCX (PK zip header) có chứa chuỗi vbaProject.bin
        $docContent = "PK\x03\x04\x14\x00\x00\x00" . str_repeat('A', 50) . "word/vbaProject.bin" . str_repeat('B', 100);
        $docPath = $this->createTempFile($docContent, 'docx');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('VBA Macro code (vbaProject.bin)');
        DocumentSecurityValidator::assertSafeOfficeDocument($docPath);
    }

    public function testClamAvScannerDetectsTestMalwareSignature(): void
    {
        $payload = 'MALWARE-TEST-SIGNATURE-VIRUS-PAYLOAD';
        $malwarePath = $this->createTempFile($payload, 'bin');

        $scanner = new ClamAvScanner();
        $isClean = $scanner->scan($malwarePath);

        $this->assertFalse($isClean);
        $this->assertSame('Malware-Test-Signature', $scanner->getThreatName());
    }

    public function testSecurityValidatorBlocksMalwareWhenScannerRegistered(): void
    {
        $payload = 'MALWARE-TEST-SIGNATURE-VIRUS-PAYLOAD';
        $malwarePath = $this->createTempFile($payload, 'txt');
        $uploaded = new UploadedFile('virus.txt', 'text/plain', strlen($payload), $malwarePath, UPLOAD_ERR_OK);

        $validator = new SecurityValidator();
        $validator->setScanner(new ClamAvScanner());

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Malware/virus signature detected in uploaded file: Malware-Test-Signature');
        $validator->validate($uploaded);
    }
}

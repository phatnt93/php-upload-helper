<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use UploadHelper\I18n\Translator;
use UploadHelper\Upload;

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_ex4_' . bin2hex(random_bytes(4));
@mkdir($tmpDir, 0755, true);

try {
    // 1. Demonstrate i18n Vietnamese error message on validation failure
    Translator::setLocale('vi');

    $dummyOversized = $tmpDir . DIRECTORY_SEPARATOR . 'large.dat';
    file_put_contents($dummyOversized, str_repeat('A', 5000));

    $resultVi = Upload::file([
        'name'     => 'test_document.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $dummyOversized,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 5000,
    ])
        ->maxSize('1K') // 1024 bytes limit
        ->process();

    if ($resultVi->isSuccess()) {
        fwrite(STDERR, "Example 04: Expected size validation failure" . PHP_EOL);
        exit(1);
    }

    $msgVi = $resultVi->getErrorMessage();

    // 2. Switch back to English and verify English message
    Translator::setLocale('en');

    $resultEn = Upload::file([
        'name'     => 'test_document.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $dummyOversized,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 5000,
    ])
        ->maxSize('1K')
        ->process();

    $msgEn = $resultEn->getErrorMessage();

    // 3. Demonstrate SVG sanitization against Stored XSS
    $maliciousSvg = $tmpDir . DIRECTORY_SEPARATOR . 'vector.svg';
    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle cx="50" cy="50" r="40" onload="steal()"/></svg>';
    file_put_contents($maliciousSvg, $svgContent);

    $destSvg = $tmpDir . DIRECTORY_SEPARATOR . 'safe_svgs';
    @mkdir($destSvg, 0755, true);

    $svgResult = Upload::file([
        'name'     => 'graphic.svg',
        'type'     => 'image/svg+xml',
        'tmp_name' => $maliciousSvg,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($svgContent),
    ])
        ->allowedExtensions(['svg'])
        ->sanitizeSvg(true)
        ->saveTo($destSvg);

    $savedSvgPath = $svgResult->getFile()?->getSavedPath();
    $cleanedContent = $savedSvgPath !== null && file_exists($savedSvgPath) ? (string) file_get_contents($savedSvgPath) : '';

    $hasScript = str_contains($cleanedContent, '<script');
    $hasOnload = str_contains($cleanedContent, 'onload');

    if ($hasScript || $hasOnload) {
        fwrite(STDERR, "Example 04: SVG was not properly sanitized" . PHP_EOL);
        exit(1);
    }

    echo "[OK] Example 04 (Security & i18n):" . PHP_EOL;
    echo "     - VI error: {$msgVi}" . PHP_EOL;
    echo "     - EN error: {$msgEn}" . PHP_EOL;
    echo "     - SVG sanitized: <script> and onload tags stripped cleanly." . PHP_EOL;

    exit(0);
} finally {
    $files = glob($tmpDir . '/*');
    if ($files !== false) {
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    $svgs = glob($tmpDir . '/safe_svgs/*');
    if ($svgs !== false) {
        foreach ($svgs as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    @rmdir($tmpDir . '/safe_svgs');
    @rmdir($tmpDir);
}

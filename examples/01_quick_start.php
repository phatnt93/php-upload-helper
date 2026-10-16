<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use UploadHelper\Upload;

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_ex1_' . bin2hex(random_bytes(4));
$destDir = $tmpDir . DIRECTORY_SEPARATOR . 'uploads';
@mkdir($destDir, 0755, true);

try {
    // 1. Create a dummy PNG image to simulate an uploaded file
    $sampleImage = $tmpDir . DIRECTORY_SEPARATOR . 'sample_avatar.png';
    $im = imagecreatetruecolor(200, 200);
    if ($im !== false) {
        $bg = imagecolorallocate($im, 70, 130, 180);
        if ($bg !== false) {
            imagefill($im, 0, 0, $bg);
        }
        imagepng($im, $sampleImage);
        imagedestroy($im);
    }

    // 2. Simulate standard PHP $_FILES payload
    $simulatedFile = [
        'name'     => 'My Avatar Photo.png',
        'type'     => 'image/png',
        'tmp_name' => $sampleImage,
        'error'    => UPLOAD_ERR_OK,
        'size'     => (int) filesize($sampleImage),
    ];

    // 3. Process the file via UploadHelper fluent pipeline
    $result = Upload::file($simulatedFile)
        ->allowedExtensions(['png', 'jpg', 'jpeg', 'webp'])
        ->allowedMimes(['image/png', 'image/jpeg', 'image/webp'])
        ->maxSize('5M')
        ->resize(120, 120, 'cover')
        ->toWebp(quality: 85)
        ->slugName()
        ->prefix('user_')
        ->saveTo($destDir);

    if (!$result->isSuccess()) {
        fwrite(STDERR, "Example 01 failed: " . $result->getErrorMessage() . PHP_EOL);
        exit(1);
    }

    $processed = $result->getFile();
    echo "[OK] Example 01 (Quick Start): Successfully processed '{$processed?->getFilename()}' ("
        . $processed?->getFormattedSize() . ") -> " . $processed?->getSavedPath() . PHP_EOL;

    exit(0);
} finally {
    // Clean up temporary files
    $files = glob($tmpDir . '/*');
    if ($files !== false) {
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    $uploads = glob($destDir . '/*');
    if ($uploads !== false) {
        foreach ($uploads as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    @rmdir($destDir);
    @rmdir($tmpDir);
}

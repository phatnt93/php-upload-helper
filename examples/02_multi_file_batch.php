<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use UploadHelper\Upload;

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_ex2_' . bin2hex(random_bytes(4));
$destDir = $tmpDir . DIRECTORY_SEPARATOR . 'gallery';
@mkdir($destDir, 0755, true);

try {
    // 1. Create two sample PNG images
    $sample1 = $tmpDir . DIRECTORY_SEPARATOR . 'photo_a.png';
    $sample2 = $tmpDir . DIRECTORY_SEPARATOR . 'photo_b.png';

    foreach ([$sample1, $sample2] as $idx => $path) {
        $im = imagecreatetruecolor(100, 100);
        if ($im !== false) {
            $color = imagecolorallocate($im, 50 * ($idx + 1), 80, 150);
            if ($color !== false) {
                imagefill($im, 0, 0, $color);
            }
            imagepng($im, $path);
            imagedestroy($im);
        }
    }

    // 2. Simulate standard multi-file array structure $_FILES['gallery']
    $multiFiles = [
        'name'     => ['gallery_one.png', 'gallery_two.png'],
        'type'     => ['image/png', 'image/png'],
        'tmp_name' => [$sample1, $sample2],
        'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size'     => [(int) filesize($sample1), (int) filesize($sample2)],
    ];

    // 3. Process the batch with atomic rollback protection
    $batchResult = Upload::files($multiFiles)
        ->allowedExtensions(['png', 'jpg', 'jpeg', 'webp'])
        ->maxSize('2M')
        ->rollbackOnError(true)
        ->each(function ($builder): void {
            $builder->uuidName()
                ->resize(80, 80)
                ->toWebp();
        })
        ->saveTo($destDir)
        ->process();

    if (!$batchResult->allSuccessful()) {
        fwrite(STDERR, "Example 02 batch had failures: " . count($batchResult->getFailed()) . PHP_EOL);
        exit(1);
    }

    $count = count($batchResult->getSuccessful());
    echo "[OK] Example 02 (Multi-File Batch): Successfully uploaded {$count} files with UUID renaming & rollback protection." . PHP_EOL;

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

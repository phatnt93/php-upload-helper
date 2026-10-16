<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use UploadHelper\Upload;

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_ex2_' . bin2hex(random_bytes(4));
$destDir = $tmpDir . DIRECTORY_SEPARATOR . 'gallery';
@mkdir($destDir, 0755, true);

$exitCode = 0;

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
                ->resize(80, 80, 'cover')
                ->toWebp();
        })
        ->destination($destDir)
        ->process();

    if (!$batchResult->allSuccessful()) {
        fwrite(STDERR, "Example 02 batch had failures: " . count($batchResult->getFailed()) . PHP_EOL);
        $exitCode = 1;
    } else {
        $count = count($batchResult->getSuccessful());
        echo "[OK] Example 02 (Multi-File Batch): Successfully uploaded {$count} files with UUID renaming & rollback protection." . PHP_EOL;
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Example 02 uncaught exception: " . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    $cleanDir = static function (string $dir) use (&$cleanDir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $cleanDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    };
    $cleanDir($tmpDir);
}

exit($exitCode);

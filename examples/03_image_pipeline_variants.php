<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use UploadHelper\Core\ProcessedFile;
use UploadHelper\Upload;

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_ex3_' . bin2hex(random_bytes(4));
$destDir = $tmpDir . DIRECTORY_SEPARATOR . 'variants';
@mkdir($destDir, 0755, true);

$exitCode = 0;

try {
    // 1. Create a 400x300 sample image (non-square to test aspect ratio preserving)
    $sampleImage = $tmpDir . DIRECTORY_SEPARATOR . 'original_banner.png';
    $im = imagecreatetruecolor(400, 300);
    if ($im !== false) {
        $color = imagecolorallocate($im, 40, 160, 120);
        if ($color !== false) {
            imagefill($im, 0, 0, $color);
        }
        imagepng($im, $sampleImage);
        imagedestroy($im);
    }

    $simulatedFile = [
        'name'     => 'original_banner.png',
        'type'     => 'image/png',
        'tmp_name' => $sampleImage,
        'error'    => UPLOAD_ERR_OK,
        'size'     => (int) filesize($sampleImage),
    ];

    $recordedSuccess = false;

    // 2. Main image pipeline with thumbnail and medium variants
    $result = Upload::file($simulatedFile)
        ->allowedExtensions(['png', 'jpg', 'webp'])
        ->resize(300, 300, 'cover')
        ->toWebp(quality: 90)
        ->randomName(12)
        ->onSuccess(function (ProcessedFile $file) use (&$recordedSuccess): void {
            $recordedSuccess = true;
        })
        ->variant('thumb', function ($v): void {
            $v->resize(80, 80, 'crop')
                ->suffix('_thumb')
                ->toWebp(quality: 75);
        })
        ->variant('medium', function ($v): void {
            $v->resize(200, 200, 'contain')
                ->suffix('_med')
                ->toJpeg(quality: 85);
        })
        ->saveTo($destDir);

    if (!$result->isSuccess()) {
        fwrite(STDERR, "Example 03 failed: " . $result->getErrorMessage() . PHP_EOL);
        $exitCode = 1;
    } else {
        $mainFile = $result->getFile();
        $thumbFile = $result->getVariant('thumb');
        $mediumFile = $result->getVariant('medium');

        if ($mainFile === null || $thumbFile === null || $mediumFile === null || !$recordedSuccess) {
            fwrite(STDERR, "Example 03: Variant missing or hook not called" . PHP_EOL);
            $exitCode = 1;
        } else {
            echo "[OK] Example 03 (Variants & Pipeline): Created main image '{$mainFile->getFilename()}', "
                . "thumb '{$thumbFile->getFilename()}' (" . $thumbFile->getWidth() . "x" . $thumbFile->getHeight() . "), "
                . "and medium '{$mediumFile->getFilename()}' (" . $mediumFile->getWidth() . "x" . $mediumFile->getHeight() . ")." . PHP_EOL;
        }
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Example 03 uncaught exception: " . $e->getMessage() . PHP_EOL);
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

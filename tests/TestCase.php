<?php

declare(strict_types=1);

namespace UploadHelper\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use UploadHelper\I18n\Translator;

abstract class TestCase extends BaseTestCase
{
    /** @var array<int, string> */
    protected array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                if (is_dir($file)) {
                    $this->removeDirectory($file);
                } else {
                    @unlink($file);
                }
            }
        }
        $this->tempFiles = [];
        Translator::reset();

        parent::tearDown();
    }

    protected function createTempFile(string $content = 'test content', string $extension = 'txt'): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_tests';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'tmp_' . uniqid('', true) . '.' . ltrim($extension, '.');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    protected function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_tests_' . uniqid('', true);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $this->tempFiles[] = $dir;

        return $dir;
    }

    protected function createTestImage(
        int $width = 100,
        int $height = 100,
        string $format = 'jpg',
        string $bgColor = '#336699'
    ): string {
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new \RuntimeException('Failed to create GD image.');
        }

        // Parse hex color
        $hex = ltrim($bgColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        $color = imagecolorallocate($image, $r, $g, $b);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);

        $path = $this->createTempFile('', $format);

        switch (strtolower($format)) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $path, 90);
                break;
            case 'png':
                imagepng($image, $path, 6);
                break;
            case 'webp':
                imagewebp($image, $path, 85);
                break;
            case 'gif':
                imagegif($image, $path);
                break;
            default:
                imagejpeg($image, $path, 90);
        }

        imagedestroy($image);

        return $path;
    }

    protected function createTransparentPng(int $width = 100, int $height = 100): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $path = $this->createTempFile('', 'png');
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        $items = glob($dir . DIRECTORY_SEPARATOR . '{,.}*', GLOB_BRACE);
        if ($items !== false) {
            foreach ($items as $item) {
                if (basename($item) === '.' || basename($item) === '..') {
                    continue;
                }
                if (is_dir($item)) {
                    $this->removeDirectory($item);
                } else {
                    @unlink($item);
                }
            }
        }
        @rmdir($dir);
    }
}

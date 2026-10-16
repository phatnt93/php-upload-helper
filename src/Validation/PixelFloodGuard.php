<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Exceptions\SecurityException;

class PixelFloodGuard
{
    /** Mặc định tối đa 40 Megapixels (~6324 x 6324 px, ~160MB RAM thô) */
    public const DEFAULT_MAX_PIXELS = 40_000_000;

    /** Mặc định ước lượng bộ nhớ giải nén tối đa 128 MB */
    public const DEFAULT_MAX_MEMORY_BYTES = 134_217_728; // 128 * 1024 * 1024

    /**
     * Kiểm tra tệp ảnh có an toàn trước tấn công Decompression Bomb / Pixel Flood DoS hay không
     *
     * @throws SecurityException Nếu số pixel hoặc lượng RAM ước tính vượt quá giới hạn an toàn
     */
    public static function assertSafe(
        string $filePath,
        ?int $maxPixels = null,
        ?int $maxMemoryBytes = null,
    ): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        // Đọc thông tin kích thước từ header ảnh mà KHÔNG giải nén vào RAM
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            // Không phải file ảnh hoặc header bị hỏng
            return;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        if ($width <= 0 || $height <= 0) {
            return;
        }

        // 1. Kiểm tra kích thước cực hạn bất thường trên từng trục (> 65,535px)
        if ($width > 65535 || $height > 65535) {
            throw SecurityException::forCode('ERR_IMAGE_PIXEL_FLOOD', [
                'width'     => $width,
                'height'    => $height,
                'pixels'    => round(($width * $height) / 1_000_000, 2),
                'limit_px'  => $maxPixels ?? self::DEFAULT_MAX_PIXELS,
            ]);
        }

        $totalPixels = $width * $height;
        $pixelLimit = $maxPixels ?? self::DEFAULT_MAX_PIXELS;

        // 2. Kiểm tra tổng số Megapixels
        if ($totalPixels > $pixelLimit) {
            $mp = round($totalPixels / 1_000_000, 2);
            $limitMp = round($pixelLimit / 1_000_000, 2);

            throw SecurityException::forCode('ERR_IMAGE_PIXEL_FLOOD', [
                'width'     => $width,
                'height'    => $height,
                'pixels'    => $mp,
                'limit_px'  => $limitMp,
            ]);
        }

        // 3. Ước tính lượng RAM cần dùng khi GD giải nén ảnh
        // Công thức: width * height * 4 bytes (kênh màu RGBA 32-bit)
        $estimatedMemory = $totalPixels * 4;
        $memoryLimit = $maxMemoryBytes ?? self::DEFAULT_MAX_MEMORY_BYTES;

        if ($estimatedMemory > $memoryLimit) {
            $estimatedMb = round($estimatedMemory / 1024 / 1024, 2);
            $limitMb = round($memoryLimit / 1024 / 1024, 2);

            throw SecurityException::forCode('ERR_IMAGE_PIXEL_FLOOD', [
                'width'            => $width,
                'height'           => $height,
                'estimated_memory' => $estimatedMb,
                'limit_memory'     => $limitMb,
            ]);
        }
    }
}

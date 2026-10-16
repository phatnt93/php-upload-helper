<?php

declare(strict_types=1);

namespace UploadHelper\Contracts;

interface ImageDriverInterface
{
    /**
     * Kiểm tra xem extension phục vụ driver này có khả dụng trên hệ thống không
     */
    public function isAvailable(): bool;

    /**
     * Nạp ảnh từ đường dẫn tệp tin
     */
    public function load(string $filePath): ImageResourceInterface;

    /**
     * Thay đổi kích thước ảnh theo các chế độ: fit, crop, pad, stretch
     */
    public function resize(
        ImageResourceInterface $image,
        int $targetWidth,
        int $targetHeight,
        string $mode = 'fit',
        bool $allowEnlarge = false,
        string $background = '#FFFFFF',
    ): ImageResourceInterface;

    /**
     * Cắt cúp ảnh theo tọa độ
     */
    public function crop(
        ImageResourceInterface $image,
        int $width,
        int $height,
        int $x = 0,
        int $y = 0,
    ): ImageResourceInterface;

    /**
     * Xoay ảnh theo góc và màu nền khi xoay góc lệch
     */
    public function rotate(
        ImageResourceInterface $image,
        float $angle,
        string $background = '#000000',
    ): ImageResourceInterface;

    /**
     * Lật ảnh theo chiều: 'h' (ngang) hoặc 'v' (dọc)
     */
    public function flip(ImageResourceInterface $image, string $direction): ImageResourceInterface;

    /**
     * Chèn watermark ảnh với độ trong suốt và vị trí
     */
    public function watermarkImage(
        ImageResourceInterface $image,
        string $watermarkPath,
        string $position = 'bottom-right',
        int $opacity = 100,
        int $offsetX = 10,
        int $offsetY = 10,
    ): ImageResourceInterface;

    /**
     * Chèn watermark chữ với TrueType font, cỡ, màu và nền
     */
    public function watermarkText(
        ImageResourceInterface $image,
        string $text,
        string $fontPath,
        int $size,
        string $color,
        string $position = 'bottom-right',
        ?string $background = null,
        int $backgroundOpacity = 0,
        int $offsetX = 10,
        int $offsetY = 10,
    ): ImageResourceInterface;

    /**
     * Áp dụng bộ lọc hiệu ứng màu sắc
     *
     * @param array<string, mixed> $args
     */
    public function filter(
        ImageResourceInterface $image,
        string $filterType,
        array $args = [],
    ): ImageResourceInterface;

    /**
     * Tự động xoay ảnh theo metadata EXIF Orientation
     */
    public function autoOrient(ImageResourceInterface $image, string $sourcePath): ImageResourceInterface;

    /**
     * Lưu ảnh ra tệp đích với định dạng và chất lượng nén chỉ định
     */
    public function save(
        ImageResourceInterface $image,
        string $destinationPath,
        string $format,
        int $quality = 85,
    ): bool;
}

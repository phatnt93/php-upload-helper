<?php

declare(strict_types=1);

namespace UploadHelper\Contracts;

interface ImageResourceInterface
{
    /**
     * Lấy chiều rộng ảnh (pixels)
     */
    public function getWidth(): int;

    /**
     * Lấy chiều cao ảnh (pixels)
     */
    public function getHeight(): int;

    /**
     * Lấy định dạng ảnh (ví dụ: 'jpeg', 'png', 'webp', 'gif')
     */
    public function getFormat(): string;

    /**
     * Lấy đối tượng handle ảnh nội bộ (\GdImage hoặc \Imagick)
     */
    public function getHandle(): mixed;

    /**
     * Giải phóng bộ nhớ của resource ảnh
     */
    public function destroy(): void;
}

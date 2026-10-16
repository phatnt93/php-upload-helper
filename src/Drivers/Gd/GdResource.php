<?php

declare(strict_types=1);

namespace UploadHelper\Drivers\Gd;

use GdImage;
use UploadHelper\Contracts\ImageResourceInterface;

class GdResource implements ImageResourceInterface
{
    public function __construct(
        private ?GdImage $handle,
        private readonly string $format,
    ) {}

    public function getWidth(): int
    {
        if ($this->handle === null) {
            return 0;
        }
        return imagesx($this->handle);
    }

    public function getHeight(): int
    {
        if ($this->handle === null) {
            return 0;
        }
        return imagesy($this->handle);
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getHandle(): ?GdImage
    {
        return $this->handle;
    }

    public function destroy(): void
    {
        if ($this->handle !== null) {
            imagedestroy($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }
}

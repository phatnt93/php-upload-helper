<?php

declare(strict_types=1);

namespace UploadHelper\Exceptions;

/**
 * The image driver failed to load, transform or encode an image.
 */
class ImageProcessingException extends UploadException
{
    private const CODE = 'ERR_PROCESS_FAILED';

    public static function cannotRead(string $path): static
    {
        return new static("Cannot read image file: {$path}", self::CODE, ['path' => $path]);
    }

    public static function cannotDecode(string $path): static
    {
        return new static("Cannot decode image file with GD: {$path}", self::CODE, ['path' => $path]);
    }

    public static function invalidHandle(): static
    {
        return new static('Invalid image handle for this driver.', self::CODE);
    }

    public static function canvasNotCreated(int $width, int $height): static
    {
        return new static("Cannot create image canvas of {$width}x{$height}.", self::CODE, ['width' => $width, 'height' => $height]);
    }

    public static function rotateFailed(float|int $angle): static
    {
        return new static("Cannot rotate image by {$angle} degrees.", self::CODE, ['angle' => $angle]);
    }

    public static function watermarkNotLoaded(string $path): static
    {
        return new static("Cannot load watermark image: {$path}", self::CODE, ['path' => $path]);
    }
}

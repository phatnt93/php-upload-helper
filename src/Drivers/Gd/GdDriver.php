<?php

declare(strict_types=1);

namespace UploadHelper\Drivers\Gd;

use GdImage;
use UploadHelper\Contracts\ImageDriverInterface;
use UploadHelper\Contracts\ImageResourceInterface;
use UploadHelper\Exceptions\ImageProcessingException;

class GdDriver implements ImageDriverInterface
{
    public function isAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    public function load(string $filePath): ImageResourceInterface
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw ImageProcessingException::cannotRead($filePath);
        }

        $info = @getimagesize($filePath);
        $mime = $info['mime'] ?? 'image/jpeg';

        $handle = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png'  => @imagecreatefrompng($filePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
            'image/gif'  => @imagecreatefromgif($filePath),
            'image/bmp'  => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($filePath) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($filePath) : false,
            default      => @imagecreatefromstring((string) file_get_contents($filePath)),
        };

        if ($handle === false) {
            throw ImageProcessingException::cannotDecode($filePath);
        }

        // Kích hoạt kênh alpha cho PNG và WebP
        imagealphablending($handle, true);
        imagesavealpha($handle, true);

        $format = match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
            'image/avif' => 'avif',
            default      => 'jpeg',
        };

        return new GdResource($handle, $format);
    }

    public function resize(
        ImageResourceInterface $image,
        int $targetWidth,
        int $targetHeight,
        string $mode = 'fit',
        bool $allowEnlarge = false,
        string $background = '#FFFFFF',
    ): ImageResourceInterface {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            throw ImageProcessingException::invalidHandle();
        }

        $srcW = $image->getWidth();
        $srcH = $image->getHeight();

        if ($srcW <= 0 || $srcH <= 0) {
            return $image;
        }

        $dstW = $targetWidth;
        $dstH = $targetHeight;
        $srcX = 0;
        $srcY = 0;
        $dstX = 0;
        $dstY = 0;
        $canvasW = $targetWidth;
        $canvasH = $targetHeight;

        switch ($mode) {
            case 'fit':
                // Giữ tỷ lệ khung hình chuẩn
                $ratioW = $targetWidth / $srcW;
                $ratioH = $targetHeight / $srcH;
                $scale = min($ratioW, $ratioH);

                if (!$allowEnlarge && $scale > 1.0) {
                    $scale = 1.0;
                }

                $canvasW = (int) max(1, round($srcW * $scale));
                $canvasH = (int) max(1, round($srcH * $scale));
                $dstW = $canvasW;
                $dstH = $canvasH;
                break;

            case 'crop':
                // Cắt từ tâm để lấp đầy khung targetWidth x targetHeight
                $ratioW = $targetWidth / $srcW;
                $ratioH = $targetHeight / $srcH;
                $scale = max($ratioW, $ratioH);

                if (!$allowEnlarge && $scale > 1.0) {
                    $scale = 1.0;
                }

                $scaledSrcW = (int) round($targetWidth / $scale);
                $scaledSrcH = (int) round($targetHeight / $scale);

                $srcX = (int) max(0, round(($srcW - $scaledSrcW) / 2));
                $srcY = (int) max(0, round(($srcH - $scaledSrcH) / 2));
                $srcW = min($srcW, $scaledSrcW);
                $srcH = min($srcH, $scaledSrcH);
                $canvasW = $targetWidth;
                $canvasH = $targetHeight;
                $dstW = $targetWidth;
                $dstH = $targetHeight;
                break;

            case 'pad':
            case 'fill':
                // Giữ tỷ lệ và chèn màu nền đệm vào các cạnh
                $ratioW = $targetWidth / $srcW;
                $ratioH = $targetHeight / $srcH;
                $scale = min($ratioW, $ratioH);

                if (!$allowEnlarge && $scale > 1.0) {
                    $scale = 1.0;
                }

                $fittedW = (int) max(1, round($srcW * $scale));
                $fittedH = (int) max(1, round($srcH * $scale));

                $canvasW = $targetWidth;
                $canvasH = $targetHeight;
                $dstX = (int) max(0, round(($canvasW - $fittedW) / 2));
                $dstY = (int) max(0, round(($canvasH - $fittedH) / 2));
                $dstW = $fittedW;
                $dstH = $fittedH;
                break;

            case 'stretch':
            default:
                $canvasW = $targetWidth;
                $canvasH = $targetHeight;
                $dstW = $targetWidth;
                $dstH = $targetHeight;
                break;
        }

        $canvas = ($canvasW >= 1 && $canvasH >= 1) ? imagecreatetruecolor($canvasW, $canvasH) : false;
        if ($canvas === false) {
            throw ImageProcessingException::canvasNotCreated($canvasW, $canvasH);
        }

        // Xử lý nền
        if ($mode === 'pad' || $mode === 'fill') {
            $color = self::allocateColor($canvas, $background);
            imagefilledrectangle($canvas, 0, 0, $canvasW, $canvasH, $color);
        } else {
            // Giữ nền trong suốt cho PNG/WebP
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefill($canvas, 0, 0, $transparent);
            }
        }

        imagecopyresampled(
            $canvas,
            $srcHandle,
            $dstX,
            $dstY,
            $srcX,
            $srcY,
            $dstW,
            $dstH,
            $srcW,
            $srcH,
        );

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        return new GdResource($canvas, $image->getFormat());
    }

    public function crop(
        ImageResourceInterface $image,
        int $width,
        int $height,
        int $x = 0,
        int $y = 0,
    ): ImageResourceInterface {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            throw ImageProcessingException::invalidHandle();
        }

        $cropped = ($width >= 1 && $height >= 1) ? imagecreatetruecolor($width, $height) : false;
        if ($cropped === false) {
            throw ImageProcessingException::canvasNotCreated($width, $height);
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($cropped, 0, 0, $transparent);
        }

        imagecopyresampled($cropped, $srcHandle, 0, 0, $x, $y, $width, $height, $width, $height);

        imagealphablending($cropped, true);
        imagesavealpha($cropped, true);

        return new GdResource($cropped, $image->getFormat());
    }

    public function rotate(
        ImageResourceInterface $image,
        float $angle,
        string $background = '#000000',
    ): ImageResourceInterface {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            throw ImageProcessingException::invalidHandle();
        }

        $bgColor = self::allocateColor($srcHandle, $background);
        $rotated = imagerotate($srcHandle, 360.0 - $angle, $bgColor);
        if ($rotated === false) {
            throw ImageProcessingException::rotateFailed($angle);
        }

        imagealphablending($rotated, true);
        imagesavealpha($rotated, true);

        return new GdResource($rotated, $image->getFormat());
    }

    public function flip(ImageResourceInterface $image, string $direction): ImageResourceInterface
    {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            throw ImageProcessingException::invalidHandle();
        }

        $mode = match (strtolower($direction)) {
            'h', 'horizontal' => IMG_FLIP_HORIZONTAL,
            'v', 'vertical'   => IMG_FLIP_VERTICAL,
            'both'            => IMG_FLIP_BOTH,
            default           => IMG_FLIP_HORIZONTAL,
        };

        imageflip($srcHandle, $mode);

        return $image;
    }

    public function watermarkImage(
        ImageResourceInterface $image,
        string $watermarkPath,
        string $position = 'bottom-right',
        int $opacity = 100,
        int $offsetX = 10,
        int $offsetY = 10,
    ): ImageResourceInterface {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            throw ImageProcessingException::invalidHandle();
        }

        $wmResource = $this->load($watermarkPath);
        $wmHandle = $wmResource->getHandle();
        if (!$wmHandle instanceof GdImage) {
            throw ImageProcessingException::watermarkNotLoaded($watermarkPath);
        }

        $imgW = $image->getWidth();
        $imgH = $image->getHeight();
        $wmW = $wmResource->getWidth();
        $wmH = $wmResource->getHeight();

        [$dstX, $dstY] = self::calculatePosition($imgW, $imgH, $wmW, $wmH, $position, $offsetX, $offsetY);

        if ($opacity >= 100) {
            imagecopy($srcHandle, $wmHandle, $dstX, $dstY, 0, 0, $wmW, $wmH);
        } else {
            imagecopymerge($srcHandle, $wmHandle, $dstX, $dstY, 0, 0, $wmW, $wmH, max(0, min(100, $opacity)));
        }

        return $image;
    }

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
    ): ImageResourceInterface {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            throw ImageProcessingException::invalidHandle();
        }

        $imgW = $image->getWidth();
        $imgH = $image->getHeight();

        // Nếu có TTF font
        if (file_exists($fontPath) && function_exists('imagettfbbox')) {
            $bbox = imagettfbbox($size, 0, $fontPath, $text);
            if ($bbox !== false) {
                $textW = (int) abs($bbox[4] - $bbox[0]);
                $textH = (int) abs($bbox[5] - $bbox[1]);

                [$x, $y] = self::calculatePosition($imgW, $imgH, $textW, $textH, $position, $offsetX, $offsetY);
                // imagettftext dùng baseline Y nên cộng thêm textH
                $actualY = $y + $textH;

                // Vẽ nền khối chữ nếu có
                if ($background !== null) {
                    $pad = 6;
                    $bgCol = self::allocateColorWithAlpha($srcHandle, $background, $backgroundOpacity);
                    imagefilledrectangle($srcHandle, $x - $pad, $y - $pad, $x + $textW + $pad, $y + $textH + $pad, $bgCol);
                }

                $textColor = self::allocateColor($srcHandle, $color);
                imagettftext($srcHandle, $size, 0, $x, $actualY, $textColor, $fontPath, $text);
                return $image;
            }
        }

        // Fallback dùng font hệ thống mặc định của GD (1 - 5)
        $font = 5;
        $textW = imagefontwidth($font) * strlen($text);
        $textH = imagefontheight($font);

        [$x, $y] = self::calculatePosition($imgW, $imgH, $textW, $textH, $position, $offsetX, $offsetY);
        $textColor = self::allocateColor($srcHandle, $color);

        if ($background !== null) {
            $bgCol = self::allocateColor($srcHandle, $background);
            imagefilledrectangle($srcHandle, $x - 2, $y - 2, $x + $textW + 2, $y + $textH + 2, $bgCol);
        }

        imagestring($srcHandle, $font, $x, $y, $text, $textColor);

        return $image;
    }

    public function filter(
        ImageResourceInterface $image,
        string $filterType,
        array $args = [],
    ): ImageResourceInterface {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            throw ImageProcessingException::invalidHandle();
        }

        match (strtolower($filterType)) {
            'grayscale', 'greyscale' => imagefilter($srcHandle, IMG_FILTER_GRAYSCALE),
            'negative', 'negate'     => imagefilter($srcHandle, IMG_FILTER_NEGATE),
            'blur'                   => imagefilter($srcHandle, IMG_FILTER_GAUSSIAN_BLUR),
            'brightness'             => imagefilter($srcHandle, IMG_FILTER_BRIGHTNESS, (int) ($args['level'] ?? 20)),
            'contrast'               => imagefilter($srcHandle, IMG_FILTER_CONTRAST, (int) ($args['level'] ?? -20)),
            'sharpen'                => imagefilter($srcHandle, IMG_FILTER_MEAN_REMOVAL),
            default                  => null,
        };

        return $image;
    }

    public function autoOrient(ImageResourceInterface $image, string $sourcePath): ImageResourceInterface
    {
        if (!function_exists('exif_read_data') || !file_exists($sourcePath)) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = $exif['Orientation'] ?? null;

        if ($orientation === null) {
            return $image;
        }

        return match ((int) $orientation) {
            3 => $this->rotate($image, 180),
            6 => $this->rotate($image, 90),
            8 => $this->rotate($image, 270),
            default => $image,
        };
    }

    public function save(
        ImageResourceInterface $image,
        string $destinationPath,
        string $format,
        int $quality = 85,
    ): bool {
        $srcHandle = $image->getHandle();
        if (!$srcHandle instanceof GdImage) {
            return false;
        }

        $cleanFormat = strtolower(ltrim($format, '.'));

        return match ($cleanFormat) {
            'jpg', 'jpeg' => imagejpeg($srcHandle, $destinationPath, max(1, min(100, $quality))),
            'png'         => imagepng($srcHandle, $destinationPath, max(0, min(9, (int) round((100 - $quality) / 10)))),
            'webp'        => function_exists('imagewebp') && imagewebp($srcHandle, $destinationPath, max(1, min(100, $quality))),
            'gif'         => imagegif($srcHandle, $destinationPath),
            'avif'        => function_exists('imageavif') && imageavif($srcHandle, $destinationPath, max(1, min(100, $quality))),
            'bmp'         => function_exists('imagebmp') && imagebmp($srcHandle, $destinationPath),
            default       => imagejpeg($srcHandle, $destinationPath, max(1, min(100, $quality))),
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function calculatePosition(
        int $canvasW,
        int $canvasH,
        int $elemW,
        int $elemH,
        string $position,
        int $offsetX,
        int $offsetY,
    ): array {
        return match (strtolower($position)) {
            'top-left', 'tl'        => [$offsetX, $offsetY],
            'top', 't', 'top-center' => [(int) round(($canvasW - $elemW) / 2), $offsetY],
            'top-right', 'tr'       => [$canvasW - $elemW - $offsetX, $offsetY],
            'center', 'c', 'middle' => [(int) round(($canvasW - $elemW) / 2), (int) round(($canvasH - $elemH) / 2)],
            'bottom-left', 'bl'     => [$offsetX, $canvasH - $elemH - $offsetY],
            'bottom', 'b'           => [(int) round(($canvasW - $elemW) / 2), $canvasH - $elemH - $offsetY],
            'bottom-right', 'br'    => [$canvasW - $elemW - $offsetX, $canvasH - $elemH - $offsetY],
            default                 => [$canvasW - $elemW - $offsetX, $canvasH - $elemH - $offsetY],
        };
    }

    private static function allocateColor(GdImage $image, string $hexColor): int
    {
        [$r, $g, $b] = self::parseHexColor($hexColor);

        $color = imagecolorallocate($image, $r, $g, $b);
        return $color !== false ? $color : 0;
    }

    private static function allocateColorWithAlpha(GdImage $image, string $hexColor, int $opacity): int
    {
        [$r, $g, $b] = self::parseHexColor($hexColor);

        // GD alpha: 0 (opaque) to 127 (fully transparent)
        $alpha = max(0, min(127, (int) round((100 - max(0, min(100, $opacity))) * 1.27)));

        $color = imagecolorallocatealpha($image, $r, $g, $b, $alpha);
        return $color !== false ? $color : 0;
    }

    /**
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    private static function parseHexColor(string $hexColor): array
    {
        $hex = ltrim($hexColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $channel = static fn(int $offset): int => max(0, min(255, (int) hexdec(substr($hex, $offset, 2))));

        return [$channel(0), $channel(2), $channel(4)];
    }
}

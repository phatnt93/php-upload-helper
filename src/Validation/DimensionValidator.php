<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\ValidationException;

class DimensionValidator
{
    /**
     * @param array{
     *     min_width?: int|null,
     *     max_width?: int|null,
     *     min_height?: int|null,
     *     max_height?: int|null,
     *     exact_width?: int|null,
     *     exact_height?: int|null,
     *     ratio?: float|string|null,
     *     ratio_tolerance?: float
     * } $constraints
     * @throws ValidationException
     */
    public static function validate(UploadedFile $file, array $constraints): void
    {
        if (empty($constraints)) {
            return;
        }

        $dimensions = $file->getImageDimensions();
        if ($dimensions === null) {
            throw ValidationException::forCode('ERR_NOT_AN_IMAGE', ['filename' => $file->getOriginalName()]);
        }

        $w = $dimensions['width'];
        $h = $dimensions['height'];

        // 1. Min width
        if (isset($constraints['min_width']) && $constraints['min_width'] !== null && $w < $constraints['min_width']) {
            throw ValidationException::forCode('ERR_DIMENSION_MIN_WIDTH', ['filename' => $file->getOriginalName(), 'width' => $w, 'min_width' => $constraints['min_width']]);
        }

        // 2. Max width
        if (isset($constraints['max_width']) && $constraints['max_width'] !== null && $w > $constraints['max_width']) {
            throw ValidationException::forCode('ERR_DIMENSION_MAX_WIDTH', ['filename' => $file->getOriginalName(), 'width' => $w, 'max_width' => $constraints['max_width']]);
        }

        // 3. Min height
        if (isset($constraints['min_height']) && $constraints['min_height'] !== null && $h < $constraints['min_height']) {
            throw ValidationException::forCode('ERR_DIMENSION_MIN_HEIGHT', ['filename' => $file->getOriginalName(), 'height' => $h, 'min_height' => $constraints['min_height']]);
        }

        // 4. Max height
        if (isset($constraints['max_height']) && $constraints['max_height'] !== null && $h > $constraints['max_height']) {
            throw ValidationException::forCode('ERR_DIMENSION_MAX_HEIGHT', ['filename' => $file->getOriginalName(), 'height' => $h, 'max_height' => $constraints['max_height']]);
        }

        // 5. Exact width
        if (isset($constraints['exact_width']) && $constraints['exact_width'] !== null && $w !== $constraints['exact_width']) {
            throw ValidationException::forCode('ERR_DIMENSION_EXACT_WIDTH', ['filename' => $file->getOriginalName(), 'width' => $w, 'exact_width' => $constraints['exact_width']]);
        }

        // 6. Exact height
        if (isset($constraints['exact_height']) && $constraints['exact_height'] !== null && $h !== $constraints['exact_height']) {
            throw ValidationException::forCode('ERR_DIMENSION_EXACT_HEIGHT', ['filename' => $file->getOriginalName(), 'height' => $h, 'exact_height' => $constraints['exact_height']]);
        }

        // 7. Ratio (ví dụ '16:9' hoặc 1.777)
        if (isset($constraints['ratio']) && $constraints['ratio'] !== null) {
            $expectedRatio = self::parseRatio($constraints['ratio']);
            if ($expectedRatio !== null && $h > 0) {
                $actualRatio = (float) ($w / $h);
                $tolerance = $constraints['ratio_tolerance'] ?? 0.05;

                if (abs($actualRatio - $expectedRatio) > $tolerance) {
                    throw ValidationException::forCode('ERR_DIMENSION_RATIO', ['filename' => $file->getOriginalName(), 'actual_ratio' => round($actualRatio, 2), 'expected_ratio' => round($expectedRatio, 2)]);
                }
            }
        }
    }

    private static function parseRatio(float|string $ratio): ?float
    {
        if (is_float($ratio) || is_int($ratio)) {
            return (float) $ratio;
        }

        if (str_contains($ratio, ':') || str_contains($ratio, '/')) {
            $parts = preg_split('[:/]', $ratio);
            if ($parts !== false && count($parts) === 2 && (float) $parts[1] > 0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return is_numeric($ratio) ? (float) $ratio : null;
    }
}

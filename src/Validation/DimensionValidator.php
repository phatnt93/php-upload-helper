<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\ValidationException;

class DimensionValidator
{
    /**
     * @param array<string, mixed> $constraints
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
        if (isset($constraints['min_width']) && is_numeric($constraints['min_width']) && $w < (int) $constraints['min_width']) {
            throw ValidationException::forCode('ERR_DIMENSION_MIN_WIDTH', ['filename' => $file->getOriginalName(), 'width' => $w, 'min_width' => (int) $constraints['min_width']]);
        }

        // 2. Max width
        if (isset($constraints['max_width']) && is_numeric($constraints['max_width']) && $w > (int) $constraints['max_width']) {
            throw ValidationException::forCode('ERR_DIMENSION_MAX_WIDTH', ['filename' => $file->getOriginalName(), 'width' => $w, 'max_width' => (int) $constraints['max_width']]);
        }

        // 3. Min height
        if (isset($constraints['min_height']) && is_numeric($constraints['min_height']) && $h < (int) $constraints['min_height']) {
            throw ValidationException::forCode('ERR_DIMENSION_MIN_HEIGHT', ['filename' => $file->getOriginalName(), 'height' => $h, 'min_height' => (int) $constraints['min_height']]);
        }

        // 4. Max height
        if (isset($constraints['max_height']) && is_numeric($constraints['max_height']) && $h > (int) $constraints['max_height']) {
            throw ValidationException::forCode('ERR_DIMENSION_MAX_HEIGHT', ['filename' => $file->getOriginalName(), 'height' => $h, 'max_height' => (int) $constraints['max_height']]);
        }

        // 5. Exact width
        if (isset($constraints['exact_width']) && is_numeric($constraints['exact_width']) && $w !== (int) $constraints['exact_width']) {
            throw ValidationException::forCode('ERR_DIMENSION_EXACT_WIDTH', ['filename' => $file->getOriginalName(), 'width' => $w, 'exact_width' => (int) $constraints['exact_width']]);
        }

        // 6. Exact height
        if (isset($constraints['exact_height']) && is_numeric($constraints['exact_height']) && $h !== (int) $constraints['exact_height']) {
            throw ValidationException::forCode('ERR_DIMENSION_EXACT_HEIGHT', ['filename' => $file->getOriginalName(), 'height' => $h, 'exact_height' => (int) $constraints['exact_height']]);
        }

        // 7. Ratio (ví dụ '16:9' hoặc 1.777)
        if (isset($constraints['ratio'])) {
            $expectedRatio = self::parseRatio($constraints['ratio']);
            if ($expectedRatio !== null && $h > 0) {
                $actualRatio = (float) ($w / $h);
                $tolerance = isset($constraints['ratio_tolerance']) && is_numeric($constraints['ratio_tolerance'])
                    ? (float) $constraints['ratio_tolerance']
                    : 0.05;
                if (abs($actualRatio - $expectedRatio) > $tolerance) {
                    throw ValidationException::forCode('ERR_DIMENSION_RATIO', ['filename' => $file->getOriginalName(), 'actual_ratio' => round($actualRatio, 2), 'expected_ratio' => round($expectedRatio, 2)]);
                }
            }
        }
    }

    private static function parseRatio(mixed $ratio): ?float
    {
        if (is_float($ratio) || is_int($ratio)) {
            return (float) $ratio;
        }

        if (!is_string($ratio)) {
            return null;
        }

        if (str_contains($ratio, ':') || str_contains($ratio, '/')) {
            $parts = preg_split('#[:/]#', $ratio);
            if ($parts !== false && count($parts) === 2 && (float) $parts[1] > 0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return is_numeric($ratio) ? (float) $ratio : null;
    }
}

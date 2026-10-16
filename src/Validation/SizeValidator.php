<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Core\UploadedFile;
use UploadHelper\Exceptions\ValidationException;

class SizeValidator
{
    /**
     * Chuyển đổi chuỗi kích thước ('5M', '500K', '1G') sang số bytes
     */
    public static function parseToBytes(mixed $size): ?int
    {
        if ($size === null || $size === '') {
            return null;
        }

        if (is_int($size)) {
            return $size;
        }

        if (!is_string($size)) {
            return null;
        }
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $value = (float) substr($size, 0, -1);

        if (!is_numeric($unit)) {
            return match ($unit) {
                'K' => (int) round($value * 1024),
                'M' => (int) round($value * 1024 * 1024),
                'G' => (int) round($value * 1024 * 1024 * 1024),
                'T' => (int) round($value * 1024 * 1024 * 1024 * 1024),
                default => (int) $size,
            };
        }

        return (int) $size;
    }

    /**
     * Kiểm tra dung lượng tệp tin có nằm trong giới hạn cho phép không
     *
     * @throws ValidationException
     */
    public static function validate(
        UploadedFile $file,
        int|string|null $maxSize = null,
        int|string|null $minSize = null,
    ): void {
        $actualBytes = $file->getSize();

        // 1. Kiểm tra kích thước tối đa
        $maxBytes = self::parseToBytes($maxSize);
        if ($maxBytes !== null && $actualBytes > $maxBytes) {
            $formattedMax = self::formatBytes($maxBytes);
            $formattedActual = $file->getFormattedSize();

            throw ValidationException::forCode('ERR_FILE_TOO_LARGE', [
                'filename' => $file->getOriginalName(),
                'actual_bytes' => $actualBytes,
                'max_bytes' => $maxBytes,
                'formatted_actual' => $formattedActual,
                'formatted_max' => $formattedMax,
            ]);
        }

        // 2. Kiểm tra kích thước tối thiểu
        $minBytes = self::parseToBytes($minSize);
        if ($minBytes !== null && $actualBytes < $minBytes) {
            $formattedMin = self::formatBytes($minBytes);
            $formattedActual = $file->getFormattedSize();

            throw ValidationException::forCode('ERR_FILE_TOO_SMALL', [
                'filename' => $file->getOriginalName(),
                'actual_bytes' => $actualBytes,
                'min_bytes' => $minBytes,
                'formatted_actual' => $formattedActual,
                'formatted_min' => $formattedMin,
            ]);
        }
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);

        $val = $bytes / (1 << (10 * $pow));

        return round($val, $precision) . ' ' . $units[$pow];
    }
}

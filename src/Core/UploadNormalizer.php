<?php

declare(strict_types=1);

namespace UploadHelper\Core;

class UploadNormalizer
{
    /**
     * Chuẩn hóa bất kỳ input nào (file đơn, mảng phẳng, mảng lồng nhau đa chiều)
     * thành danh sách các đối tượng UploadedFile.
     *
     * @param mixed $input
     * @param bool $ignoreNoFile Bỏ qua các file không được upload (UPLOAD_ERR_NO_FILE)
     * @return array<int, UploadedFile>
     */
    public static function normalize(mixed $input, bool $ignoreNoFile = true): array
    {
        if ($input === null || $input === false || $input === '') {
            return [];
        }

        if ($input instanceof UploadedFile) {
            return [$input];
        }

        if (!is_array($input)) {
            return [];
        }

        // Trường hợp 1: Nhận trực tiếp mảng $_FILES chứa nhiều trường gốc
        // ví dụ: ['avatar' => [...], 'gallery' => [...]]
        if (self::isFilesSuperglobal($input)) {
            $result = [];
            foreach ($input as $fieldKey => $fieldValue) {
                if (is_array($fieldValue)) {
                    $subFiles = self::normalizeFieldSpec($fieldValue, (string) $fieldKey, $ignoreNoFile);
                    $result = array_merge($result, $subFiles);
                }
            }
            return $result;
        }

        // Trường hợp 2: Nhận 1 trường $_FILES['field']
        if (self::isPhpFileStructure($input)) {
            return self::normalizeFieldSpec($input, null, $ignoreNoFile);
        }

        // Trường hợp 3: Nhận mảng các file đã được normalize hoặc danh sách các spec
        $result = [];
        foreach ($input as $key => $item) {
            if ($item instanceof UploadedFile) {
                $result[] = $item;
            } elseif (is_array($item)) {
                $sub = self::normalize($item, $ignoreNoFile);
                $result = array_merge($result, $sub);
            }
        }

        return $result;
    }

    /**
     * Chuẩn hóa một spec của trường upload (có thể là file đơn hoặc mảng ma trận)
     *
     * @param array<string, mixed> $spec
     * @return array<int, UploadedFile>
     */
    private static function normalizeFieldSpec(array $spec, ?string $prefix = null, bool $ignoreNoFile = true): array
    {
        $name = $spec['name'] ?? null;

        // Nếu 'name' không phải là mảng -> đây là 1 file đơn lẻ
        if (!is_array($name)) {
            $error = (int) ($spec['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($ignoreNoFile && $error === UPLOAD_ERR_NO_FILE) {
                return [];
            }

            return [
                new UploadedFile(
                    originalName: (string) ($spec['name'] ?? ''),
                    clientMime: (string) ($spec['type'] ?? 'application/octet-stream'),
                    size: (int) ($spec['size'] ?? 0),
                    tmpPath: (string) ($spec['tmp_name'] ?? ''),
                    errorCode: $error,
                    fieldKey: $prefix
                ),
            ];
        }

        // Nếu 'name' là mảng -> mảng nhiều file hoặc mảng lồng nhau
        $result = [];
        self::restructureArrayRecursive($spec, $result, $prefix, $ignoreNoFile);

        return $result;
    }

    /**
     * Đệ quy duyệt cây ma trận của PHP $_FILES để tái lập lại các UploadedFile
     *
     * @param array<string, mixed> $spec
     * @param array<int, UploadedFile> $result
     */
    private static function restructureArrayRecursive(
        array $spec,
        array &$result,
        ?string $currentKey = null,
        bool $ignoreNoFile = true
    ): void {
        $names = $spec['name'] ?? [];
        if (!is_array($names)) {
            return;
        }

        foreach ($names as $key => $subName) {
            $fieldKey = $currentKey !== null ? "{$currentKey}.{$key}" : (string) $key;

            $subSpec = [
                'name'     => $subName,
                'type'     => $spec['type'][$key] ?? 'application/octet-stream',
                'tmp_name' => $spec['tmp_name'][$key] ?? '',
                'error'    => $spec['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $spec['size'][$key] ?? 0,
            ];

            if (is_array($subName)) {
                // Tiếp tục đệ quy xuống cấp sâu hơn
                self::restructureArrayRecursive($subSpec, $result, $fieldKey, $ignoreNoFile);
            } else {
                $error = (int) $subSpec['error'];
                if ($ignoreNoFile && $error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $result[] = new UploadedFile(
                    originalName: (string) $subSpec['name'],
                    clientMime: (string) $subSpec['type'],
                    size: (int) $subSpec['size'],
                    tmpPath: (string) $subSpec['tmp_name'],
                    errorCode: $error,
                    fieldKey: $fieldKey
                );
            }
        }
    }

    /**
     * Kiểm tra mảng có cấu trúc các key chuẩn của PHP file upload hay không
     *
     * @param array<string, mixed> $array
     */
    public static function isPhpFileStructure(array $array): bool
    {
        return isset($array['name']) && (isset($array['tmp_name']) || isset($array['error']));
    }

    /**
     * Kiểm tra mảng có phải là toàn bộ siêu biến $_FILES hay không
     *
     * @param array<string, mixed> $array
     */
    public static function isFilesSuperglobal(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        if (self::isPhpFileStructure($array)) {
            return false;
        }

        foreach ($array as $value) {
            if (is_array($value) && self::isPhpFileStructure($value)) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace UploadHelper;

use UploadHelper\Core\BatchUploadBuilder;
use UploadHelper\Core\FileCollection;
use UploadHelper\Core\UploadBuilder;
use UploadHelper\Core\UploadedFile;
use UploadHelper\Core\UploadNormalizer;
use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\Remote\UrlDownloader;

class Upload
{
    /**
     * Khởi tạo xử lý một tệp tin duy nhất
     *
     * @param mixed $input (Có thể là $_FILES['field'], UploadedFile, hoặc đường dẫn)
     */
    public static function file(mixed $input): UploadBuilder
    {
        $normalized = UploadNormalizer::normalize($input);
        $first = $normalized[0] ?? null;

        return new UploadBuilder($first);
    }

    /**
     * Khởi tạo xử lý tập hợp mảng nhiều tệp tin
     *
     * @param mixed $input (Có thể là $_FILES['photos'], mảng lồng nhau, danh sách UploadedFile)
     */
    public static function files(mixed $input): BatchUploadBuilder
    {
        $normalized = UploadNormalizer::normalize($input);
        return new BatchUploadBuilder(new FileCollection($normalized));
    }

    /**
     * Đọc trực tiếp toàn bộ siêu biến $_FILES
     *
     * @param array<string, mixed>|null $files Mặc định lấy từ $_FILES
     */
    public static function fromGlobals(?array $files = null): BatchUploadBuilder
    {
        $source = $files ?? $_FILES;
        return self::files($source);
    }

    /**
     * Tạo đối tượng upload từ một tệp tin cục bộ có sẵn trên máy chủ
     */
    public static function fromPath(mixed $localFilePath, ?string $filename = null): UploadBuilder
    {
        if (!is_string($localFilePath) || $localFilePath === '' || !file_exists($localFilePath) || !is_readable($localFilePath)) {
            $pathStr = is_string($localFilePath) ? $localFilePath : get_debug_type($localFilePath);
            throw UploadException::fileNotReadable($pathStr);
        }

        $cleanName = $filename ?? basename($localFilePath);
        $size = filesize($localFilePath);
        $mime = 'application/octet-stream';

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $localFilePath);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }

        $uploadedFile = new UploadedFile(
            originalName: $cleanName,
            clientMime: $mime,
            size: $size !== false ? $size : 0,
            tmpPath: $localFilePath,
            errorCode: UPLOAD_ERR_OK,
        );

        return new UploadBuilder($uploadedFile);
    }

    /**
     * Tạo đối tượng upload từ chuỗi Base64 Data URI (ví dụ: 'data:image/png;base64,iVBORw...')
     */
    public static function fromBase64(mixed $base64String, ?string $filename = null): UploadBuilder
    {
        if (!is_string($base64String) || $base64String === '') {
            throw UploadException::invalidBase64();
        }
        $pattern = '/^data:([a-zA-Z0-9_.\/-]+);base64,(.*)\z/s';
        $mime = 'application/octet-stream';
        $encodedData = $base64String;

        if (preg_match($pattern, $base64String, $matches)) {
            $mime = $matches[1];
            $encodedData = $matches[2];
        }

        $binaryData = base64_decode($encodedData, true);
        if ($binaryData === false) {
            throw UploadException::invalidBase64();
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
            default      => 'bin',
        };

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_b64_' . uniqid('', true) . '.' . $ext;
        file_put_contents($tempPath, $binaryData);

        $defaultName = $filename ?? 'upload_' . uniqid('', false) . '.' . $ext;

        $uploadedFile = new UploadedFile(
            originalName: $defaultName,
            clientMime: $mime,
            size: strlen($binaryData),
            tmpPath: $tempPath,
            errorCode: UPLOAD_ERR_OK,
        );

        return new UploadBuilder($uploadedFile);
    }

    /**
     * Tải và xử lý tệp tin trực tiếp từ một địa chỉ URL từ xa
     *
     * Mọi bước redirect đều được kiểm tra lại bằng SsrfGuard và kết nối được ghim vào IP đã kiểm tra.
     */
    public static function fromUrl(
        mixed $url,
        ?string $filename = null,
        int $timeout = 15,
        int $maxDownloadBytes = 52_428_800,
        bool $allowPrivateIp = false,
        int $maxRedirects = UrlDownloader::DEFAULT_MAX_REDIRECTS,
    ): UploadBuilder {
        if (!is_string($url) || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw SecurityException::forCode('ERR_SSRF_INVALID_URL', ['url' => is_string($url) ? $url : get_debug_type($url)]);
        }

        $urlPath = parse_url($url, PHP_URL_PATH);
        $suggestedName = $filename ?? (is_string($urlPath) && basename($urlPath) !== '' ? basename($urlPath) : 'download_' . uniqid('', false));

        $ext = pathinfo($suggestedName, PATHINFO_EXTENSION);
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_url_' . uniqid('', true) . ($ext !== '' ? '.' . $ext : '');

        $downloader = new UrlDownloader($timeout, $maxDownloadBytes, $allowPrivateIp, $maxRedirects);

        try {
            $bytesDownloaded = $downloader->downloadTo($url, $tempPath);
        } catch (\Throwable $e) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
            throw $e;
        }

        $uploadedFile = new UploadedFile(
            originalName: $suggestedName,
            clientMime: 'application/octet-stream',
            size: $bytesDownloaded,
            tmpPath: $tempPath,
            errorCode: UPLOAD_ERR_OK,
        );

        return new UploadBuilder($uploadedFile);
    }

    /**
     * Tích hợp với PSR-7 UploadedFileInterface
     *
     * @param object|array<mixed> $psr7Input
     */
    public static function fromPsr7(object|array $psr7Input): UploadBuilder|BatchUploadBuilder
    {
        if (is_array($psr7Input)) {
            $files = [];
            foreach ($psr7Input as $item) {
                if (is_object($item) && method_exists($item, 'getClientFilename')) {
                    $files[] = self::convertPsr7ToUploadedFile($item);
                }
            }
            return new BatchUploadBuilder(new FileCollection($files));
        }

        if (method_exists($psr7Input, 'getClientFilename')) {
            return new UploadBuilder(self::convertPsr7ToUploadedFile($psr7Input));
        }

        throw UploadException::unsupportedPsr7Input();
    }

    private static function convertPsr7ToUploadedFile(object $psr7): UploadedFile
    {
        $name = self::psr7Scalar($psr7, 'getClientFilename', 'unnamed');
        $mime = self::psr7Scalar($psr7, 'getClientMediaType', 'application/octet-stream');
        $size = (int) self::psr7Scalar($psr7, 'getSize', '0');
        $error = (int) self::psr7Scalar($psr7, 'getError', (string) UPLOAD_ERR_OK);

        $tempPath = '';
        if ($error === UPLOAD_ERR_OK) {
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uh_psr7_' . uniqid('', true);
            $stream = method_exists($psr7, 'getStream') ? $psr7->getStream() : null;
            file_put_contents($tempPath, is_string($stream) || $stream instanceof \Stringable ? (string) $stream : '');
        }

        return new UploadedFile(
            originalName: $name,
            clientMime: $mime,
            size: $size,
            tmpPath: $tempPath,
            errorCode: $error,
        );
    }

    /**
     * Call an optional PSR-7 getter and return its scalar result as a string, or the default.
     */
    private static function psr7Scalar(object $psr7, string $method, string $default): string
    {
        $value = method_exists($psr7, $method) ? $psr7->{$method}() : null;

        return is_scalar($value) ? (string) $value : $default;
    }
}

<?php

declare(strict_types=1);

namespace UploadHelper\Remote;

use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\Validation\SsrfGuard;

/**
 * Downloads a remote file with SSRF protection.
 *
 * - Redirects are never followed by the stream wrapper; each hop is validated by SsrfGuard.
 * - The connection is pinned to the IP address SsrfGuard vetted (Host header and TLS peer name
 *   keep the original host), so a second DNS lookup cannot point the request elsewhere.
 */
class UrlDownloader
{
    public const DEFAULT_MAX_REDIRECTS = 3;

    private const CHUNK_SIZE = 8192;

    /** @var (callable(string): array<int, string>)|null */
    private $resolver;

    /**
     * @param (callable(string): array<int, string>)|null $resolver Custom DNS resolver, mainly for tests
     */
    public function __construct(
        private readonly int $timeout = 15,
        private readonly int $maxDownloadBytes = 52_428_800,
        private readonly bool $allowPrivateIp = false,
        private readonly int $maxRedirects = self::DEFAULT_MAX_REDIRECTS,
        ?callable $resolver = null,
    ) {
        $this->resolver = $resolver;
    }

    /**
     * Download the URL into the given local path.
     *
     * @return int Number of bytes written
     *
     * @throws UploadException On SSRF violations (SecurityException), HTTP errors, redirect loops or size limits
     */
    public function downloadTo(string $url, string $destinationPath): int
    {
        $currentUrl = $url;

        for ($redirects = 0; ; $redirects++) {
            $ips = SsrfGuard::assertSafeUrl($currentUrl, $this->allowPrivateIp, $this->resolver);

            [$stream, $headers] = $this->openStream(
                self::pinnedUrl($currentUrl, $ips[0]),
                $this->contextOptions($currentUrl),
            );

            $status = self::statusCode($headers);

            if ($status >= 300 && $status < 400) {
                fclose($stream);

                $location = self::headerValue($headers, 'location');
                if ($location === null || $location === '') {
                    throw UploadException::forCode('ERR_URL_DOWNLOAD_FAILED', ['url' => $currentUrl, 'status' => $status]);
                }

                if ($redirects >= $this->maxRedirects) {
                    throw UploadException::forCode('ERR_URL_TOO_MANY_REDIRECTS', ['url' => $url, 'max_redirects' => $this->maxRedirects]);
                }

                $currentUrl = self::resolveLocation($currentUrl, $location);
                continue;
            }

            if ($status < 200 || $status >= 300) {
                fclose($stream);
                throw UploadException::forCode('ERR_URL_DOWNLOAD_FAILED', ['url' => $currentUrl, 'status' => $status]);
            }

            return $this->copyLimited($stream, $destinationPath, $currentUrl);
        }
    }

    /**
     * Open the HTTP stream without following redirects.
     *
     * @param array<string, array<string, mixed>> $contextOptions
     * @return array{0: resource, 1: list<string>} The body stream and the raw response header lines
     */
    protected function openStream(string $requestUrl, array $contextOptions): array
    {
        $stream = @fopen($requestUrl, 'rb', false, stream_context_create($contextOptions));
        if ($stream === false) {
            throw UploadException::forCode('ERR_URL_DOWNLOAD_FAILED', ['url' => $requestUrl]);
        }

        $meta = stream_get_meta_data($stream);
        $headers = array_values(array_filter((array) ($meta['wrapper_data'] ?? []), 'is_string'));

        return [$stream, $headers];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function contextOptions(string $url): array
    {
        $parts = self::parseUrl($url);
        $host = trim($parts['host'], '[]');
        $hostHeader = (str_contains($host, ':') ? "[{$host}]" : $host) . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return [
            'http' => [
                'method'          => 'GET',
                'timeout'         => (float) $this->timeout,
                'follow_location' => 0,
                'ignore_errors'   => true,
                'header'          => "Host: {$hostHeader}\r\n",
                'user_agent'      => 'Mozilla/5.0 (compatible; PhpUploadHelper/1.0)',
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'peer_name'        => $host,
            ],
        ];
    }

    /**
     * @param resource $stream
     */
    private function copyLimited($stream, string $destinationPath, string $url): int
    {
        $dest = @fopen($destinationPath, 'wb');
        if ($dest === false) {
            fclose($stream);
            throw UploadException::tempFileNotCreated($destinationPath);
        }

        $bytes = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK_SIZE);
                if ($chunk === false) {
                    break;
                }

                $bytes += strlen($chunk);
                if ($bytes > $this->maxDownloadBytes) {
                    throw UploadException::forCode('ERR_URL_DOWNLOAD_TOO_LARGE', ['url' => $url, 'max_bytes' => $this->maxDownloadBytes]);
                }

                fwrite($dest, $chunk);
            }
        } finally {
            fclose($stream);
            fclose($dest);
        }

        return $bytes;
    }

    /**
     * Replace the host of the URL with the vetted IP; userinfo and fragment are dropped.
     */
    private static function pinnedUrl(string $url, string $ip): string
    {
        $parts = self::parseUrl($url);
        $ipHost = str_contains($ip, ':') ? "[{$ip}]" : $ip;
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return strtolower($parts['scheme']) . '://' . $ipHost . $port . ($path === '' ? '/' : $path) . $query;
    }

    /**
     * @param list<string> $headers
     */
    private static function statusCode(array $headers): int
    {
        $status = 0;
        foreach ($headers as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return $status;
    }

    /**
     * @param list<string> $headers
     */
    private static function headerValue(array $headers, string $name): ?string
    {
        $value = null;
        $prefix = strtolower($name) . ':';
        foreach ($headers as $line) {
            if (str_starts_with(strtolower($line), $prefix)) {
                $value = trim(substr($line, strlen($prefix)));
            }
        }

        return $value;
    }

    private static function resolveLocation(string $baseUrl, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $location) === 1) {
            return $location;
        }

        $base = self::parseUrl($baseUrl);
        $scheme = $base['scheme'];
        $authority = $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$authority}{$location}";
        }

        $basePath = $base['path'] ?? '/';
        $directory = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);

        return "{$scheme}://{$authority}" . ($directory === '' ? '/' : $directory) . $location;
    }

    /**
     * Parse a URL that SsrfGuard has already accepted (scheme and host are guaranteed).
     *
     * @return array{scheme: string, host: string, port?: int, path?: string, query?: string}
     */
    private static function parseUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw SecurityException::forCode('ERR_SSRF_INVALID_URL', ['url' => $url]);
        }

        return $parts;
    }
}

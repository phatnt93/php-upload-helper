<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Remote;

use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\UploadException;
use UploadHelper\Tests\TestCase;

final class UrlDownloaderTest extends TestCase
{
    private const PUBLIC_IP = '93.184.216.34';

    public function testDownloadsBodyAndPinsConnectionToVettedIp(): void
    {
        $downloader = $this->downloader([
            [['HTTP/1.1 200 OK', 'Content-Type: image/png'], 'PNGDATA'],
        ]);
        $target = $this->targetPath();

        $bytes = $downloader->downloadTo('https://cdn.example.com/img/photo.png?v=2', $target);

        $this->assertSame(7, $bytes);
        $this->assertSame('PNGDATA', file_get_contents($target));

        [$requestUrl, $options] = $downloader->requests[0];
        $this->assertSame('https://' . self::PUBLIC_IP . '/img/photo.png?v=2', $requestUrl);
        $this->assertSame("Host: cdn.example.com\r\n", $options['http']['header']);
        $this->assertSame(0, $options['http']['follow_location']);
        $this->assertSame('cdn.example.com', $options['ssl']['peer_name']);
        $this->assertTrue($options['ssl']['verify_peer']);
    }

    public function testRedirectToLoopbackIsBlocked(): void
    {
        $downloader = $this->downloader([
            [['HTTP/1.1 302 Found', 'Location: http://127.0.0.1/admin'], ''],
        ]);

        try {
            $downloader->downloadTo('http://cdn.example.com/a.jpg', $this->targetPath());
            $this->fail('Redirect to loopback must be blocked.');
        } catch (SecurityException $e) {
            $this->assertSame('ERR_SSRF_BLOCKED', $e->getErrorCode());
        }

        $this->assertCount(1, $downloader->requests, 'The blocked hop must never be requested.');
    }

    public function testRedirectToCloudMetadataIsBlocked(): void
    {
        $downloader = $this->downloader([
            [['HTTP/1.1 301 Moved Permanently', 'Location: http://169.254.169.254/latest/meta-data/'], ''],
        ]);

        $this->expectException(SecurityException::class);
        $downloader->downloadTo('http://cdn.example.com/a.jpg', $this->targetPath());
    }

    public function testRedirectToFileSchemeIsBlocked(): void
    {
        $downloader = $this->downloader([
            [['HTTP/1.1 302 Found', 'Location: file:///etc/passwd'], ''],
        ]);

        try {
            $downloader->downloadTo('http://cdn.example.com/a.jpg', $this->targetPath());
            $this->fail('Redirect to file:// must be blocked.');
        } catch (SecurityException $e) {
            $this->assertSame('ERR_SSRF_DISALLOWED_SCHEME', $e->getErrorCode());
        }
    }

    public function testHostResolvingToPrivateIpIsBlocked(): void
    {
        $downloader = new RecordingUrlDownloader([], static fn(string $host): array => ['10.0.0.5']);

        $this->expectException(SecurityException::class);
        $downloader->downloadTo('http://internal.example.com/a.jpg', $this->targetPath());
    }

    public function testRelativeRedirectIsResolvedAndFollowed(): void
    {
        $downloader = $this->downloader([
            [['HTTP/1.1 302 Found', 'Location: /files/real.jpg'], ''],
            [['HTTP/1.1 200 OK'], 'JPEG'],
        ]);
        $target = $this->targetPath();

        $downloader->downloadTo('http://cdn.example.com:8080/old/a.jpg', $target);

        $this->assertSame('JPEG', file_get_contents($target));
        $this->assertSame('http://' . self::PUBLIC_IP . ':8080/files/real.jpg', $downloader->requests[1][0]);
        $this->assertSame("Host: cdn.example.com:8080\r\n", $downloader->requests[1][1]['http']['header']);
    }

    public function testTooManyRedirectsThrows(): void
    {
        $loop = [['HTTP/1.1 302 Found', 'Location: http://cdn.example.com/next'], ''];
        $downloader = $this->downloader([$loop, $loop, $loop], maxRedirects: 2);

        try {
            $downloader->downloadTo('http://cdn.example.com/start', $this->targetPath());
            $this->fail('Redirect loop must stop.');
        } catch (UploadException $e) {
            $this->assertSame('ERR_URL_TOO_MANY_REDIRECTS', $e->getErrorCode());
        }

        $this->assertCount(3, $downloader->requests);
    }

    public function testHttpErrorStatusThrows(): void
    {
        $downloader = $this->downloader([
            [['HTTP/1.1 404 Not Found'], 'missing'],
        ]);

        try {
            $downloader->downloadTo('http://cdn.example.com/a.jpg', $this->targetPath());
            $this->fail('HTTP 404 must fail.');
        } catch (UploadException $e) {
            $this->assertSame('ERR_URL_DOWNLOAD_FAILED', $e->getErrorCode());
            $this->assertSame(404, $e->getContext()['status']);
        }
    }

    public function testDownloadLargerThanLimitThrows(): void
    {
        $downloader = $this->downloader([
            [['HTTP/1.1 200 OK'], str_repeat('A', 100)],
        ], maxBytes: 10);

        try {
            $downloader->downloadTo('http://cdn.example.com/a.jpg', $this->targetPath());
            $this->fail('Oversized download must fail.');
        } catch (UploadException $e) {
            $this->assertSame('ERR_URL_DOWNLOAD_TOO_LARGE', $e->getErrorCode());
        }
    }

    /**
     * @param list<array{0: list<string>, 1: string}> $responses
     */
    private function downloader(array $responses, int $maxRedirects = 3, int $maxBytes = 1024): RecordingUrlDownloader
    {
        return new RecordingUrlDownloader(
            $responses,
            static fn(string $host): array => [self::PUBLIC_IP],
            $maxRedirects,
            $maxBytes,
        );
    }

    private function targetPath(): string
    {
        $path = $this->createTempFile('', 'bin');
        @unlink($path);

        return $path;
    }
}

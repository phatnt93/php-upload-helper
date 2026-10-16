<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Remote;

use UploadHelper\Remote\UrlDownloader;

/**
 * Replays scripted HTTP responses instead of touching the network.
 */
final class RecordingUrlDownloader extends UrlDownloader
{
    /** @var list<array{0: string, 1: array<string, array<string, mixed>>}> */
    public array $requests = [];

    /**
     * @param list<array{0: list<string>, 1: string}> $responses
     * @param callable(string): array<int, string> $resolver
     */
    public function __construct(
        private array $responses,
        callable $resolver,
        int $maxRedirects = 3,
        int $maxBytes = 1024,
    ) {
        parent::__construct(5, $maxBytes, false, $maxRedirects, $resolver);
    }

    protected function openStream(string $requestUrl, array $contextOptions): array
    {
        $this->requests[] = [$requestUrl, $contextOptions];

        $response = array_shift($this->responses);
        if ($response === null) {
            throw new \LogicException("Unexpected request to {$requestUrl}");
        }

        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Cannot open memory stream.');
        }
        fwrite($stream, $response[1]);
        rewind($stream);

        return [$stream, $response[0]];
    }
}

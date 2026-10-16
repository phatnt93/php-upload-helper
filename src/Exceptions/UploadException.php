<?php

declare(strict_types=1);

namespace UploadHelper\Exceptions;

use RuntimeException;
use Throwable;
use UploadHelper\I18n\Translator;

/**
 * Base exception of the library. Carries a stable error code and context
 * that the result layer translates into end-user messages.
 */
class UploadException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    final public function __construct(
        string $message = '',
        private readonly string $errorCode = 'ERR_PROCESS_FAILED',
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Build an exception whose (English) message comes from the 'en' dictionary entry of the error code.
     *
     * @param array<string, mixed> $context
     */
    public static function forCode(string $errorCode, array $context = [], ?Throwable $previous = null): static
    {
        return new static(Translator::translate($errorCode, $context, Translator::DEFAULT_LOCALE), $errorCode, $context, 0, $previous);
    }

    public static function fileNotReadable(string $path): static
    {
        return new static("Local file does not exist or is not readable: {$path}", 'ERR_PROCESS_FAILED', ['path' => $path]);
    }

    public static function invalidBase64(): static
    {
        return new static('Base64 payload is invalid or corrupted.', 'ERR_PROCESS_FAILED');
    }

    public static function unsupportedPsr7Input(): static
    {
        return new static('Input is not a PSR-7 UploadedFileInterface instance or a list of them.', 'ERR_PROCESS_FAILED');
    }

    public static function tempFileNotCreated(string $path): static
    {
        return new static("Could not create temporary file: {$path}", 'ERR_PROCESS_FAILED', ['path' => $path]);
    }

    public static function destinationNotConfigured(): static
    {
        return new static('No destination directory configured for saving the file.', 'ERR_PROCESS_FAILED');
    }

    public static function directoryNotCreated(string $directory): static
    {
        return new static("Could not create destination directory: {$directory}", 'ERR_PROCESS_FAILED', ['directory' => $directory]);
    }

    public static function invalidDestination(string $directory): static
    {
        return new static("Destination directory path is invalid: {$directory}", 'ERR_PROCESS_FAILED', ['directory' => $directory]);
    }

    public static function fileExists(string $filename): static
    {
        return static::forCode('ERR_FILE_EXISTS', ['filename' => $filename]);
    }

    public static function sourceMissing(string $path): static
    {
        return new static("Source file does not exist: {$path}", 'ERR_PROCESS_FAILED', ['path' => $path]);
    }

    public static function saveFailed(string $path): static
    {
        return new static("Could not save file to destination: {$path}", 'ERR_PROCESS_FAILED', ['path' => $path]);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

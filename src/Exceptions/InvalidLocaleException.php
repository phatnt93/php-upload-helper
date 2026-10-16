<?php

declare(strict_types=1);

namespace UploadHelper\Exceptions;

use InvalidArgumentException;

final class InvalidLocaleException extends InvalidArgumentException
{
    public static function forLocale(string $locale): self
    {
        return new self("Invalid locale code '{$locale}'. Expected ISO 639-1 such as 'en', 'vi' or 'en_US'.");
    }
}

<?php

declare(strict_types=1);

namespace UploadHelper\Exceptions;

/**
 * The uploaded file or remote source was rejected by a security guard.
 */
class SecurityException extends ValidationException
{
}

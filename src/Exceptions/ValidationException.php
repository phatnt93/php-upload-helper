<?php

declare(strict_types=1);

namespace UploadHelper\Exceptions;

/**
 * The uploaded file failed a validation rule (size, extension, MIME, dimensions...).
 */
class ValidationException extends UploadException
{
}

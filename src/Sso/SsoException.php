<?php

namespace Novvor\Identity\Sso;

use RuntimeException;

final class SsoException extends RuntimeException
{
    public function __construct(
        public readonly string $codeName,
        public readonly int $httpStatus,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

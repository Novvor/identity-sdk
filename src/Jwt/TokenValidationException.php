<?php

namespace Novvor\Identity\Jwt;

use RuntimeException;

final class TokenValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $codeName,
        string $message,
        public readonly int $httpStatus = 401,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalid(string $codeName, string $message): self
    {
        return new self($codeName, $message, 401);
    }
}

<?php

namespace Novvor\Identity\Contracts;

use Novvor\Identity\IdentityConfig;

interface IdentityTokenValidationPolicyInterface
{
    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    public function assertValidPayload(IdentityConfig $config, string $expectedAud, array $header, array $payload): void;
}


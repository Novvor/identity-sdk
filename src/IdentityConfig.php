<?php

namespace Novvor\Identity;

final class IdentityConfig
{
    public function __construct(
        public readonly string $issuer,
        public readonly string $jwksUrl,
        public readonly string $exchangeUrl,
        public readonly string $apiKey,
        public readonly int $jwksCacheTtlSeconds = 300,
        public readonly int $clockSkewSeconds = 30,
        public readonly float $httpTimeoutSeconds = 5.0,
    ) {
    }
}

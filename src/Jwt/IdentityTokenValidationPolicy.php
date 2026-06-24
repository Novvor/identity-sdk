<?php

namespace Novvor\Identity\Jwt;

use Novvor\Identity\Contracts\IdentityTokenValidationPolicyInterface;
use Novvor\Identity\IdentityConfig;
use Novvor\Identity\Jwt\TokenValidationException;

final class IdentityTokenValidationPolicy implements IdentityTokenValidationPolicyInterface
{
    private const SUPPORTED_VERSION = '1.0';

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    public function assertValidPayload(IdentityConfig $config, string $expectedAud, array $header, array $payload): void
    {
        $alg = (string) ($header['alg'] ?? '');
        $kid = (string) ($header['kid'] ?? '');

        if (strtoupper($alg) !== 'RS256' || $kid === '') {
            throw TokenValidationException::invalid('invalid_header', 'Invalid token header.');
        }

        $issuer = (string) ($payload['iss'] ?? '');
        if ($issuer !== $config->issuer) {
            throw TokenValidationException::invalid('invalid_issuer', 'Invalid token issuer.');
        }

        $aud = $payload['aud'] ?? null;
        if (! is_string($aud) || ! hash_equals($expectedAud, $aud)) {
            throw TokenValidationException::invalid('invalid_audience', 'Invalid token audience.');
        }

        $tenantId = (string) ($payload['tenant_id'] ?? '');
        if ($tenantId === '') {
            throw TokenValidationException::invalid('missing_tenant_id', 'Invalid token payload.');
        }

        $jti = (string) ($payload['jti'] ?? '');
        if ($jti === '') {
            throw TokenValidationException::invalid('missing_jti', 'Invalid token payload.');
        }

        $ver = $payload['ver'] ?? null;
        if ($ver === null) {
            $ver = self::SUPPORTED_VERSION;
        }

        if (! is_string($ver) || $ver === '') {
            throw TokenValidationException::invalid('invalid_version', 'Invalid token version.');
        }

        if ($ver !== self::SUPPORTED_VERSION) {
            throw new TokenValidationException('unsupported_version', 'Unsupported token version.', 401);
        }
    }
}


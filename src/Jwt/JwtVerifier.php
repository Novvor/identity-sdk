<?php

namespace Novvor\Identity\Jwt;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\ClientInterface;
use Novvor\Identity\Contracts\IdentityTokenValidationPolicyInterface;
use Novvor\Identity\IdentityConfig;
use Psr\SimpleCache\CacheInterface;

final class JwtVerifier
{
    /** @var array<string, array{expires_at:int, jwks:array<string,mixed>}> */
    private array $jwksMemoryCache = [];

    public function __construct(
        private readonly IdentityConfig $config,
        private readonly ClientInterface $http,
        private readonly ?JwksClient $jwksClient = null,
        private readonly ?CacheInterface $cache = null,
        private readonly ?IdentityTokenValidationPolicyInterface $tokenPolicy = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $jwt, string $expectedAud): array
    {
        if ($jwt === '') {
            throw TokenValidationException::invalid('missing_token', 'Missing token.');
        }

        [$header, $payload] = $this->decodeHeaderAndPayload($jwt);
        $kid = (string) ($header['kid'] ?? '');

        $now = time();
        $skew = $this->config->clockSkewSeconds;

        $nbf = (int) ($payload['nbf'] ?? 0);
        $exp = (int) ($payload['exp'] ?? 0);

        if ($nbf !== 0 && $now + $skew < $nbf) {
            throw TokenValidationException::invalid('token_not_valid_yet', 'Token not valid yet.');
        }

        if ($exp === 0 || $now - $skew >= $exp) {
            throw TokenValidationException::invalid('token_expired', 'Token expired.');
        }

        ($this->tokenPolicy ?? new IdentityTokenValidationPolicy())->assertValidPayload(
            $this->config,
            $expectedAud,
            $header,
            $payload,
        );

        $jwks = $this->getJwks(forceRefresh: false);

        $keySet = JWK::parseKeySet($jwks);

        if (! array_key_exists($kid, $keySet)) {
            $jwks = $this->getJwks(forceRefresh: true);
            $keySet = JWK::parseKeySet($jwks);
        }

        $key = $keySet[$kid] ?? null;
        if (! $key instanceof Key) {
            throw TokenValidationException::invalid('unknown_signing_key', 'Unknown signing key.');
        }

        try {
            JWT::decode($jwt, $key);
        } catch (\Throwable $e) {
            throw TokenValidationException::invalid('invalid_signature', 'Invalid token signature.');
        }

        return $payload;
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function decodeHeaderAndPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw TokenValidationException::invalid('malformed_token', 'Malformed token.');
        }

        $headerJson = $this->base64UrlDecode($parts[0]);
        $payloadJson = $this->base64UrlDecode($parts[1]);

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (! is_array($header) || ! is_array($payload)) {
            throw TokenValidationException::invalid('malformed_token', 'Malformed token.');
        }

        return [$header, $payload];
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    /** @return array<string,mixed> */
    private function getJwks(bool $forceRefresh): array
    {
        $cacheKey = sha1($this->config->jwksUrl);
        $now = time();

        if (! $forceRefresh && $this->cache) {
            $cached = $this->cache->get('novvor_identity.jwks.' . $cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        if (! $forceRefresh && isset($this->jwksMemoryCache[$cacheKey])) {
            $cached = $this->jwksMemoryCache[$cacheKey];
            if ($cached['expires_at'] > $now) {
                return $cached['jwks'];
            }
        }

        $client = $this->jwksClient ?? new JwksClient($this->http);
        $jwks = $client->fetch($this->config->jwksUrl, $this->config->httpTimeoutSeconds);

        $ttl = max(1, $this->config->jwksCacheTtlSeconds);
        $this->jwksMemoryCache[$cacheKey] = [
            'expires_at' => $now + $ttl,
            'jwks' => $jwks,
        ];

        if ($this->cache) {
            $this->cache->set('novvor_identity.jwks.' . $cacheKey, $jwks, $ttl);
        }

        return $jwks;
    }
}

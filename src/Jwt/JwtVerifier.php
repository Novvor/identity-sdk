<?php

namespace Novvor\Identity\Jwt;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\ClientInterface;
use Novvor\Identity\IdentityConfig;
use Psr\SimpleCache\CacheInterface;

final class JwtVerifier
{
    private const SUPPORTED_VERSION = '1.0';

    /** @var array<string, array{expires_at:int, jwks:array<string,mixed>}> */
    private array $jwksMemoryCache = [];

    public function __construct(
        private readonly IdentityConfig $config,
        private readonly ClientInterface $http,
        private readonly ?JwksClient $jwksClient = null,
        private readonly ?CacheInterface $cache = null,
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

        $alg = (string) ($header['alg'] ?? '');
        $kid = (string) ($header['kid'] ?? '');

        if (strtoupper($alg) !== 'RS256' || $kid === '') {
            throw TokenValidationException::invalid('invalid_header', 'Invalid token header.');
        }

        $issuer = (string) ($payload['iss'] ?? '');
        if ($issuer !== $this->config->issuer) {
            throw TokenValidationException::invalid('invalid_issuer', 'Invalid token issuer.');
        }

        $aud = $payload['aud'] ?? null;
        if (! is_string($aud) || ! hash_equals($expectedAud, $aud)) {
            throw TokenValidationException::invalid('invalid_audience', 'Invalid token audience.');
        }

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

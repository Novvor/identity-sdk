<?php

namespace Novvor\Identity\Jwt;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

final class JwksClient
{
    public function __construct(
        private readonly ClientInterface $http,
    ) {
    }

    /** @return array<string, mixed> */
    public function fetch(string $jwksUrl, float $timeoutSeconds = 5.0): array
    {
        try {
            $response = $this->http->request('GET', $jwksUrl, [
                'timeout' => $timeoutSeconds,
                'connect_timeout' => $timeoutSeconds,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw TokenValidationException::invalid('jwks_fetch_failed', 'Failed to fetch JWKS.');
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw TokenValidationException::invalid('jwks_fetch_failed', 'Failed to fetch JWKS.');
        }

        $body = (string) $response->getBody();
        $json = json_decode($body, true);

        $json = is_array($json) ? $json : [];
        $keys = $json['keys'] ?? null;
        if (! is_array($keys)) {
            return ['keys' => []];
        }

        return $json;
    }
}

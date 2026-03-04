<?php

namespace Novvor\Identity;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Novvor\Identity\Jwt\JwtVerifier;
use Novvor\Identity\Sso\SsoExchangeClient;
use Psr\SimpleCache\CacheInterface;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdentityConfig::class, function (): IdentityConfig {
            $config = (array) config('identity', []);

            return new IdentityConfig(
                issuer: (string) ($config['issuer'] ?? ''),
                jwksUrl: (string) ($config['jwks_url'] ?? ''),
                exchangeUrl: (string) ($config['exchange_url'] ?? ''),
                apiKey: (string) ($config['api_key'] ?? ''),
                jwksCacheTtlSeconds: (int) ($config['jwks_cache_ttl_seconds'] ?? 300),
                clockSkewSeconds: (int) ($config['clock_skew_seconds'] ?? 30),
                httpTimeoutSeconds: (float) ($config['http_timeout_seconds'] ?? 5.0),
            );
        });

        $this->app->singleton(Client::class, function (): Client {
            return new Client();
        });

        $this->app->singleton(JwtVerifier::class, function ($app): JwtVerifier {
            $cache = null;
            $resolvedCache = $app->bound('cache.store') ? $app->make('cache.store') : null;
            if ($resolvedCache instanceof CacheInterface) {
                $cache = $resolvedCache;
            }

            return new JwtVerifier(
                config: $app->make(IdentityConfig::class),
                http: $app->make(Client::class),
                cache: $cache,
            );
        });

        $this->app->singleton(SsoExchangeClient::class, function ($app): SsoExchangeClient {
            return new SsoExchangeClient(
                config: $app->make(IdentityConfig::class),
                http: $app->make(Client::class),
            );
        });
    }
}

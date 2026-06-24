<?php

namespace Novvor\Identity;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Novvor\Identity\Auth\IdentityErrorSurfaceRedirector;
use Novvor\Identity\Contracts\IdentitySessionMapperInterface;
use Novvor\Identity\Contracts\IdentityTokenValidationPolicyInterface;
use Novvor\Identity\Jwt\IdentityTokenValidationPolicy;
use Novvor\Identity\Jwt\JwtVerifier;
use Novvor\Identity\Session\IdentitySessionMapper;
use Novvor\Identity\Sso\SsoExchangeClient;
use Psr\SimpleCache\CacheInterface;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/identity.php', 'identity');

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
                tokenPolicy: $app->make(IdentityTokenValidationPolicyInterface::class),
            );
        });

        $this->app->singleton(SsoExchangeClient::class, function ($app): SsoExchangeClient {
            return new SsoExchangeClient(
                config: $app->make(IdentityConfig::class),
                http: $app->make(Client::class),
            );
        });

        $identityConfig = (array) config('identity', []);
        $tokenValidationPolicy = (string) ($identityConfig['token_validation_policy'] ?? IdentityTokenValidationPolicy::class);
        if (is_subclass_of($tokenValidationPolicy, IdentityTokenValidationPolicyInterface::class) || is_a($tokenValidationPolicy, IdentityTokenValidationPolicyInterface::class, true)) {
            $this->app->bind(IdentityTokenValidationPolicyInterface::class, $tokenValidationPolicy);
        } else {
            $this->app->bind(IdentityTokenValidationPolicyInterface::class, IdentityTokenValidationPolicy::class);
        }

        $sessionMapper = (string) ($identityConfig['session_mapper'] ?? IdentitySessionMapper::class);
        if (is_subclass_of($sessionMapper, IdentitySessionMapperInterface::class) || is_a($sessionMapper, IdentitySessionMapperInterface::class, true)) {
            $this->app->bind(IdentitySessionMapperInterface::class, $sessionMapper);
        } else {
            $this->app->bind(IdentitySessionMapperInterface::class, IdentitySessionMapper::class);
        }

        $this->app->singleton(IdentityErrorSurfaceRedirector::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/identity.php' => config_path('identity.php'),
            ], 'novvor-identity-config');
        }
    }
}

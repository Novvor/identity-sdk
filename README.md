# Novvor Identity SDK

SDK oficial para integrar apps Laravel con Novvor Cloud Identity.

Incluye:

- verificación JWT/JWKS;
- cliente SSO exchange;
- mapeo de sesión;
- superficie estándar de errores Identity para apps externas.

## Identity Error Surface

Las apps no deben renderizar páginas propias de error Identity/OIDC. Deben redirigir a la superficie oficial de Identity:

```php
use Novvor\Identity\Auth\IdentityErrorSurfaceRedirector;

return app(IdentityErrorSurfaceRedirector::class)->redirect([
    'code' => 'identity_provider_unreachable',
    'message' => 'Identity no respondió durante el intercambio seguro.',
    'correlation_id' => (string) request()->attributes->get('correlation_id', ''),
]);
```

Config recomendada:

```php
// config/identity.php
return [
    'error_surface' => [
        'identity_base_url' => env('IDENTITY_ERROR_SURFACE_BASE_URL', 'https://identity.enixconsole.test'),
        'app_key' => env('IDENTITY_ERROR_APP_KEY', 'orbit-intelligence'),
        'return_url' => env('IDENTITY_ERROR_RETURN_URL'),
        'default_code' => 'identity_login_failed',
        'default_message' => 'No se pudo completar el inicio de sesión.',
        'path' => '/auth/identity/error',
    ],
];
```

No enviar tokens, secrets, authorization codes, OTP/MFA codes ni stack traces en `message`.

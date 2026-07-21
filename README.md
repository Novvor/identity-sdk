# Novvor Identity SDK

> [!WARNING]
> This legacy Laravel compatibility package is deprecated and no longer receives
> feature development. New integrations must use
> [`novvor/identity-sdk-php`](https://github.com/Novvor/identity-sdk-php).
> Protocol constants are published separately as
> [`novvor/identity-contracts`](https://github.com/Novvor/identity-contracts),
> and test fakes as
> [`novvor/identity-sdk-testing`](https://github.com/Novvor/identity-sdk-testing).

## Migration

Do not install both SDK generations in a new application. Migrate authentication
flows to the OIDC Authorization Code with PKCE APIs in `identity-sdk-php`, retain
server-side validation of state and nonce, and remove this package after all
legacy namespace references have been replaced.

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

---

## Estado de distribución

`novvor/identity-sdk` es un paquete de compatibilidad de la línea `1.x`. Debe
consumirse desde releases taggeados y un repositorio privado. No contiene APIs
de administración, App Ops, políticas de tenant ni secretos. La siguiente línea
de producto separa explícitamente `identity-contracts`, `identity-sdk-php`,
`identity-admin-sdk-php` e `identity-sdk-testing`.

Configura siempre `IDENTITY_ERROR_SURFACE_BASE_URL` o `IDENTITY_OIDC_ISSUER`.
El SDK ya no usa un host productivo por defecto.

## Modelo recomendado para usarlo como "Auth0 interno"

Si el objetivo es empaquetar este SDK como producto de identidad para terceros, te conviene esta separación:

1. **API pública del SDK** (viene en `novvor/identity-sdk`):
   - Verificación y parsing de JWT/JWKS.
   - Cliente SSO para exchange de código.
   - Mapper de sesión base.
   - Configuración y provider de Laravel.
   - Superficie de errores estandarizada (`IdentityErrorSurfaceRedirector`).

2. **Reglas privadas de producto (en un módulo separado de cada app)**:
   - Reglas de riesgo/antifraude.
   - Políticas de sesión avanzadas por tenant.
   - Auditoría legal/financiera de autenticación.
   - Connectores de identidad adicionales (OAuth/SAML/B2B).

La idea es exponer una API estable y establecida desde el SDK, y que cada aplicación
inyecte sus restricciones desde su propio layer sin modificar el núcleo.

### Extensión para apps consumidoras

Implementación base sugerida:

```php
// app/Providers/IdentityOverridesServiceProvider.php

use Novvor\Identity\Auth\IdentityErrorSurfaceRedirector;
use Novvor\Identity\Contracts\IdentitySessionMapperInterface;
use Novvor\Identity\Contracts\IdentityTokenValidationPolicyInterface;
use Novvor\Identity\Session\IdentitySessionMapper;
use Novvor\Identity\Jwt\IdentityTokenValidationPolicy;
use Novvor\Identity\YourTenantNamespace\Auth\TenantIdentitySessionMapper;
use Novvor\Identity\YourTenantNamespace\Auth\TenantIdentityTokenPolicy;

class IdentityOverridesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IdentitySessionMapperInterface::class, TenantIdentitySessionMapper::class);
        $this->app->bind(IdentityTokenValidationPolicyInterface::class, TenantIdentityTokenPolicy::class);

        $this->app->bind(IdentityErrorSurfaceRedirector::class, function () {
            return new IdentityErrorSurfaceRedirector();
        });
    }
}
```

Alternativamente, también puede configurarse por `config/identity.php` sin ServiceProvider extra:

```php
return [
    'token_validation_policy' => TenantIdentityTokenPolicy::class,
    'session_mapper' => TenantIdentitySessionMapper::class,
];
```

Qué hace cada contrato:

- `IdentityTokenValidationPolicyInterface`
  - Valida claims y metadatos del JWT.
  - Útil para reglas de tenant, risk-score, claims personalizados o cambios de issuer/aud.
- `IdentitySessionMapperInterface`
  - Define cómo se transforma el payload del token en sesión de aplicación.
  - Útil para mapear campos corporativos, claims anidados o metadatos de autorización por app.

No toques el SDK para cambiar reglas de producto por cliente.

### Recomendación operativa para repositorios

- Mantener los SDKs de Novvor **privados** mientras se estabilizan contratos,
  distribución Composer y governance de seguridad.
- Mantener lógica sensible en capas privadas; la superficie cliente nunca
  incluye operaciones administrativas, soporte o App Ops.

### Señal de que ya está listo como producto

- El SDK no depende de código del tenant específico.
- Los consumidores pueden integrarlo sin tocar nada más que config + eventos/mapeos.
- Las mejoras de identidad del negocio se entregan en módulos/servicios externos del ecosistema.

<?php

$identityBaseUrl = env('IDENTITY_ERROR_SURFACE_BASE_URL')
    ?: env('IDENTITY_OIDC_PUBLIC_BASE_URL')
    ?: env('IDENTITY_OIDC_ISSUER')
    ?: '';

$appKey = env('IDENTITY_ERROR_APP_KEY')
    ?: env('IDENTITY_APP_KEY')
    ?: env('IDENTITY_OIDC_CLIENT_ID')
    ?: env('APP_NAME', 'external-app');

return [
    'token_validation_policy' => Novvor\Identity\Jwt\IdentityTokenValidationPolicy::class,
    'session_mapper' => Novvor\Identity\Session\IdentitySessionMapper::class,

    'error_surface' => [
        'identity_base_url' => rtrim((string) $identityBaseUrl, '/'),
        'app_key' => $appKey,
        'return_url' => env('IDENTITY_ERROR_RETURN_URL'),
        'default_code' => 'identity_login_failed',
        'default_message' => 'No se pudo completar el inicio de sesión.',
        'path' => '/auth/identity/error',
    ],
];

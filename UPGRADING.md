# Upgrading

## 1.1.3

Set one of `IDENTITY_ERROR_SURFACE_BASE_URL`, `IDENTITY_OIDC_PUBLIC_BASE_URL`
or `IDENTITY_OIDC_ISSUER` in every consumer. The package no longer assumes an
Identity production hostname.

Use tagged releases only. Do not install `dev-main`, an arbitrary commit hash,
or a path repository in a production consumer.

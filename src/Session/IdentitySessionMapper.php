<?php

namespace Novvor\Identity\Session;

final class IdentitySessionMapper
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function map(array $payload): array
    {
        $user = $payload['user'] ?? [];
        $user = is_array($user) ? $user : [];

        $permissions = $payload['permissions'] ?? [];
        $permissions = is_array($permissions) ? $permissions : [];
        $permissions = array_values(array_filter($permissions, fn ($v) => is_string($v) && $v !== ''));

        $features = $payload['features'] ?? [];
        $features = is_array($features) ? $features : [];

        return [
            'user_id' => (string) ($user['id'] ?? ($payload['sub'] ?? '')),
            'email' => (string) ($user['email'] ?? ''),
            'name' => (string) ($user['name'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'tenant_id' => (string) ($payload['tenant_id'] ?? ''),
            'permissions' => $permissions,
            'features' => $features,
            'token_version' => (string) ($payload['ver'] ?? '1.0'),
        ];
    }
}

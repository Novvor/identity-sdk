<?php

namespace Novvor\Identity\Contracts;

interface IdentitySessionMapperInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function map(array $payload): array;
}


<?php

declare(strict_types=1);

namespace SFATSA\Services;

use SFATSA\Security\SessionStore;

final class CsrfService
{
    public function __construct(private readonly SessionStore $store)
    {
    }

    public function token(): string
    {
        $token = $this->store->obtener('csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->store->guardar('csrf', $token);
        }
        return $token;
    }

    public function validar(#[\SensitiveParameter] mixed $token): bool
    {
        $esperado = $this->store->obtener('csrf');
        return is_string($token) && is_string($esperado) && strlen($token) === 64
            && hash_equals($esperado, $token);
    }
}

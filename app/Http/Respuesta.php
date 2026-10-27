<?php

declare(strict_types=1);

namespace SFATSA\Http;

use InvalidArgumentException;

final class Respuesta
{
    public function __construct(
        public readonly string $cuerpo,
        public readonly int $estado = 200,
        public readonly array $cabeceras = []
    ) {
    }

    public static function redirigir(string $destino): self
    {
        if (!in_array($destino, ['/', '/?ruta=inicio', '/?ruta=usuarios', '/?ruta=conductores', '/?ruta=vehiculos'], true)) {
            throw new InvalidArgumentException('Destino de redirección no permitido.');
        }
        return new self('', 303, ['Location' => $destino]);
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        foreach ($this->cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
        echo $this->cuerpo;
    }
}

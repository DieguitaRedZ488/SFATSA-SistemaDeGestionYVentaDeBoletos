<?php

declare(strict_types=1);

namespace SFATSA\Console;

interface Terminal
{
    public function verificarEntradaOculta(): void;
    public function leerTexto(string $pregunta): string;
    public function leerSecreto(string $pregunta): string;
    public function escribir(string $mensaje, bool $error = false): void;
}

<?php

declare(strict_types=1);

namespace SFATSA\Console;

use Closure;
use SFATSA\Services\AdministradorInicialService;
use SFATSA\Services\ErrorAdministradorInicial;
use Throwable;

final class CrearAdministradorInicialCommand
{
    /** La fábrica no se invoca antes de comprobar argumentos y terminal. */
    public function __construct(private readonly Terminal $terminal, private readonly Closure $crearServicio)
    {
    }

    public function ejecutar(string $sapi, array $argumentos): int
    {
        if ($sapi !== 'cli') {
            return 1;
        }
        $service = null;
        try {
            if (count($argumentos) !== 1) {
                throw new ErrorAdministradorInicial('ARGUMENTOS');
            }
            $this->terminal->verificarEntradaOculta();
            $service = ($this->crearServicio)();
            if (!$service->necesitaAlta()) {
                $this->terminal->escribir("Ya existe una cuenta ADMINISTRADOR; no se realizó ningún cambio.\n");
                return 0;
            }
            $identidad = $service->validarIdentidad([
                'nombres' => $this->terminal->leerTexto('Nombres: '),
                'apellidos' => $this->terminal->leerTexto('Apellidos: '),
                'usuario' => $this->terminal->leerTexto('Nombre de usuario: '),
            ]);
            $contrasena = $this->terminal->leerSecreto('Contraseña (entrada oculta): ');
            $confirmacion = $this->terminal->leerSecreto('Confirmar contraseña (entrada oculta): ');
            $creado = $service->crear($identidad, $contrasena, $confirmacion);
            $this->terminal->escribir($creado
                ? "Administrador inicial creado correctamente.\n"
                : "Ya existe una cuenta ADMINISTRADOR; no se realizó ningún cambio.\n");
            return 0;
        } catch (Throwable $error) {
            $mensaje = $error instanceof ErrorAdministradorInicial ? $error->getMessage() : (new ErrorAdministradorInicial())->getMessage();
            $this->terminal->escribir($mensaje . "\n", true);
            return 1;
        } finally {
            unset($contrasena, $confirmacion);
            if ($service instanceof AdministradorInicialService) {
                $service->cerrar();
            }
        }
    }
}

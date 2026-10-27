<?php

declare(strict_types=1);

namespace SFATSA\Http;

use InvalidArgumentException;
use Throwable;

final class Vista
{
    public function renderizar(string $plantilla, array $datos = []): string
    {
        $permitidas = [
            'login', 'inicio', 'mensaje', 'usuarios/listado', 'usuarios/formulario', 'usuarios/desactivar',
            'usuarios/activar', 'conductores/listado', 'conductores/formulario', 'conductores/estado',
        ];
        foreach (['listado', 'formulario', 'confirmacion', 'estado', 'eliminar'] as $vistaVehiculos) {
            $plantillaVehiculos = 'vehiculos/' . $vistaVehiculos;
            if (is_file(dirname(__DIR__) . '/Views/' . $plantillaVehiculos . '.php')) {
                $permitidas[] = $plantillaVehiculos;
            }
        }
        if (!in_array($plantilla, $permitidas, true)) {
            // Allowlist fija HU-04; no habilita rutas ni genera plantillas ausentes.
            foreach (IntegracionHU04::PLANTILLAS as $modulo => $plantillasHU04) {
                foreach ($plantillasHU04 as $nombreHU04) {
                    $candidata = $modulo . '/' . $nombreHU04;
                    if (is_file(dirname(__DIR__) . '/Views/' . $candidata . '.php')) { $permitidas[] = $candidata; }
                }
            }
        }
        if (!in_array($plantilla, $permitidas, true)) {
            throw new InvalidArgumentException('Vista no disponible.');
        }
        $escapar = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Liberar el nombre del parámetro: una plantilla puede recibir un campo llamado datos.
        $contextoVista = $datos;
        unset($datos);
        extract($contextoVista, EXTR_SKIP);
        ob_start();
        try {
            require dirname(__DIR__) . '/Views/' . $plantilla . '.php';
            return (string) ob_get_clean();
        } catch (Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}

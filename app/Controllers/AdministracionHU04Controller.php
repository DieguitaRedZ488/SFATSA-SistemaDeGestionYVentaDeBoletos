<?php

declare(strict_types=1);

namespace SFATSA\Controllers;

use Closure;
use SFATSA\Http\{Respuesta, Vista};
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Security\SessionStore;
use SFATSA\Services\{AutorizacionService, CsrfService, ErrorGestionHU04};
use Throwable;

/** Protecciones comunes solo de HU-04; conserva el patrón HTTP de HU-02. */
abstract class AdministracionHU04Controller
{
    private readonly Closure $renderizar;

    public function __construct(
        private readonly Closure $usuarioActual,
        protected readonly CsrfService $csrf,
        protected readonly SessionStore $store,
        Vista $vista,
        private readonly AutorizacionService $autorizacion,
        ?Closure $renderizar = null
    ) {
        $this->renderizar = $renderizar ?? static fn (string $plantilla, array $datos): string => $vista->renderizar($plantilla, $datos);
    }

    protected function ejecutar(Closure $operacion): Respuesta
    {
        try {
            $usuario = ($this->usuarioActual)();
            if ($usuario === null) { return Respuesta::redirigir('/'); }
            if (!$this->autorizacion->puedeGestionar($usuario)) {
                return $this->mensaje('Su rol no permite administrar rutas y catálogos.', 403);
            }
            return $operacion($usuario);
        } catch (ErrorGestionHU04 $e) {
            return $this->mensaje($e->getMessage(), $e->estado);
        } catch (Throwable) {
            return $this->mensaje('No se pudo procesar la solicitud. Consulte el listado antes de repetir la operación.', 503);
        }
    }

    protected function vista(string $plantilla, array $datos, int $estado = 200): Respuesta
    {
        return new Respuesta(($this->renderizar)($plantilla, $datos), $estado);
    }

    protected function mensaje(string $mensaje, int $estado): Respuesta
    {
        return $this->vista('mensaje', ['titulo' => 'Gestión de rutas y catálogos', 'mensaje' => $mensaje], $estado);
    }

    /** CSRF antes de resolver servicios o cambiar captura. No descartar campos protegidos. */
    protected function entrada(array $datos, array $campos, array $transporte = []): array
    {
        if (!$this->csrf->validar($datos['csrf'] ?? null)) {
            throw new ErrorGestionHU04([], 'El formulario expiró o no es válido. Vuelva a abrirlo.', 403);
        }
        $permitidos = [...$campos, 'csrf', ...$transporte];
        foreach (array_keys($datos) as $campo) {
            if (!in_array($campo, $permitidos, true)) {
                throw new ErrorGestionHU04([(string) $campo => 'Este campo no está permitido.']);
            }
        }
        return array_intersect_key($datos, array_flip($campos));
    }

    /** Solo datos públicos acotados para corregir capturas; nunca reenviar metadatos/secretos. */
    protected function valores(array $entrada, array $campos): array
    {
        $salida = [];
        foreach ($campos as $campo) {
            $valor = $entrada[$campo] ?? '';
            if ($campo === 'intermedias') {
                $salida[$campo] = is_array($valor) ? array_map(static fn (mixed $v): string =>
                    (is_string($v) || is_int($v)) && strlen((string) $v) <= 30 ? (string) $v : '', array_values(array_slice($valor, 0, 50))) : [];
            } else {
                $salida[$campo] = (is_string($valor) || is_int($valor)) && strlen((string) $valor) <= 2000 ? (string) $valor : '';
            }
        }
        return $salida;
    }

    protected function exito(string $modulo, string $mensaje): Respuesta
    {
        // La clave incluye actor a través de sesión vigente; iniciar otra sesión limpia todo.
        $this->store->guardar($modulo . '_exito', $mensaje);
        return Respuesta::redirigir('/?ruta=' . $modulo);
    }

    protected function flash(string $modulo): string
    {
        $mensaje = $this->store->obtener($modulo . '_exito');
        $this->store->quitar($modulo . '_exito');
        return is_string($mensaje) ? $mensaje : '';
    }
}

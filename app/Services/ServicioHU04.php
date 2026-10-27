<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use PDOException;
use Throwable;

/** Utilidades internas de los tres dominios HU-04; no cambia servicios del Sprint 1/HU-02. */
abstract class ServicioHU04
{
    protected const ERROR = ErrorGestionHU04::class;
    protected const CAMPOS_VERSION = [];

    public function __construct(protected readonly OperacionAdministrativaService $operaciones)
    {
    }

    protected static function error(array $errores = [], string $mensaje = 'Revise los datos señalados.', int $estado = 422): ErrorGestionHU04
    {
        $clase = static::ERROR;
        return new $clase($errores, $mensaje, $estado);
    }

    public static function id(mixed $valor, int $maximo = PHP_INT_MAX): int
    {
        if ((!is_string($valor) && !is_int($valor)) || preg_match('/\A[1-9][0-9]*\z/', (string) $valor) !== 1
            || filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximo]]) === false) {
            throw static::error([], 'El identificador no es válido.', 400);
        }
        return (int) $valor;
    }

    protected function texto(mixed $valor, string $campo, int $limite, bool $opcional = false, bool $mayusculas = false): ?string
    {
        if ($opcional && $valor === null) { return null; }
        if (!is_string($valor) || preg_match('//u', $valor) !== 1 || preg_match('/[\p{Cc}\p{Cf}]/u', $valor)) {
            throw static::error([$campo => 'Introduzca texto UTF-8 válido, sin caracteres de control.']);
        }
        $valor = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $valor) ?? '';
        if ($valor === '') {
            if ($opcional) { return null; }
            throw static::error([$campo => 'Este campo es obligatorio.']);
        }
        if ($mayusculas) { $valor = mb_strtoupper($valor, 'UTF-8'); }
        if (mb_strlen($valor, 'UTF-8') > $limite) { throw static::error([$campo => 'Utilice como máximo ' . $limite . ' caracteres.']); }
        return $valor;
    }

    protected function campos(array $datos, array $permitidos): void
    {
        $errores = [];
        foreach (array_keys($datos) as $campo) {
            if (!in_array($campo, $permitidos, true)) { $errores[(string) $campo] = 'Este campo no es editable en esta operación.'; }
        }
        if ($errores !== []) { throw static::error($errores); }
    }

    protected function busqueda(mixed $valor, int $limite): string
    {
        if (!is_string($valor)) { throw static::error([], 'La búsqueda no es válida.', 400); }
        try { return $this->texto($valor, 'busqueda', $limite, true) ?? ''; }
        catch (ErrorGestionHU04) { throw static::error([], 'La búsqueda no es válida.', 400); }
    }

    public static function estado(mixed $valor): int
    {
        if (!in_array($valor, [0, 1, '0', '1'], true)) { throw static::error(['estado' => 'Seleccione Activo o Inactivo.'], 'Estado no válido.', 400); }
        return (int) $valor;
    }

    protected function confirmar(mixed $confirmacion): void
    {
        if ($confirmacion !== '1') { throw static::error([], 'Confirme la operación antes de continuar.'); }
    }

    /** Huella de concurrencia, no credencial ni sustituto de autorización/CSRF. */
    protected function version(array $fila): string
    {
        $datos = [];
        foreach (static::CAMPOS_VERSION as $campo) {
            if (!array_key_exists($campo, $fila)) { throw static::error([], 'Información no disponible.', 503); }
            $datos[$campo] = $fila[$campo] === null ? null : (string) $fila[$campo];
        }
        return hash('sha256', serialize($datos));
    }

    protected function publicar(array $fila): array
    {
        return $fila + ['version' => $this->version($fila)];
    }

    protected function comprobarVersion(array $fila, mixed $esperada): void
    {
        if (!is_string($esperada) || preg_match('/\A[a-f0-9]{64}\z/', $esperada) !== 1) {
            throw static::error([], 'La versión de los datos no es válida.', 400);
        }
        if (!hash_equals($this->version($fila), $esperada)) {
            throw static::error([], 'Los datos cambiaron. Consulte nuevamente antes de guardar.', 409);
        }
    }

    protected function leer(Closure $lectura): mixed
    {
        try { return $lectura(); }
        catch (ErrorGestionHU04 $e) { throw $e; }
        catch (Throwable) { throw static::error([], 'No se pudo consultar la información.', 503); }
    }

    protected function escribir(int $actor, Closure $escritura, ?string $campoUnico = null): mixed
    {
        $resultado = null;
        try {
            $this->operaciones->ejecutar($actor, function () use ($escritura, &$resultado): void { $resultado = $escritura(); });
            return $resultado;
        } catch (ErrorGestionHU04 $e) { throw $e; }
        catch (ErrorGestionUsuarios $e) { throw static::error([], $e->getMessage(), $e->estado); }
        catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $conocido = $campoUnico !== null && preg_match("~for key '(?:[a-z_]+\\.)?" . preg_quote($campoUnico, '~') . "'~i", $e->errorInfo[2] ?? '') === 1;
                throw static::error($conocido ? [$campoUnico => 'Este valor ya está registrado.'] : [], 'La operación entra en conflicto con un registro existente.', 409);
            }
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                throw static::error([], 'No se puede eliminar porque existen registros relacionados que deben conservarse.', 409);
            }
            if (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213, 1452], true)) {
                throw static::error([], 'La información cambió o está siendo utilizada. Consulte nuevamente antes de guardar.', 409);
            }
            throw static::error([], 'No se pudo guardar. Revise el listado antes de repetir la operación.', 503);
        } catch (Throwable) { throw static::error([], 'No se pudo guardar. Revise el listado antes de repetir la operación.', 503); }
    }
}

<?php
declare(strict_types=1);
namespace SFATSA\Services;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/** DATETIME local, precisión de segundos; no depende del ini ni del reloj SQL. */
final class RelojOperacion
{
    public const ZONA = 'America/Mexico_City';
    public function __construct(private readonly ?Closure $fuente = null) {}
    public function ahora(): string
    {
        $instante = $this->fuente === null ? new DateTimeImmutable('now', new DateTimeZone(self::ZONA)) : ($this->fuente)();
        if (!$instante instanceof DateTimeImmutable) { throw new \RuntimeException('Reloj no disponible.'); }
        return $instante->setTimezone(new DateTimeZone(self::ZONA))->format('Y-m-d H:i:s');
    }
    public static function fecha(mixed $fecha, string $campo): string
    {
        if (!is_string($fecha) || preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $fecha) !== 1) {
            throw new ErrorGestionSprint03([$campo => 'Introduzca una fecha válida.']);
        }
        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, new DateTimeZone(self::ZONA));
        if ($valor === false || $valor->format('Y-m-d') !== $fecha) { throw new ErrorGestionSprint03([$campo => 'Introduzca una fecha válida.']); }
        return $fecha;
    }
}

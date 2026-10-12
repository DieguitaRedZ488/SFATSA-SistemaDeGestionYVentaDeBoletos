<?php

declare(strict_types=1);

namespace SFATSA\Config;

use RuntimeException;

/** Lector local: sin expansión, escapes, ejecución ni modificación del proceso. */
final class Environment
{
    private array $values;

    public function __construct(?string $filePath = null)
    {
        $this->values = [];
        if ($filePath !== null && file_exists($filePath)) {
            $contents = @file_get_contents($filePath);
            if ($contents === false) {
                throw new RuntimeException('No se pudo leer la configuración local.');
            }
            $this->values = self::parse($contents);
        }
    }

    /** Variables del proceso tienen prioridad, incluso cuando están vacías. */
    public function get(string $key, ?string $default = null): ?string
    {
        $processValue = getenv($key);
        return $processValue !== false ? $processValue : ($this->values[$key] ?? $default);
    }

    /**
     * UTF-8 sin BOM. Una asignación por línea; comentarios solo en líneas propias.
     * Claves A-Z, 0-9 y _. Valores sin comillas conservan todo tras el primer =.
     * Comillas exteriores simples/dobles se eliminan; el interior es literal.
     * No se admiten duplicados, export ni multilínea. # dentro del valor es literal.
     */
    public static function parse(#[\SensitiveParameter] string $contents): array
    {
        if (preg_match('//u', $contents) !== 1 || str_starts_with($contents, "\xEF\xBB\xBF")) {
            throw new RuntimeException('Formato de configuración local inválido.');
        }
        $values = [];
        foreach (preg_split('/\r\n|\n|\r/', $contents) as $index => $line) {
            if (trim($line, " \t") === '' || preg_match('/^[ \t]*#/', $line)) {
                continue;
            }
            $fail = static function () use ($index): never {
                throw new RuntimeException('Configuración local inválida en línea ' . ($index + 1) . '.');
            };
            if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/D', $line, $matches)) {
                $fail();
            }
            [$whole, $key, $value] = $matches;
            if (array_key_exists($key, $values) || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $fail();
            }
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (strlen($value) < 2 || substr($value, -1) !== $quote) {
                    $fail();
                }
                $value = substr($value, 1, -1);
                if (str_contains($value, $quote)) {
                    $fail();
                }
            }
            $values[$key] = $value;
        }
        return $values;
    }
}

<?php

declare(strict_types=1);

namespace SFATSA\Console;

use SFATSA\Services\ErrorAdministradorInicial;
use Throwable;

/** Entrada privada usando la consola nativa existente de Windows, sin archivos. */
final class TerminalWindows implements Terminal
{
    private const POWERSHELL = 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
    private bool $verificada = false;

    public function verificarEntradaOculta(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || !function_exists('proc_open') || !function_exists('stream_isatty')
            || !stream_isatty(STDIN) || !stream_isatty(STDOUT) || !is_file(self::POWERSHELL)) {
            throw new ErrorAdministradorInicial('TERMINAL');
        }
        $result = $this->ejecutarPowerShell(
            'if ([Console]::IsInputRedirected) { exit 1 }; [Console]::Write("OK")'
        );
        if ($result !== 'OK') {
            throw new ErrorAdministradorInicial('TERMINAL');
        }
        $this->verificada = true;
    }

    public function leerTexto(string $pregunta): string
    {
        $this->escribir($pregunta);
        $linea = fgets(STDIN, 4098);
        if ($linea === false || !str_ends_with($linea, "\n")) {
            throw new ErrorAdministradorInicial('DATOS');
        }
        // Solo retirar el delimitador de la terminal, no espacios del usuario.
        return rtrim($linea, "\r\n");
    }

    public function leerSecreto(string $pregunta): string
    {
        if (!$this->verificada) {
            throw new ErrorAdministradorInicial('TERMINAL');
        }
        $this->escribir($pregunta);
        try {
            // Código estático: la contraseña viaja solo por un pipe privado en memoria.
            return $this->ejecutarPowerShell(<<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$anterior = [Console]::TreatControlCAsInput
try {
    if ([Console]::IsInputRedirected) { exit 1 }
    [Console]::TreatControlCAsInput = $true
    $valor = [Text.StringBuilder]::new()
    while ($true) {
        $tecla = [Console]::ReadKey($true)
        if ($tecla.Key -eq [ConsoleKey]::Enter) { break }
        if (($tecla.Modifiers -band [ConsoleModifiers]::Control) -and $tecla.Key -eq [ConsoleKey]::C) { throw 'Cancelado' }
        if ($tecla.Key -eq [ConsoleKey]::Backspace) {
            if ($valor.Length -gt 0) {
                $ultimo = $valor[$valor.Length - 1]
                $valor.Length--
                if ([char]::IsLowSurrogate($ultimo) -and $valor.Length -gt 0 -and [char]::IsHighSurrogate($valor[$valor.Length - 1])) { $valor.Length-- }
            }
            continue
        }
        if ([char]::IsControl($tecla.KeyChar) -or $valor.Length -ge 4096) { throw 'Entrada no admitida' }
        [void]$valor.Append($tecla.KeyChar)
    }
    [Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)
    [Console]::Write($valor.ToString())
} catch { exit 1 } finally { [Console]::TreatControlCAsInput = $anterior }
POWERSHELL);
        } finally {
            $this->escribir("\n");
        }
    }

    private function ejecutarPowerShell(string $codigo): string
    {
        $pipes = [];
        $process = @proc_open(
            [self::POWERSHELL, '-NoLogo', '-NoProfile', '-NonInteractive', '-Command', $codigo],
            [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new ErrorAdministradorInicial('TERMINAL');
        }
        try {
            $result = stream_get_contents($pipes[1]);
            // Descartar diagnósticos del proceso hijo; nunca mostrarlos ni guardarlos.
            stream_get_contents($pipes[2]);
        } catch (Throwable) {
            // No esperar a un lector que pudo quedar bloqueado ante un fallo del pipe.
            proc_terminate($process);
            throw new ErrorAdministradorInicial('TERMINAL');
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $exitCode = proc_close($process);
        }
        if ($exitCode !== 0 || $result === false) {
            throw new ErrorAdministradorInicial('TERMINAL');
        }
        return $result;
    }

    public function escribir(string $mensaje, bool $error = false): void
    {
        fwrite($error ? STDERR : STDOUT, $mensaje);
    }
}

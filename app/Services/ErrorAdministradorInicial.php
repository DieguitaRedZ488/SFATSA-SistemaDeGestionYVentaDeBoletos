<?php

declare(strict_types=1);

namespace SFATSA\Services;

use RuntimeException;

/** Solo mensajes de un catálogo fijo; nunca recibe una excepción original. */
final class ErrorAdministradorInicial extends RuntimeException
{
    public function __construct(string $codigo = 'FALLO')
    {
        parent::__construct(match ($codigo) {
            'NOMBRES' => 'Nombres obligatorios: UTF-8 válido, hasta 120 caracteres y algún carácter distinto de espacio.',
            'APELLIDOS' => 'Apellidos obligatorios: UTF-8 válido, hasta 120 caracteres y algún carácter distinto de espacio.',
            'USUARIO' => 'Usuario obligatorio: UTF-8 válido, hasta 50 caracteres y algún carácter distinto de espacio.',
            'CONTRASENA' => 'Contraseña inválida: mínimo 12 caracteres UTF-8, máximo 72 bytes, sin NUL ni valores solo de espacios.',
            'CONFIRMACION' => 'Las contraseñas no coinciden.',
            'ROL' => 'No existe el rol ADMINISTRADOR esperado. No se creó ninguna cuenta.',
            'DUPLICADO' => 'El nombre de usuario ya existe. No se modificó ninguna cuenta.',
            'OCUPADO' => 'Otra ejecución tiene el bloqueo del alta inicial. No se creó una cuenta desde esta ejecución.',
            'TERMINAL' => 'Se requiere una consola Windows interactiva con entrada oculta disponible; operación cancelada.',
            'ARGUMENTOS' => 'Este comando no acepta argumentos. Introduzca los datos únicamente de forma interactiva.',
            'DATOS' => 'Datos de alta inicial inválidos; no se aceptan campos adicionales ni selección de rol.',
            default => 'No se pudo completar o confirmar el alta inicial. Revise el estado antes de reintentar; no se muestran detalles privados.',
        });
    }
}

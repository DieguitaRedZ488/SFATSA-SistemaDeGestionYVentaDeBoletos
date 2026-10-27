<?php

declare(strict_types=1);

// Se usan query routes porque Apache tiene AllowOverride None: no requiere reescrituras.
return [
    'login' => ['GET' => 'mostrarLogin', 'POST' => 'iniciarSesion'],
    'inicio' => ['GET' => 'inicio'],
    'logout' => ['POST' => 'cerrarSesion'],
    'usuarios' => ['GET' => ['usuarios', 'listar']],
    'usuarios.registrar' => ['GET' => ['usuarios', 'nuevo'], 'POST' => ['usuarios', 'registrar']],
    'usuarios.editar' => ['GET' => ['usuarios', 'editar'], 'POST' => ['usuarios', 'modificar']],
    'usuarios.desactivar' => ['GET' => ['usuarios', 'confirmarDesactivacion'], 'POST' => ['usuarios', 'desactivar']],
    'usuarios.activar' => ['GET' => ['usuarios', 'confirmarActivacion'], 'POST' => ['usuarios', 'activar']],
    'conductores' => ['GET' => ['conductores', 'listar']],
    'conductores.registrar' => ['GET' => ['conductores', 'nuevo'], 'POST' => ['conductores', 'registrar']],
    'conductores.editar' => ['GET' => ['conductores', 'editar'], 'POST' => ['conductores', 'modificar']],
    'conductores.estado' => ['GET' => ['conductores', 'confirmarEstado'], 'POST' => ['conductores', 'cambiarEstado']],
    'vehiculos' => ['GET' => ['vehiculos', 'listar']],
    'vehiculos.registrar' => ['GET' => ['vehiculos', 'nuevo']],
    'vehiculos.revisar' => ['POST' => ['vehiculos', 'revisar']],
    'vehiculos.confirmar' => ['POST' => ['vehiculos', 'confirmar']],
    'vehiculos.cancelar' => ['POST' => ['vehiculos', 'cancelar']],
    'vehiculos.editar' => ['GET' => ['vehiculos', 'editar'], 'POST' => ['vehiculos', 'modificar']],
    'vehiculos.estado' => ['GET' => ['vehiculos', 'confirmarEstado'], 'POST' => ['vehiculos', 'cambiarEstado']],
];

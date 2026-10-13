<?php

declare(strict_types=1);

// Carga explícita: no abre conexiones, sesiones ni archivos privados al incluirla.
foreach ([
    '/Models/UsuarioAutenticado.php',
    '/Repositories/UsuarioRepository.php',
    '/Repositories/PdoUsuarioRepository.php',
    '/Repositories/GestionUsuarioRepository.php',
    '/Repositories/PdoGestionUsuarioRepository.php',
    '/Repositories/ConductorRepository.php',
    '/Repositories/PdoConductorRepository.php',
    '/Repositories/ConnectionFactory.php',
    '/Security/SessionStore.php',
    '/Security/SesionPhp.php',
    '/Services/SesionService.php',
    '/Services/CsrfService.php',
    '/Services/AutenticacionService.php',
    '/Services/AutorizacionService.php',
    '/Services/ErrorGestionUsuarios.php',
    '/Services/OperacionAdministrativaService.php',
    '/Services/UsuarioService.php',
    '/Services/ErrorGestionConductores.php',
    '/Services/ConductorService.php',
    '/Http/Respuesta.php',
    '/Http/Vista.php',
    '/Controllers/AutenticacionController.php',
    '/Controllers/UsuarioController.php',
    '/Controllers/ConductorController.php',
] as $archivoClase) {
    require_once __DIR__ . $archivoClase;
}

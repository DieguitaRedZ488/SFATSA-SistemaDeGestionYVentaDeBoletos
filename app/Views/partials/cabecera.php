<?php require __DIR__ . '/iconos.php'; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escapar($titulo) ?> · SFATSA</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/autenticacion.css">
    <script src="/assets/js/autenticacion.js" defer></script>
    <?php if ($estilosUsuarios ?? false): ?>
        <link rel="stylesheet" href="/assets/css/usuarios.css">
        <script src="/assets/js/usuarios.js" defer></script>
        <script src="/assets/js/presentacion.js" defer></script>
    <?php endif; ?>
    <?php if ($scriptRutas ?? false): ?><script src="/assets/js/rutas.js" defer></script><?php endif; ?>
    <?php if ($capturaPlacaVehiculo ?? false): ?>
        <link rel="stylesheet" href="/assets/css/vehiculos-placa.css">
        <script src="/assets/js/vehiculos-placa.js" defer></script>
    <?php endif; ?>
</head>
<body class="<?= $escapar($claseBody ?? '') ?>">

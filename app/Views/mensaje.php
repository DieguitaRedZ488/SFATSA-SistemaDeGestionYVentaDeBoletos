<?php
$claseBody = 'pagina-mensaje';
require __DIR__ . '/partials/cabecera.php';
?>
<main class="mensaje-main">
    <section class="card mensaje-card" aria-labelledby="mensaje-title">
        <span class="brand brand--oscura">SFATSA</span>
        <h1 id="mensaje-title"><?= $escapar($titulo) ?></h1>
        <p><?= $escapar($mensaje) ?></p>
        <a class="btn button button--primary" href="/">Volver al acceso</a>
    </section>
</main>
<?php require __DIR__ . '/partials/pie.php'; ?>

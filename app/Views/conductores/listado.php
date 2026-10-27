<?php
$titulo = 'Gestión de conductores';
$descripcion = 'Consulte los datos y administre el estado de los conductores.';
$moduloGestion = 'conductores';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<?php if ($exito !== ''): ?><div class="sfatsa-alert sfatsa-alert--exito" role="status" aria-live="polite"><span class="alert__mark" aria-hidden="true">✓</span><span><?= $escapar($exito) ?></span></div><?php endif; ?>
<div class="toolbar">
    <form class="search-form" action="/" method="get" role="search">
        <input type="hidden" name="ruta" value="conductores">
        <label class="visually-hidden" for="busqueda">Buscar por nombres, apellidos o licencia</label>
        <input class="form-control field__control search-control" id="busqueda" name="q" type="search" value="<?= $escapar($busqueda) ?>" placeholder="Nombres, apellidos o licencia" maxlength="482" data-max-caracteres="241">
        <button class="btn button button--secondary" type="submit"><?= $icono('search') ?> Buscar</button>
        <?php if ($busqueda !== ''): ?><a class="text-action" href="/?ruta=conductores">Limpiar búsqueda</a><?php endif; ?>
    </form>
    <a class="btn button button--primary" href="/?ruta=conductores.registrar"><?= $icono('plus') ?> Registrar conductor</a>
</div>
<section class="table-card" aria-labelledby="conductores-title">
    <h2 class="visually-hidden" id="conductores-title">Conductores registrados</h2>
    <?php if ($conductores === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><?= $icono('search') ?></span><h3><?= $busqueda === '' ? 'No hay conductores registrados' : 'No se encontraron conductores' ?></h3><p><?= $busqueda === '' ? 'Use Registrar conductor para dar de alta un conductor.' : 'Pruebe con otro nombre, apellido o número de licencia.' ?></p></div>
    <?php else: ?>
        <p class="mobile-table-note" id="tabla-ayuda">En pantallas pequeñas puede desplazar la tabla horizontalmente.</p>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla de conductores" aria-describedby="tabla-ayuda">
            <table class="table usuarios-table mb-0">
                <caption class="visually-hidden">Conductores<?= $busqueda !== '' ? ' que coinciden con la búsqueda' : ' registrados' ?> y su estado actual.</caption>
                <thead><tr><th scope="col">Folio</th><th scope="col">Nombre completo</th><th scope="col">Licencia</th><th scope="col">Teléfono</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($conductores as $conductor): $id = (int) $conductor['id_conductor']; $activo = (int) $conductor['activo'] === 1; ?>
                    <tr>
                        <th scope="row">#<?= $id ?></th>
                        <td class="nombre-completo"><?= $escapar($conductor['nombres'] . ' ' . $conductor['apellidos']) ?></td>
                        <td class="nombre-usuario"><?= $escapar($conductor['numero_licencia'] ?? 'Sin registrar') ?></td>
                        <td><?= $escapar($conductor['telefono'] ?? 'Sin registrar') ?></td>
                        <td><span class="status <?= $activo ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono($activo ? 'check-circle' : 'x-circle') ?></span><?= $activo ? 'Habilitado' : 'Deshabilitado' ?></span></td>
                        <td><div class="table-actions"><a class="text-action" href="/?ruta=conductores.editar&amp;id=<?= $id ?>" aria-label="Modificar conductor <?= $id ?>"><?= $icono('pencil') ?> Modificar</a><a class="text-action" href="/?ruta=conductores.estado&amp;id=<?= $id ?>&amp;estado=<?= $activo ? '0' : '1' ?>" aria-label="<?= $activo ? 'Deshabilitar' : 'Habilitar' ?> conductor <?= $id ?>"><?= $activo ? 'Deshabilitar' : 'Habilitar' ?></a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="table-footer"><?= count($conductores) ?> <?= count($conductores) === 1 ? 'conductor' : 'conductores' ?><?= $busqueda !== '' ? ' en los resultados' : ' en el listado' ?></div>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>

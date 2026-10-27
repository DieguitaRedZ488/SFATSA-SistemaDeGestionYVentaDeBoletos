<section class="table-card" aria-label="Recorrido ordenado">
    <p class="mobile-table-note">Puede desplazar la tabla horizontalmente.</p>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Puntos del recorrido">
        <table class="table usuarios-table mb-0"><caption class="visually-hidden">Puntos en orden de recorrido</caption>
            <thead><tr><th scope="col">Orden</th><th scope="col">Punto</th><th scope="col">ID</th><th scope="col">Parada</th><th scope="col">Referencia</th><th scope="col">Estado actual</th></tr></thead>
            <tbody><?php foreach ($puntos as $posicion => $punto): ?>
                <tr><th scope="row"><?= $escapar($punto['orden']) ?></th><td><?= $posicion === 0 ? 'Origen' : ($posicion === count($puntos) - 1 ? 'Destino' : 'Intermedia') ?></td>
                    <td><?= (int) $punto['id_parada'] ?></td><td class="nombre-completo"><?= $escapar($punto['nombre']) ?></td><td class="nombre-completo"><?= $escapar($punto['referencia'] ?? 'Sin referencia') ?></td>
                    <td><span class="status <?= (int) $punto['activo'] === 1 ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono((int) $punto['activo'] === 1 ? 'check-circle' : 'x-circle') ?></span><?= (int) $punto['activo'] === 1 ? 'Habilitada' : 'Deshabilitada' ?></span></td></tr>
            <?php endforeach; ?></tbody>
        </table>
    </div><div class="table-footer"><?= count($puntos) ?> puntos del recorrido</div>
</section>

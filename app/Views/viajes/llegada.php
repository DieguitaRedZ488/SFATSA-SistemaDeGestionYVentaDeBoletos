<?php
$titulo='Registrar llegada';$descripcion='Confirme la finalización del recorrido.';$moduloGestion='viajes';require dirname(__DIR__).'/partials/gestion-inicio.php';require dirname(__DIR__).'/partials/viaje-datos.php';
?>
<section class="confirm-card" data-simple-confirm aria-labelledby="confirmar-title"><h2 id="confirmar-title">¿Confirma la llegada de este viaje?</h2><p>Se registrará la hora actual de Ciudad de México y el viaje quedará finalizado. La llegada solo puede registrarse una vez.</p><form method="post" action="/?ruta=viajes.llegada&amp;id=<?= (int)$viaje['id_viaje'] ?>"><input type="hidden" name="csrf" value="<?= $escapar($token) ?>"><input type="hidden" name="confirmacion" value="1"><div class="confirm-actions"><a class="btn button button--secondary" href="/?ruta=viajes">Cancelar</a><button class="btn button button--primary" type="submit">Confirmar llegada</button></div></form></section>
<?php require dirname(__DIR__).'/partials/gestion-fin.php';?>

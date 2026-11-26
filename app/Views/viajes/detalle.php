<?php
$titulo='Detalle de viaje';$descripcion='Consulte los datos operativos y las personas responsables del registro.';$moduloGestion='viajes';require dirname(__DIR__).'/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=viajes">Volver a viajes</a>
<?php require dirname(__DIR__).'/partials/viaje-datos.php';?>
<?php if($viaje['estado']==='EN_CURSO'):?><a class="btn button button--primary" href="/?ruta=viajes.llegada&amp;id=<?= (int)$viaje['id_viaje'] ?>">Registrar llegada</a><?php endif;?>
<?php require dirname(__DIR__).'/partials/gestion-fin.php';?>

<?php
// Selectores compartidos de captura. Todas las referencias se revalidan en servidor.
$select = static function (string $campo,string $etiqueta,array $filas,string $clave,\Closure $nombre) use ($escapar,$valores,$errores): void {
    $valor=(string)($valores[$campo]??'');
    echo '<div class="field"><label for="'.$campo.'">'.$escapar($etiqueta).' *</label><select class="form-select field__control" required id="'.$campo.'" name="'.$campo.'"'.(isset($errores[$campo])?' aria-invalid="true" aria-describedby="'.$campo.'-error"':'').'>';
    echo '<option value="">Seleccione una opción</option>';
    if($valor!==''&&!in_array($valor,array_map('strval',array_column($filas,$clave)),true)){echo '<option value="'.$escapar($valor).'" selected disabled>Opción no disponible; seleccione otra</option>';}
    foreach($filas as$f){echo '<option value="'.(int)$f[$clave].'"'.($valor===(string)$f[$clave]?' selected':'').(isset($f['id_ruta'])?' data-ruta="'.(int)$f['id_ruta'].'"':'').(isset($f['id_tipo_servicio'])?' data-tipo="'.(int)$f['id_tipo_servicio'].'"':'').'>'.$escapar($nombre($f)).'</option>';}
    echo '</select>';if(isset($errores[$campo])){echo '<p class="field-error" role="alert" id="'.$campo.'-error">'.$escapar($errores[$campo]).'</p>';}echo '</div>';
};
$select('id_ruta','Ruta',$opciones['rutas'],'id_ruta',static fn($r)=>$r['codigo'].' · '.$r['nombre'].' · '.$r['servicio']);
$select('id_tipo_servicio','Tipo de servicio',$opciones['tipos'],'id_tipo_servicio',static fn($r)=>$r['nombre']);
if($moduloGestion==='tarifas'){
    $rutasNombres=array_column($opciones['rutas'],'nombre','id_ruta');
    $puntos=array_values(array_filter($opciones['puntos'],static fn($p)=>(int)$p['activo']===1));
    $etiquetaPunto=static fn($p)=>($rutasNombres[$p['id_ruta']]??'Ruta #'.$p['id_ruta']).' · '.$p['orden'].'. '.$p['nombre'];
    $select('id_origen_ruta_parada','Origen',$puntos,'id_ruta_parada',$etiquetaPunto);
    $select('id_destino_ruta_parada','Destino',$puntos,'id_ruta_parada',$etiquetaPunto);
}else{
    $select('id_vehiculo','Vehículo disponible',$opciones['vehiculos'],'id_vehiculo',static fn($r)=>$r['numero_economico'].' · '.$r['placa'].' · '.$r['capacidad'].' lugares');
    $select('id_conductor','Conductor disponible',$opciones['conductores'],'id_conductor',static fn($r)=>$r['nombres'].' '.$r['apellidos']);
}
?>

-- SFATSA initial schema for a NEW local installation only.
-- This script creates the database and all 15 tables; it is not a migration.
-- The plain CREATE DATABASE is intentional: it fails if sfatza already exists.
-- Run only for a new installation, with a MariaDB client that stops on errors.
-- DDL is not one transaction; after an error, stop and inspect, do not rerun or drop blindly.
-- It includes the result of migration 20261013_001 (separate names/apellidos)
-- and the target of 20261013_002 (conductor.numero_licencia NOT NULL).
-- Do not run either migration after this script. Do not run this on an
-- existing database. It creates no SQL users or application administrator.
-- The only inserted rows are the three non-personal catalogs below.

CREATE DATABASE sfatza
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_uca1400_ai_ci;

USE sfatza;

SET NAMES utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;

CREATE TABLE `rol` (
  `id_rol` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `metodo_pago` (
  `id_metodo_pago` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_metodo_pago`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `tipo_servicio` (
  `id_tipo_servicio` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `controla_capacidad` tinyint(1) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipo_servicio`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `conductor` (
  `id_conductor` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombres` varchar(120) NOT NULL,
  `apellidos` varchar(120) NOT NULL,
  `numero_licencia` varchar(40) NOT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_conductor`),
  UNIQUE KEY `numero_licencia` (`numero_licencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `parada` (
  `id_parada` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_parada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `ruta` (
  `id_ruta` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_ruta`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `vehiculo` (
  `id_vehiculo` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero_economico` varchar(30) NOT NULL,
  `placa` varchar(20) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  `capacidad` smallint(5) unsigned NOT NULL,
  `estado` enum('ACTIVO','MANTENIMIENTO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_vehiculo`),
  UNIQUE KEY `numero_economico` (`numero_economico`),
  UNIQUE KEY `placa` (`placa`),
  CONSTRAINT `chk_vehiculo_capacidad` CHECK (`capacidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `usuario` (
  `id_usuario` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_rol` smallint(5) unsigned NOT NULL,
  `nombres` varchar(120) NOT NULL,
  `apellidos` varchar(120) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `hash_contrasena` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_usuario_rol` (`id_rol`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `mantenimiento` (
  `id_mantenimiento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_vehiculo` bigint(20) unsigned NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `descripcion` text NOT NULL,
  `costo` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id_mantenimiento`),
  KEY `fk_mant_vehiculo` (`id_vehiculo`),
  CONSTRAINT `fk_mant_vehiculo` FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculo` (`id_vehiculo`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_mant_fechas` CHECK (`fecha_fin` is null or `fecha_fin` >= `fecha_inicio`),
  CONSTRAINT `chk_mant_costo` CHECK (`costo` is null or `costo` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `ruta_parada` (
  `id_ruta_parada` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_ruta` bigint(20) unsigned NOT NULL,
  `id_parada` bigint(20) unsigned NOT NULL,
  `orden` smallint(5) unsigned NOT NULL,
  `distancia_acumulada_km` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id_ruta_parada`),
  UNIQUE KEY `uq_ruta_orden` (`id_ruta`,`orden`),
  UNIQUE KEY `uq_ruta_parada` (`id_ruta`,`id_parada`),
  KEY `fk_rp_parada` (`id_parada`),
  CONSTRAINT `fk_rp_parada` FOREIGN KEY (`id_parada`) REFERENCES `parada` (`id_parada`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rp_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `ruta` (`id_ruta`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_rp_orden` CHECK (`orden` > 0),
  CONSTRAINT `chk_rp_distancia` CHECK (`distancia_acumulada_km` is null or `distancia_acumulada_km` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `tarifa` (
  `id_tarifa` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_tipo_servicio` tinyint(3) unsigned NOT NULL,
  `id_origen_ruta_parada` bigint(20) unsigned NOT NULL,
  `id_destino_ruta_parada` bigint(20) unsigned NOT NULL,
  `importe` decimal(10,2) NOT NULL,
  `vigente_desde` datetime NOT NULL,
  `vigente_hasta` datetime DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tarifa`),
  KEY `ix_tarifa_busqueda` (`id_tipo_servicio`,`id_origen_ruta_parada`,`id_destino_ruta_parada`,`vigente_desde`),
  KEY `fk_tarifa_origen` (`id_origen_ruta_parada`),
  KEY `fk_tarifa_destino` (`id_destino_ruta_parada`),
  CONSTRAINT `fk_tarifa_destino` FOREIGN KEY (`id_destino_ruta_parada`) REFERENCES `ruta_parada` (`id_ruta_parada`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_tarifa_origen` FOREIGN KEY (`id_origen_ruta_parada`) REFERENCES `ruta_parada` (`id_ruta_parada`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_tarifa_servicio` FOREIGN KEY (`id_tipo_servicio`) REFERENCES `tipo_servicio` (`id_tipo_servicio`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_tarifa_importe` CHECK (`importe` >= 0),
  CONSTRAINT `chk_tarifa_fechas` CHECK (`vigente_hasta` is null or `vigente_hasta` > `vigente_desde`),
  CONSTRAINT `chk_tarifa_paradas` CHECK (`id_origen_ruta_parada` <> `id_destino_ruta_parada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `viaje` (
  `id_viaje` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_ruta` bigint(20) unsigned NOT NULL,
  `id_tipo_servicio` tinyint(3) unsigned NOT NULL,
  `id_vehiculo` bigint(20) unsigned NOT NULL,
  `id_conductor` bigint(20) unsigned DEFAULT NULL,
  `salida_real` datetime NOT NULL,
  `llegada_real` datetime DEFAULT NULL,
  `id_usuario_salida` bigint(20) unsigned NOT NULL,
  `id_usuario_llegada` bigint(20) unsigned DEFAULT NULL,
  `capacidad_al_salir` smallint(5) unsigned NOT NULL,
  `estado` enum('EN_CURSO','FINALIZADO','CANCELADO') NOT NULL DEFAULT 'EN_CURSO',
  `observaciones` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_viaje`),
  KEY `ix_viaje_salida` (`salida_real`),
  KEY `fk_viaje_ruta` (`id_ruta`),
  KEY `fk_viaje_servicio` (`id_tipo_servicio`),
  KEY `fk_viaje_vehiculo` (`id_vehiculo`),
  KEY `fk_viaje_conductor` (`id_conductor`),
  KEY `fk_viaje_usuario_salida` (`id_usuario_salida`),
  KEY `fk_viaje_usuario_llegada` (`id_usuario_llegada`),
  CONSTRAINT `fk_viaje_conductor` FOREIGN KEY (`id_conductor`) REFERENCES `conductor` (`id_conductor`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_viaje_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `ruta` (`id_ruta`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_viaje_servicio` FOREIGN KEY (`id_tipo_servicio`) REFERENCES `tipo_servicio` (`id_tipo_servicio`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_viaje_usuario_llegada` FOREIGN KEY (`id_usuario_llegada`) REFERENCES `usuario` (`id_usuario`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_viaje_usuario_salida` FOREIGN KEY (`id_usuario_salida`) REFERENCES `usuario` (`id_usuario`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_viaje_vehiculo` FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculo` (`id_vehiculo`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_viaje_capacidad` CHECK (`capacidad_al_salir` > 0),
  CONSTRAINT `chk_viaje_tiempos` CHECK (`llegada_real` is null or `llegada_real` >= `salida_real`),
  CONSTRAINT `chk_viaje_llegada` CHECK (`estado` <> 'FINALIZADO' or `llegada_real` is not null and `id_usuario_llegada` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `venta` (
  `folio_venta` varchar(40) NOT NULL,
  `id_cajero` bigint(20) unsigned NOT NULL,
  `id_metodo_pago` tinyint(3) unsigned NOT NULL,
  `fecha_venta` datetime NOT NULL DEFAULT current_timestamp(),
  `nombre_comprador` varchar(120) DEFAULT NULL,
  `importe_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('PENDIENTE','PAGADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_cancelacion` datetime DEFAULT NULL,
  `id_usuario_cancelacion` bigint(20) unsigned DEFAULT NULL,
  `motivo_cancelacion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`folio_venta`),
  KEY `fk_venta_cajero` (`id_cajero`),
  KEY `fk_venta_metodo_pago` (`id_metodo_pago`),
  KEY `fk_venta_usuario_cancelacion` (`id_usuario_cancelacion`),
  CONSTRAINT `fk_venta_cajero` FOREIGN KEY (`id_cajero`) REFERENCES `usuario` (`id_usuario`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_venta_metodo_pago` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodo_pago` (`id_metodo_pago`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_venta_usuario_cancelacion` FOREIGN KEY (`id_usuario_cancelacion`) REFERENCES `usuario` (`id_usuario`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_venta_importe` CHECK (`importe_total` >= 0),
  CONSTRAINT `chk_venta_cancelacion` CHECK (`estado` <> 'CANCELADA' and `fecha_cancelacion` is null or `estado` = 'CANCELADA' and `fecha_cancelacion` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `boleto` (
  `folio_boleto` varchar(40) NOT NULL,
  `folio_venta` varchar(40) NOT NULL,
  `id_viaje` bigint(20) unsigned NOT NULL,
  `id_tarifa` bigint(20) unsigned NOT NULL,
  `nombre_pasajero` varchar(120) DEFAULT NULL,
  `numero_asiento` smallint(5) unsigned DEFAULT NULL,
  `estado` enum('EMITIDO','CANCELADO') NOT NULL DEFAULT 'EMITIDO',
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `asiento_activo` smallint(5) unsigned GENERATED ALWAYS AS (case when `estado` = 'EMITIDO' then `numero_asiento` else NULL end) STORED,
  PRIMARY KEY (`folio_boleto`),
  UNIQUE KEY `uq_asiento_activo` (`id_viaje`,`asiento_activo`),
  KEY `ix_boleto_venta` (`folio_venta`),
  KEY `ix_boleto_viaje_estado` (`id_viaje`,`estado`),
  KEY `fk_boleto_tarifa` (`id_tarifa`),
  CONSTRAINT `fk_boleto_tarifa` FOREIGN KEY (`id_tarifa`) REFERENCES `tarifa` (`id_tarifa`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_boleto_venta` FOREIGN KEY (`folio_venta`) REFERENCES `venta` (`folio_venta`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_boleto_viaje` FOREIGN KEY (`id_viaje`) REFERENCES `viaje` (`id_viaje`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_boleto_asiento` CHECK (`numero_asiento` is null or `numero_asiento` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `reembolso` (
  `id_reembolso` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `folio_venta` varchar(40) NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `id_metodo_pago` tinyint(3) unsigned NOT NULL,
  `fecha_reembolso` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` bigint(20) unsigned NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_reembolso`),
  UNIQUE KEY `uq_reembolso_venta` (`folio_venta`),
  KEY `fk_reembolso_metodo_pago` (`id_metodo_pago`),
  KEY `fk_reembolso_usuario` (`id_usuario`),
  CONSTRAINT `fk_reembolso_metodo_pago` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodo_pago` (`id_metodo_pago`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_reembolso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_reembolso_venta` FOREIGN KEY (`folio_venta`) REFERENCES `venta` (`folio_venta`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `rol` (`id_rol`, `nombre`) VALUES
  (1, 'ADMINISTRADOR'),
  (2, 'CAJERO'),
  (3, 'CHECADORA');

INSERT INTO `metodo_pago` (`id_metodo_pago`, `nombre`, `activo`) VALUES
  (1, 'EFECTIVO', 1),
  (2, 'TARJETA', 1),
  (3, 'TRANSFERENCIA', 1);

INSERT INTO `tipo_servicio` (`id_tipo_servicio`, `nombre`, `controla_capacidad`, `activo`) VALUES
  (1, 'DIRECTO', 1, 1),
  (2, 'PARADAS_CONTINUAS', 0, 1);

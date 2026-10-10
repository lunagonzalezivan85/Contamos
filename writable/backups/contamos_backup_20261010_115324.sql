-- Contamos — backup completo
-- Generado: 2026-10-10 11:53:24 · BD: cfsi

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- cuotas
DROP TABLE IF EXISTS `cuotas`;
CREATE TABLE `cuotas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `solicitud_id` bigint(20) unsigned NOT NULL,
  `n` smallint(5) unsigned NOT NULL,
  `fecha_vence` date NOT NULL,
  `cuota` decimal(12,2) NOT NULL,
  `interes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `capital` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo_proyectado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pagado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mora_dev` decimal(12,2) DEFAULT 0.00,
  `mora` decimal(12,2) DEFAULT 0.00,
  `descuento` decimal(12,2) DEFAULT 0.00,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `fecha_pago` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cuotas_sol` (`solicitud_id`,`n`),
  KEY `idx_cuotas_vence` (`tenant_id`,`fecha_vence`),
  CONSTRAINT `fk_cuotas_solicitudes` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cuotas_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cuotas` VALUES
('16','3','10','1','2026-09-06','1395.67','110.85','1284.82','10715.18','1395.67','29.31','0.00','0.00','PAGADA','2026-09-27','2026-09-27 00:18:15','2026-09-27 00:18:15'),
('17','3','10','2','2026-09-13','1395.67','98.99','1296.68','9418.50','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('18','3','10','3','2026-09-20','1395.67','87.01','1308.66','8109.84','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('19','3','10','4','2026-09-27','1395.67','74.92','1320.75','6789.09','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('20','3','10','5','2026-10-04','1395.67','62.72','1332.95','5456.14','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('21','3','10','6','2026-10-11','1395.67','50.40','1345.27','4110.87','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('22','3','10','7','2026-10-18','1395.67','37.98','1357.69','2753.18','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('23','3','10','8','2026-10-25','1395.67','25.43','1370.24','1382.94','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('24','3','10','9','2026-11-01','1395.72','12.78','1382.94','0.00','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('92','2','28','1','2026-10-16','2466.67','800.00','1666.67','8333.33','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-10-08 12:34:57','2026-10-08 12:38:11'),
('93','2','28','2','2026-10-31','2466.67','800.00','1666.67','6666.66','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-10-08 12:34:57','2026-10-08 12:38:11'),
('94','2','28','3','2026-11-15','2466.67','800.00','1666.67','4999.99','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-10-08 12:34:57','2026-10-08 12:38:11'),
('95','2','28','4','2026-11-30','2466.67','800.00','1666.67','3333.32','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-10-08 12:34:57','2026-10-08 12:38:11'),
('96','2','28','5','2026-12-15','2466.67','800.00','1666.67','1666.65','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-10-08 12:34:57','2026-10-08 12:38:11'),
('97','2','28','6','2026-12-31','2466.65','800.00','1666.67','0.00','0.00','0.00','0.00','0.00','PENDIENTE',NULL,'2026-10-08 12:34:57','2026-10-08 12:38:11');
-- pagos
DROP TABLE IF EXISTS `pagos`;
CREATE TABLE `pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `solicitud_id` bigint(20) unsigned NOT NULL,
  `cuota_id` bigint(20) unsigned DEFAULT NULL,
  `revierte_id` bigint(20) unsigned DEFAULT NULL,
  `empleado_id` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `aprobado_por` bigint(20) unsigned DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `metodo` varchar(20) NOT NULL DEFAULT 'EFECTIVO',
  `tipo` varchar(10) NOT NULL DEFAULT 'PAGO',
  `fecha_hora` datetime NOT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'REVISION',
  `resuelto_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pagos_cuotas` (`cuota_id`),
  KEY `idx_pagos_estado` (`tenant_id`,`estado`),
  KEY `idx_pagos_sol` (`solicitud_id`),
  KEY `idx_pagos_gestor` (`empleado_id`,`fecha_hora`),
  KEY `fk_pagos_revierte` (`revierte_id`),
  CONSTRAINT `fk_pagos_cuotas` FOREIGN KEY (`cuota_id`) REFERENCES `cuotas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_empleados` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_revierte` FOREIGN KEY (`revierte_id`) REFERENCES `pagos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_solicitudes` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pagos` VALUES
('5','3','10','16',NULL,'3','3','3','1395.67','EFECTIVO','PAGO','2026-09-26 00:18:15',NULL,'APLICADO','2026-09-27 00:18:15','2026-09-26 00:18:15','2026-09-27 00:18:15'),
('6','3','10',NULL,NULL,'3','3',NULL,'3000.00','EFECTIVO','PAGO','2026-09-27 00:18:15',NULL,'REVISION',NULL,'2026-09-27 00:18:15','2026-09-27 00:18:15'),
('38','3','10','17',NULL,'3',NULL,NULL,'1395.67','EFECTIVO','PAGO','2026-09-28 03:52:20',NULL,'REVISION',NULL,'2026-09-28 03:52:20','2026-09-28 03:52:20');
SET FOREIGN_KEY_CHECKS = 1;

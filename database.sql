-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: contahercar_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `contahercar_db`
--

/*!40000 DROP DATABASE IF EXISTS `contahercar_db`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `contahercar_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `contahercar_db`;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Herramientas Manuales','Llaves, alicates, destornilladores, martillos',1,'2026-09-07 17:35:43'),(2,'Herramientas El├®ctricas','Taladros, amoladoras, sierras, rotomartillos',1,'2026-09-07 17:35:43'),(3,'Materiales de Construcci├│n','Cementos, pegamentos, siliconas, fijaciones',1,'2026-09-07 17:35:43'),(4,'Electricidad e Iluminaci├│n','Cables, focos LED, interruptores, tableros',1,'2026-09-07 17:35:43'),(5,'Pinturas y Acabados','Pinturas latex, esmaltes, brochas, rodillos',1,'2026-09-07 17:35:43');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_doc` varchar(10) NOT NULL DEFAULT 'DNI',
  `num_doc` varchar(20) NOT NULL,
  `nombre_razon_social` varchar(200) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `condicion` varchar(50) DEFAULT 'HABIDO',
  `estado_sunat` varchar(50) DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `num_doc` (`num_doc`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,'DNI','00000000','CLIENTE VARIOS / GENERAL','Lima - Per├║','999999999','ventas@contahercar.com','HABIDO','ACTIVO','2026-09-07 17:35:43'),(2,'DNI','45891234','JUAN CARLOS P├ëREZ R├ìOS','Av. Arequipa 1420, Lince','987 654 321','jperez@gmail.com','HABIDO','ACTIVO','2026-09-07 17:35:43'),(3,'RUC','20601987654','CONSTRUCTORA E INMOBILIARIA DEL VALLE S.A.C.','Av. Javier Prado Este 2450, San Borja','01 719-5500','logistica@constructordelvalle.pe','HABIDO','ACTIVO','2026-09-07 17:35:43'),(6,'RUC','20601030013','REXTIE S.A.C.','CAL. LAS CAMELIAS NRO 256 INT. 701A URB. JARDIN','','','HABIDO','ACTIVO','2026-09-07 17:59:38');
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `tipo_comprobante` varchar(50) DEFAULT 'Factura',
  `serie_numero` varchar(50) NOT NULL,
  `fecha_compra` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `impuesto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(20) DEFAULT 'COMPLETADA',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
INSERT INTO `compras` VALUES (2,1,'Factura','F001-000124','2026-09-08',350.00,63.00,413.00,'COMPLETADA','Compra inicial de mercader├¡a','2026-09-08 03:06:57');
/*!40000 ALTER TABLE `compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_empresa` varchar(150) NOT NULL DEFAULT 'ContaHercar S.A.C.',
  `ruc_empresa` varchar(20) NOT NULL DEFAULT '20601234567',
  `direccion` varchar(255) DEFAULT 'Av. Principal 123, Lima - Per├║',
  `telefono` varchar(50) DEFAULT '+51 987 654 321',
  `email` varchar(100) DEFAULT 'contacto@contahercar.com',
  `moneda_simbolo` varchar(10) DEFAULT 'S/.',
  `moneda_nombre` varchar(30) DEFAULT 'Soles',
  `impuesto_nombre` varchar(20) DEFAULT 'IGV',
  `impuesto_porcentaje` decimal(5,2) DEFAULT 18.00,
  `api_ruc_url` varchar(255) DEFAULT 'https://api.apis.net.pe/v2/sunat/ruc?numero={numero}',
  `api_dni_url` varchar(255) DEFAULT 'https://api.apis.net.pe/v2/reniec/dni?numero={numero}',
  `api_ruc_token` varchar(255) DEFAULT '',
  `api_ruc_provider` varchar(50) DEFAULT 'apisnet',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sire_client_id` varchar(255) DEFAULT '',
  `sire_client_secret` varchar(255) DEFAULT '',
  `sire_usuario_sol` varchar(50) DEFAULT '',
  `sire_clave_sol` varchar(50) DEFAULT '',
  `sire_ambiente` varchar(20) DEFAULT 'beta',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion`
--

LOCK TABLES `configuracion` WRITE;
/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
INSERT INTO `configuracion` VALUES (1,'ContaHercar S.A.C.','20601234567','Av. La Marina 450, Lima','01 456-7890','ventas@contahercar.com','S/.','Soles','IGV',18.00,'https://api.decolecta.com/v1/sunat/ruc?numero={numero}','https://api.decolecta.com/v1/reniec/dni?numero={numero}','sk_19173.Qv4zS5df9j2TSQLKv6shuipgGMoGXlMt','decolecta','2026-09-07 17:57:58','','','','','beta');
/*!40000 ALTER TABLE `configuracion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_compras`
--

DROP TABLE IF EXISTS `detalle_compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `compra_id` (`compra_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `detalle_compras_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_compras_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compras`
--

LOCK TABLES `detalle_compras` WRITE;
/*!40000 ALTER TABLE `detalle_compras` DISABLE KEYS */;
INSERT INTO `detalle_compras` VALUES (2,2,1,2,175.00,350.00);
/*!40000 ALTER TABLE `detalle_compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_ventas`
--

DROP TABLE IF EXISTS `detalle_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_ventas`
--

LOCK TABLES `detalle_ventas` WRITE;
/*!40000 ALTER TABLE `detalle_ventas` DISABLE KEYS */;
INSERT INTO `detalle_ventas` VALUES (2,2,6,2,10.50,6.20,0.00,21.00),(4,4,2,1,285.00,210.00,0.00,285.00);
/*!40000 ALTER TABLE `detalle_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kardex`
--

DROP TABLE IF EXISTS `kardex`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kardex` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `tipo_movimiento` varchar(30) NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_anterior` int(11) NOT NULL,
  `stock_nuevo` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `kardex_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kardex`
--

LOCK TABLES `kardex` WRITE;
/*!40000 ALTER TABLE `kardex` DISABLE KEYS */;
INSERT INTO `kardex` VALUES (1,1,'INVENTARIO_INICIAL',NULL,18,0,18,180.00,'Carga inicial de inventario','2026-09-07 12:35:43'),(2,2,'INVENTARIO_INICIAL',NULL,12,0,12,210.00,'Carga inicial de inventario','2026-09-07 12:35:43'),(3,3,'INVENTARIO_INICIAL',NULL,25,0,25,75.00,'Carga inicial de inventario','2026-09-07 12:35:43'),(4,4,'INVENTARIO_INICIAL',NULL,30,0,30,22.00,'Carga inicial de inventario','2026-09-07 12:35:43'),(5,5,'INVENTARIO_INICIAL',NULL,4,0,4,14.50,'Carga inicial de inventario','2026-09-07 12:35:43'),(6,6,'INVENTARIO_INICIAL',NULL,60,0,60,6.20,'Carga inicial de inventario','2026-09-07 12:35:43'),(7,7,'INVENTARIO_INICIAL',NULL,3,0,3,115.00,'Carga inicial de inventario','2026-09-07 12:35:43'),(8,8,'INVENTARIO_INICIAL',NULL,45,0,45,11.00,'Carga inicial de inventario','2026-09-07 12:35:43'),(11,6,'VENTA',2,2,60,58,10.50,'Venta Boleta B001-000001','2026-09-07 12:53:09'),(13,2,'VENTA',4,1,12,11,285.00,'Venta Boleta B001-000002','2026-09-07 13:14:00');
/*!40000 ALTER TABLE `kardex` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_barra` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_compra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_venta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  `unidad_medida` varchar(20) DEFAULT 'UNID',
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_barra` (`codigo_barra`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'77501001','Taladro Percutor 1/2 pulg 650W Bosch',2,'Taladro con velocidad variable y reversa',180.00,245.00,18,5,'UNID',1,'2026-09-07 17:35:43','2026-09-07 17:55:45'),(2,'77501002','Amoladora Angular 4-1/2 pulg 850W Dewalt',2,'Motor protegido contra la abrasi├│n',210.00,285.00,11,4,'UNID',1,'2026-09-07 17:35:43','2026-09-07 18:14:00'),(3,'77501003','Juego de Llaves Combinadas 8-24mm Stanley (14 pzs)',1,'Acero cromo vanadio forjado',75.00,115.00,25,6,'JGO',1,'2026-09-07 17:35:43','2026-09-07 17:35:43'),(4,'77501004','Martillo Carpintero 16oz Mango Fibra Truper',1,'Cabeza de acero forjado pulido',22.00,36.00,30,8,'UNID',1,'2026-09-07 17:35:43','2026-09-07 17:35:43'),(5,'77501005','Cinta M├®trica 5m / 16ft Global Plus Stanley',1,'Cinta recubierta de pol├¡mero',14.50,24.00,4,10,'UNID',1,'2026-09-07 17:35:43','2026-09-08 03:19:18'),(6,'77501006','Foco LED 12W Luz Blanca E27 Philips',4,'Ahorro de energ├¡a hasta 85%',6.20,10.50,58,15,'UNID',1,'2026-09-07 17:35:43','2026-09-07 17:53:09'),(7,'77501007','Cable Mellizo 2x14 AWG Indeco (Rollo 100m)',4,'Conductor de cobre electrol├¡tico recocido',115.00,160.00,3,5,'ROLLO',1,'2026-09-07 17:35:43','2026-09-07 17:35:43'),(8,'77501008','Silicona Multiuso Transparente 280ml Tekbond',3,'Excelente adherencia para sellados generales',11.00,18.00,45,10,'UNID',1,'2026-09-07 17:35:43','2026-09-07 17:35:43');
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_doc` varchar(10) NOT NULL DEFAULT 'RUC',
  `num_doc` varchar(20) NOT NULL,
  `razon_social` varchar(200) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `estado_sunat` varchar(50) DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `num_doc` (`num_doc`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'RUC','20100070970','CORPORACION DISTRIBUIDORA FERRETERA S.A.C.','Carlos Mendoza','Av. Elmer Faucett 1980, Callao','01 574-8890','distribucion@ferretera.com','ACTIVO','2026-09-07 17:35:43'),(2,'RUC','20501234589','IMPORTADORA INDUSTRIAL HERCAR E.I.R.L.','Elena Herrera','Jr. Paruro 1024, Lima Centro','998 112 233','ventas@hercarindustrial.pe','ACTIVO','2026-09-07 17:35:43');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `tipo_comprobante` varchar(50) DEFAULT 'Boleta',
  `serie` varchar(10) NOT NULL DEFAULT 'B001',
  `correlativo` int(11) NOT NULL,
  `fecha_venta` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `impuesto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` varchar(50) DEFAULT 'Efectivo',
  `estado` varchar(20) DEFAULT 'COMPLETADA',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
INSERT INTO `ventas` VALUES (2,1,'Boleta','B001',1,'2026-09-07 12:53:09',17.80,3.20,0.00,21.00,'Efectivo','COMPLETADA','','2026-09-07 17:53:09'),(4,1,'Boleta','B001',2,'2026-09-07 13:14:00',241.53,43.47,0.00,285.00,'Efectivo','COMPLETADA','','2026-09-07 18:14:00');
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-07 22:19:30

<?php
/**
 * ContaHercar - Conexión a Base de Datos y Auto-instalación
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'contahercar_db');
define('DB_CHARSET', 'utf8mb4');

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // Conexión inicial al servidor MySQL para asegurar que la base de datos exista
        $dsnInitial = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $initPdo = new PDO($dsnInitial, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Crear base de datos si no existe
        $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Conectar a la base de datos específica
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Verificar e inicializar las tablas
        initDatabaseTables($pdo);

        return $pdo;
    } catch (PDOException $e) {
        die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;'>
            <h3>Error de Conexión a la Base de Datos</h3>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <p>Por favor verifique que el servicio MySQL esté iniciado en XAMPP.</p>
        </div>");
    }
}

function initDatabaseTables(PDO $pdo) {
    // 1. Tabla de configuración
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre_empresa VARCHAR(150) NOT NULL DEFAULT 'ContaHercar S.A.C.',
        ruc_empresa VARCHAR(20) NOT NULL DEFAULT '20601234567',
        direccion VARCHAR(255) DEFAULT 'Av. Principal 123, Lima - Perú',
        telefono VARCHAR(50) DEFAULT '+51 987 654 321',
        email VARCHAR(100) DEFAULT 'contacto@contahercar.com',
        moneda_simbolo VARCHAR(10) DEFAULT 'S/.',
        moneda_nombre VARCHAR(30) DEFAULT 'Soles',
        impuesto_nombre VARCHAR(20) DEFAULT 'IGV',
        impuesto_porcentaje DECIMAL(5,2) DEFAULT 18.00,
        api_ruc_url VARCHAR(255) DEFAULT 'https://api.decolecta.com/v1/sunat/ruc?numero={numero}',
        api_dni_url VARCHAR(255) DEFAULT 'https://api.decolecta.com/v1/reniec/dni?numero={numero}',
        api_ruc_token VARCHAR(255) DEFAULT 'sk_19173.Qv4zS5df9j2TSQLKv6shuipgGMoGXlMt',
        api_ruc_provider VARCHAR(50) DEFAULT 'decolecta',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Asegurar registro inicial de configuración
    $checkConfig = $pdo->query("SELECT COUNT(*) FROM configuracion")->fetchColumn();
    if ($checkConfig == 0) {
        $pdo->exec("INSERT INTO configuracion (
            nombre_empresa, ruc_empresa, direccion, telefono, email,
            moneda_simbolo, moneda_nombre, impuesto_nombre, impuesto_porcentaje,
            api_ruc_url, api_dni_url, api_ruc_token, api_ruc_provider
        ) VALUES (
            'ContaHercar S.A.C.', '20601234567', 'Av. La Marina 450, Lima', '01 456-7890', 'ventas@contahercar.com',
            'S/.', 'Soles', 'IGV', 18.00,
            'https://api.decolecta.com/v1/sunat/ruc?numero={numero}',
            'https://api.decolecta.com/v1/reniec/dni?numero={numero}',
            'sk_19173.Qv4zS5df9j2TSQLKv6shuipgGMoGXlMt', 'decolecta'
        )");
    }

    // 2. Tabla de categorías
    $pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT NULL,
        estado TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Tabla de productos / inventario
    $pdo->exec("CREATE TABLE IF NOT EXISTS productos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo_barra VARCHAR(50) NOT NULL UNIQUE,
        nombre VARCHAR(150) NOT NULL,
        categoria_id INT NULL,
        descripcion TEXT NULL,
        precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        precio_venta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        stock INT NOT NULL DEFAULT 0,
        stock_minimo INT NOT NULL DEFAULT 5,
        unidad_medida VARCHAR(20) DEFAULT 'UNID',
        estado TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Tabla de clientes
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo_doc VARCHAR(10) NOT NULL DEFAULT 'DNI',
        num_doc VARCHAR(20) NOT NULL UNIQUE,
        nombre_razon_social VARCHAR(200) NOT NULL,
        direccion VARCHAR(255) NULL,
        telefono VARCHAR(50) NULL,
        email VARCHAR(100) NULL,
        condicion VARCHAR(50) DEFAULT 'HABIDO',
        estado_sunat VARCHAR(50) DEFAULT 'ACTIVO',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Tabla de proveedores
    $pdo->exec("CREATE TABLE IF NOT EXISTS proveedores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo_doc VARCHAR(10) NOT NULL DEFAULT 'RUC',
        num_doc VARCHAR(20) NOT NULL UNIQUE,
        razon_social VARCHAR(200) NOT NULL,
        contacto VARCHAR(100) NULL,
        direccion VARCHAR(255) NULL,
        telefono VARCHAR(50) NULL,
        email VARCHAR(100) NULL,
        estado_sunat VARCHAR(50) DEFAULT 'ACTIVO',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Tabla de compras
    $pdo->exec("CREATE TABLE IF NOT EXISTS compras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor_id INT NOT NULL,
        tipo_comprobante VARCHAR(50) DEFAULT 'Factura',
        serie_numero VARCHAR(50) NOT NULL,
        fecha_compra DATE NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        impuesto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        estado VARCHAR(20) DEFAULT 'COMPLETADA',
        observaciones TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. Detalle de compras
    $pdo->exec("CREATE TABLE IF NOT EXISTS detalle_compras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compra_id INT NOT NULL,
        producto_id INT NOT NULL,
        cantidad INT NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 8. Tabla de ventas
    $pdo->exec("CREATE TABLE IF NOT EXISTS ventas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        tipo_comprobante VARCHAR(50) DEFAULT 'Boleta',
        serie VARCHAR(10) NOT NULL DEFAULT 'B001',
        correlativo INT NOT NULL,
        fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        impuesto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        descuento DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        metodo_pago VARCHAR(50) DEFAULT 'Efectivo',
        estado VARCHAR(20) DEFAULT 'COMPLETADA',
        observaciones TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 9. Detalle de ventas
    $pdo->exec("CREATE TABLE IF NOT EXISTS detalle_ventas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        venta_id INT NOT NULL,
        producto_id INT NOT NULL,
        cantidad INT NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL,
        costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        subtotal DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 10. Tabla de Kardex / Historial de movimientos de stock
    $pdo->exec("CREATE TABLE IF NOT EXISTS kardex (
        id INT AUTO_INCREMENT PRIMARY KEY,
        producto_id INT NOT NULL,
        tipo_movimiento VARCHAR(30) NOT NULL,
        referencia_id INT NULL,
        cantidad INT NOT NULL,
        stock_anterior INT NOT NULL,
        stock_nuevo INT NOT NULL,
        precio_unitario DECIMAL(10,2) DEFAULT 0.00,
        motivo VARCHAR(255) NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Cargar datos semilla si no hay categorías
    $checkCat = $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
    if ($checkCat == 0) {
        seedInitialData($pdo);
    }
}

function seedInitialData(PDO $pdo) {
    // Categorías iniciales
    $pdo->exec("INSERT INTO categorias (nombre, descripcion) VALUES
        ('Herramientas Manuales', 'Llaves, alicates, destornilladores, martillos'),
        ('Herramientas Eléctricas', 'Taladros, amoladoras, sierras, rotomartillos'),
        ('Materiales de Construcción', 'Cementos, pegamentos, siliconas, fijaciones'),
        ('Electricidad e Iluminación', 'Cables, focos LED, interruptores, tableros'),
        ('Pinturas y Acabados', 'Pinturas latex, esmaltes, brochas, rodillos')
    ");

    // Proveedores iniciales
    $pdo->exec("INSERT INTO proveedores (tipo_doc, num_doc, razon_social, contacto, direccion, telefono, email, estado_sunat) VALUES
        ('RUC', '20100070970', 'CORPORACION DISTRIBUIDORA FERRETERA S.A.C.', 'Carlos Mendoza', 'Av. Elmer Faucett 1980, Callao', '01 574-8890', 'distribucion@ferretera.com', 'ACTIVO'),
        ('RUC', '20501234589', 'IMPORTADORA INDUSTRIAL HERCAR E.I.R.L.', 'Elena Herrera', 'Jr. Paruro 1024, Lima Centro', '998 112 233', 'ventas@hercarindustrial.pe', 'ACTIVO')
    ");

    // Clientes iniciales
    $pdo->exec("INSERT INTO clientes (tipo_doc, num_doc, nombre_razon_social, direccion, telefono, email) VALUES
        ('DNI', '00000000', 'CLIENTE VARIOS / GENERAL', 'Lima - Perú', '999999999', 'ventas@contahercar.com'),
        ('DNI', '45891234', 'JUAN CARLOS PÉREZ RÍOS', 'Av. Arequipa 1420, Lince', '987 654 321', 'jperez@gmail.com'),
        ('RUC', '20601987654', 'CONSTRUCTORA E INMOBILIARIA DEL VALLE S.A.C.', 'Av. Javier Prado Este 2450, San Borja', '01 719-5500', 'logistica@constructordelvalle.pe')
    ");

    // Productos iniciales
    $pdo->exec("INSERT INTO productos (codigo_barra, nombre, categoria_id, descripcion, precio_compra, precio_venta, stock, stock_minimo, unidad_medida) VALUES
        ('77501001', 'Taladro Percutor 1/2 pulg 650W Bosch', 2, 'Taladro con velocidad variable y reversa', 180.00, 245.00, 18, 5, 'UNID'),
        ('77501002', 'Amoladora Angular 4-1/2 pulg 850W Dewalt', 2, 'Motor protegido contra la abrasión', 210.00, 285.00, 12, 4, 'UNID'),
        ('77501003', 'Juego de Llaves Combinadas 8-24mm Stanley (14 pzs)', 1, 'Acero cromo vanadio forjado', 75.00, 115.00, 25, 6, 'JGO'),
        ('77501004', 'Martillo Carpintero 16oz Mango Fibra Truper', 1, 'Cabeza de acero forjado pulido', 22.00, 36.00, 30, 8, 'UNID'),
        ('77501005', 'Cinta Métrica 5m / 16ft Global Plus Stanley', 1, 'Cinta recubierta de polímero', 14.50, 24.00, 4, 10, 'UNID'),
        ('77501006', 'Foco LED 12W Luz Blanca E27 Philips', 4, 'Ahorro de energía hasta 85%', 6.20, 10.50, 60, 15, 'UNID'),
        ('77501007', 'Cable Mellizo 2x14 AWG Indeco (Rollo 100m)', 4, 'Conductor de cobre electrolítico recocido', 115.00, 160.00, 3, 5, 'ROLLO'),
        ('77501008', 'Silicona Multiuso Transparente 280ml Tekbond', 3, 'Excelente adherencia para sellados generales', 11.00, 18.00, 45, 10, 'UNID')
    ");

    // Registrar kardex inicial de los productos
    $stmt = $pdo->query("SELECT id, stock, precio_compra FROM productos");
    $productos = $stmt->fetchAll();
    $insertKardex = $pdo->prepare("INSERT INTO kardex (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo) VALUES (?, 'INVENTARIO_INICIAL', ?, 0, ?, ?, 'Carga inicial de inventario')");
    foreach ($productos as $p) {
        $insertKardex->execute([$p['id'], $p['stock'], $p['stock'], $p['precio_compra']]);
    }
}


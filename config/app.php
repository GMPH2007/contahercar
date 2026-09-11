<?php
/**
 * ContaHercar - Funciones de Utilidad y Configuración General
 */

if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Obtener configuración actual del sistema
 */
function getSystemConfig() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM configuracion ORDER BY id ASC LIMIT 1");
    $config = $stmt->fetch();
    if (!$config) {
        // Valores por defecto
        $config = [
            'nombre_empresa' => 'ContaHercar S.A.C.',
            'ruc_empresa' => '20601234567',
            'direccion' => 'Av. Principal 123, Lima - Perú',
            'telefono' => '+51 987 654 321',
            'email' => 'contacto@contahercar.com',
            'moneda_simbolo' => 'S/.',
            'moneda_nombre' => 'Soles',
            'impuesto_nombre' => 'IGV',
            'impuesto_porcentaje' => 18.00,
            'api_ruc_url' => 'https://api.apis.net.pe/v2/sunat/ruc?numero={numero}',
            'api_dni_url' => 'https://api.apis.net.pe/v2/reniec/dni?numero={numero}',
            'api_ruc_token' => '',
            'api_ruc_provider' => 'apisnet'
        ];
    }
    return $config;
}

/**
 * Formatear un valor numérico a moneda
 */
function formatMoney($amount) {
    $cfg = getSystemConfig();
    $symbol = $cfg['moneda_simbolo'] ?? 'S/.';
    return $symbol . ' ' . number_format((float)$amount, 2, '.', ',');
}

/**
 * Formatear fecha
 */
function formatDate($dateStr) {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    return date('d/m/Y', $time);
}

/**
 * Formatear fecha y hora
 */
function formatDateTime($dateTimeStr) {
    if (empty($dateTimeStr)) return '-';
    $time = strtotime($dateTimeStr);
    return date('d/m/Y H:i', $time);
}

/**
 * Limpiar entradas de texto
 */
function sanitize($value) {
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }
    return trim(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}

/**
 * Manejo de mensajes flash en sesión (SweetAlert o alertas estándar)
 */
function setFlash($type, $title, $message = '') {
    $_SESSION['flash_msg'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'title' => $title,
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
        return $msg;
    }
    return null;
}

/**
 * Respuesta JSON limpia
 */
function jsonResponse($data, $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Obtener correlativo siguiente para una serie
 */
function getNextCorrelativo($tipoComprobante, $serie) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT MAX(correlativo) as max_corr FROM ventas WHERE tipo_comprobante = ? AND serie = ?");
    $stmt->execute([$tipoComprobante, $serie]);
    $res = $stmt->fetch();
    $next = ($res && $res['max_corr']) ? (int)$res['max_corr'] + 1 : 1;
    return $next;
}

/**
 * Contadores globales para la barra de navegación o dashboard
 */
function getQuickCounters() {
    $pdo = getDBConnection();
    
    // Productos con stock bajo
    $stmtLowStock = $pdo->query("SELECT COUNT(*) FROM productos WHERE estado = 1 AND stock <= stock_minimo");
    $lowStockCount = (int)$stmtLowStock->fetchColumn();

    // Total de productos
    $stmtProducts = $pdo->query("SELECT COUNT(*) FROM productos WHERE estado = 1");
    $productsCount = (int)$stmtProducts->fetchColumn();

    // Total de clientes
    $stmtClients = $pdo->query("SELECT COUNT(*) FROM clientes");
    $clientsCount = (int)$stmtClients->fetchColumn();

    // Total de proveedores
    $stmtSuppliers = $pdo->query("SELECT COUNT(*) FROM proveedores");
    $suppliersCount = (int)$stmtSuppliers->fetchColumn();

    return [
        'low_stock' => $lowStockCount,
        'products' => $productsCount,
        'clients' => $clientsCount,
        'suppliers' => $suppliersCount
    ];
}


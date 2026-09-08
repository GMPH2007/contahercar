<?php
/**
 * ContaHercar - Búsqueda de Productos AJAX
 */

require_once __DIR__ . '/../config/app.php';

$term = isset($_GET['q']) ? trim($_GET['q']) : '';
$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';

$pdo = getDBConnection();

if (!empty($barcode)) {
    $stmt = $pdo->prepare("SELECT p.*, c.nombre as categoria_nombre 
                           FROM productos p 
                           LEFT JOIN categorias c ON p.categoria_id = c.id 
                           WHERE p.codigo_barra = ? AND p.estado = 1 LIMIT 1");
    $stmt->execute([$barcode]);
    $producto = $stmt->fetch();

    if ($producto) {
        jsonResponse([
            'success' => true,
            'producto' => $producto
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Producto no encontrado por código de barras.'
        ], 404);
    }
}

$query = "SELECT p.*, c.nombre as categoria_nombre 
          FROM productos p 
          LEFT JOIN categorias c ON p.categoria_id = c.id 
          WHERE p.estado = 1";
$params = [];

if (!empty($term)) {
    $query .= " AND (p.nombre LIKE ? OR p.codigo_barra LIKE ?)";
    $params[] = "%$term%";
    $params[] = "%$term%";
}

$query .= " ORDER BY p.nombre ASC LIMIT 30";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'total' => count($productos),
    'productos' => $productos
]);


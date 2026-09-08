<?php
/**
 * ContaHercar - Consulta de Kardex por Producto
 */

require_once __DIR__ . '/../config/app.php';

$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

if ($productoId <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID de producto no especificado.'], 400);
}

$pdo = getDBConnection();

$stmtProd = $pdo->prepare("SELECT id, nombre, codigo_barra, stock, precio_compra, precio_venta, unidad_medida FROM productos WHERE id = ?");
$stmtProd->execute([$productoId]);
$producto = $stmtProd->fetch();

if (!$producto) {
    jsonResponse(['success' => false, 'message' => 'Producto no encontrado.'], 404);
}

$stmtKardex = $pdo->prepare("SELECT * FROM kardex WHERE producto_id = ? ORDER BY fecha DESC, id DESC LIMIT 50");
$stmtKardex->execute([$productoId]);
$movimientos = $stmtKardex->fetchAll();

jsonResponse([
    'success' => true,
    'producto' => $producto,
    'movimientos' => $movimientos
]);


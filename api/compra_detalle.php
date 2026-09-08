<?php
/**
 * ContaHercar - Detalle de Compra AJAX
 */

require_once __DIR__ . '/../config/app.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID no válido'], 400);
}

$pdo = getDBConnection();
$cfg = getSystemConfig();

$stmtC = $pdo->prepare("SELECT c.*, p.razon_social as proveedor_nombre, p.num_doc as proveedor_ruc 
    FROM compras c 
    INNER JOIN proveedores p ON c.proveedor_id = p.id 
    WHERE c.id = ?");
$stmtC->execute([$id]);
$compra = $stmtC->fetch();

if (!$compra) {
    jsonResponse(['success' => false, 'message' => 'Compra no encontrada'], 404);
}

$stmtD = $pdo->prepare("SELECT dc.*, p.nombre as producto_nombre, p.codigo_barra, p.unidad_medida 
    FROM detalle_compras dc 
    INNER JOIN productos p ON dc.producto_id = p.id 
    WHERE dc.compra_id = ?");
$stmtD->execute([$id]);
$detalles = $stmtD->fetchAll();

$items = [];
foreach ($detalles as $d) {
    $items[] = [
        'producto_id' => $d['producto_id'],
        'producto_nombre' => $d['producto_nombre'],
        'codigo_barra' => $d['codigo_barra'],
        'unidad_medida' => $d['unidad_medida'],
        'cantidad' => $d['cantidad'],
        'precio_unitario' => (float)$d['precio_unitario'],
        'precio_unitario_fmt' => formatMoney($d['precio_unitario']),
        'subtotal' => (float)$d['subtotal'],
        'subtotal_fmt' => formatMoney($d['subtotal'])
    ];
}

jsonResponse([
    'success' => true,
    'compra' => [
        'id' => $compra['id'],
        'tipo_comprobante' => $compra['tipo_comprobante'],
        'serie_numero' => $compra['serie_numero'],
        'fecha_compra' => formatDate($compra['fecha_compra']),
        'proveedor_nombre' => $compra['proveedor_nombre'],
        'proveedor_ruc' => $compra['proveedor_ruc'],
        'estado' => $compra['estado'],
        'subtotal' => (float)$compra['subtotal'],
        'subtotal_fmt' => formatMoney($compra['subtotal']),
        'impuesto' => (float)$compra['impuesto'],
        'impuesto_fmt' => formatMoney($compra['impuesto']),
        'impuesto_nombre' => $cfg['impuesto_nombre'],
        'total' => (float)$compra['total'],
        'total_fmt' => formatMoney($compra['total']),
        'observaciones' => $compra['observaciones']
    ],
    'items' => $items
]);


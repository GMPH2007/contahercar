<?php
/**
 * ContaHercar - Detalle de Venta AJAX
 */

require_once __DIR__ . '/../config/app.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID no válido'], 400);
}

$pdo = getDBConnection();
$cfg = getSystemConfig();

$stmtV = $pdo->prepare("SELECT v.*, c.nombre_razon_social as cliente_nombre, c.num_doc as cliente_doc 
    FROM ventas v 
    INNER JOIN clientes c ON v.cliente_id = c.id 
    WHERE v.id = ?");
$stmtV->execute([$id]);
$venta = $stmtV->fetch();

if (!$venta) {
    jsonResponse(['success' => false, 'message' => 'Venta no encontrada'], 404);
}

$stmtD = $pdo->prepare("SELECT dv.*, p.nombre as producto_nombre, p.codigo_barra, p.unidad_medida 
    FROM detalle_ventas dv 
    INNER JOIN productos p ON dv.producto_id = p.id 
    WHERE dv.venta_id = ?");
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
    'venta' => [
        'id' => $venta['id'],
        'tipo_comprobante' => $venta['tipo_comprobante'],
        'serie' => $venta['serie'],
        'correlativo' => str_pad($venta['correlativo'], 6, '0', STR_PAD_LEFT),
        'fecha_venta' => formatDateTime($venta['fecha_venta']),
        'cliente_nombre' => $venta['cliente_nombre'],
        'cliente_doc' => $venta['cliente_doc'],
        'metodo_pago' => $venta['metodo_pago'],
        'estado' => $venta['estado'],
        'subtotal' => (float)$venta['subtotal'],
        'subtotal_fmt' => formatMoney($venta['subtotal']),
        'impuesto' => (float)$venta['impuesto'],
        'impuesto_fmt' => formatMoney($venta['impuesto']),
        'impuesto_nombre' => $cfg['impuesto_nombre'],
        'total' => (float)$venta['total'],
        'total_fmt' => formatMoney($venta['total']),
        'observaciones' => $venta['observaciones']
    ],
    'items' => $items
]);


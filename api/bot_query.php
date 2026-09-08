<?php
/**
 * ContaSmart - API Backend para Consultas en Tiempo Real del Asistente Bot Siri
 */
require_once __DIR__ . '/../config/app.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = getDBConnection();
$cfg = getSystemConfig();
$tipo = $_GET['tipo'] ?? 'resumen_general';

try {
    if ($tipo === 'resumen_general') {
        $hoy = date('Y-m-d');
        $inicioMes = date('Y-m-01 00:00:00');
        $finMes = date('Y-m-t 23:59:59');

        // Ventas de hoy
        $stmtHoy = $pdo->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total), 0) as total FROM ventas WHERE estado = 'COMPLETADA' AND DATE(fecha_venta) = ?");
        $stmtHoy->execute([$hoy]);
        $ventasHoy = $stmtHoy->fetch();

        // Ventas del mes
        $stmtMes = $pdo->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total), 0) as total FROM ventas WHERE estado = 'COMPLETADA' AND fecha_venta BETWEEN ? AND ?");
        $stmtMes->execute([$inicioMes, $finMes]);
        $ventasMes = $stmtMes->fetch();

        // Compras del mes
        $stmtComp = $pdo->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total), 0) as total FROM compras WHERE estado = 'COMPLETADA' AND fecha_compra BETWEEN ? AND ?");
        $stmtComp->execute([date('Y-m-01'), date('Y-m-t')]);
        $comprasMes = $stmtComp->fetch();

        // Costo de ventas para calcular utilidad real
        $stmtCosto = $pdo->prepare("SELECT COALESCE(SUM(dv.costo_unitario * dv.cantidad), 0) 
            FROM detalle_ventas dv INNER JOIN ventas v ON dv.venta_id = v.id 
            WHERE v.estado = 'COMPLETADA' AND v.fecha_venta BETWEEN ? AND ?");
        $stmtCosto->execute([$inicioMes, $finMes]);
        $costoMes = (float)$stmtCosto->fetchColumn();
        $utilidadMes = (float)$ventasMes['total'] - $costoMes;

        // Productos con stock bajo
        $stmtStock = $pdo->query("SELECT id, nombre, stock, stock_minimo, precio_venta, unidad_medida 
            FROM productos WHERE estado = 1 AND stock <= stock_minimo ORDER BY stock ASC LIMIT 6");
        $stockBajo = $stmtStock->fetchAll();

        // Conteo total de productos y clientes
        $totalProds = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE estado = 1")->fetchColumn();
        $totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();

        jsonResponse([
            'success' => true,
            'moneda' => $cfg['moneda_simbolo'],
            'ventas_hoy' => [
                'cantidad' => (int)$ventasHoy['cant'],
                'total' => (float)$ventasHoy['total'],
                'total_formateado' => formatMoney($ventasHoy['total'])
            ],
            'ventas_mes' => [
                'cantidad' => (int)$ventasMes['cant'],
                'total' => (float)$ventasMes['total'],
                'total_formateado' => formatMoney($ventasMes['total'])
            ],
            'compras_mes' => [
                'cantidad' => (int)$comprasMes['cant'],
                'total' => (float)$comprasMes['total'],
                'total_formateado' => formatMoney($comprasMes['total'])
            ],
            'utilidad_mes' => [
                'total' => $utilidadMes,
                'total_formateado' => formatMoney($utilidadMes)
            ],
            'stock_bajo' => [
                'total_criticos' => count($stockBajo),
                'items' => $stockBajo
            ],
            'total_productos' => $totalProds,
            'total_clientes' => $totalClientes
        ]);
    }

    if ($tipo === 'stock_bajo') {
        $stmt = $pdo->query("SELECT p.*, c.nombre as categoria_nombre 
            FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.estado = 1 AND p.stock <= p.stock_minimo 
            ORDER BY p.stock ASC");
        $items = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'total' => count($items),
            'items' => $items
        ]);
    }

    if ($tipo === 'ultimas_ventas') {
        $stmt = $pdo->query("SELECT v.*, c.nombre_razon_social as cliente_nombre 
            FROM ventas v INNER JOIN clientes c ON v.cliente_id = c.id 
            ORDER BY v.fecha_venta DESC LIMIT 5");
        $items = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'items' => $items
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Tipo de consulta no válido.'], 400);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

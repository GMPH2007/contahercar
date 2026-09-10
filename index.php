<?php
$pageTitle = 'Dashboard - ContaSmart Inteligente';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();

// Fecha actual
$hoy = date('Y-m-d');
$mesActual = date('Y-m');
$inicioMes = date('Y-m-01 00:00:00');
$finMes = date('Y-m-t 23:59:59');

// 1. Ventas de Hoy
$stmtVentasHoy = $pdo->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total), 0) as total FROM ventas WHERE estado = 'COMPLETADA' AND DATE(fecha_venta) = ?");
$stmtVentasHoy->execute([$hoy]);
$ventasHoy = $stmtVentasHoy->fetch();

// 2. Ventas del Mes
$stmtVentasMes = $pdo->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total), 0) as total, COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(impuesto), 0) as impuesto FROM ventas WHERE estado = 'COMPLETADA' AND fecha_venta BETWEEN ? AND ?");
$stmtVentasMes->execute([$inicioMes, $finMes]);
$ventasMes = $stmtVentasMes->fetch();

// Ventas mes anterior para cálculo de variación %
$inicioMesAnt = date('Y-m-01 00:00:00', strtotime('-1 month'));
$finMesAnt = date('Y-m-t 23:59:59', strtotime('-1 month'));
$stmtVentasAnt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total FROM ventas WHERE estado = 'COMPLETADA' AND fecha_venta BETWEEN ? AND ?");
$stmtVentasAnt->execute([$inicioMesAnt, $finMesAnt]);
$totalVentasAnt = (float)$stmtVentasAnt->fetchColumn();

$varVentasPct = 0;
if ($totalVentasAnt > 0) {
    $varVentasPct = round((((float)$ventasMes['total'] - $totalVentasAnt) / $totalVentasAnt) * 100, 1);
}

// 3. Compras del Mes
$stmtComprasMes = $pdo->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total), 0) as total FROM compras WHERE estado = 'COMPLETADA' AND fecha_compra BETWEEN ? AND ?");
$stmtComprasMes->execute([date('Y-m-01'), date('Y-m-t')]);
$comprasMes = $stmtComprasMes->fetch();

// 4. Costo de Ventas del Mes (para calcular ganancia bruta real)
$stmtCostoVentas = $pdo->prepare("SELECT COALESCE(SUM(dv.costo_unitario * dv.cantidad), 0) as costo_total 
    FROM detalle_ventas dv 
    INNER JOIN ventas v ON dv.venta_id = v.id 
    WHERE v.estado = 'COMPLETADA' AND v.fecha_venta BETWEEN ? AND ?");
$stmtCostoVentas->execute([$inicioMes, $finMes]);
$costoVentasMes = (float)$stmtCostoVentas->fetchColumn();

// Utilidad Bruta del Mes = Total Ventas - Costo de los productos vendidos
$utilidadMes = (float)$ventasMes['total'] - $costoVentasMes;

// 5. Total de Inventario Valorizado y Cantidad de Productos
$stmtInventario = $pdo->query("SELECT 
    COUNT(*) as total_items,
    COALESCE(SUM(stock), 0) as total_unidades,
    COALESCE(SUM(stock * precio_compra), 0) as valor_costo,
    COALESCE(SUM(stock * precio_venta), 0) as valor_venta,
    SUM(CASE WHEN stock <= stock_minimo THEN 1 ELSE 0 END) as total_stock_bajo
FROM productos WHERE estado = 1");
$invStats = $stmtInventario->fetch();

// Desglose de salud de inventario (Óptimo, Stock Bajo, Agotado) para gráfico Donut
$stmtInvBreakdown = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN stock > 0 AND stock <= stock_minimo THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN stock > stock_minimo THEN 1 ELSE 0 END) as in_stock
FROM productos WHERE estado = 1");
$invBreakdown = $stmtInvBreakdown->fetch();

// 6. Lista de Productos para Monitoreo de Almacén
$stmtProductosTable = $pdo->query("SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.estado = 1 
    ORDER BY (p.stock <= p.stock_minimo) DESC, p.stock ASC LIMIT 6");
$productosTable = $stmtProductosTable->fetchAll();

// 7. Últimas 5 Ventas
$stmtUltimasVentas = $pdo->query("SELECT v.*, c.nombre_razon_social as cliente_nombre 
    FROM ventas v 
    INNER JOIN clientes c ON v.cliente_id = c.id 
    ORDER BY v.fecha_venta DESC, v.id DESC LIMIT 5");
$ultimasVentas = $stmtUltimasVentas->fetchAll();

// 8. Últimas 5 Compras
$stmtUltimasCompras = $pdo->query("SELECT c.*, p.razon_social as proveedor_nombre 
    FROM compras c 
    INNER JOIN proveedores p ON c.proveedor_id = p.id 
    ORDER BY c.fecha_compra DESC, c.id DESC LIMIT 5");
$ultimasCompras = $stmtUltimasCompras->fetchAll();

// 9. Datos para el gráfico de los últimos 6 meses (Cash Flow)
$mesesChart = [];
$ventasChart = [];
$comprasChart = [];

for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("-$i month");
    $mesKey = date('Y-m', $time);
    $mesLabel = date('M Y', $time);
    $mesesChart[] = $mesLabel;

    $ini = $mesKey . '-01 00:00:00';
    $fin = date('Y-m-t 23:59:59', $time);

    // Ventas del mes
    $sV = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE estado = 'COMPLETADA' AND fecha_venta BETWEEN ? AND ?");
    $sV->execute([$ini, $fin]);
    $ventasChart[] = (float)$sV->fetchColumn();

    // Compras del mes
    $sC = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM compras WHERE estado = 'COMPLETADA' AND fecha_compra BETWEEN ? AND ?");
    $sC->execute([$mesKey . '-01', date('Y-m-t', $time)]);
    $comprasChart[] = (float)$sC->fetchColumn();
}
?>

<!-- ============================================================
     1. BANNER EJECUTIVO CONTA SMART (ENFOQUE MYPE PERUANA)
     ============================================================ -->
<div class="card-custom p-4 mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; border-radius: 16px;" id="tourHeaderGreeting">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-primary px-3 py-1 font-monospace" style="font-size: 0.75rem;">CONTA SMART v3.4</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;">
                    <i class="fa fa-circle-check me-1"></i> SIRE SUNAT Activo
                </span>
            </div>
            <h3 class="fw-bold mb-1 text-white">
                ¡Hola, <?= htmlspecialchars($cfg['nombre_empresa']) ?>! 👋
            </h3>
            <p class="text-light text-opacity-75 small mb-0">
                Sistema inteligente de gestión contable, analítica financiera y registros electrónicos para MYPES.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 w-100 w-lg-auto mt-2 mt-lg-0">
            <button type="button" class="btn btn-sm px-3 py-2 btn-open-ai fw-bold shadow text-white d-flex align-items-center justify-content-center gap-2 flex-fill flex-sm-grow-0" style="background: linear-gradient(135deg, #2563eb, #8b5cf6);" onclick="if(window.openSiri) openSiri(); else if(window.ContaSmartAI) ContaSmartAI.open();" title="Hablar con Siri ContaSmart (Comandos de Voz)">
                <i class="fa fa-microphone text-warning"></i>
                <span>Hablar con Siri</span>
            </button>
            <a href="venta_nueva.php" class="btn btn-success btn-sm px-3 py-2 fw-bold shadow d-flex align-items-center justify-content-center gap-1 flex-fill flex-sm-grow-0" title="Ir al Punto de Venta POS">
                <i class="fa fa-cash-register"></i>
                <span>Emitir Venta POS</span>
            </a>
            <a href="consulta_sunat.php" class="btn btn-info btn-sm px-3 py-2 fw-bold text-dark shadow d-flex align-items-center justify-content-center gap-1 flex-fill flex-sm-grow-0" title="Consultar RUC o DNI en Vivo SUNAT/RENIEC">
                <i class="fa fa-building-flag"></i>
                <span>Buscar RUC / DNI</span>
            </a>
        </div>
    </div>
</div>

<!-- ============================================================
     2. TARJETAS KPI INTELIGENTES (Cuadrícula 2x2 en Celular)
     ============================================================ -->
<div class="row g-3 g-md-3 mb-4" id="tourKpiCards">
    <!-- Ventas del Mes -->
    <div class="col-6 col-xl-3">
        <a href="ventas.php" class="text-decoration-none d-block h-100">
            <div class="kpi-smart-card h-100">
                <div class="kpi-header">
                    <span class="kpi-title">Ventas del Mes</span>
                    <div class="kpi-icon-wrap bg-primary bg-opacity-10 text-primary">
                        <i class="fa fa-wallet"></i>
                    </div>
                </div>
                <div class="kpi-value text-primary"><?= formatMoney($ventasMes['total']) ?></div>
                <div class="kpi-footer">
                    <span class="badge-trend-up">
                        <i class="fa fa-arrow-trend-up me-1"></i><?= $varVentasPct >= 0 ? '+' : '' ?><?= $varVentasPct ?>%
                    </span>
                    <span class="d-none d-sm-inline"><?= $ventasMes['cant'] ?> comprobantes</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Compras & Egresos -->
    <div class="col-6 col-xl-3">
        <a href="compras.php" class="text-decoration-none d-block h-100">
            <div class="kpi-smart-card h-100">
                <div class="kpi-header">
                    <span class="kpi-title">Compras & Gastos</span>
                    <div class="kpi-icon-wrap bg-warning bg-opacity-10 text-warning">
                        <i class="fa fa-cart-shopping"></i>
                    </div>
                </div>
                <div class="kpi-value text-dark"><?= formatMoney($comprasMes['total']) ?></div>
                <div class="kpi-footer">
                    <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.72rem;">
                        Insumos
                    </span>
                    <span class="d-none d-sm-inline"><?= $comprasMes['cant'] ?> compras</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Margen Comercial / Utilidad -->
    <div class="col-6 col-xl-3">
        <a href="reportes.php" class="text-decoration-none d-block h-100">
            <div class="kpi-smart-card h-100">
                <div class="kpi-header">
                    <span class="kpi-title">Utilidad Bruta</span>
                    <div class="kpi-icon-wrap bg-success bg-opacity-10 text-success">
                        <i class="fa fa-sack-dollar"></i>
                    </div>
                </div>
                <div class="kpi-value text-success"><?= formatMoney($utilidadMes) ?></div>
                <div class="kpi-footer">
                    <span class="badge-trend-up">
                        <i class="fa fa-shield-check me-1"></i>Rentable
                    </span>
                    <span class="d-none d-sm-inline">Margen neto</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Alertas de Stock Bajo -->
    <div class="col-6 col-xl-3">
        <a href="inventario.php?filtro=stock_bajo" class="text-decoration-none d-block h-100">
            <div class="kpi-smart-card h-100">
                <div class="kpi-header">
                    <span class="kpi-title">Alerta de Stock</span>
                    <div class="kpi-icon-wrap bg-danger bg-opacity-10 text-danger">
                        <i class="fa fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="kpi-value text-danger"><?= (int)$invStats['total_stock_bajo'] ?> <small class="fs-6 fw-normal text-muted">items</small></div>
                <div class="kpi-footer">
                    <?php if ((int)$invStats['total_stock_bajo'] > 0): ?>
                        <span class="badge-trend-down">
                            <i class="fa fa-arrow-down me-1"></i>Reponer
                        </span>
                        <span class="text-danger fw-semibold small">Ver &rarr;</span>
                    <?php else: ?>
                        <span class="badge-trend-up">
                            <i class="fa fa-check me-1"></i>Óptimo
                        </span>
                        <span class="d-none d-sm-inline">Inventario en regla</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ============================================================
     3. CUADRÍCULA DE ACCIONES RÁPIDAS (ESTILO STOCK MATE)
     ============================================================ -->
<div class="mb-4" id="tourQuickActions">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold text-dark text-uppercase small mb-0">
            <i class="fa fa-bolt text-warning me-1"></i> Acciones Rápidas del Sistema
        </h6>
        <small class="text-muted">Operaciones con 1 clic</small>
    </div>
    <div class="row g-3">
        <div class="col-4 col-md-2">
            <a href="javascript:void(0)" onclick="openPosModal()" class="quick-action-card text-decoration-none">
                <div class="action-icon-circle bg-primary bg-opacity-10 text-primary">
                    <i class="fa fa-cash-register"></i>
                </div>
                <span class="action-label">Venta POS</span>
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="compra_nueva.php" class="quick-action-card text-decoration-none">
                <div class="action-icon-circle bg-warning bg-opacity-10 text-warning">
                    <i class="fa fa-cart-arrow-down"></i>
                </div>
                <span class="action-label">+ Compra</span>
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="clientes.php" class="quick-action-card">
                <div class="action-icon-circle bg-success bg-opacity-10 text-success">
                    <i class="fa fa-user-plus"></i>
                </div>
                <span class="action-label">+ Cliente</span>
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="consulta_sunat.php" class="quick-action-card">
                <div class="action-icon-circle bg-info bg-opacity-10 text-info">
                    <i class="fa fa-building-flag"></i>
                </div>
                <span class="action-label">RUC / SUNAT</span>
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="sire.php" class="quick-action-card">
                <div class="action-icon-circle bg-primary bg-opacity-10 text-primary">
                    <i class="fa fa-file-zipper"></i>
                </div>
                <span class="action-label">SIRE Libros</span>
            </a>
        </div>
        <div class="col-4 col-md-2">
            <div class="quick-action-card btn-open-ai" onclick="if(window.openSiri) openSiri(); else if(window.ContaSmartAI) ContaSmartAI.open();" style="border: 1px dashed #3b82f6; cursor: pointer;" title="Hablar con Siri ContaSmart (Comandos de Voz)">
                <div class="action-icon-circle bg-danger bg-opacity-10 text-danger">
                    <i class="fa fa-microphone"></i>
                </div>
                <span class="action-label text-primary">ContaVoz IA</span>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     4. GRÁFICOS: FLUJO DE CAJA & SALUD DE INVENTARIO (INVENTO STYLE)
     ============================================================ -->
<div class="row g-3 mb-4" id="tourChartsSection">
    <!-- Flujo de Caja (Cash Flow mensual) -->
    <div class="col-12 col-lg-8">
        <div class="card-custom h-100">
            <div class="card-custom-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0"><i class="fa fa-chart-column text-primary me-2"></i>Flujo de Caja (Cash Flow)</h5>
                    <small class="text-muted">Comparativa de Ventas vs Compras de los últimos 6 meses</small>
                </div>
                <span class="badge bg-light text-secondary border">En <?= htmlspecialchars($cfg['moneda_simbolo']) ?></span>
            </div>
            <div class="card-custom-body">
                <canvas id="chartVentasCompras" height="270"></canvas>
            </div>
        </div>
    </div>

    <!-- Donut de Salud de Inventario (Inventory Alerts) -->
    <div class="col-12 col-lg-4">
        <div class="card-custom h-100">
            <div class="card-custom-header">
                <h5 class="mb-0"><i class="fa fa-chart-pie text-success me-2"></i>Salud del Inventario</h5>
                <small class="text-muted">Estado actual de existencias</small>
            </div>
            <div class="card-custom-body d-flex flex-column align-items-center justify-content-center">
                <div style="width: 170px; height: 170px; position: relative;">
                    <canvas id="chartDonutInventario"></canvas>
                </div>
                <div class="w-100 mt-3 pt-2 border-top">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><i class="fa fa-circle text-success me-1"></i> En Stock Óptimo:</span>
                        <strong class="text-success"><?= (int)$invBreakdown['in_stock'] ?> productos</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><i class="fa fa-circle text-warning me-1"></i> Stock Bajo (Riesgo):</span>
                        <strong class="text-warning"><?= (int)$invBreakdown['low_stock'] ?> productos</strong>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span><i class="fa fa-circle text-danger me-1"></i> Agotados:</span>
                        <strong class="text-danger"><?= (int)$invBreakdown['out_of_stock'] ?> productos</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     5. MONITOREO INTELIGENTE DE PRODUCTOS (INVENTO / INVENTORY REPORT)
     ============================================================ -->
<div class="card-custom mb-4" id="tourInventoryTable">
    <div class="card-custom-header d-flex align-items-center justify-content-between">
        <div>
            <h5 class="mb-0"><i class="fa fa-boxes-stacked text-primary me-2"></i>Monitoreo Inteligente de Almacén</h5>
            <small class="text-muted">Supervisión automática de stock y sugerencias de reabastecimiento</small>
        </div>
        <a href="inventario.php" class="btn btn-sm btn-outline-primary fw-semibold">
            Ver Catálogo Completo &rarr;
        </a>
    </div>
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Producto</th>
                        <th>Código</th>
                        <th>Categoría</th>
                        <th>Precio Venta</th>
                        <th>Stock Actual</th>
                        <th>Estado Inteligente</th>
                        <th class="text-end pe-3">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productosTable as $p): ?>
                        <?php 
                            $esCritico = ($p['stock'] <= $p['stock_minimo']); 
                            $esAgotado = ($p['stock'] <= 0);
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-light rounded p-2 text-primary" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa fa-box"></i>
                                    </div>
                                    <span><?= htmlspecialchars($p['nombre']) ?></span>
                                </div>
                            </td>
                            <td class="font-monospace small text-muted"><?= htmlspecialchars($p['codigo_barra']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['categoria_nombre'] ?? 'General') ?></span></td>
                            <td class="fw-bold"><?= formatMoney($p['precio_venta']) ?></td>
                            <td>
                                <span class="fw-bold font-monospace <?= $esCritico ? 'text-danger' : 'text-dark' ?>">
                                    <?= $p['stock'] ?> <?= htmlspecialchars($p['unidad_medida']) ?>
                                </span>
                                <small class="text-muted">(Mín: <?= $p['stock_minimo'] ?>)</small>
                            </td>
                            <td>
                                <?php if ($esAgotado): ?>
                                    <span class="badge bg-danger">Agotado</span>
                                <?php elseif ($esCritico): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fa fa-triangle-exclamation me-1"></i> Stock Bajo
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">En Stock</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if ($esCritico): ?>
                                    <a href="compra_nueva.php" class="btn btn-xs btn-outline-warning fw-bold py-1 px-2" title="Reponer este producto">
                                        <i class="fa fa-cart-plus me-1"></i> Reponer
                                    </a>
                                <?php else: ?>
                                    <a href="venta_nueva.php" class="btn btn-xs btn-outline-primary py-1 px-2" title="Vender en POS">
                                        <i class="fa fa-cash-register me-1"></i> Vender
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================
     6. ACTIVIDAD RECIENTE: VENTAS Y COMPRAS
     ============================================================ -->
<div class="row g-3">
    <!-- Últimas Ventas -->
    <div class="col-12 col-lg-6">
        <div class="card-custom h-100">
            <div class="card-custom-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fa fa-receipt text-success me-2"></i>Últimas Ventas Emitidas</h5>
                <a href="ventas.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Doc / Serie</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimasVentas)): ?>
                                <tr><td colspan="5" class="text-center py-3 text-muted">Aún no hay ventas registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ultimasVentas as $v): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold"><?= htmlspecialchars($v['tipo_comprobante']) ?></span><br>
                                            <small class="text-muted"><?= htmlspecialchars($v['serie']) ?>-<?= str_pad($v['correlativo'], 6, '0', STR_PAD_LEFT) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($v['cliente_nombre']) ?></td>
                                        <td><small><?= formatDate($v['fecha_venta']) ?></small></td>
                                        <td class="fw-bold text-success"><?= formatMoney($v['total']) ?></td>
                                        <td>
                                            <a href="ticket.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimir Ticket">
                                                <i class="fa fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimas Compras -->
    <div class="col-12 col-lg-6">
        <div class="card-custom h-100">
            <div class="card-custom-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fa fa-cart-shopping text-warning me-2"></i>Últimas Compras</h5>
                <a href="compras.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Comprobante</th>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimasCompras)): ?>
                                <tr><td colspan="5" class="text-center py-3 text-muted">Aún no hay compras registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ultimasCompras as $c): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold"><?= htmlspecialchars($c['tipo_comprobante']) ?></span><br>
                                            <small class="text-muted"><?= htmlspecialchars($c['serie_numero']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($c['proveedor_nombre']) ?></td>
                                        <td><small><?= formatDate($c['fecha_compra']) ?></small></td>
                                        <td class="fw-bold text-warning"><?= formatMoney($c['total']) ?></td>
                                        <td>
                                            <span class="badge <?= $c['estado'] === 'COMPLETADA' ? 'badge-soft-success' : 'badge-soft-danger' ?>">
                                                <?= htmlspecialchars($c['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts de Gráficos Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Gráfico de Barras Flujo de Caja
    const ctxBar = document.getElementById('chartVentasCompras');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($mesesChart) ?>,
                datasets: [
                    {
                        label: 'Ventas (<?= $cfg['moneda_simbolo'] ?>)',
                        data: <?= json_encode($ventasChart) ?>,
                        backgroundColor: 'rgba(37, 99, 235, 0.85)',
                        borderRadius: 6,
                        borderWidth: 0
                    },
                    {
                        label: 'Compras (<?= $cfg['moneda_simbolo'] ?>)',
                        data: <?= json_encode($comprasChart) ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.85)',
                        borderRadius: 6,
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { family: 'Inter', size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += '<?= $cfg['moneda_simbolo'] ?> ' + context.parsed.y.toLocaleString('es-PE', { minimumFractionDigits: 2 });
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '<?= $cfg['moneda_simbolo'] ?> ' + value.toLocaleString();
                            }
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Gráfico Donut de Salud de Inventario (Invento Style)
    const ctxDonut = document.getElementById('chartDonutInventario');
    if (ctxDonut) {
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Óptimo', 'Stock Bajo', 'Agotado'],
                datasets: [{
                    data: [
                        <?= (int)$invBreakdown['in_stock'] ?>,
                        <?= (int)$invBreakdown['low_stock'] ?>,
                        <?= (int)$invBreakdown['out_of_stock'] ?>
                    ],
                    backgroundColor: [
                        '#10b981', // Verde
                        '#f59e0b', // Amarillo
                        '#ef4444'  // Rojo
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + context.parsed + ' productos';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
$pageTitle = 'Dashboard - Contadores & Métricas';
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

// 6. Últimas 5 Ventas
$stmtUltimasVentas = $pdo->query("SELECT v.*, c.nombre_razon_social as cliente_nombre 
    FROM ventas v 
    INNER JOIN clientes c ON v.cliente_id = c.id 
    ORDER BY v.fecha_venta DESC, v.id DESC LIMIT 5");
$ultimasVentas = $stmtUltimasVentas->fetchAll();

// 7. Últimas 5 Compras
$stmtUltimasCompras = $pdo->query("SELECT c.*, p.razon_social as proveedor_nombre 
    FROM compras c 
    INNER JOIN proveedores p ON c.proveedor_id = p.id 
    ORDER BY c.fecha_compra DESC, c.id DESC LIMIT 5");
$ultimasCompras = $stmtUltimasCompras->fetchAll();

// 8. Productos con Stock Bajo
$stmtAlertasStock = $pdo->query("SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.estado = 1 AND p.stock <= p.stock_minimo 
    ORDER BY p.stock ASC LIMIT 5");
$alertasStock = $stmtAlertasStock->fetchAll();

// 9. Datos para el gráfico de los últimos 6 meses
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

<!-- Fila de Contadores Principales / KPIs -->
<div class="row g-3 mb-4">
    <!-- Ventas del Mes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card border-primary-accent">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-title">Ventas del Mes</div>
                    <div class="stat-value text-primary"><?= formatMoney($ventasMes['total']) ?></div>
                    <div class="stat-subtitle"><?= $ventasMes['cant'] ?> comprobantes emitidos</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa fa-cash-register"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Compras del Mes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card border-warning-accent">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-title">Compras del Mes</div>
                    <div class="stat-value text-warning"><?= formatMoney($comprasMes['total']) ?></div>
                    <div class="stat-subtitle"><?= $comprasMes['cant'] ?> compras a proveedores</div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa fa-cart-shopping"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Ganancia Bruta Estimada -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card border-success-accent">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-title">Margen Bruto (Utilidad)</div>
                    <div class="stat-value text-success"><?= formatMoney($utilidadMes) ?></div>
                    <div class="stat-subtitle">Ventas vs Costos de mercadería</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fa fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventario Valorizado -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card border-info-accent">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-title">Almacén Valorizado</div>
                    <div class="stat-value text-info"><?= formatMoney($invStats['valor_costo']) ?></div>
                    <div class="stat-subtitle"><?= (int)$invStats['total_unidades'] ?> unidades (<?= (int)$invStats['total_items'] ?> productos)</div>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="fa fa-boxes-stacked"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fila Secundaria: Contadores Adicionales -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center">
            <div class="text-muted small fw-bold text-uppercase">Ventas de Hoy</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= formatMoney($ventasHoy['total']) ?></div>
            <span class="badge bg-light text-secondary border mt-1"><?= $ventasHoy['cant'] ?> operaciones</span>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center">
            <div class="text-muted small fw-bold text-uppercase">Stock Bajo Alerta</div>
            <div class="fs-4 fw-bold text-danger mt-1"><?= (int)$invStats['total_stock_bajo'] ?></div>
            <a href="inventario.php?filtro=stock_bajo" class="small text-danger text-decoration-none">Ver productos en riesgo &rarr;</a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center">
            <div class="text-muted small fw-bold text-uppercase">Clientes Activos</div>
            <div class="fs-4 fw-bold text-primary mt-1"><?= $quickCounters['clients'] ?></div>
            <a href="clientes.php" class="small text-primary text-decoration-none">Gestionar cartera &rarr;</a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center">
            <div class="text-muted small fw-bold text-uppercase">Proveedores</div>
            <div class="fs-4 fw-bold text-secondary mt-1"><?= $quickCounters['suppliers'] ?></div>
            <a href="proveedores.php" class="small text-secondary text-decoration-none">Ver proveedores &rarr;</a>
        </div>
    </div>
</div>

<!-- Gráficos y Actividad -->
<div class="row g-3 mb-4">
    <!-- Gráfico Comparativo -->
    <div class="col-12 col-lg-8">
        <div class="card-custom h-100">
            <div class="card-custom-header">
                <h5><i class="fa fa-chart-column text-primary"></i> Ventas vs Compras (Últimos 6 Meses)</h5>
                <span class="badge bg-light text-muted border">En <?= htmlspecialchars($cfg['moneda_simbolo']) ?></span>
            </div>
            <div class="card-custom-body">
                <canvas id="chartVentasCompras" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- Alertas de Stock Urgentes -->
    <div class="col-12 col-lg-4">
        <div class="card-custom h-100">
            <div class="card-custom-header">
                <h5 class="text-danger"><i class="fa fa-triangle-exclamation"></i> Reabastecimiento Urgente</h5>
                <a href="inventario.php?filtro=stock_bajo" class="btn btn-sm btn-outline-danger">Ver todos</a>
            </div>
            <div class="card-custom-body p-0">
                <?php if (empty($alertasStock)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fa fa-circle-check text-success fs-1 mb-2"></i>
                        <p class="mb-0">Todo el inventario cuenta con stock por encima del mínimo.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($alertasStock as $item): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($item['nombre']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($item['codigo_barra']) ?> • <?= htmlspecialchars($item['categoria_nombre'] ?? 'Sin cat.') ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger fs-6"><?= $item['stock'] ?> <?= htmlspecialchars($item['unidad_medida']) ?></span>
                                    <div class="small text-muted">Mín: <?= $item['stock_minimo'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tablas de Actividad Reciente -->
<div class="row g-3">
    <!-- Últimas Ventas -->
    <div class="col-12 col-lg-6">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fa fa-receipt text-success"></i> Últimas Ventas Emitidas</h5>
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
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fa fa-cart-shopping text-warning"></i> Últimas Compras Registradas</h5>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartVentasCompras');
    if (!ctx) return;

    new Chart(ctx, {
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
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


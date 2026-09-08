<?php
require_once __DIR__ . '/config/app.php';

$pdo = getDBConnection();
$cfg = getSystemConfig();

// Manejo de Exportación CSV
if (isset($_GET['export'])) {
    $tipoExport = $_GET['export'];
    $fechaIni = $_GET['fecha_ini'] ?? date('Y-m-01');
    $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

    if ($tipoExport === 'ventas') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_ventas_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        // UTF-8 BOM para que Excel en Windows reconozca caracteres y tildes
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['ID', 'Fecha', 'Tipo Comprobante', 'Serie-Correlativo', 'Cliente', 'Doc Cliente', 'Subtotal', 'Impuesto', 'Total', 'Metodo Pago', 'Estado']);

        $stmt = $pdo->prepare("SELECT v.*, c.nombre_razon_social as cliente_nombre, c.num_doc as cliente_doc 
            FROM ventas v 
            INNER JOIN clientes c ON v.cliente_id = c.id 
            WHERE DATE(v.fecha_venta) BETWEEN ? AND ? 
            ORDER BY v.fecha_venta DESC");
        $stmt->execute([$fechaIni, $fechaFin]);

        while ($r = $stmt->fetch()) {
            fputcsv($output, [
                $r['id'],
                $r['fecha_venta'],
                $r['tipo_comprobante'],
                $r['serie'] . '-' . str_pad($r['correlativo'], 6, '0', STR_PAD_LEFT),
                $r['cliente_nombre'],
                $r['cliente_doc'],
                $r['subtotal'],
                $r['impuesto'],
                $r['total'],
                $r['metodo_pago'],
                $r['estado']
            ]);
        }
        fclose($output);
        exit;
    }

    if ($tipoExport === 'compras') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_compras_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['ID', 'Fecha', 'Tipo Comprobante', 'Serie/Numero', 'Proveedor', 'RUC Proveedor', 'Subtotal', 'Impuesto', 'Total', 'Estado']);

        $stmt = $pdo->prepare("SELECT c.*, p.razon_social as proveedor_nombre, p.num_doc as proveedor_ruc 
            FROM compras c 
            INNER JOIN proveedores p ON c.proveedor_id = p.id 
            WHERE c.fecha_compra BETWEEN ? AND ? 
            ORDER BY c.fecha_compra DESC");
        $stmt->execute([$fechaIni, $fechaFin]);

        while ($r = $stmt->fetch()) {
            fputcsv($output, [
                $r['id'],
                $r['fecha_compra'],
                $r['tipo_comprobante'],
                $r['serie_numero'],
                $r['proveedor_nombre'],
                $r['proveedor_ruc'],
                $r['subtotal'],
                $r['impuesto'],
                $r['total'],
                $r['estado']
            ]);
        }
        fclose($output);
        exit;
    }

    if ($tipoExport === 'inventario') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_inventario_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['Codigo', 'Producto', 'Categoria', 'Stock Actual', 'Stock Minimo', 'Unidad', 'Precio Compra', 'Precio Venta', 'Valor Costo Total', 'Valor Venta Total', 'Estado']);

        $stmt = $pdo->query("SELECT p.*, c.nombre as categoria_nombre 
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.estado = 1 
            ORDER BY p.nombre ASC");

        while ($r = $stmt->fetch()) {
            fputcsv($output, [
                $r['codigo_barra'],
                $r['nombre'],
                $r['categoria_nombre'] ?? 'Sin Categoria',
                $r['stock'],
                $r['stock_minimo'],
                $r['unidad_medida'],
                $r['precio_compra'],
                $r['precio_venta'],
                round($r['stock'] * $r['precio_compra'], 2),
                round($r['stock'] * $r['precio_venta'], 2),
                $r['estado'] ? 'Activo' : 'Inactivo'
            ]);
        }
        fclose($output);
        exit;
    }
}

$pageTitle = 'Reportes & Estadísticas Financieras';
require_once __DIR__ . '/includes/header.php';

// Filtros para la vista
$tab = $_GET['tab'] ?? 'ventas';
$fechaIni = $_GET['fecha_ini'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Consulta de Ventas y Utilidades en el rango
$stmtVentas = $pdo->prepare("SELECT v.*, c.nombre_razon_social as cliente_nombre 
    FROM ventas v 
    INNER JOIN clientes c ON v.cliente_id = c.id 
    WHERE DATE(v.fecha_venta) BETWEEN ? AND ? 
    ORDER BY v.fecha_venta DESC");
$stmtVentas->execute([$fechaIni, $fechaFin]);
$reporteVentas = $stmtVentas->fetchAll();

$totalVentasPeriodo = 0;
$totalImpuestosPeriodo = 0;
$totalSubtotalPeriodo = 0;
$ventasCompletadasCount = 0;

foreach ($reporteVentas as $rv) {
    if ($rv['estado'] === 'COMPLETADA') {
        $totalVentasPeriodo += (float)$rv['total'];
        $totalImpuestosPeriodo += (float)$rv['impuesto'];
        $totalSubtotalPeriodo += (float)$rv['subtotal'];
        $ventasCompletadasCount++;
    }
}

// Costo de los productos vendidos para calcular margen real
$stmtCosto = $pdo->prepare("SELECT COALESCE(SUM(dv.costo_unitario * dv.cantidad), 0) 
    FROM detalle_ventas dv 
    INNER JOIN ventas v ON dv.venta_id = v.id 
    WHERE v.estado = 'COMPLETADA' AND DATE(v.fecha_venta) BETWEEN ? AND ?");
$stmtCosto->execute([$fechaIni, $fechaFin]);
$costoTotalVentas = (float)$stmtCosto->fetchColumn();
$gananciaBrutaPeriodo = $totalVentasPeriodo - $costoTotalVentas;

// Consulta de Compras en el rango
$stmtCompras = $pdo->prepare("SELECT c.*, p.razon_social as proveedor_nombre, p.num_doc as proveedor_ruc 
    FROM compras c 
    INNER JOIN proveedores p ON c.proveedor_id = p.id 
    WHERE c.fecha_compra BETWEEN ? AND ? 
    ORDER BY c.fecha_compra DESC");
$stmtCompras->execute([$fechaIni, $fechaFin]);
$reporteCompras = $stmtCompras->fetchAll();

$totalComprasPeriodo = 0;
$comprasCompletadasCount = 0;
foreach ($reporteCompras as $rc) {
    if ($rc['estado'] === 'COMPLETADA') {
        $totalComprasPeriodo += (float)$rc['total'];
        $comprasCompletadasCount++;
    }
}

// Inventario Valorizado Actual
$stmtInvVal = $pdo->query("SELECT 
    p.*, c.nombre as categoria_nombre,
    (p.stock * p.precio_compra) as total_costo,
    (p.stock * p.precio_venta) as total_venta,
    ((p.stock * p.precio_venta) - (p.stock * p.precio_compra)) as ganancia_potencial
FROM productos p 
LEFT JOIN categorias c ON p.categoria_id = c.id 
WHERE p.estado = 1 
ORDER BY p.stock ASC");
$reporteInventario = $stmtInvVal->fetchAll();

$totalInversionAlmacen = 0;
$totalVentaPotencialAlmacen = 0;
$totalArticulosAlmacen = 0;

foreach ($reporteInventario as $ri) {
    $totalInversionAlmacen += (float)$ri['total_costo'];
    $totalVentaPotencialAlmacen += (float)$ri['total_venta'];
    $totalArticulosAlmacen += (int)$ri['stock'];
}
$gananciaPotencialAlmacen = $totalVentaPotencialAlmacen - $totalInversionAlmacen;
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Centro de Reportes & Balances Fiables</h4>
        <p class="text-muted small mb-0">Consolidado financiero, margen de ganancia real, valorización de inventario y descargas a Excel.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fa fa-print me-1"></i> Imprimir Reporte
        </button>
    </div>
</div>

<!-- Selector de Pestañas de Reporte -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'ventas') ? 'active' : '' ?>" href="reportes.php?tab=ventas&fecha_ini=<?= $fechaIni ?>&fecha_fin=<?= $fechaFin ?>">
            <i class="fa fa-receipt me-1"></i> Ventas & Utilidades
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'compras') ? 'active' : '' ?>" href="reportes.php?tab=compras&fecha_ini=<?= $fechaIni ?>&fecha_fin=<?= $fechaFin ?>">
            <i class="fa fa-cart-arrow-down me-1"></i> Compras & Proveedores
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tab === 'inventario') ? 'active' : '' ?>" href="reportes.php?tab=inventario">
            <i class="fa fa-boxes-stacked me-1"></i> Inventario Valorizado
        </a>
    </li>
</ul>

<?php if ($tab !== 'inventario'): ?>
    <!-- Filtro de Fechas -->
    <div class="card-custom mb-4 no-print">
        <div class="card-custom-body py-3">
            <form method="GET" action="reportes.php" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha Inicial:</label>
                    <input type="date" name="fecha_ini" class="form-control" value="<?= htmlspecialchars($fechaIni) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha Final:</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa fa-rotate me-1"></i> Actualizar Reporte
                    </button>
                </div>
                <div class="col-6 col-md-3 text-end">
                    <a href="reportes.php?export=<?= $tab ?>&fecha_ini=<?= $fechaIni ?>&fecha_fin=<?= $fechaFin ?>" class="btn btn-success w-100">
                        <i class="fa fa-file-excel me-1"></i> Descargar CSV Excel
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- ======================= TAB VENTAS ======================= -->
<?php if ($tab === 'ventas'): ?>
    <!-- KPIs Ventas -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card border-primary-accent">
                <div class="stat-title">Facturación Total</div>
                <div class="stat-value text-primary"><?= formatMoney($totalVentasPeriodo) ?></div>
                <div class="stat-subtitle"><?= $ventasCompletadasCount ?> ventas en el rango</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card border-warning-accent">
                <div class="stat-title">Costo de Mercadería</div>
                <div class="stat-value text-warning"><?= formatMoney($costoTotalVentas) ?></div>
                <div class="stat-subtitle">Costo de compra invertido</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card border-success-accent">
                <div class="stat-title">Utilidad Bruta Real</div>
                <div class="stat-value text-success"><?= formatMoney($gananciaBrutaPeriodo) ?></div>
                <div class="stat-subtitle">Margen neto de ventas</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card border-info-accent">
                <div class="stat-title">Impuestos (<?= $cfg['impuesto_nombre'] ?>)</div>
                <div class="stat-value text-info"><?= formatMoney($totalImpuestosPeriodo) ?></div>
                <div class="stat-subtitle">Tasa del <?= (float)$cfg['impuesto_porcentaje'] ?>%</div>
            </div>
        </div>
    </div>

    <!-- Tabla Detallada de Ventas -->
    <div class="card-custom">
        <div class="card-custom-header">
            <h5><i class="fa fa-list text-primary"></i> Detalle de Comprobantes Emitidos (<?= formatDate($fechaIni) ?> al <?= formatDate($fechaFin) ?>)</h5>
        </div>
        <div class="card-custom-body p-0">
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Cliente</th>
                            <th>Medio Pago</th>
                            <th>Subtotal</th>
                            <th><?= htmlspecialchars($cfg['impuesto_nombre']) ?></th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reporteVentas)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No se registran ventas en este rango.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reporteVentas as $v): ?>
                                <tr>
                                    <td><?= formatDateTime($v['fecha_venta']) ?></td>
                                    <td><strong><?= $v['tipo_comprobante'] ?></strong> <?= $v['serie'] ?>-<?= str_pad($v['correlativo'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= htmlspecialchars($v['cliente_nombre']) ?></td>
                                    <td><?= htmlspecialchars($v['metodo_pago']) ?></td>
                                    <td><?= formatMoney($v['subtotal']) ?></td>
                                    <td><?= formatMoney($v['impuesto']) ?></td>
                                    <td class="fw-bold text-dark"><?= formatMoney($v['total']) ?></td>
                                    <td>
                                        <span class="badge <?= $v['estado'] === 'COMPLETADA' ? 'badge-soft-success' : 'badge-soft-danger' ?>">
                                            <?= $v['estado'] ?>
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
<?php endif; ?>

<!-- ======================= TAB COMPRAS ======================= -->
<?php if ($tab === 'compras'): ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="stat-card border-warning-accent">
                <div class="stat-title">Total Compras del Período</div>
                <div class="stat-value text-warning"><?= formatMoney($totalComprasPeriodo) ?></div>
                <div class="stat-subtitle"><?= $comprasCompletadasCount ?> compras completadas</div>
            </div>
        </div>
    </div>

    <div class="card-custom">
        <div class="card-custom-header">
            <h5><i class="fa fa-truck text-warning"></i> Compras Registradas (<?= formatDate($fechaIni) ?> al <?= formatDate($fechaFin) ?>)</h5>
        </div>
        <div class="card-custom-body p-0">
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Proveedor</th>
                            <th>Subtotal</th>
                            <th>Impuesto</th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reporteCompras)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No se registran compras en este rango.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reporteCompras as $c): ?>
                                <tr>
                                    <td><?= formatDate($c['fecha_compra']) ?></td>
                                    <td><strong><?= $c['tipo_comprobante'] ?></strong> <?= htmlspecialchars($c['serie_numero']) ?></td>
                                    <td><?= htmlspecialchars($c['proveedor_nombre']) ?> (RUC: <?= $c['proveedor_ruc'] ?>)</td>
                                    <td><?= formatMoney($c['subtotal']) ?></td>
                                    <td><?= formatMoney($c['impuesto']) ?></td>
                                    <td class="fw-bold text-warning"><?= formatMoney($c['total']) ?></td>
                                    <td>
                                        <span class="badge <?= $c['estado'] === 'COMPLETADA' ? 'badge-soft-success' : 'badge-soft-danger' ?>">
                                            <?= $c['estado'] ?>
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
<?php endif; ?>

<!-- ======================= TAB INVENTARIO VALORIZADO ======================= -->
<?php if ($tab === 'inventario'): ?>
    <div class="d-flex justify-content-end mb-3 no-print">
        <a href="reportes.php?export=inventario" class="btn btn-success">
            <i class="fa fa-file-excel me-1"></i> Exportar Inventario Completo a CSV
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card border-info-accent">
                <div class="stat-title">Costo Total Almacén (Inversión)</div>
                <div class="stat-value text-info"><?= formatMoney($totalInversionAlmacen) ?></div>
                <div class="stat-subtitle"><?= $totalArticulosAlmacen ?> unidades en existencias</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card border-primary-accent">
                <div class="stat-title">Valorización a Precio Venta</div>
                <div class="stat-value text-primary"><?= formatMoney($totalVentaPotencialAlmacen) ?></div>
                <div class="stat-subtitle">Proyección al vender todo el stock</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card border-success-accent">
                <div class="stat-title">Utilidad Proyectada</div>
                <div class="stat-value text-success"><?= formatMoney($gananciaPotencialAlmacen) ?></div>
                <div class="stat-subtitle">Margen potencial de existencias</div>
            </div>
        </div>
    </div>

    <div class="card-custom">
        <div class="card-custom-header">
            <h5><i class="fa fa-boxes-stacked text-primary"></i> Existencias Físicas y Valorización de Stock</h5>
        </div>
        <div class="card-custom-body p-0">
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Stock</th>
                            <th>Costo Unit.</th>
                            <th>P. Venta</th>
                            <th>Valor Costo</th>
                            <th>Valor Venta</th>
                            <th>Utilidad Potencial</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporteInventario as $p): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($p['codigo_barra']) ?></span></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($p['nombre']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin cat.') ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $p['stock'] <= $p['stock_minimo'] ? 'bg-danger' : 'bg-success' ?>">
                                        <?= $p['stock'] ?> <?= htmlspecialchars($p['unidad_medida']) ?>
                                    </span>
                                </td>
                                <td><?= formatMoney($p['precio_compra']) ?></td>
                                <td><?= formatMoney($p['precio_venta']) ?></td>
                                <td class="text-secondary fw-semibold"><?= formatMoney($p['total_costo']) ?></td>
                                <td class="text-primary fw-semibold"><?= formatMoney($p['total_venta']) ?></td>
                                <td class="text-success fw-bold"><?= formatMoney($p['ganancia_potencial']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>


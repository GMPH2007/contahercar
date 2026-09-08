<?php
$pageTitle = 'Historial de Ventas & Facturación';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();
$cfg = getSystemConfig();

// Procesar Anulación de Venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'anular_venta') {
    $ventaId = (int)($_POST['venta_id'] ?? 0);

    if ($ventaId > 0) {
        $pdo->beginTransaction();
        try {
            $stmtV = $pdo->prepare("SELECT * FROM ventas WHERE id = ? FOR UPDATE");
            $stmtV->execute([$ventaId]);
            $venta = $stmtV->fetch();

            if (!$venta) {
                throw new Exception("Venta no encontrada.");
            }
            if ($venta['estado'] === 'ANULADA') {
                throw new Exception("La venta ya se encuentra anulada.");
            }

            // Obtener productos de la venta
            $stmtD = $pdo->prepare("SELECT dv.*, p.stock, p.nombre as producto_nombre FROM detalle_ventas dv INNER JOIN productos p ON dv.producto_id = p.id WHERE dv.venta_id = ?");
            $stmtD->execute([$ventaId]);
            $detalles = $stmtD->fetchAll();

            // Revertir el stock (sumar al inventario los artículos vendidos)
            $stmtUpProd = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmtKardex = $pdo->prepare("INSERT INTO kardex (producto_id, tipo_movimiento, referencia_id, cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo) VALUES (?, 'ANULACION_VENTA', ?, ?, ?, ?, ?, ?)");

            foreach ($detalles as $det) {
                $stockActual = (int)$det['stock'];
                $cantDevuelta = (int)$det['cantidad'];
                $nuevoStock = $stockActual + $cantDevuelta;

                $stmtUpProd->execute([$cantDevuelta, $det['producto_id']]);

                // Registrar en Kardex
                $stmtKardex->execute([
                    $det['producto_id'],
                    $ventaId,
                    $cantDevuelta,
                    $stockActual,
                    $nuevoStock,
                    $det['precio_unitario'],
                    "Anulación de {$venta['tipo_comprobante']} {$venta['serie']}-" . str_pad($venta['correlativo'], 6, '0', STR_PAD_LEFT)
                ]);
            }

            // Cambiar estado a ANULADA
            $stmtAnular = $pdo->prepare("UPDATE ventas SET estado = 'ANULADA' WHERE id = ?");
            $stmtAnular->execute([$ventaId]);

            $pdo->commit();
            setFlash('success', 'Venta Anulada', "Comprobante {$venta['serie']}-" . str_pad($venta['correlativo'], 6, '0', STR_PAD_LEFT) . " anulado. Las unidades regresaron al inventario.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error al Anular', $e->getMessage());
        }
    }
    header('Location: ventas.php');
    exit;
}

// Filtros
$fechaIni = $_GET['fecha_ini'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$clienteId = (int)($_GET['cliente_id'] ?? 0);
$tipoComprobante = $_GET['tipo_comprobante'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT v.*, c.nombre_razon_social as cliente_nombre, c.num_doc as cliente_doc, c.tipo_doc as cliente_tipo_doc 
        FROM ventas v 
        INNER JOIN clientes c ON v.cliente_id = c.id 
        WHERE DATE(v.fecha_venta) BETWEEN ? AND ?";
$params = [$fechaIni, $fechaFin];

if (!empty($busqueda)) {
    $sql .= " AND (c.num_doc LIKE ? OR c.nombre_razon_social LIKE ? OR CONCAT(v.serie, '-', v.correlativo) LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($clienteId > 0) {
    $sql .= " AND v.cliente_id = ?";
    $params[] = $clienteId;
}

if (!empty($tipoComprobante)) {
    $sql .= " AND v.tipo_comprobante = ?";
    $params[] = $tipoComprobante;
}

if (!empty($estadoFiltro)) {
    $sql .= " AND v.estado = ?";
    $params[] = $estadoFiltro;
}

$sql .= " ORDER BY v.fecha_venta DESC, v.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll();

// Estadísticas del período
$totalVendido = 0;
$totalComprobantes = 0;
foreach ($ventas as $v) {
    if ($v['estado'] === 'COMPLETADA') {
        $totalVendido += (float)$v['total'];
        $totalComprobantes++;
    }
}
$ticketPromedio = ($totalComprobantes > 0) ? ($totalVendido / $totalComprobantes) : 0;

// Lista de clientes para el filtro
$clientes = $pdo->query("SELECT id, nombre_razon_social FROM clientes ORDER BY nombre_razon_social ASC")->fetchAll();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Historial de Ventas & Comprobantes</h4>
        <p class="text-muted small mb-0">Auditoría completa de boletas, facturas y tickets emitidos, con reimpresión y anulación atómica.</p>
    </div>
    <a href="venta_nueva.php" class="btn btn-primary">
        <i class="fa fa-cash-register me-1"></i> Ir al Punto de Venta (POS)
    </a>
</div>

<!-- Tarjetas KPI del Filtro -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card border-primary-accent">
            <div class="stat-title">Ventas Totales en Rango</div>
            <div class="stat-value text-primary"><?= formatMoney($totalVendido) ?></div>
            <div class="stat-subtitle"><?= $totalComprobantes ?> ventas completadas</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card border-success-accent">
            <div class="stat-title">Comprobantes Emitidos</div>
            <div class="stat-value text-success"><?= $totalComprobantes ?></div>
            <div class="stat-subtitle">Boletas, facturas y notas</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card border-info-accent">
            <div class="stat-title">Ticket Promedio</div>
            <div class="stat-value text-info"><?= formatMoney($ticketPromedio) ?></div>
            <div class="stat-subtitle">Monto promedio por cliente</div>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card-custom mb-4">
    <div class="card-custom-body py-3">
        <form method="GET" action="ventas.php" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Desde:</label>
                <input type="date" name="fecha_ini" class="form-control" value="<?= htmlspecialchars($fechaIni) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Hasta:</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Buscar por RUC / DNI / Serie:</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa fa-id-card text-muted small"></i></span>
                    <input type="text" name="q" class="form-control font-monospace" placeholder="Número de RUC o DNI..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Tipo:</label>
                <select name="tipo_comprobante" class="form-select">
                    <option value="">-- Todos --</option>
                    <option value="Boleta" <?= $tipoComprobante === 'Boleta' ? 'selected' : '' ?>>Boleta</option>
                    <option value="Factura" <?= $tipoComprobante === 'Factura' ? 'selected' : '' ?>>Factura</option>
                    <option value="Nota de Venta" <?= $tipoComprobante === 'Nota de Venta' ? 'selected' : '' ?>>Nota Venta</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Estado:</label>
                <select name="estado" class="form-select">
                    <option value="">-- Todos --</option>
                    <option value="COMPLETADA" <?= $estadoFiltro === 'COMPLETADA' ? 'selected' : '' ?>>Completada</option>
                    <option value="ANULADA" <?= $estadoFiltro === 'ANULADA' ? 'selected' : '' ?>>Anulada</option>
                </select>
            </div>
            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-primary w-100" title="Filtrar"><i class="fa fa-filter"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Ventas -->
<div class="card-custom">
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Fecha / Hora</th>
                        <th>Comprobante</th>
                        <th>Cliente</th>
                        <th>Método Pago</th>
                        <th>Subtotal</th>
                        <th><?= htmlspecialchars($cfg['impuesto_nombre']) ?></th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="fa fa-inbox fs-2 mb-2 d-block text-secondary"></i>
                                No se encontraron ventas con los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ventas as $v): ?>
                            <tr>
                                <td><span class="small fw-semibold"><?= formatDateTime($v['fecha_venta']) ?></span></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($v['tipo_comprobante']) ?></span><br>
                                    <span class="fw-bold font-monospace"><?= htmlspecialchars($v['serie']) ?>-<?= str_pad($v['correlativo'], 6, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($v['cliente_nombre']) ?></div>
                                    <small class="text-muted"><?= $v['cliente_tipo_doc'] ?>: <?= htmlspecialchars($v['cliente_doc']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($v['metodo_pago']) ?></span></td>
                                <td><?= formatMoney($v['subtotal']) ?></td>
                                <td><?= formatMoney($v['impuesto']) ?></td>
                                <td class="fw-bold text-primary fs-6"><?= formatMoney($v['total']) ?></td>
                                <td>
                                    <span class="badge <?= $v['estado'] === 'COMPLETADA' ? 'badge-soft-success' : 'badge-soft-danger' ?>">
                                        <?= htmlspecialchars($v['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Ver Ticket Impresión -->
                                        <a href="ticket.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Imprimir Ticket">
                                            <i class="fa fa-print"></i>
                                        </a>
                                        <!-- Ver Detalle Modal -->
                                        <button type="button" class="btn btn-outline-info" title="Ver Detalle" onclick="verDetalleVenta(<?= $v['id'] ?>)">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <!-- Anular si está completada -->
                                        <?php if ($v['estado'] === 'COMPLETADA'): ?>
                                            <button type="button" class="btn btn-outline-danger" title="Anular Venta y Devolver Stock" onclick="confirmarAnularVenta(<?= $v['id'] ?>, '<?= $v['serie'] . '-' . str_pad($v['correlativo'], 6, '0', STR_PAD_LEFT) ?>')">
                                                <i class="fa fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detalle Venta -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fs-6 fw-bold" id="detalleVentaTitulo">Detalle de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detalleVentaContenido">
                <!-- AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Form oculto para anular venta -->
<form method="POST" action="ventas.php" id="formAnularVenta" style="display:none;">
    <input type="hidden" name="action" value="anular_venta">
    <input type="hidden" name="venta_id" id="anular_venta_id">
</form>

<script>
function verDetalleVenta(id) {
    const modalEl = document.getElementById('modalDetalleVenta');
    const bsModal = new bootstrap.Modal(modalEl);
    const content = document.getElementById('detalleVentaContenido');
    const title = document.getElementById('detalleVentaTitulo');

    title.textContent = 'Cargando venta #' + id;
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    bsModal.show();

    fetch('api/venta_detalle.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                return;
            }

            const v = data.venta;
            title.textContent = `${v.tipo_comprobante} ${v.serie}-${v.correlativo} - ${v.cliente_nombre}`;

            let html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Cliente:</small>
                        <strong>${v.cliente_nombre}</strong> (${v.cliente_doc})
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Fecha y Hora:</small>
                        <strong>${v.fecha_venta}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Método de Pago:</small>
                        <span class="badge bg-light text-dark border">${v.metodo_pago}</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.items.forEach(item => {
                html += `
                    <tr>
                        <td>
                            <span class="fw-bold">${item.producto_nombre}</span><br>
                            <small class="text-muted">Cód: ${item.codigo_barra}</small>
                        </td>
                        <td class="text-center fw-bold">${item.cantidad}</td>
                        <td class="text-end">${item.precio_unitario_fmt}</td>
                        <td class="text-end fw-bold">${item.subtotal_fmt}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Op. Gravada (Subtotal):</th>
                                <th class="text-end">${v.subtotal_fmt}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">${v.impuesto_nombre}:</th>
                                <th class="text-end">${v.impuesto_fmt}</th>
                            </tr>
                            <tr class="table-light fs-6">
                                <th colspan="3" class="text-end">Total Facturado:</th>
                                <th class="text-end text-primary fw-bold">${v.total_fmt}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;

            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<div class="alert alert-danger">Error al cargar detalle de venta.</div>';
        });
}

function confirmarAnularVenta(id, comprobante) {
    Swal.fire({
        title: '¿Anular Venta?',
        html: `¿Está seguro de anular el comprobante <strong>${comprobante}</strong>?<br><br><span class="text-danger small">Atención: Los productos vendidos serán reincorporados automáticamente al stock del almacén.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, anular y reponer stock',
        cancelButtonText: 'Cancelar'
    }).then((res) => {
        if (res.isConfirmed) {
            document.getElementById('anular_venta_id').value = id;
            document.getElementById('formAnularVenta').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


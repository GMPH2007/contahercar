<?php
$pageTitle = 'Historial de Compras & Proveedores';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();
$cfg = getSystemConfig();

// Procesar Anulación de Compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'anular_compra') {
    $compraId = (int)($_POST['compra_id'] ?? 0);

    if ($compraId > 0) {
        $pdo->beginTransaction();
        try {
            $stmtC = $pdo->prepare("SELECT * FROM compras WHERE id = ? FOR UPDATE");
            $stmtC->execute([$compraId]);
            $compra = $stmtC->fetch();

            if (!$compra) {
                throw new Exception("Compra no encontrada.");
            }
            if ($compra['estado'] === 'ANULADA') {
                throw new Exception("La compra ya se encuentra anulada.");
            }

            // Obtener detalles de la compra
            $stmtD = $pdo->prepare("SELECT dc.*, p.stock, p.nombre as producto_nombre FROM detalle_compras dc INNER JOIN productos p ON dc.producto_id = p.id WHERE dc.compra_id = ?");
            $stmtD->execute([$compraId]);
            $detalles = $stmtD->fetchAll();

            // Revertir el stock (restar la cantidad que ingresó por la compra)
            $stmtUpProd = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmtKardex = $pdo->prepare("INSERT INTO kardex (producto_id, tipo_movimiento, referencia_id, cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo) VALUES (?, 'ANULACION_COMPRA', ?, ?, ?, ?, ?, ?)");

            foreach ($detalles as $det) {
                $stockActual = (int)$det['stock'];
                $cantARestar = (int)$det['cantidad'];

                if ($stockActual < $cantARestar) {
                    throw new Exception("No se puede anular: el producto '{$det['producto_nombre']}' solo tiene $stockActual unidades en inventario y se requieren revertir $cantARestar unidades compradas.");
                }

                $nuevoStock = $stockActual - $cantARestar;
                $stmtUpProd->execute([$cantARestar, $det['producto_id']]);

                // Registrar en Kardex
                $stmtKardex->execute([
                    $det['producto_id'],
                    $compraId,
                    $cantARestar,
                    $stockActual,
                    $nuevoStock,
                    $det['precio_unitario'],
                    "Anulación de compra #{$compra['serie_numero']}"
                ]);
            }

            // Cambiar estado de la compra a ANULADA
            $stmtAnular = $pdo->prepare("UPDATE compras SET estado = 'ANULADA' WHERE id = ?");
            $stmtAnular->execute([$compraId]);

            $pdo->commit();
            setFlash('success', 'Compra Anulada', "La compra {$compra['serie_numero']} ha sido anulada y el stock fue revertido de manera fiable.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error al Anular', $e->getMessage());
        }
    }
    header('Location: compras.php');
    exit;
}

// Filtros
$fechaIni = $_GET['fecha_ini'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$proveedorId = (int)($_GET['proveedor_id'] ?? 0);
$estadoFiltro = $_GET['estado'] ?? '';

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT c.*, p.razon_social as proveedor_nombre, p.num_doc as proveedor_ruc 
        FROM compras c 
        INNER JOIN proveedores p ON c.proveedor_id = p.id 
        WHERE c.fecha_compra BETWEEN ? AND ?";
$params = [$fechaIni, $fechaFin];

if (!empty($busqueda)) {
    $sql .= " AND (p.num_doc LIKE ? OR p.razon_social LIKE ? OR c.serie_numero LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($proveedorId > 0) {
    $sql .= " AND c.proveedor_id = ?";
    $params[] = $proveedorId;
}

if (!empty($estadoFiltro)) {
    $sql .= " AND c.estado = ?";
    $params[] = $estadoFiltro;
}

$sql .= " ORDER BY c.fecha_compra DESC, c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$compras = $stmt->fetchAll();

// Totales de la consulta
$totalMonto = 0;
$totalCompletadas = 0;
foreach ($compras as $c) {
    if ($c['estado'] === 'COMPLETADA') {
        $totalMonto += (float)$c['total'];
        $totalCompletadas++;
    }
}

// Proveedores para el filtro
$proveedores = $pdo->query("SELECT id, razon_social FROM proveedores ORDER BY razon_social ASC")->fetchAll();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Registro de Compras & Facturación de Proveedores</h4>
        <p class="text-muted small mb-0">Control de adquisiciones de mercadería, historial de gastos y auditoría de inventario.</p>
    </div>
    <a href="compra_nueva.php" class="btn btn-primary">
        <i class="fa fa-cart-plus me-1"></i> + Nueva Compra
    </a>
</div>

<!-- Tarjetas Resumen de Compras del Período -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card border-warning-accent">
            <div class="stat-title">Total Compras del Período</div>
            <div class="stat-value text-warning"><?= formatMoney($totalMonto) ?></div>
            <div class="stat-subtitle"><?= $totalCompletadas ?> compras activas en rango seleccionado</div>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card-custom mb-4">
    <div class="card-custom-body py-3">
        <form method="GET" action="compras.php" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Desde:</label>
                <input type="date" name="fecha_ini" class="form-control" value="<?= htmlspecialchars($fechaIni) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Hasta:</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Buscar por RUC o Comprobante:</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa fa-truck-moving text-muted small"></i></span>
                    <input type="text" name="q" class="form-control font-monospace" placeholder="RUC, proveedor, serie..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Proveedor:</label>
                <select name="proveedor_id" class="form-select">
                    <option value="0">-- Todos --</option>
                    <?php foreach ($proveedores as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= $proveedorId == $pr['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pr['razon_social']) ?>
                        </option>
                    <?php endforeach; ?>
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

<!-- Tabla de Compras -->
<div class="card-custom">
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Comprobante</th>
                        <th>Proveedor</th>
                        <th>Subtotal</th>
                        <th><?= htmlspecialchars($cfg['impuesto_nombre']) ?></th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($compras)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fa fa-receipt fs-2 mb-2 d-block text-secondary"></i>
                                No se encontraron compras en el rango de fechas seleccionado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($compras as $c): ?>
                            <tr>
                                <td><span class="fw-semibold"><?= formatDate($c['fecha_compra']) ?></span></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['tipo_comprobante']) ?></span><br>
                                    <span class="fw-bold font-monospace"><?= htmlspecialchars($c['serie_numero']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($c['proveedor_nombre']) ?></div>
                                    <small class="text-muted">RUC: <?= htmlspecialchars($c['proveedor_ruc']) ?></small>
                                </td>
                                <td><?= formatMoney($c['subtotal']) ?></td>
                                <td><?= formatMoney($c['impuesto']) ?></td>
                                <td class="fw-bold text-warning fs-6"><?= formatMoney($c['total']) ?></td>
                                <td>
                                    <span class="badge <?= $c['estado'] === 'COMPLETADA' ? 'badge-soft-success' : 'badge-soft-danger' ?>">
                                        <?= htmlspecialchars($c['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Ver Detalle Modal -->
                                        <button type="button" class="btn btn-outline-info" title="Ver Ítems Comprados" onclick="verDetalleCompra(<?= $c['id'] ?>)">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <!-- Anular si está completada -->
                                        <?php if ($c['estado'] === 'COMPLETADA'): ?>
                                            <button type="button" class="btn btn-outline-danger" title="Anular Compra y Revertir Stock" onclick="confirmarAnularCompra(<?= $c['id'] ?>, '<?= addslashes($c['serie_numero']) ?>')">
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

<!-- Modal Detalle de Compra -->
<div class="modal fade" id="modalDetalleCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fs-6 fw-bold" id="detalleCompraTitulo">Detalle de Compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detalleCompraContenido">
                <!-- Se llena vía AJAX / JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Form oculto para anulación -->
<form method="POST" action="compras.php" id="formAnularCompra" style="display:none;">
    <input type="hidden" name="action" value="anular_compra">
    <input type="hidden" name="compra_id" id="anular_compra_id">
</form>

<script>
function verDetalleCompra(id) {
    const modalEl = document.getElementById('modalDetalleCompra');
    const bsModal = new bootstrap.Modal(modalEl);
    const content = document.getElementById('detalleCompraContenido');
    const title = document.getElementById('detalleCompraTitulo');

    title.textContent = 'Detalle de Compra #' + id;
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    bsModal.show();

    fetch('api/compra_detalle.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                return;
            }

            const c = data.compra;
            title.textContent = `Compra: ${c.tipo_comprobante} ${c.serie_numero} - ${c.proveedor_nombre}`;

            let html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Proveedor:</small>
                        <strong>${c.proveedor_nombre}</strong> (RUC: ${c.proveedor_ruc})
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Fecha:</small>
                        <strong>${c.fecha_compra}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Estado:</small>
                        <span class="badge ${c.estado === 'COMPLETADA' ? 'bg-success' : 'bg-danger'}">${c.estado}</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Costo Unit.</th>
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
                                <th colspan="3" class="text-end">Subtotal:</th>
                                <th class="text-end">${c.subtotal_fmt}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Impuesto (${c.impuesto_nombre}):</th>
                                <th class="text-end">${c.impuesto_fmt}</th>
                            </tr>
                            <tr class="table-light fs-6">
                                <th colspan="3" class="text-end">Total Compra:</th>
                                <th class="text-end text-primary fw-bold">${c.total_fmt}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;

            if (c.observaciones) {
                html += `<div class="mt-2 small text-muted"><strong>Observaciones:</strong> ${c.observaciones}</div>`;
            }

            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<div class="alert alert-danger">Error al cargar información de la compra.</div>';
        });
}

function confirmarAnularCompra(id, comprobante) {
    Swal.fire({
        title: '¿Anular Compra?',
        html: `¿Está seguro de anular el comprobante <strong>${comprobante}</strong>?<br><br><span class="text-danger small">Atención: Se descontarán del inventario las unidades que ingresaron en esta compra. Esta acción es irreversible.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, anular y revertir stock',
        cancelButtonText: 'Cancelar'
    }).then((res) => {
        if (res.isConfirmed) {
            document.getElementById('anular_compra_id').value = id;
            document.getElementById('formAnularCompra').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


<?php
$pageTitle = 'SIRE SUNAT - Registros Electrónicos (RVIE / RCE)';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();
$cfg = getSystemConfig();

$periodoActual = isset($_GET['periodo']) ? trim($_GET['periodo']) : date('Y-m');
$tabActual = isset($_GET['tab']) ? trim($_GET['tab']) : 'rvie';

// Obtener datos del periodo seleccionado para RVIE (Ventas)
$fechaIni = "$periodoActual-01 00:00:00";
$fechaFin = date('Y-m-t 23:59:59', strtotime("$periodoActual-01"));

$stmtVentas = $pdo->prepare("SELECT v.*, c.nombre_razon_social, c.num_doc, c.tipo_doc 
    FROM ventas v 
    INNER JOIN clientes c ON v.cliente_id = c.id 
    WHERE v.fecha_venta BETWEEN ? AND ? 
    ORDER BY v.fecha_venta ASC, v.id ASC");
$stmtVentas->execute([$fechaIni, $fechaFin]);
$ventasSire = $stmtVentas->fetchAll();

$totBaseVentas = 0;
$totIgvVentas = 0;
$totTotalVentas = 0;
foreach ($ventasSire as $v) {
    if ($v['estado'] === 'COMPLETADA') {
        $totBaseVentas += (float)$v['subtotal'];
        $totIgvVentas += (float)$v['impuesto'];
        $totTotalVentas += (float)$v['total'];
    }
}

// Obtener datos del periodo seleccionado para RCE (Compras)
$stmtCompras = $pdo->prepare("SELECT c.*, p.razon_social as nombre_razon_social, p.num_doc, p.tipo_doc 
    FROM compras c 
    INNER JOIN proveedores p ON c.proveedor_id = p.id 
    WHERE c.fecha_compra BETWEEN ? AND ? 
    ORDER BY c.fecha_compra ASC, c.id ASC");
$stmtCompras->execute([$periodoActual . '-01', date('Y-m-t', strtotime("$periodoActual-01"))]);
$comprasSire = $stmtCompras->fetchAll();

$totBaseCompras = 0;
$totIgvCompras = 0;
$totTotalCompras = 0;
foreach ($comprasSire as $c) {
    if ($c['estado'] === 'COMPLETADA') {
        $totBaseCompras += (float)$c['subtotal'];
        $totIgvCompras += (float)$c['impuesto'];
        $totTotalCompras += (float)$c['total'];
    }
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">
            <i class="fa fa-book-bookmark text-primary me-2"></i>SIRE SUNAT - Registros Electrónicos
        </h4>
        <p class="text-muted small mb-0">
            Gestión oficial automatizada de <strong>RVIE</strong> (Ventas e Ingresos - Libro 140400) y <strong>RCE</strong> (Compras - Libro 080400) según normativa SUNAT.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="sire.php" class="d-flex align-items-center gap-2">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tabActual) ?>">
            <label class="small fw-semibold text-muted mb-0 d-none d-sm-inline">Periodo:</label>
            <input type="month" name="periodo" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars($periodoActual) ?>" onchange="this.form.submit()">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fa fa-rotate"></i>
            </button>
        </form>
</div>

<!-- Accesos Directos a Portales Oficiales SUNAT / SIRE en Nueva Pestaña -->
<div class="row g-2 mb-3">
    <div class="col-12 col-sm-6 col-md-3">
        <a href="https://sire.sunat.gob.pe/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm w-100 py-2 d-flex align-items-center justify-content-between shadow-sm">
            <span class="text-truncate fw-semibold"><i class="fa fa-globe me-2"></i>Portal SIRE SUNAT</span>
            <i class="fa fa-arrow-up-right-from-square small text-primary"></i>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <a href="https://www.sunat.gob.pe/sol.html" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm w-100 py-2 d-flex align-items-center justify-content-between shadow-sm">
            <span class="text-truncate fw-semibold"><i class="fa fa-key me-2"></i>SUNAT Clave SOL</span>
            <i class="fa fa-arrow-up-right-from-square small text-secondary"></i>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <a href="consulta_sunat.php" target="_blank" class="btn btn-outline-warning text-dark btn-sm w-100 py-2 d-flex align-items-center justify-content-between shadow-sm">
            <span class="text-truncate fw-semibold"><i class="fa fa-building-flag me-2"></i>Consulta RUC / DNI</span>
            <i class="fa fa-arrow-up-right-from-square small text-dark"></i>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <a href="https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/FrameCriterioBusquedaWeb.jsp" target="_blank" rel="noopener noreferrer" class="btn btn-outline-info text-dark btn-sm w-100 py-2 d-flex align-items-center justify-content-between shadow-sm">
            <span class="text-truncate fw-semibold"><i class="fa fa-magnifying-glass-chart me-2 text-info"></i>Portal RUC SUNAT</span>
            <i class="fa fa-arrow-up-right-from-square small text-info"></i>
        </a>
    </div>
</div>

<!-- Selector de Pestañas SIRE -->
<ul class="nav nav-pills mb-4" id="sireTabs">
    <li class="nav-item">
        <a class="nav-link <?= ($tabActual === 'rvie') ? 'active' : '' ?>" href="sire.php?tab=rvie&periodo=<?= $periodoActual ?>">
            <i class="fa fa-file-invoice-dollar me-1"></i> RVIE - Ventas e Ingresos (140400)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tabActual === 'rce') ? 'active' : '' ?>" href="sire.php?tab=rce&periodo=<?= $periodoActual ?>">
            <i class="fa fa-cart-arrow-down me-1"></i> RCE - Compras Electrónico (080400)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($tabActual === 'config') ? 'active' : '' ?>" href="sire.php?tab=config&periodo=<?= $periodoActual ?>">
            <i class="fa fa-plug me-1"></i> API SIRE & Credenciales SOL
        </a>
    </li>
</ul>

<?php if ($tabActual === 'rvie'): ?>
<!-- ==========================================================
     PESTAÑA RVIE: REGISTRO DE VENTAS E INGRESOS ELECTRÓNICO
     ========================================================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card border-primary-accent">
            <div class="stat-title">Base Gravada (Ventas)</div>
            <div class="stat-value text-primary"><?= formatMoney($totBaseVentas) ?></div>
            <div class="stat-subtitle"><?= count($ventasSire) ?> comprobantes en el mes</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-success-accent">
            <div class="stat-title">IGV Débito Fiscal (18%)</div>
            <div class="stat-value text-success"><?= formatMoney($totIgvVentas) ?></div>
            <div class="stat-subtitle">Impuesto generado a pagar</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-info-accent">
            <div class="stat-title">Total Facturado RVIE</div>
            <div class="stat-value text-info"><?= formatMoney($totTotalVentas) ?></div>
            <div class="stat-subtitle">Periodo <?= $periodoActual ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-warning-accent">
            <div class="stat-title">Estructura SUNAT</div>
            <div class="stat-value text-warning fs-5 font-monospace">140400</div>
            <div class="stat-subtitle">Libro Electrónico RVIE</div>
        </div>
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-custom-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary px-3 py-2 fs-6">Propuesta RVIE</span>
            <span class="small text-muted">Periodo: <strong><?= $periodoActual ?></strong> (<?= count($ventasSire) ?> registros)</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <!-- Descarga TXT Oficial SUNAT -->
            <a href="api/sire_export.php?tipo=rvie&periodo=<?= $periodoActual ?>&formato=txt" class="btn btn-sm btn-outline-primary" download>
                <i class="fa fa-file-lines me-1"></i> Descargar TXT Oficial SUNAT
            </a>
            <!-- Descarga ZIP Oficial SUNAT -->
            <a href="api/sire_export.php?tipo=rvie&periodo=<?= $periodoActual ?>&formato=zip" class="btn btn-sm btn-primary" download>
                <i class="fa fa-file-zipper me-1"></i> Descargar ZIP para SIRE
            </a>
        </div>
    </div>

    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">CAR (Código SIRE)</th>
                        <th>Emisión</th>
                        <th>Comprobante</th>
                        <th>Cliente</th>
                        <th>Doc / RUC</th>
                        <th>Base Gravada</th>
                        <th>IGV (18%)</th>
                        <th>Total</th>
                        <th class="text-center pe-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventasSire)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa fa-inbox fs-2 mb-2 d-block text-secondary"></i>
                                No se registran ventas emitidas en el periodo <?= htmlspecialchars($periodoActual) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ventasSire as $v): 
                            $tipoCompCode = ($v['tipo_comprobante'] === 'Factura') ? '01' : '03';
                            $serieFmt = str_pad(substr($v['serie'], 0, 4), 4, '0', STR_PAD_RIGHT);
                            $car = $cfg['ruc_empresa'] . $tipoCompCode . $serieFmt . str_pad($v['correlativo'], 10, '0', STR_PAD_LEFT);
                        ?>
                            <tr>
                                <td class="ps-3 font-monospace small text-primary fw-bold" title="<?= $car ?>">
                                    <?= substr($car, 0, 15) ?>...
                                </td>
                                <td><?= date('d/m/Y', strtotime($v['fecha_venta'])) ?></td>
                                <td>
                                    <span class="badge <?= $v['tipo_comprobante'] === 'Factura' ? 'bg-primary' : 'bg-secondary' ?>">
                                        <?= htmlspecialchars($v['tipo_comprobante']) ?>
                                    </span>
                                    <span class="fw-bold font-monospace ms-1"><?= $v['serie'] ?>-<?= str_pad($v['correlativo'], 6, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td class="fw-semibold text-dark text-truncate" style="max-width: 220px;">
                                    <?= htmlspecialchars($v['nombre_razon_social']) ?>
                                </td>
                                <td class="font-monospace small"><?= htmlspecialchars($v['num_doc']) ?></td>
                                <td class="fw-bold"><?= formatMoney($v['subtotal']) ?></td>
                                <td class="text-secondary"><?= formatMoney($v['impuesto']) ?></td>
                                <td class="fw-bold text-success"><?= formatMoney($v['total']) ?></td>
                                <td class="text-center pe-3">
                                    <span class="badge <?= $v['estado'] === 'COMPLETADA' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $v['estado'] === 'COMPLETADA' ? 'ACTIVO (1)' : 'ANULADO (2)' ?>
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

<?php elseif ($tabActual === 'rce'): ?>
<!-- ==========================================================
     PESTAÑA RCE: REGISTRO DE COMPRAS ELECTRÓNICO
     ========================================================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card border-primary-accent">
            <div class="stat-title">Base Compras (Crédito Fiscal)</div>
            <div class="stat-value text-primary"><?= formatMoney($totBaseCompras) ?></div>
            <div class="stat-subtitle"><?= count($comprasSire) ?> comprobantes de compras</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-success-accent">
            <div class="stat-title">IGV Crédito Fiscal (18%)</div>
            <div class="stat-value text-success"><?= formatMoney($totIgvCompras) ?></div>
            <div class="stat-subtitle">Impuesto a favor a deducir</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-info-accent">
            <div class="stat-title">Total Compras RCE</div>
            <div class="stat-value text-info"><?= formatMoney($totTotalCompras) ?></div>
            <div class="stat-subtitle">Periodo <?= $periodoActual ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-warning-accent">
            <div class="stat-title">Estructura SUNAT</div>
            <div class="stat-value text-warning fs-5 font-monospace">080400</div>
            <div class="stat-subtitle">Libro Electrónico RCE</div>
        </div>
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-custom-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark px-3 py-2 fs-6">Propuesta RCE</span>
            <span class="small text-muted">Periodo: <strong><?= $periodoActual ?></strong> (<?= count($comprasSire) ?> registros)</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <!-- Descarga TXT Oficial SUNAT -->
            <a href="api/sire_export.php?tipo=rce&periodo=<?= $periodoActual ?>&formato=txt" class="btn btn-sm btn-outline-warning" download>
                <i class="fa fa-file-lines me-1"></i> Descargar TXT Oficial SUNAT
            </a>
            <!-- Descarga ZIP Oficial SUNAT -->
            <a href="api/sire_export.php?tipo=rce&periodo=<?= $periodoActual ?>&formato=zip" class="btn btn-sm btn-warning" download>
                <i class="fa fa-file-zipper me-1"></i> Descargar ZIP para SIRE
            </a>
        </div>
    </div>

    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Emisión</th>
                        <th>Comprobante</th>
                        <th>Proveedor</th>
                        <th>RUC Proveedor</th>
                        <th>Base Gravada</th>
                        <th>IGV (Crédito)</th>
                        <th>Total Compra</th>
                        <th class="text-center pe-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comprasSire)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa fa-inbox fs-2 mb-2 d-block text-secondary"></i>
                                No se registran compras anotadas en el periodo <?= htmlspecialchars($periodoActual) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comprasSire as $c): ?>
                            <tr>
                                <td class="ps-3"><?= date('d/m/Y', strtotime($c['fecha_compra'])) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($c['tipo_comprobante']) ?></span>
                                    <span class="fw-bold font-monospace ms-1"><?= htmlspecialchars($c['serie_numero']) ?></span>
                                </td>
                                <td class="fw-semibold text-dark text-truncate" style="max-width: 240px;">
                                    <?= htmlspecialchars($c['nombre_razon_social']) ?>
                                </td>
                                <td class="font-monospace small"><?= htmlspecialchars($c['num_doc']) ?></td>
                                <td class="fw-bold"><?= formatMoney($c['subtotal']) ?></td>
                                <td class="text-secondary"><?= formatMoney($c['impuesto']) ?></td>
                                <td class="fw-bold text-success"><?= formatMoney($c['total']) ?></td>
                                <td class="text-center pe-3">
                                    <span class="badge <?= $c['estado'] === 'COMPLETADA' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $c['estado'] === 'COMPLETADA' ? 'ANOTADO (1)' : 'ANULADO (2)' ?>
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

<?php else: ?>
<!-- ==========================================================
     PESTAÑA CONFIG: CREDENCIALES SOL Y API SIRE SUNAT
     ========================================================== -->
<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card-custom">
            <div class="card-custom-header">
                <h5><i class="fa fa-key text-primary me-2"></i>Credenciales API SIRE & Clave SOL SUNAT</h5>
            </div>
            <div class="card-custom-body">
                <form id="formSireConfig" onsubmit="guardarCredencialesSire(event)">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fa fa-circle-info me-1"></i> Para conectarse con la API del SIRE de SUNAT necesita generar sus credenciales (Client ID y Client Secret) en el menú <strong>Gestión de Credenciales API</strong> del portal SUNAT Operaciones en Línea (SOL).
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Client ID (API SUNAT):</label>
                            <input type="text" id="sire_client_id" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['sire_client_id'] ?? '') ?>" placeholder="Ej: 5b38d4f0-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Client Secret (API SUNAT):</label>
                            <input type="password" id="sire_client_secret" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['sire_client_secret'] ?? '') ?>" placeholder="Pegue aquí el Client Secret">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Usuario SOL:</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace small"><?= $cfg['ruc_empresa'] ?></span>
                                <input type="text" id="sire_usuario_sol" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['sire_usuario_sol'] ?? '') ?>" placeholder="Ej: MODDATOS">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Clave SOL:</label>
                            <input type="password" id="sire_clave_sol" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['sire_clave_sol'] ?? '') ?>" placeholder="Clave SOL del usuario">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Ambiente del Servicio SIRE:</label>
                            <select id="sire_ambiente" class="form-select">
                                <option value="beta" <?= (($cfg['sire_ambiente'] ?? '') === 'beta') ? 'selected' : '' ?>>Beta / Pruebas SUNAT</option>
                                <option value="produccion" <?= (($cfg['sire_ambiente'] ?? '') === 'produccion') ? 'selected' : '' ?>>Producción Oficial (api-sire.sunat.gob.pe)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-primary" id="btnTestSire" onclick="probarTokenSire()">
                            <i class="fa fa-play me-1"></i> Probar Conexión OAuth2 SUNAT
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-floppy-disk me-1"></i> Guardar Credenciales
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Guía y Estado de Conexión -->
    <div class="col-12 col-lg-4">
        <div class="card-custom">
            <div class="card-custom-header bg-light">
                <h6 class="mb-0 fw-bold"><i class="fa fa-circle-question text-info me-1"></i> ¿Qué permite el SIRE?</h6>
            </div>
            <div class="card-custom-body small">
                <p>El <strong>SIRE (Sistema Integrado de Registros Electrónicos)</strong> sustituye al antiguo PLE (Programa de Libros Electrónicos):</p>
                <ul class="ps-3 mb-3">
                    <li><strong>Descarga de Propuestas:</strong> SUNAT precarga los comprobantes electrónicos emitidos en el periodo.</li>
                    <li><strong>RVIE (Libro 140400):</strong> Ventas e ingresos facturados.</li>
                    <li><strong>RCE (Libro 080400):</strong> Compras con derecho a crédito fiscal.</li>
                    <li><strong>Generación de TXT/ZIP:</strong> Archivos planos para aceptar o reemplazar la propuesta SUNAT.</li>
                </ul>
                <div class="alert alert-secondary py-2 mb-0" id="sireStatusBox">
                    <strong>Estado API:</strong> <span id="sireStatusText">Listo para conectar</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function guardarCredencialesSire(e) {
    if (e) e.preventDefault();
    const formData = new FormData();
    formData.append('action', 'guardar_credenciales');
    formData.append('sire_client_id', document.getElementById('sire_client_id').value.trim());
    formData.append('sire_client_secret', document.getElementById('sire_client_secret').value.trim());
    formData.append('sire_usuario_sol', document.getElementById('sire_usuario_sol').value.trim());
    formData.append('sire_clave_sol', document.getElementById('sire_clave_sol').value.trim());
    formData.append('sire_ambiente', document.getElementById('sire_ambiente').value);

    fetch('api/sire_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Credenciales Guardadas',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'No se pudo guardar la configuración: ' + err.message, 'error');
    });
}

function probarTokenSire() {
    const btn = document.getElementById('btnTestSire');
    const statusText = document.getElementById('sireStatusText');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Conectando con SUNAT...';
    if (statusText) statusText.textContent = 'Autenticando con servidor OAuth2 de SUNAT...';

    const formData = new FormData();
    formData.append('action', 'probar_token');
    formData.append('sire_client_id', document.getElementById('sire_client_id').value.trim());
    formData.append('sire_client_secret', document.getElementById('sire_client_secret').value.trim());
    formData.append('sire_usuario_sol', document.getElementById('sire_usuario_sol').value.trim());
    formData.append('sire_clave_sol', document.getElementById('sire_clave_sol').value.trim());
    formData.append('sire_ambiente', document.getElementById('sire_ambiente').value);

    fetch('api/sire_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-play me-1"></i> Probar Conexión OAuth2 SUNAT';

        if (data.success) {
            if (statusText) statusText.innerHTML = '<span class="text-success fw-bold"><i class="fa fa-circle-check"></i> Conectado a SUNAT</span>';
            Swal.fire({
                icon: 'success',
                title: '¡Conexión Exitosa con SUNAT!',
                html: `${data.message}<br><small class="text-muted">Token generado expira en: ${data.expires_in}s</small>`,
                confirmButtonText: 'Genial'
            });
        } else {
            if (statusText) statusText.innerHTML = '<span class="text-danger fw-bold"><i class="fa fa-circle-xmark"></i> Error de Autenticación</span>';
            Swal.fire({
                icon: 'warning',
                title: 'Respuesta de SUNAT',
                text: data.message,
                confirmButtonText: 'Entendido'
            });
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-play me-1"></i> Probar Conexión OAuth2 SUNAT';
        if (statusText) statusText.textContent = 'Error de conexión';
        Swal.fire('Error de Red', err.message, 'error');
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

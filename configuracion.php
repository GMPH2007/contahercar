<?php
require_once __DIR__ . '/config/app.php';

$pdo = getDBConnection();

// Procesar Actualización de Configuración ANTES de enviar salida HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar_config') {
        $nombreEmpresa = sanitize($_POST['nombre_empresa'] ?? '');
        $rucEmpresa = sanitize($_POST['ruc_empresa'] ?? '');
        $direccion = sanitize($_POST['direccion'] ?? '');
        $telefono = sanitize($_POST['telefono'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $monedaSimbolo = sanitize($_POST['moneda_simbolo'] ?? 'S/.');
        $monedaNombre = sanitize($_POST['moneda_nombre'] ?? 'Soles');
        $impuestoNombre = sanitize($_POST['impuesto_nombre'] ?? 'IGV');
        $impuestoPorcentaje = (float)($_POST['impuesto_porcentaje'] ?? 18.00);

        $apiProvider = sanitize($_POST['api_ruc_provider'] ?? 'decolecta');
        $apiRucUrl = trim($_POST['api_ruc_url'] ?? '');
        $apiDniUrl = trim($_POST['api_dni_url'] ?? '');
        $apiToken = trim($_POST['api_ruc_token'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE configuracion SET 
                nombre_empresa = ?, ruc_empresa = ?, direccion = ?, telefono = ?, email = ?,
                moneda_simbolo = ?, moneda_nombre = ?, impuesto_nombre = ?, impuesto_porcentaje = ?,
                api_ruc_provider = ?, api_ruc_url = ?, api_dni_url = ?, api_ruc_token = ?
                WHERE id = 1");
            $stmt->execute([
                $nombreEmpresa, $rucEmpresa, $direccion, $telefono, $email,
                $monedaSimbolo, $monedaNombre, $impuestoNombre, $impuestoPorcentaje,
                $apiProvider, $apiRucUrl, $apiDniUrl, $apiToken
            ]);

            setFlash('success', 'Configuración Guardada', 'Los ajustes del sistema y de la API RUC se actualizaron con éxito.');
        } catch (PDOException $e) {
            setFlash('error', 'Error al Guardar', $e->getMessage());
        }

        header('Location: configuracion.php');
        exit;
    }
}

// Cargar configuración actual
$cfg = getSystemConfig();
$pageTitle = 'Configuración del Sistema & Integración API';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Configuración de ContaHercar</h4>
        <p class="text-muted small mb-0">Parámetros generales de la empresa, divisas, impuestos e integración de API para consultas RUC / DNI.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Formulario de Configuración Principal -->
    <div class="col-12 col-lg-8">
        <form method="POST" action="configuracion.php">
            <input type="hidden" name="action" value="guardar_config">

            <!-- Datos de la Empresa -->
            <div class="card-custom mb-4">
                <div class="card-custom-header">
                    <h5><i class="fa fa-building text-primary"></i> Identidad de la Empresa</h5>
                </div>
                <div class="card-custom-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Razón Social / Nombre Comercial <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_empresa" class="form-control" value="<?= htmlspecialchars($cfg['nombre_empresa']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">RUC de la Empresa <span class="text-danger">*</span></label>
                            <input type="text" name="ruc_empresa" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['ruc_empresa']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección Fiscal / Sede</label>
                            <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($cfg['direccion']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono / WhatsApp</label>
                            <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($cfg['telefono']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico de Facturación</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cfg['email']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Moneda e Impuestos -->
            <div class="card-custom mb-4">
                <div class="card-custom-header">
                    <h5><i class="fa fa-coins text-warning"></i> Moneda & Parámetros Tributarios</h5>
                </div>
                <div class="card-custom-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Símbolo Moneda</label>
                            <input type="text" name="moneda_simbolo" class="form-control" value="<?= htmlspecialchars($cfg['moneda_simbolo']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Nombre Moneda</label>
                            <input type="text" name="moneda_nombre" class="form-control" value="<?= htmlspecialchars($cfg['moneda_nombre']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Nombre Impuesto</label>
                            <input type="text" name="impuesto_nombre" class="form-control" value="<?= htmlspecialchars($cfg['impuesto_nombre']) ?>" placeholder="IGV, IVA, etc." required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Porcentaje (%)</label>
                            <input type="number" step="0.01" min="0" name="impuesto_porcentaje" class="form-control" value="<?= htmlspecialchars($cfg['impuesto_porcentaje']) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Integración de API para Consulta RUC / DNI -->
            <div class="card-custom mb-4">
                <div class="card-custom-header">
                    <h5><i class="fa fa-plug text-success"></i> Integración de Consultas RUC & DNI</h5>
                    <span class="badge bg-soft-success text-success">Proxy Backend Activo</span>
                </div>
                <div class="card-custom-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fa fa-circle-info me-1"></i> El sistema realiza la consulta a través de un proxy local PHP, eliminando problemas de CORS en el navegador. Puedes usar tokens de proveedores como <strong>apis.net.pe</strong>, <strong>apiperu.dev</strong> o URLs personalizadas.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Proveedor Predeterminado / Plantilla de URLs</label>
                            <select name="api_ruc_provider" id="cfg_api_provider" class="form-select" onchange="cambiarPlantillaApi()">
                                <option value="decolecta" <?= ($cfg['api_ruc_provider'] === 'decolecta') ? 'selected' : '' ?>>Decolecta / APIS.net.pe v1 (Recomendado SUNAT)</option>
                                <option value="decolecta_full" <?= ($cfg['api_ruc_provider'] === 'decolecta_full') ? 'selected' : '' ?>>Decolecta / APIS.net.pe (Información Completa /full)</option>
                                <option value="peruapi" <?= ($cfg['api_ruc_provider'] === 'peruapi') ? 'selected' : '' ?>>PeruAPI (peruapi.com - RUC, DNI y TC)</option>
                                <option value="apisnet" <?= ($cfg['api_ruc_provider'] === 'apisnet') ? 'selected' : '' ?>>apis.net.pe v2 (Legado SUNAT / RENIEC)</option>
                                <option value="apiperu" <?= ($cfg['api_ruc_provider'] === 'apiperu') ? 'selected' : '' ?>>apiperu.dev</option>
                                <option value="custom" <?= ($cfg['api_ruc_provider'] === 'custom') ? 'selected' : '' ?>>URL Personalizada / Otra API</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">URL del Endpoint para Consulta RUC</label>
                            <input type="text" name="api_ruc_url" id="cfg_api_ruc_url" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['api_ruc_url']) ?>" placeholder="https://api.decolecta.com/v1/sunat/ruc?numero={numero}" required>
                            <small class="text-muted">Usa <code>{numero}</code> como marcador de posición donde se inyectará el RUC.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">URL del Endpoint para Consulta DNI</label>
                            <input type="text" name="api_dni_url" id="cfg_api_dni_url" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['api_dni_url']) ?>" placeholder="https://api.apis.net.pe/v2/reniec/dni?numero={numero}" required>
                            <small class="text-muted">Nota: La consulta pública de DNI puede requerir token o convenio específico por normativa.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Token de API / API Key (Opcional)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-key"></i></span>
                                <input type="text" name="api_ruc_token" id="cfg_api_token" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['api_ruc_token']) ?>" placeholder="Pega aquí tu token Bearer o API Key">
                            </div>
                            <small class="text-muted">Proveedores recomendados: <a href="https://peruapi.com/" target="_blank" class="fw-semibold text-primary">PeruAPI.com</a>, <a href="https://decolecta.com/" target="_blank" class="fw-semibold text-primary">Decolecta.com</a> o <a href="https://apis.net.pe/" target="_blank" class="fw-semibold text-primary">APIS.net.pe</a>.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                    <i class="fa fa-floppy-disk me-1"></i> Guardar Toda la Configuración
                </button>
            </div>
        </form>
    </div>

    <!-- Herramienta Interactiva para Probar Conexión con API RUC -->
    <div class="col-12 col-lg-4">
        <div class="card-custom sticky-top" style="top: 85px;">
            <div class="card-custom-header bg-light">
                <h5 class="fs-6"><i class="fa fa-flask text-primary"></i> Probar Conexión API RUC</h5>
            </div>
            <div class="card-custom-body">
                <p class="small text-muted mb-3">Verifica que el servicio de consulta RUC y tu token respondan adecuadamente en vivo con peticiones Backend seguras.</p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Número de RUC / DNI de Prueba:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="test_ruc_input" class="form-control font-monospace" value="20100070970" maxlength="11" onkeydown="if(event.key==='Enter'){event.preventDefault(); probarApiRuc();}">
                        <button type="button" class="btn btn-primary" id="btnTestApi" onclick="probarApiRuc()" title="Probar consulta">
                            <i class="fa fa-play me-1"></i> Probar
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 small" onclick="setTestRuc('20100070970')">Supermercados</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 small" onclick="setTestRuc('20601030013')">Rextie S.A.C.</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 small" onclick="setTestRuc('10460278975')">RUC Persona</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 small" onclick="setTestRuc('45871234')">DNI Reniec</button>
                    </div>
                </div>

                <!-- Resultado de la Prueba -->
                <div id="testResultArea" style="display:none;">
                    <div class="alert alert-secondary py-2 px-3 small mb-2" id="testResultBadge">
                        <strong>Estado:</strong> <span id="testStatusText">-</span>
                    </div>

                    <div class="p-2 border rounded bg-light" style="font-size: 11px; max-height: 220px; overflow-y: auto;">
                        <pre id="testJsonOutput" class="mb-0 text-dark" style="white-space: pre-wrap; word-break: break-all;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function cambiarPlantillaApi() {
    const prov = document.getElementById('cfg_api_provider').value;
    const rucUrl = document.getElementById('cfg_api_ruc_url');
    const dniUrl = document.getElementById('cfg_api_dni_url');

    if (prov === 'peruapi') {
        rucUrl.value = 'https://peruapi.com/api/ruc/{numero}?api_token={token}';
        dniUrl.value = 'https://peruapi.com/api/dni/{numero}?api_token={token}';
    } else if (prov === 'decolecta') {
        rucUrl.value = 'https://api.decolecta.com/v1/sunat/ruc?numero={numero}';
        dniUrl.value = 'https://api.decolecta.com/v1/reniec/dni?numero={numero}';
    } else if (prov === 'decolecta_full') {
        rucUrl.value = 'https://api.decolecta.com/v1/sunat/ruc/full?numero={numero}';
        dniUrl.value = 'https://api.decolecta.com/v1/reniec/dni?numero={numero}';
    } else if (prov === 'apisnet') {
        rucUrl.value = 'https://api.apis.net.pe/v2/sunat/ruc?numero={numero}';
        dniUrl.value = 'https://api.apis.net.pe/v2/reniec/dni?numero={numero}';
    } else if (prov === 'apiperu') {
        rucUrl.value = 'https://apiperu.dev/api/ruc/{numero}';
        dniUrl.value = 'https://apiperu.dev/api/dni/{numero}';
    }
}

function setTestRuc(num) {
    document.getElementById('test_ruc_input').value = num;
    probarApiRuc();
}

function probarApiRuc() {
    const num = document.getElementById('test_ruc_input').value.trim();
    const btn = document.getElementById('btnTestApi');
    const resultArea = document.getElementById('testResultArea');
    const statusText = document.getElementById('testStatusText');
    const jsonOutput = document.getElementById('testJsonOutput');

    if (!num) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    resultArea.style.display = 'block';
    statusText.textContent = 'Consultando endpoint...';
    jsonOutput.textContent = 'Esperando respuesta del servidor...';

    fetch('api/consulta_ruc.php?numero=' + encodeURIComponent(num))
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-play me-1"></i> Probar';

            if (data.success) {
                statusText.innerHTML = `<span class="text-success"><i class="fa fa-circle-check"></i> Éxito (${data.source})</span>`;
            } else {
                statusText.innerHTML = `<span class="text-danger"><i class="fa fa-circle-xmark"></i> Error</span>`;
            }

            jsonOutput.textContent = JSON.stringify(data, null, 2);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-play me-1"></i> Probar';
            statusText.innerHTML = `<span class="text-danger"><i class="fa fa-circle-xmark"></i> Error de conexión</span>`;
            jsonOutput.textContent = 'Error: ' + err.message;
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


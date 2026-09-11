<?php
require_once __DIR__ . '/config/app.php';
$pdo = getDBConnection();

// Procesar Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar_proveedor') {
        $id = (int)($_POST['id'] ?? 0);
        $tipoDoc = sanitize($_POST['tipo_doc'] ?? 'RUC');
        $numDoc = sanitize($_POST['num_doc'] ?? '');
        $razonSocial = sanitize($_POST['razon_social'] ?? '');
        $contacto = sanitize($_POST['contacto'] ?? '');
        $direccion = sanitize($_POST['direccion'] ?? '');
        $telefono = sanitize($_POST['telefono'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $estadoSunat = sanitize($_POST['estado_sunat'] ?? 'ACTIVO');

        if (empty($numDoc) || empty($razonSocial)) {
            setFlash('error', 'Campos Obligatorios', 'El RUC y la razón social del proveedor son requeridos.');
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE proveedores SET 
                        tipo_doc = ?, num_doc = ?, razon_social = ?, contacto = ?, 
                        direccion = ?, telefono = ?, email = ?, estado_sunat = ? 
                        WHERE id = ?");
                    $stmt->execute([$tipoDoc, $numDoc, $razonSocial, $contacto, $direccion, $telefono, $email, $estadoSunat, $id]);
                    setFlash('success', 'Proveedor Actualizado', 'Los datos del proveedor fueron actualizados correctamente.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO proveedores 
                        (tipo_doc, num_doc, razon_social, contacto, direccion, telefono, email, estado_sunat) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$tipoDoc, $numDoc, $razonSocial, $contacto, $direccion, $telefono, $email, $estadoSunat]);
                    setFlash('success', 'Proveedor Registrado', 'El nuevo proveedor fue añadido al catálogo.');
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'RUC Duplicado', 'Ya existe un proveedor registrado con ese número de documento.');
                } else {
                    setFlash('error', 'Error en base de datos', $e->getMessage());
                }
            }
        }
        header('Location: proveedores.php');
        exit;
    }

    if ($action === 'eliminar_proveedor') {
        $id = (int)($_POST['id'] ?? 0);
        $checkPurchases = $pdo->prepare("SELECT COUNT(*) FROM compras WHERE proveedor_id = ?");
        $checkPurchases->execute([$id]);
        if ($checkPurchases->fetchColumn() > 0) {
            setFlash('warning', 'No se puede eliminar', 'Este proveedor posee compras registradas en el sistema. Para mantener la fiabilidad de contabilidad, no se puede eliminar.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Proveedor Eliminado', 'El proveedor ha sido retirado del sistema.');
        }
        header('Location: proveedores.php');
        exit;
    }
}

$pageTitle = 'Directorio de Proveedores';
require_once __DIR__ . '/includes/header.php';

// Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql = "SELECT p.*, 
        COUNT(c.id) as total_compras,
        COALESCE(SUM(c.total), 0) as total_comprado
        FROM proveedores p
        LEFT JOIN compras c ON p.id = c.proveedor_id AND c.estado = 'COMPLETADA'
        WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (p.razon_social LIKE ? OR p.num_doc LIKE ? OR p.contacto LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql .= " GROUP BY p.id ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proveedores = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Directorio de Proveedores</h4>
        <p class="text-muted small mb-0">Administración de proveedores comerciales con consulta RUC y seguimiento de compras.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProveedor" onclick="abrirModalNuevoProveedor()">
        <i class="fa fa-truck-field me-1"></i> Nuevo Proveedor
    </button>
</div>

<!-- Filtro de Búsqueda -->
<div class="card-custom mb-4">
    <div class="card-custom-body py-3">
        <form method="GET" action="proveedores.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar por RUC, Razón Social o Contacto..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search me-1"></i> Buscar</button>
                <?php if (!empty($busqueda)): ?>
                    <a href="proveedores.php" class="btn btn-outline-secondary" title="Limpiar Búsqueda"><i class="fa fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Proveedores -->
<div class="card-custom">
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>RUC / Doc</th>
                        <th>Razón Social</th>
                        <th>Contacto</th>
                        <th>Dirección</th>
                        <th>Teléfono / Email</th>
                        <th>Compras Realizadas</th>
                        <th>Estado SUNAT</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proveedores)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fa fa-truck-ramp-box fs-2 mb-2 d-block text-secondary"></i>
                                No se encontraron proveedores registrados con el criterio especificado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($proveedores as $p): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['tipo_doc']) ?></span><br>
                                    <span class="fw-bold font-monospace"><?= htmlspecialchars($p['num_doc']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['razon_social']) ?></div>
                                    <small class="text-muted">Registrado: <?= formatDate($p['created_at']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($p['contacto'] ?: '-') ?></td>
                                <td><small class="text-secondary"><?= htmlspecialchars($p['direccion'] ?: 'No registrada') ?></small></td>
                                <td>
                                    <?php if ($p['telefono']): ?>
                                        <div><i class="fa fa-phone text-muted me-1 small"></i> <small><?= htmlspecialchars($p['telefono']) ?></small></div>
                                    <?php endif; ?>
                                    <?php if ($p['email']): ?>
                                        <div><i class="fa fa-envelope text-muted me-1 small"></i> <small><?= htmlspecialchars($p['email']) ?></small></div>
                                    <?php endif; ?>
                                    <?php if (!$p['telefono'] && !$p['email']): ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-warning"><?= formatMoney($p['total_comprado']) ?></div>
                                    <small class="text-muted"><?= $p['total_compras'] ?> compras</small>
                                </td>
                                <td>
                                    <span class="badge badge-soft-success"><?= htmlspecialchars($p['estado_sunat'] ?: 'ACTIVO') ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" title="Editar Proveedor" onclick="editarProveedor(<?= htmlspecialchars(json_encode($p)) ?>)">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" title="Eliminar Proveedor" onclick="confirmarEliminarProveedor(<?= $p['id'] ?>, '<?= addslashes($p['razon_social']) ?>')">
                                            <i class="fa fa-trash"></i>
                                        </button>
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

<!-- Modal Nuevo / Editar Proveedor con Consulta RUC -->
<div class="modal fade" id="modalProveedor" tabindex="-1" aria-labelledby="modalProveedorTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="proveedores.php" id="formProveedor">
                <input type="hidden" name="action" value="guardar_proveedor">
                <input type="hidden" name="id" id="prov_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold" id="modalProveedorTitle">Registrar Proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipo de Documento</label>
                            <select name="tipo_doc" id="prov_tipo_doc" class="form-select">
                                <option value="RUC">RUC (11 dígitos)</option>
                                <option value="RUT">RUT / NIT</option>
                                <option value="OTRO">Otro Documento</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Número de RUC <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="num_doc" id="prov_num_doc" class="form-control font-monospace" placeholder="Ej: 20100070970 (presione Enter o botón)..." maxlength="11" onkeydown="if(event.key==='Enter'){event.preventDefault(); ejecutarConsultaRucProv();}" required>
                                <button type="button" class="btn btn-primary" id="btnConsultarRucProv" onclick="ejecutarConsultaRucProv()">
                                    <i class="fa fa-magnifying-glass me-1"></i> Consultar RUC
                                </button>
                            </div>
                            <small class="text-muted">Consulta directa a la API para autocompletar la Razón Social y Domicilio Fiscal (Presione Enter para buscar).</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
                            <input type="text" name="razon_social" id="prov_razon_social" class="form-control" placeholder="Nombre oficial de la empresa proveedora" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Persona de Contacto / Vendedor</label>
                            <input type="text" name="contacto" id="prov_contacto" class="form-control" placeholder="Nombre del representante">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estado en SUNAT</label>
                            <input type="text" name="estado_sunat" id="prov_estado_sunat" class="form-control" value="ACTIVO">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección Fiscal</label>
                            <input type="text" name="direccion" id="prov_direccion" class="form-control" placeholder="Dirección o local comercial">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono / Celular</label>
                            <input type="text" name="telefono" id="prov_telefono" class="form-control" placeholder="01 234-5678 / 987 654 321">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" name="email" id="prov_email" class="form-control" placeholder="ventas@proveedor.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Guardar Proveedor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form oculto para eliminación -->
<form method="POST" action="proveedores.php" id="formEliminarProveedor" style="display:none;">
    <input type="hidden" name="action" value="eliminar_proveedor">
    <input type="hidden" name="id" id="eliminar_prov_id">
</form>

<script>
function abrirModalNuevoProveedor() {
    document.getElementById('modalProveedorTitle').textContent = 'Registrar Proveedor';
    document.getElementById('prov_id').value = '0';
    document.getElementById('prov_tipo_doc').value = 'RUC';
    document.getElementById('prov_num_doc').value = '';
    document.getElementById('prov_razon_social').value = '';
    document.getElementById('prov_contacto').value = '';
    document.getElementById('prov_direccion').value = '';
    document.getElementById('prov_telefono').value = '';
    document.getElementById('prov_email').value = '';
    document.getElementById('prov_estado_sunat').value = 'ACTIVO';
}

function editarProveedor(p) {
    document.getElementById('modalProveedorTitle').textContent = 'Editar Proveedor: ' + p.razon_social;
    document.getElementById('prov_id').value = p.id;
    document.getElementById('prov_tipo_doc').value = p.tipo_doc;
    document.getElementById('prov_num_doc').value = p.num_doc;
    document.getElementById('prov_razon_social').value = p.razon_social;
    document.getElementById('prov_contacto').value = p.contacto || '';
    document.getElementById('prov_direccion').value = p.direccion || '';
    document.getElementById('prov_telefono').value = p.telefono || '';
    document.getElementById('prov_email').value = p.email || '';
    document.getElementById('prov_estado_sunat').value = p.estado_sunat || 'ACTIVO';

    const modal = new bootstrap.Modal(document.getElementById('modalProveedor'));
    modal.show();
}

function ejecutarConsultaRucProv() {
    consultarDocumento('prov_num_doc', 'prov_razon_social', 'prov_direccion', 'prov_estado_sunat', 'btnConsultarRucProv');
}

function confirmarEliminarProveedor(id, razon) {
    Swal.fire({
        title: '¿Eliminar proveedor?',
        text: `¿Estás seguro de eliminar al proveedor "${razon}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const isStatic = window.location.protocol === 'file:' || 
                             window.location.hostname.includes('github.io') || 
                             window.location.pathname.endsWith('.html');
            if (isStatic) {
                Swal.fire('Eliminado', 'Proveedor eliminado del catálogo.', 'success');
            } else {
                document.getElementById('eliminar_prov_id').value = id;
                document.getElementById('formEliminarProveedor').submit();
            }
        }
    });
}

// Interceptar Guardado de Proveedor en Modo Estático
const formPrv = document.getElementById('formProveedor');
if (formPrv) {
    formPrv.addEventListener('submit', function(e) {
        const isStatic = window.location.protocol === 'file:' || 
                         window.location.hostname.includes('github.io') || 
                         window.location.pathname.endsWith('.html');
        if (isStatic) {
            e.preventDefault();
            const nom = document.getElementById('prov_razon_social').value.trim();
            const num = document.getElementById('prov_num_doc').value.trim();
            if (!num || !nom) return;

            const mEl = document.getElementById('modalProveedor');
            const m = bootstrap.Modal.getInstance(mEl);
            if (m) m.hide();

            Swal.fire('¡Proveedor Guardado!', `Los datos de <strong>${nom}</strong> han sido registrados exitosamente.`, 'success');
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


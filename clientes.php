<?php
require_once __DIR__ . '/config/app.php';
$pdo = getDBConnection();

// Procesar Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar_cliente') {
        $id = (int)($_POST['id'] ?? 0);
        $tipoDoc = sanitize($_POST['tipo_doc'] ?? 'DNI');
        $numDoc = sanitize($_POST['num_doc'] ?? '');
        $nombre = sanitize($_POST['nombre_razon_social'] ?? '');
        $direccion = sanitize($_POST['direccion'] ?? '');
        $telefono = sanitize($_POST['telefono'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $condicion = sanitize($_POST['condicion'] ?? 'HABIDO');
        $estadoSunat = sanitize($_POST['estado_sunat'] ?? 'ACTIVO');

        if (empty($numDoc) || empty($nombre)) {
            setFlash('error', 'Campos Obligatorios', 'El número de documento y el nombre o razón social son obligatorios.');
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE clientes SET 
                        tipo_doc = ?, num_doc = ?, nombre_razon_social = ?, direccion = ?, 
                        telefono = ?, email = ?, condicion = ?, estado_sunat = ? 
                        WHERE id = ?");
                    $stmt->execute([$tipoDoc, $numDoc, $nombre, $direccion, $telefono, $email, $condicion, $estadoSunat, $id]);
                    setFlash('success', 'Cliente Actualizado', 'Los datos del cliente fueron actualizados correctamente.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO clientes 
                        (tipo_doc, num_doc, nombre_razon_social, direccion, telefono, email, condicion, estado_sunat) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$tipoDoc, $numDoc, $nombre, $direccion, $telefono, $email, $condicion, $estadoSunat]);
                    setFlash('success', 'Cliente Registrado', 'El nuevo cliente fue guardado en el sistema.');
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'Documento Duplicado', 'Ya existe un cliente registrado con ese número de documento.');
                } else {
                    setFlash('error', 'Error en base de datos', $e->getMessage());
                }
            }
        }
        header('Location: clientes.php');
        exit;
    }

    if ($action === 'eliminar_cliente') {
        $id = (int)($_POST['id'] ?? 0);
        // Validar que no tenga ventas asociadas
        $checkSales = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE cliente_id = ?");
        $checkSales->execute([$id]);
        if ($checkSales->fetchColumn() > 0) {
            setFlash('warning', 'No se puede eliminar', 'Este cliente posee comprobantes de venta registrados. No puede ser borrado para mantener la fiabilidad histórica.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Cliente Eliminado', 'El registro ha sido retirado.');
        }
        header('Location: clientes.php');
        exit;
    }
}

$pageTitle = 'Directorio de Clientes';
require_once __DIR__ . '/includes/header.php';

// Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql = "SELECT c.*, 
        COUNT(v.id) as total_compras,
        COALESCE(SUM(v.total), 0) as total_gastado
        FROM clientes c
        LEFT JOIN ventas v ON c.id = v.cliente_id AND v.estado = 'COMPLETADA'
        WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (c.nombre_razon_social LIKE ? OR c.num_doc LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql .= " GROUP BY c.id ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Cartera de Clientes</h4>
        <p class="text-muted small mb-0">Gestión de clientes, historial de ventas e integración directa con consultas RUC / DNI.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente" onclick="abrirModalNuevoCliente()">
        <i class="fa fa-user-plus me-1"></i> Nuevo Cliente
    </button>
</div>

<!-- Filtro de Búsqueda -->
<div class="card-custom mb-4">
    <div class="card-custom-body py-3">
        <form method="GET" action="clientes.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar por DNI, RUC o Nombre..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search me-1"></i> Buscar</button>
                <?php if (!empty($busqueda)): ?>
                    <a href="clientes.php" class="btn btn-outline-secondary" title="Limpiar Búsqueda"><i class="fa fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Clientes -->
<div class="card-custom">
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Tipo / Doc</th>
                        <th>Nombre / Razón Social</th>
                        <th>Dirección</th>
                        <th>Contacto</th>
                        <th>SUNAT / Condición</th>
                        <th>Compras Realizadas</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fa fa-users-slash fs-2 mb-2 d-block text-secondary"></i>
                                No se encontraron clientes registrados con el criterio especificado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['tipo_doc']) ?></span><br>
                                    <span class="fw-bold font-monospace"><?= htmlspecialchars($c['num_doc']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($c['nombre_razon_social']) ?></div>
                                    <small class="text-muted"><?= formatDate($c['created_at']) ?></small>
                                </td>
                                <td><small class="text-secondary"><?= htmlspecialchars($c['direccion'] ?: 'No registrada') ?></small></td>
                                <td>
                                    <?php if ($c['telefono']): ?>
                                        <div><i class="fa fa-phone text-muted me-1 small"></i> <small><?= htmlspecialchars($c['telefono']) ?></small></div>
                                    <?php endif; ?>
                                    <?php if ($c['email']): ?>
                                        <div><i class="fa fa-envelope text-muted me-1 small"></i> <small><?= htmlspecialchars($c['email']) ?></small></div>
                                    <?php endif; ?>
                                    <?php if (!$c['telefono'] && !$c['email']): ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-soft-success"><?= htmlspecialchars($c['estado_sunat'] ?: 'ACTIVO') ?></span>
                                    <span class="badge badge-soft-info"><?= htmlspecialchars($c['condicion'] ?: 'HABIDO') ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-success"><?= formatMoney($c['total_gastado']) ?></div>
                                    <small class="text-muted"><?= $c['total_compras'] ?> ventas</small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" title="Editar Cliente" onclick="editarCliente(<?= htmlspecialchars(json_encode($c)) ?>)">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <?php if ($c['num_doc'] !== '00000000'): ?>
                                            <button type="button" class="btn btn-outline-danger" title="Eliminar Cliente" onclick="confirmarEliminarCliente(<?= $c['id'] ?>, '<?= addslashes($c['nombre_razon_social']) ?>')">
                                                <i class="fa fa-trash"></i>
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

<!-- Modal Nuevo / Editar Cliente con Consulta RUC/DNI -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalClienteTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="clientes.php" id="formCliente">
                <input type="hidden" name="action" value="guardar_cliente">
                <input type="hidden" name="id" id="cliente_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold" id="modalClienteTitle">Registrar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipo de Documento</label>
                            <select name="tipo_doc" id="cliente_tipo_doc" class="form-select" onchange="ajustarTipoDocumento()">
                                <option value="DNI">DNI (8 dígitos)</option>
                                <option value="RUC">RUC (11 dígitos)</option>
                                <option value="RUT">RUT / CI</option>
                                <option value="OTRO">Otro Documento</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Número de Documento <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="num_doc" id="cliente_num_doc" class="form-control font-monospace" placeholder="Ingrese DNI o RUC (presione Enter o botón)..." onkeydown="if(event.key==='Enter'){event.preventDefault(); ejecutarConsultaDocumento();}" required>
                                <button type="button" class="btn btn-primary" id="btnConsultarDoc" onclick="ejecutarConsultaDocumento()">
                                    <i class="fa fa-magnifying-glass me-1"></i> Consultar RUC/DNI
                                </button>
                            </div>
                            <small class="text-muted">Consulta directa a la API configurada para autocompletar datos oficiales (Presione Enter para buscar).</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Nombre Completo o Razón Social <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_razon_social" id="cliente_nombre" class="form-control" placeholder="Nombre del cliente o empresa" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección / Domicilio Fiscal</label>
                            <input type="text" name="direccion" id="cliente_direccion" class="form-control" placeholder="Dirección completa">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono / Celular</label>
                            <input type="text" name="telefono" id="cliente_telefono" class="form-control" placeholder="+51 987654321">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" name="email" id="cliente_email" class="form-control" placeholder="correo@ejemplo.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estado en SUNAT</label>
                            <input type="text" name="estado_sunat" id="cliente_estado_sunat" class="form-control" value="ACTIVO">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Condición</label>
                            <input type="text" name="condicion" id="cliente_condicion" class="form-control" value="HABIDO">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form oculto para eliminación -->
<form method="POST" action="clientes.php" id="formEliminarCliente" style="display:none;">
    <input type="hidden" name="action" value="eliminar_cliente">
    <input type="hidden" name="id" id="eliminar_cliente_id">
</form>

<script>
function abrirModalNuevoCliente() {
    document.getElementById('modalClienteTitle').textContent = 'Registrar Nuevo Cliente';
    document.getElementById('cliente_id').value = '0';
    document.getElementById('cliente_tipo_doc').value = 'DNI';
    document.getElementById('cliente_num_doc').value = '';
    document.getElementById('cliente_nombre').value = '';
    document.getElementById('cliente_direccion').value = '';
    document.getElementById('cliente_telefono').value = '';
    document.getElementById('cliente_email').value = '';
    document.getElementById('cliente_estado_sunat').value = 'ACTIVO';
    document.getElementById('cliente_condicion').value = 'HABIDO';
}

function editarCliente(c) {
    document.getElementById('modalClienteTitle').textContent = 'Editar Cliente: ' + c.nombre_razon_social;
    document.getElementById('cliente_id').value = c.id;
    document.getElementById('cliente_tipo_doc').value = c.tipo_doc;
    document.getElementById('cliente_num_doc').value = c.num_doc;
    document.getElementById('cliente_nombre').value = c.nombre_razon_social;
    document.getElementById('cliente_direccion').value = c.direccion || '';
    document.getElementById('cliente_telefono').value = c.telefono || '';
    document.getElementById('cliente_email').value = c.email || '';
    document.getElementById('cliente_estado_sunat').value = c.estado_sunat || 'ACTIVO';
    document.getElementById('cliente_condicion').value = c.condicion || 'HABIDO';

    const modal = new bootstrap.Modal(document.getElementById('modalCliente'));
    modal.show();
}

function ajustarTipoDocumento() {
    const tipo = document.getElementById('cliente_tipo_doc').value;
    const inputNum = document.getElementById('cliente_num_doc');
    if (tipo === 'DNI') {
        inputNum.placeholder = 'DNI de 8 dígitos';
        inputNum.maxLength = 8;
    } else if (tipo === 'RUC') {
        inputNum.placeholder = 'RUC de 11 dígitos';
        inputNum.maxLength = 11;
    } else {
        inputNum.placeholder = 'Número de documento';
        inputNum.removeAttribute('maxLength');
    }
}

function ejecutarConsultaDocumento() {
    consultarDocumento('cliente_num_doc', 'cliente_nombre', 'cliente_direccion', 'cliente_estado_sunat', 'btnConsultarDoc');
}

function confirmarEliminarCliente(id, nombre) {
    Swal.fire({
        title: '¿Eliminar cliente?',
        text: `¿Estás seguro de eliminar a "${nombre}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('eliminar_cliente_id').value = id;
            document.getElementById('formEliminarCliente').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


<?php
$pageTitle = 'Inventario & Catálogo de Productos';
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();

// Procesar Acciones POST (Crear, Editar, Ajuste de Stock, Categoría)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Guardar o Actualizar Producto
    if ($action === 'guardar_producto') {
        $id = (int)($_POST['id'] ?? 0);
        $codigo = sanitize($_POST['codigo_barra'] ?? '');
        $nombre = sanitize($_POST['nombre'] ?? '');
        $categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
        $descripcion = sanitize($_POST['descripcion'] ?? '');
        $precioCompra = (float)($_POST['precio_compra'] ?? 0);
        $precioVenta = (float)($_POST['precio_venta'] ?? 0);
        $stockMinimo = (int)($_POST['stock_minimo'] ?? 5);
        $unidadMedida = sanitize($_POST['unidad_medida'] ?? 'UNID');
        $estado = isset($_POST['estado']) ? 1 : 0;

        if (empty($codigo) || empty($nombre)) {
            setFlash('error', 'Campos obligatorios', 'El código y el nombre del producto son requeridos.');
        } else {
            try {
                if ($id > 0) {
                    // Actualizar
                    $stmt = $pdo->prepare("UPDATE productos SET 
                        codigo_barra = ?, nombre = ?, categoria_id = ?, descripcion = ?, 
                        precio_compra = ?, precio_venta = ?, stock_minimo = ?, unidad_medida = ?, estado = ?
                        WHERE id = ?");
                    $stmt->execute([$codigo, $nombre, $categoriaId, $descripcion, $precioCompra, $precioVenta, $stockMinimo, $unidadMedida, $estado, $id]);
                    setFlash('success', 'Producto Actualizado', 'Los datos del producto han sido guardados correctamente.');
                } else {
                    // Nuevo Producto
                    $stockInicial = (int)($_POST['stock_inicial'] ?? 0);
                    $stmt = $pdo->prepare("INSERT INTO productos 
                        (codigo_barra, nombre, categoria_id, descripcion, precio_compra, precio_venta, stock, stock_minimo, unidad_medida, estado) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$codigo, $nombre, $categoriaId, $descripcion, $precioCompra, $precioVenta, $stockInicial, $stockMinimo, $unidadMedida, 1]);
                    $newId = $pdo->lastInsertId();

                    // Registrar en Kardex si tiene stock inicial
                    if ($stockInicial > 0) {
                        $stmtK = $pdo->prepare("INSERT INTO kardex (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo) VALUES (?, 'INVENTARIO_INICIAL', ?, 0, ?, ?, 'Alta de producto con stock inicial')");
                        $stmtK->execute([$newId, $stockInicial, $stockInicial, $precioCompra]);
                    }
                    setFlash('success', 'Producto Creado', 'El producto ha sido incorporado al catálogo exitosamente.');
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'Código duplicado', 'Ya existe un producto registrado con ese código de barras.');
                } else {
                    setFlash('error', 'Error en la operación', $e->getMessage());
                }
            }
        }
        header('Location: inventario.php');
        exit;
    }

    // 2. Ajuste manual de stock
    if ($action === 'ajuste_stock') {
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $tipoAjuste = $_POST['tipo_ajuste'] ?? 'INGRESO'; // 'INGRESO' o 'SALIDA'
        $cantidad = (int)($_POST['cantidad'] ?? 0);
        $motivo = sanitize($_POST['motivo'] ?? 'Ajuste manual de inventario');

        if ($productoId > 0 && $cantidad > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT stock, precio_compra FROM productos WHERE id = ? FOR UPDATE");
                $stmt->execute([$productoId]);
                $prod = $stmt->fetch();

                if ($prod) {
                    $stockAnt = (int)$prod['stock'];
                    if ($tipoAjuste === 'INGRESO') {
                        $stockNuevo = $stockAnt + $cantidad;
                        $tipoMov = 'AJUSTE_INGRESO';
                    } else {
                        if ($cantidad > $stockAnt) {
                            throw new Exception("No puede retirar más unidades ($cantidad) de las que existen en stock ($stockAnt).");
                        }
                        $stockNuevo = $stockAnt - $cantidad;
                        $tipoMov = 'AJUSTE_SALIDA';
                    }

                    // Actualizar stock de producto
                    $stmtUp = $pdo->prepare("UPDATE productos SET stock = ? WHERE id = ?");
                    $stmtUp->execute([$stockNuevo, $productoId]);

                    // Registrar en Kardex
                    $stmtK = $pdo->prepare("INSERT INTO kardex (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtK->execute([$productoId, $tipoMov, $cantidad, $stockAnt, $stockNuevo, $prod['precio_compra'], $motivo]);

                    $pdo->commit();
                    setFlash('success', 'Ajuste Realizado', "El stock se actualizó correctamente a $stockNuevo unidades.");
                } else {
                    $pdo->rollBack();
                    setFlash('error', 'Error', 'Producto no encontrado.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('error', 'Error de Ajuste', $e->getMessage());
            }
        }
        header('Location: inventario.php');
        exit;
    }

    // 3. Crear Categoría rápida
    if ($action === 'guardar_categoria') {
        $catNombre = sanitize($_POST['cat_nombre'] ?? '');
        $catDesc = sanitize($_POST['cat_desc'] ?? '');
        if (!empty($catNombre)) {
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
            $stmt->execute([$catNombre, $catDesc]);
            setFlash('success', 'Categoría Creada', 'La nueva categoría fue registrada.');
        }
        header('Location: inventario.php');
        exit;
    }
}

// Filtros y Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroCat = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
$filtroStock = isset($_GET['filtro']) ? trim($_GET['filtro']) : '';

$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (p.nombre LIKE ? OR p.codigo_barra LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($filtroCat > 0) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $filtroCat;
}

if ($filtroStock === 'stock_bajo') {
    $sql .= " AND p.stock <= p.stock_minimo";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Categorías para los selectores
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Control de Inventario & Kardex</h4>
        <p class="text-muted small mb-0">Gestión de productos, existencias en almacén y registro de movimientos fiables.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
            <i class="fa fa-folder-plus me-1"></i> Categorías
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="abrirModalNuevoProducto()">
            <i class="fa fa-plus me-1"></i> Nuevo Producto
        </button>
    </div>
</div>

<!-- Barra de Filtros y Búsqueda -->
<div class="card-custom mb-4">
    <div class="card-custom-body py-3">
        <form method="GET" action="inventario.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar por código de barra o nombre..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="categoria_id" class="form-select">
                    <option value="0">-- Todas las Categorías --</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filtroCat == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="filtro" class="form-select">
                    <option value="">-- Todos los Stocks --</option>
                    <option value="stock_bajo" <?= $filtroStock === 'stock_bajo' ? 'selected' : '' ?>>Stock Crítico / Bajo</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-filter me-1"></i> Filtrar</button>
                <?php if (!empty($busqueda) || $filtroCat > 0 || !empty($filtroStock)): ?>
                    <a href="inventario.php" class="btn btn-outline-secondary" title="Limpiar Filtros"><i class="fa fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Productos -->
<div class="card-custom">
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Código / SKU</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>P. Compra</th>
                        <th>P. Venta</th>
                        <th>Stock Actual</th>
                        <th>Mínimo</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="fa fa-box-open fs-2 mb-2 d-block text-secondary"></i>
                                No se encontraron productos con los criterios de búsqueda seleccionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <?php 
                                $isBajo = $p['stock'] <= $p['stock_minimo'];
                                $isAgotado = $p['stock'] <= 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($p['codigo_barra']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['nombre']) ?></div>
                                    <?php if (!empty($p['descripcion'])): ?>
                                        <small class="text-muted"><?= htmlspecialchars($p['descripcion']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría') ?></td>
                                <td class="text-secondary"><?= formatMoney($p['precio_compra']) ?></td>
                                <td class="fw-bold text-dark"><?= formatMoney($p['precio_venta']) ?></td>
                                <td>
                                    <?php if ($isAgotado): ?>
                                        <span class="badge badge-soft-danger"><i class="fa fa-circle-xmark me-1"></i> 0 <?= htmlspecialchars($p['unidad_medida']) ?> (Agotado)</span>
                                    <?php elseif ($isBajo): ?>
                                        <span class="badge badge-soft-warning"><i class="fa fa-triangle-exclamation me-1"></i> <?= $p['stock'] ?> <?= htmlspecialchars($p['unidad_medida']) ?> (Bajo)</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-success"><i class="fa fa-circle-check me-1"></i> <?= $p['stock'] ?> <?= htmlspecialchars($p['unidad_medida']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted"><?= $p['stock_minimo'] ?> <?= htmlspecialchars($p['unidad_medida']) ?></span></td>
                                <td>
                                    <span class="badge <?= $p['estado'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $p['estado'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Ajuste Rápido de Stock -->
                                        <button type="button" class="btn btn-outline-warning" title="Ajuste manual de stock" onclick="abrirAjusteStock(<?= htmlspecialchars(json_encode($p)) ?>)">
                                            <i class="fa fa-sliders"></i>
                                        </button>
                                        <!-- Kardex -->
                                        <button type="button" class="btn btn-outline-info" title="Ver Historial Kardex" onclick="verKardexProducto(<?= $p['id'] ?>)">
                                            <i class="fa fa-clock-rotate-left"></i>
                                        </button>
                                        <!-- Editar -->
                                        <button type="button" class="btn btn-outline-primary" title="Editar Producto" onclick="editarProducto(<?= htmlspecialchars(json_encode($p)) ?>)">
                                            <i class="fa fa-pen"></i>
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

<!-- Modal Nuevo / Editar Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="inventario.php">
                <input type="hidden" name="action" value="guardar_producto">
                <input type="hidden" name="id" id="prod_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold" id="modalProductoTitle">Registrar Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Código de Barras / SKU <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-barcode"></i></span>
                                <input type="text" name="codigo_barra" id="prod_codigo" class="form-control" placeholder="Ej: 77501009" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generarCodigoAleatorio()" title="Generar código automático"><i class="fa fa-arrows-rotate"></i></button>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Nombre del Producto <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="prod_nombre" class="form-control" placeholder="Ej: Taladro Percutor 650W" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Categoría</label>
                            <select name="categoria_id" id="prod_categoria" class="form-select">
                                <option value="">-- Seleccionar Categoría --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unidad de Medida</label>
                            <select name="unidad_medida" id="prod_unidad" class="form-select">
                                <option value="UNID">UNIDAD (UNID)</option>
                                <option value="JGO">JUEGO (JGO)</option>
                                <option value="ROLLO">ROLLO (ROLLO)</option>
                                <option value="CAJA">CAJA (CAJA)</option>
                                <option value="KG">KILOGRAMO (KG)</option>
                                <option value="MT">METRO (MT)</option>
                                <option value="GLN">GALÓN (GLN)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Precio de Compra (Costo)</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= $cfg['moneda_simbolo'] ?></span>
                                <input type="number" step="0.01" min="0" name="precio_compra" id="prod_pcompra" class="form-control" value="0.00" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Precio de Venta</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= $cfg['moneda_simbolo'] ?></span>
                                <input type="number" step="0.01" min="0" name="precio_venta" id="prod_pventa" class="form-control" value="0.00" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock Mínimo (Alerta)</label>
                            <input type="number" min="0" name="stock_minimo" id="prod_stock_minimo" class="form-control" value="5" required>
                        </div>

                        <!-- Solo visible cuando es producto nuevo -->
                        <div class="col-md-6" id="divStockInicial">
                            <label class="form-label fw-semibold">Stock Inicial en Almacén</label>
                            <input type="number" min="0" name="stock_inicial" id="prod_stock_inicial" class="form-control" value="0">
                            <small class="text-muted">Se registrará automáticamente en el Kardex.</small>
                        </div>

                        <div class="col-md-6 d-flex align-items-center pt-3" id="divEstado">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="estado" id="prod_estado" value="1" checked>
                                <label class="form-check-label fw-semibold" for="prod_estado">Producto Habilitado / Activo</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Descripción o Especificaciones</label>
                            <textarea name="descripcion" id="prod_descripcion" class="form-control" rows="2" placeholder="Detalles técnicos, marca, modelo..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajuste de Stock -->
<div class="modal fade" id="modalAjusteStock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="inventario.php">
                <input type="hidden" name="action" value="ajuste_stock">
                <input type="hidden" name="producto_id" id="ajuste_prod_id">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold"><i class="fa fa-sliders text-warning me-1"></i> Ajuste Manual de Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-secondary py-2">
                        <strong id="ajuste_prod_nombre">Producto</strong><br>
                        <small>Stock actual en sistema: <span class="badge bg-dark" id="ajuste_stock_actual">0</span></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de Ajuste</label>
                        <select name="tipo_ajuste" class="form-select" required>
                            <option value="INGRESO">Ingreso / Entrada (+ Sumar unidades)</option>
                            <option value="SALIDA">Salida / Retiro (- Restar unidades)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cantidad a Ajustar</label>
                        <input type="number" min="1" name="cantidad" class="form-control" placeholder="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo del Ajuste</label>
                        <input type="text" name="motivo" class="form-control" placeholder="Ej: Conteo físico, merma, calibración, muestra..." required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-check me-1"></i> Confirmar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Categorías -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="inventario.php">
                <input type="hidden" name="action" value="guardar_categoria">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold"><i class="fa fa-folder-plus text-primary me-1"></i> Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la Categoría</label>
                        <input type="text" name="cat_nombre" class="form-control" placeholder="Ej: Pinturas y Acabados" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción (Opcional)</label>
                        <textarea name="cat_desc" class="form-control" rows="2" placeholder="Breve detalle de los artículos que agrupa"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Registrar Categoría</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalNuevoProducto() {
    document.getElementById('modalProductoTitle').textContent = 'Registrar Nuevo Producto';
    document.getElementById('prod_id').value = '0';
    document.getElementById('prod_codigo').value = '';
    document.getElementById('prod_nombre').value = '';
    document.getElementById('prod_categoria').value = '';
    document.getElementById('prod_unidad').value = 'UNID';
    document.getElementById('prod_pcompra').value = '0.00';
    document.getElementById('prod_pventa').value = '0.00';
    document.getElementById('prod_stock_minimo').value = '5';
    document.getElementById('prod_stock_inicial').value = '0';
    document.getElementById('prod_descripcion').value = '';
    document.getElementById('prod_estado').checked = true;
    document.getElementById('divStockInicial').style.display = 'block';
}

function editarProducto(p) {
    document.getElementById('modalProductoTitle').textContent = 'Editar Producto: ' + p.nombre;
    document.getElementById('prod_id').value = p.id;
    document.getElementById('prod_codigo').value = p.codigo_barra;
    document.getElementById('prod_nombre').value = p.nombre;
    document.getElementById('prod_categoria').value = p.categoria_id || '';
    document.getElementById('prod_unidad').value = p.unidad_medida || 'UNID';
    document.getElementById('prod_pcompra').value = p.precio_compra;
    document.getElementById('prod_pventa').value = p.precio_venta;
    document.getElementById('prod_stock_minimo').value = p.stock_minimo;
    document.getElementById('prod_descripcion').value = p.descripcion || '';
    document.getElementById('prod_estado').checked = (p.estado == 1);
    document.getElementById('divStockInicial').style.display = 'none'; // No se edita stock directo aquí, se usa ajuste o compras

    const modal = new bootstrap.Modal(document.getElementById('modalProducto'));
    modal.show();
}

function abrirAjusteStock(p) {
    document.getElementById('ajuste_prod_id').value = p.id;
    document.getElementById('ajuste_prod_nombre').textContent = p.nombre + ' (' + p.codigo_barra + ')';
    document.getElementById('ajuste_stock_actual').textContent = p.stock + ' ' + (p.unidad_medida || 'UNID');

    const modal = new bootstrap.Modal(document.getElementById('modalAjusteStock'));
    modal.show();
}

function generarCodigoAleatorio() {
    const randomNum = Math.floor(10000000 + Math.random() * 90000000);
    document.getElementById('prod_codigo').value = '775' + randomNum.toString().substring(0, 5);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


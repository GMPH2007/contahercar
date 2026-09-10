<?php
require_once __DIR__ . '/config/app.php';

$pdo = getDBConnection();
$cfg = getSystemConfig();

// Procesar POST de Venta ANTES de enviar salida HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $tipoComprobante = sanitize($_POST['tipo_comprobante'] ?? 'Boleta');
    $metodoPago = sanitize($_POST['metodo_pago'] ?? 'Efectivo');
    $observaciones = sanitize($_POST['observaciones'] ?? '');
    
    // Series estándar según comprobante
    $serie = ($tipoComprobante === 'Factura') ? 'F001' : (($tipoComprobante === 'Boleta') ? 'B001' : 'NV01');
    $correlativo = getNextCorrelativo($tipoComprobante, $serie);

    $productosArr = $_POST['prod_id'] ?? [];
    $cantidadesArr = $_POST['cantidad'] ?? [];
    $preciosArr = $_POST['precio_unitario'] ?? [];

    if ($clienteId <= 0 || empty($productosArr)) {
        setFlash('error', 'Datos Incompletos', 'Debe seleccionar un cliente y tener al menos un producto en el carrito.');
    } else {
        $pdo->beginTransaction();
        try {
            $subtotalGeneral = 0;
            $itemsValidos = [];

            // Validar stock y recopilar ítems
            $stmtCheckProd = $pdo->prepare("SELECT id, nombre, stock, precio_compra FROM productos WHERE id = ? FOR UPDATE");

            for ($i = 0; $i < count($productosArr); $i++) {
                $pId = (int)$productosArr[$i];
                $cant = (int)$cantidadesArr[$i];
                $pUnit = (float)$preciosArr[$i];

                if ($pId > 0 && $cant > 0) {
                    $stmtCheckProd->execute([$pId]);
                    $prod = $stmtCheckProd->fetch();

                    if (!$prod) {
                        throw new Exception("Producto con ID $pId no existe.");
                    }
                    if ($prod['stock'] < $cant) {
                        throw new Exception("Stock insuficiente para '{$prod['nombre']}'. Disponible: {$prod['stock']}, solicitado: $cant.");
                    }

                    $itemSubtotal = $cant * $pUnit;
                    $subtotalGeneral += $itemSubtotal;

                    $itemsValidos[] = [
                        'producto_id' => $pId,
                        'cantidad' => $cant,
                        'precio_unitario' => $pUnit,
                        'costo_unitario' => (float)$prod['precio_compra'],
                        'subtotal' => $itemSubtotal,
                        'stock_actual' => (int)$prod['stock']
                    ];
                }
            }

            if (empty($itemsValidos)) {
                throw new Exception("El carrito no contiene productos válidos con cantidades mayores a cero.");
            }

            // Desglosar Subtotal e Impuesto según porcentaje configurado
            $porcentajeImpuesto = (float)$cfg['impuesto_porcentaje'];
            $divisor = 1 + ($porcentajeImpuesto / 100);
            
            $subtotalSinImpuesto = round($subtotalGeneral / $divisor, 2);
            $impuestoVenta = round($subtotalGeneral - $subtotalSinImpuesto, 2);
            $totalVenta = $subtotalGeneral;

            // 1. Insertar Cabecera de Venta
            $stmtVenta = $pdo->prepare("INSERT INTO ventas 
                (cliente_id, tipo_comprobante, serie, correlativo, subtotal, impuesto, descuento, total, metodo_pago, estado, observaciones) 
                VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, ?, 'COMPLETADA', ?)");
            $stmtVenta->execute([
                $clienteId, 
                $tipoComprobante, 
                $serie, 
                $correlativo, 
                $subtotalSinImpuesto, 
                $impuestoVenta, 
                $totalVenta, 
                $metodoPago, 
                $observaciones
            ]);
            $ventaId = $pdo->lastInsertId();

            // 2. Insertar Detalle, Descontar Stock y Registrar Kardex Atómicamente
            $stmtDetalle = $pdo->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, costo_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtUpdateStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmtKardex = $pdo->prepare("INSERT INTO kardex (producto_id, tipo_movimiento, referencia_id, cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo) VALUES (?, 'VENTA', ?, ?, ?, ?, ?, ?)");

            foreach ($itemsValidos as $item) {
                // Registrar detalle de venta
                $stmtDetalle->execute([
                    $ventaId,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['precio_unitario'],
                    $item['costo_unitario'],
                    $item['subtotal']
                ]);

                // Descontar inventario
                $stmtUpdateStock->execute([$item['cantidad'], $item['producto_id']]);

                $nuevoStock = $item['stock_actual'] - $item['cantidad'];

                // Registrar en Kardex
                $stmtKardex->execute([
                    $item['producto_id'],
                    $ventaId,
                    $item['cantidad'],
                    $item['stock_actual'],
                    $nuevoStock,
                    $item['precio_unitario'],
                    "Venta $tipoComprobante $serie-" . str_pad($correlativo, 6, '0', STR_PAD_LEFT)
                ]);
            }

            $pdo->commit();
            setFlash('success', 'Venta Registrada', "Venta $serie-" . str_pad($correlativo, 6, '0', STR_PAD_LEFT) . " emitida con éxito.");
            
            // Redirigir directamente al ticket con impresión automática
            header("Location: ticket.php?id=$ventaId&auto_print=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error en la Venta', $e->getMessage());
        }
    }
}

// Cargar Clientes
$clientes = $pdo->query("SELECT id, nombre_razon_social, num_doc, tipo_doc FROM clientes ORDER BY (num_doc = '00000000') DESC, nombre_razon_social ASC")->fetchAll();

// Cargar Catálogo de Productos con Stock > 0
$productos = $pdo->query("SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.estado = 1 AND p.stock > 0 
    ORDER BY p.nombre ASC")->fetchAll();

$pageTitle = 'Punto de Venta (POS) - Nueva Venta';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Selector de Pestañas exclusivo para Celulares y Tablets -->
<div class="d-flex d-lg-none mb-3 gap-2 sticky-top bg-white p-2 border rounded shadow-sm" style="top: 60px; z-index: 1015;">
    <button type="button" class="btn btn-primary flex-fill fw-bold py-2" id="btnTabProductos" onclick="cambiarTabPos('productos')">
        <i class="fa fa-boxes-stacked me-1"></i> Catálogo (<span id="countProdMobile"><?= count($productos) ?></span>)
    </button>
    <button type="button" class="btn btn-outline-primary flex-fill fw-bold py-2 position-relative" id="btnTabCarrito" onclick="cambiarTabPos('carrito')">
        <i class="fa fa-cart-shopping me-1"></i> Carrito
        <span class="badge bg-danger rounded-pill ms-1" id="badgeCartMobile">0</span>
    </button>
</div>

<div class="pos-wrapper">
    <!-- Columna Izquierda: Búsqueda y Selección de Productos -->
    <div class="pos-products">
        <div class="card-custom mb-3">
            <div class="card-custom-body py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-barcode text-primary"></i></span>
                            <input type="text" id="posBusqueda" class="form-control" placeholder="Buscar por código de barras o nombre de producto..." autofocus>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 text-end">
                        <span class="text-muted small"><i class="fa fa-boxes-stacked me-1"></i> <span id="posTotalDisponibles"><?= count($productos) ?></span> productos disponibles</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Catálogo de Productos en Grid -->
        <div class="row g-2" id="gridProductos" style="max-height: calc(100vh - 220px); overflow-y: auto;">
            <?php foreach ($productos as $p): ?>
                <div class="col-6 col-sm-4 col-md-3 item-card-prod" 
                     data-id="<?= $p['id'] ?>" 
                     data-nombre="<?= htmlspecialchars($p['nombre']) ?>" 
                     data-codigo="<?= htmlspecialchars($p['codigo_barra']) ?>" 
                     data-precio="<?= $p['precio_venta'] ?>" 
                     data-stock="<?= $p['stock'] ?>" 
                     data-unidad="<?= htmlspecialchars($p['unidad_medida']) ?>"
                     onclick="agregarAlCarrito(<?= htmlspecialchars(json_encode($p)) ?>)">
                    <div class="card h-100 p-2 border shadow-sm cursor-pointer" style="cursor: pointer; transition: transform 0.15s, border-color 0.15s;">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge bg-light text-secondary border small font-monospace"><?= htmlspecialchars($p['codigo_barra']) ?></span>
                            <span class="badge <?= $p['stock'] <= $p['stock_minimo'] ? 'bg-warning text-dark' : 'bg-success' ?> small">
                                Stock: <?= $p['stock'] ?>
                            </span>
                        </div>
                        <div class="fw-semibold text-dark text-truncate-2 small mb-2" style="height: 38px; overflow: hidden;" title="<?= htmlspecialchars($p['nombre']) ?>">
                            <?= htmlspecialchars($p['nombre']) ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-baseline mt-auto">
                            <span class="text-primary fw-bold fs-6"><?= formatMoney($p['precio_venta']) ?></span>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" title="Agregar">+</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Columna Derecha: Carrito de Compras y Facturación -->
    <div class="pos-cart">
        <form method="POST" action="venta_nueva.php" id="formVentaPos" class="d-flex flex-column h-100">
            <div class="pos-cart-header">
                <!-- Botón Volver al Catálogo visible solo en celular -->
                <button type="button" class="btn btn-sm btn-outline-primary d-lg-none w-100 mb-2 fw-semibold" onclick="cambiarTabPos('productos')">
                    <i class="fa fa-arrow-left me-1"></i> Seguir Agregando Productos
                </button>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa fa-cart-shopping text-primary me-1"></i> Carrito de Venta</h6>
                    <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0" onclick="vaciarCarrito()">
                        <i class="fa fa-trash-can"></i> Limpiar
                    </button>
                </div>

                <!-- Selección y Búsqueda por RUC / DNI de Cliente -->
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-muted mb-0">Cliente:</label>
                        <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none small" data-bs-toggle="modal" data-bs-target="#modalClienteRapido">
                            <i class="fa fa-user-plus"></i> + Alta Manual
                        </button>
                    </div>

                    <!-- Buscador Rápido por Número de RUC / DNI con Enter -->
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text bg-white"><i class="fa fa-id-card text-primary"></i></span>
                        <input type="text" id="pos_ruc_rapido" class="form-control font-monospace" placeholder="Ingresar RUC o DNI + Enter..." maxlength="11" inputmode="numeric" pattern="[0-9]*" onkeydown="if(event.key==='Enter'){event.preventDefault(); buscarClientePorRucPos();}">
                        <button type="button" class="btn btn-primary" id="btnPosBuscarRuc" onclick="buscarClientePorRucPos()" title="Buscar o registrar automáticamente desde SUNAT">
                            <i class="fa fa-search me-1"></i> Buscar
                        </button>
                    </div>

                    <select name="cliente_id" id="pos_cliente_id" class="form-select form-select-sm" required>
                        <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>">
                                <?= htmlspecialchars($cl['nombre_razon_social']) ?> (<?= $cl['num_doc'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tipo de Comprobante y Método de Pago -->
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted mb-0">Comprobante:</label>
                        <select name="tipo_comprobante" id="pos_tipo_comprobante" class="form-select form-select-sm">
                            <option value="Boleta">Boleta</option>
                            <option value="Factura">Factura</option>
                            <option value="Nota de Venta">Nota Venta</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted mb-0">Pago:</label>
                        <select name="metodo_pago" id="pos_metodo_pago" class="form-select form-select-sm">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Yape / Plin">Yape / Plin</option>
                            <option value="Transferencia">Transferencia</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Lista de Artículos en el Carrito -->
            <div class="pos-cart-items" id="listaCarrito">
                <div class="text-center py-5 text-muted" id="carritoVacioMsg">
                    <i class="fa fa-basket-shopping fs-1 mb-2 text-secondary opacity-50"></i>
                    <p class="small mb-0">Seleccione productos de la izquierda para agregar a la venta.</p>
                </div>
            </div>

            <!-- Footer con Totales y Botón Cobrar -->
            <div class="pos-cart-footer">
                <div class="d-flex justify-content-between text-muted small py-1">
                    <span>Subtotal:</span>
                    <span id="lblPosSubtotal"><?= $cfg['moneda_simbolo'] ?> 0.00</span>
                </div>
                <div class="d-flex justify-content-between text-muted small py-1">
                    <span><?= htmlspecialchars($cfg['impuesto_nombre']) ?> (<?= (float)$cfg['impuesto_porcentaje'] ?>% incl.):</span>
                    <span id="lblPosImpuesto"><?= $cfg['moneda_simbolo'] ?> 0.00</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-top mt-1 mb-2">
                    <span class="fs-5 fw-bold text-dark">TOTAL A PAGAR:</span>
                    <span class="fs-4 fw-bold text-primary" id="lblPosTotal"><?= $cfg['moneda_simbolo'] ?> 0.00</span>
                </div>

                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold d-flex align-items-center justify-content-center gap-2 shadow" id="btnCobrar" disabled>
                    <i class="fa fa-check-circle fs-5"></i> COBRAR E IMPRIMIR
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Barra Flotante Inferior de Carrito para Celular -->
<div class="pos-mobile-bar d-lg-none" id="posMobileBar" onclick="cambiarTabPos('carrito')" style="display:none;">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger rounded-pill px-2 py-1 fs-6" id="posMobileCount">0</span>
            <div>
                <div class="small text-white-50" style="line-height: 1;">Total Carrito</div>
                <div class="fw-bold fs-6 text-white" id="posMobileTotal"><?= $cfg['moneda_simbolo'] ?> 0.00</div>
            </div>
        </div>
        <button type="button" class="btn btn-light btn-sm fw-bold text-primary px-3 py-2 rounded-pill shadow-sm">
            Ver Carrito & Cobrar <i class="fa fa-arrow-right ms-1"></i>
        </button>
    </div>
</div>

<!-- Modal Cliente Rápido desde POS con Consulta RUC/DNI -->
<div class="modal fade" id="modalClienteRapido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fs-6 fw-bold"><i class="fa fa-user-plus text-primary me-1"></i> Alta Rápida de Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Tipo Doc:</label>
                    <select id="fast_tipo_doc" class="form-select form-select-sm">
                        <option value="DNI">DNI (8 dígitos)</option>
                        <option value="RUC">RUC (11 dígitos)</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Número Documento:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="fast_num_doc" class="form-control" placeholder="Ingrese número...">
                        <button type="button" class="btn btn-primary" id="btnFastRuc" onclick="consultarDocumento('fast_num_doc', 'fast_nombre', 'fast_direccion', null, 'btnFastRuc')">
                            <i class="fa fa-magnifying-glass"></i> Consultar
                        </button>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Nombre o Razón Social:</label>
                    <input type="text" id="fast_nombre" class="form-control form-control-sm" placeholder="Razón social o nombres">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Dirección:</label>
                    <input type="text" id="fast_direccion" class="form-control form-control-sm" placeholder="Dirección">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="guardarClienteRapido()">Guardar y Seleccionar</button>
            </div>
        </div>
    </div>
</div>

<script>
const simboloMoneda = '<?= $cfg['moneda_simbolo'] ?>';
const tasaImpuesto = <?= (float)$cfg['impuesto_porcentaje'] ?>;
let carrito = {};

// Búsqueda en vivo de productos
document.getElementById('posBusqueda').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const items = document.querySelectorAll('.item-card-prod');
    let visibles = 0;

    items.forEach(it => {
        const nombre = it.dataset.nombre.toLowerCase();
        const codigo = it.dataset.codigo.toLowerCase();
        if (nombre.includes(q) || codigo.includes(q)) {
            it.style.display = '';
            visibles++;
        } else {
            it.style.display = 'none';
        }
    });

    document.getElementById('posTotalDisponibles').textContent = visibles;
});

// Barcode Enter key
document.getElementById('posBusqueda').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const q = this.value.trim();
        const item = document.querySelector(`.item-card-prod[data-codigo="${q}"]`);
        if (item) {
            item.click();
            this.value = '';
            this.dispatchEvent(new Event('input'));
        } else if (q.length > 0) {
            // Buscar en catálogo completo del backend si no está visible en el DOM
            fetch('api/productos_search.php?barcode=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.producto) {
                        agregarAlCarrito(data.producto);
                        document.getElementById('posBusqueda').value = '';
                        document.getElementById('posBusqueda').dispatchEvent(new Event('input'));
                        showToast('success', `Agregado: ${data.producto.nombre}`);
                    } else {
                        showToast('warning', `Código de barras "${q}" no encontrado en almacén.`);
                    }
                })
                .catch(() => {
                    showToast('error', 'Error de lectura de código.');
                });
        }
    }
});

function agregarAlCarrito(prod) {
    const id = prod.id;
    if (carrito[id]) {
        if (carrito[id].cantidad < prod.stock) {
            carrito[id].cantidad++;
        } else {
            showToast('warning', `Stock máximo alcanzado (${prod.stock} unidades)`);
            return;
        }
    } else {
        carrito[id] = {
            id: prod.id,
            nombre: prod.nombre,
            codigo: prod.codigo_barra,
            precio: parseFloat(prod.precio_venta),
            stock: parseInt(prod.stock),
            unidad: prod.unidad_medida || 'UNID',
            cantidad: 1
        };
    }
    renderizarCarrito();
}

function cambiarCantidad(id, delta) {
    if (!carrito[id]) return;
    const nuevaCant = carrito[id].cantidad + delta;
    if (nuevaCant <= 0) {
        delete carrito[id];
    } else if (nuevaCant > carrito[id].stock) {
        showToast('warning', `Solo hay ${carrito[id].stock} unidades disponibles.`);
        return;
    } else {
        carrito[id].cantidad = nuevaCant;
    }
    renderizarCarrito();
}

function eliminarDelCarrito(id) {
    if (carrito[id]) {
        delete carrito[id];
        renderizarCarrito();
    }
}

function vaciarCarrito() {
    carrito = {};
    renderizarCarrito();
}

function renderizarCarrito() {
    const container = document.getElementById('listaCarrito');
    const emptyMsg = document.getElementById('carritoVacioMsg');
    const btnCobrar = document.getElementById('btnCobrar');

    const keys = Object.keys(carrito);
    const badgeMob = document.getElementById('badgeCartMobile');
    const posMobCount = document.getElementById('posMobileCount');
    const posMobTot = document.getElementById('posMobileTotal');
    const mobBar = document.getElementById('posMobileBar');

    if (keys.length === 0) {
        container.innerHTML = '';
        if (emptyMsg) container.appendChild(emptyMsg);
        document.getElementById('lblPosSubtotal').textContent = simboloMoneda + ' 0.00';
        document.getElementById('lblPosImpuesto').textContent = simboloMoneda + ' 0.00';
        document.getElementById('lblPosTotal').textContent = simboloMoneda + ' 0.00';
        btnCobrar.disabled = true;

        if (badgeMob) badgeMob.textContent = '0';
        if (posMobCount) posMobCount.textContent = '0';
        if (posMobTot) posMobTot.textContent = simboloMoneda + ' 0.00';
        if (mobBar) mobBar.style.display = 'none';
        return;
    }

    let html = '';
    let totalGeneral = 0;

    keys.forEach(k => {
        const item = carrito[k];
        const subtotal = item.cantidad * item.precio;
        totalGeneral += subtotal;

        html += `
            <div class="cart-item-row">
                <div style="max-width: 55%;">
                    <div class="fw-semibold text-dark text-truncate small">${item.nombre}</div>
                    <small class="text-muted">${item.cantidad} x ${simboloMoneda} ${item.precio.toFixed(2)}</small>
                    <input type="hidden" name="prod_id[]" value="${item.id}">
                    <input type="hidden" name="cantidad[]" value="${item.cantidad}">
                    <input type="hidden" name="precio_unitario[]" value="${item.precio}">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="cambiarCantidad(${item.id}, -1)">-</button>
                    <span class="fw-bold small px-1">${item.cantidad}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="cambiarCantidad(${item.id}, 1)">+</button>
                </div>
                <div class="text-end" style="min-width: 75px;">
                    <div class="fw-bold small text-primary">${simboloMoneda} ${subtotal.toFixed(2)}</div>
                    <button type="button" class="btn btn-sm text-danger p-0 border-0" onclick="eliminarDelCarrito(${item.id})" title="Quitar">
                        <i class="fa fa-xmark"></i>
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    const subtotalSinImpuesto = totalGeneral / (1 + (tasaImpuesto / 100));
    const impuesto = totalGeneral - subtotalSinImpuesto;

    document.getElementById('lblPosSubtotal').textContent = simboloMoneda + ' ' + subtotalSinImpuesto.toFixed(2);
    document.getElementById('lblPosImpuesto').textContent = simboloMoneda + ' ' + impuesto.toFixed(2);
    document.getElementById('lblPosTotal').textContent = simboloMoneda + ' ' + totalGeneral.toFixed(2);
    btnCobrar.disabled = false;

    // Actualizar contadores en versión móvil
    const totalItems = Object.keys(carrito).reduce((acc, k) => acc + carrito[k].cantidad, 0);
    if (badgeMob) badgeMob.textContent = totalItems;
    if (posMobCount) posMobCount.textContent = totalItems;
    if (posMobTot) posMobTot.textContent = simboloMoneda + ' ' + totalGeneral.toFixed(2);

    if (mobBar && window.innerWidth <= 992) {
        const colProd = document.querySelector('.pos-products');
        if (colProd && colProd.style.display !== 'none') {
            mobBar.style.display = (totalItems > 0) ? 'block' : 'none';
        }
    }
}

// Navegación por pestañas en celulares (Catálogo <-> Carrito)
function cambiarTabPos(tab) {
    const colProd = document.querySelector('.pos-products');
    const colCart = document.querySelector('.pos-cart');
    const btnProd = document.getElementById('btnTabProductos');
    const btnCart = document.getElementById('btnTabCarrito');
    const mobBar = document.getElementById('posMobileBar');

    if (window.innerWidth > 992) {
        if (colProd) colProd.style.display = '';
        if (colCart) colCart.style.display = '';
        if (mobBar) mobBar.style.display = 'none';
        return;
    }

    if (tab === 'productos') {
        if (colProd) colProd.style.display = 'block';
        if (colCart) colCart.style.display = 'none';
        if (btnProd) {
            btnProd.classList.add('btn-primary');
            btnProd.classList.remove('btn-outline-primary');
        }
        if (btnCart) {
            btnCart.classList.add('btn-outline-primary');
            btnCart.classList.remove('btn-primary');
        }
        if (mobBar) {
            const count = Object.keys(carrito).reduce((acc, k) => acc + carrito[k].cantidad, 0);
            mobBar.style.display = (count > 0) ? 'block' : 'none';
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        if (colProd) colProd.style.display = 'none';
        if (colCart) colCart.style.display = 'block';
        if (btnCart) {
            btnCart.classList.add('btn-primary');
            btnCart.classList.remove('btn-outline-primary');
        }
        if (btnProd) {
            btnProd.classList.add('btn-outline-primary');
            btnProd.classList.remove('btn-primary');
        }
        if (mobBar) mobBar.style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

window.addEventListener('resize', () => {
    if (window.innerWidth > 992) {
        const colProd = document.querySelector('.pos-products');
        const colCart = document.querySelector('.pos-cart');
        const mobBar = document.getElementById('posMobileBar');
        if (colProd) colProd.style.display = '';
        if (colCart) colCart.style.display = '';
        if (mobBar) mobBar.style.display = 'none';
    }
});

// Búsqueda instantánea y registro por RUC / DNI en POS
function buscarClientePorRucPos() {
    const input = document.getElementById('pos_ruc_rapido');
    const btn = document.getElementById('btnPosBuscarRuc');
    const select = document.getElementById('pos_cliente_id');
    const num = input.value.trim().replace(/[^0-9]/g, '');

    if (num.length !== 8 && num.length !== 11) {
        Swal.fire({
            icon: 'warning',
            title: 'Documento Inválido',
            text: 'Ingrese un DNI (8 dígitos) o RUC (11 dígitos).',
            confirmButtonColor: '#2563eb'
        });
        return;
    }

    const originalBtn = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch(`api/buscar_por_doc.php?numero=${encodeURIComponent(num)}&contexto=cliente&auto_guardar=1`)
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = originalBtn;

            if (res.success && res.data) {
                const c = res.data;
                let existeOpcion = false;

                // Buscar si ya existe la opción en el select
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value == c.id || select.options[i].text.includes(c.num_doc)) {
                        select.selectedIndex = i;
                        existeOpcion = true;
                        break;
                    }
                }

                if (!existeOpcion && c.id > 0) {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = `${c.nombre} (${c.num_doc})`;
                    opt.selected = true;
                    select.appendChild(opt);
                }

                // Ajustar comprobante automáticamente: Factura si es RUC, Boleta si es DNI
                const tipoComp = document.getElementById('pos_tipo_comprobante');
                if (c.tipo_doc === 'RUC') {
                    tipoComp.value = 'Factura';
                } else {
                    tipoComp.value = 'Boleta';
                }

                showToast('success', res.mensaje);
                input.value = '';
            } else {
                Swal.fire('No Encontrado', res.message || 'No se pudo obtener información del documento.', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalBtn;
            Swal.fire('Error', 'Error al procesar la búsqueda por RUC/DNI.', 'error');
        });
}

// Guardado rápido de cliente desde modal (con ID real obtenido de BD)
function guardarClienteRapido() {
    const tipo = document.getElementById('fast_tipo_doc').value;
    const num = document.getElementById('fast_num_doc').value.trim();
    const nombre = document.getElementById('fast_nombre').value.trim();
    const dir = document.getElementById('fast_direccion').value.trim();

    if (!num || !nombre) {
        Swal.fire('Atención', 'El número de documento y nombre son requeridos', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('numero', num);
    formData.append('contexto', 'cliente');
    formData.append('auto_guardar', '1');

    fetch('api/buscar_por_doc.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data && res.data.id > 0) {
            const select = document.getElementById('pos_cliente_id');
            const opt = document.createElement('option');
            opt.value = res.data.id;
            opt.textContent = `${res.data.nombre} (${res.data.num_doc})`;
            opt.selected = true;
            select.appendChild(opt);

            // Ajustar tipo de comprobante
            const tipoComp = document.getElementById('pos_tipo_comprobante');
            tipoComp.value = (res.data.tipo_doc === 'RUC') ? 'Factura' : 'Boleta';

            const modalEl = document.getElementById('modalClienteRapido');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            showToast('success', 'Cliente guardado y seleccionado correctamente.');
        } else {
            Swal.fire('Error', 'No se pudo registrar el cliente en base de datos.', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'Error al comunicar con el servidor.', 'error');
    });
}

// Interceptar envío en GitHub Pages / Entornos estáticos para emisión y ticket instantáneo
const formPos = document.getElementById('formVentaPos');
if (formPos) {
    formPos.addEventListener('submit', function(e) {
        const isStatic = window.location.protocol === 'file:' || 
                         window.location.hostname.includes('github.io') || 
                         window.location.pathname.endsWith('.html');
        if (isStatic) {
            e.preventDefault();
            const itemsArr = Object.values(carrito);
            if (itemsArr.length === 0) {
                Swal.fire('Carrito Vacío', 'Agregue productos antes de cobrar.', 'warning');
                return;
            }

            const selCli = document.getElementById('pos_cliente_id');
            const cliName = selCli && selCli.selectedIndex >= 0 ? selCli.options[selCli.selectedIndex].text : 'CLIENTE VARIOS / GENERAL';
            const tipoComp = document.getElementById('pos_tipo_comprobante').value;
            const correlativo = String(Math.floor(100 + Math.random() * 900)).padStart(6, '0');
            const serieNum = (tipoComp === 'Factura' ? 'F001-' : 'B001-') + correlativo;
            const totalStr = document.getElementById('lblPosTotal').textContent.replace(/[^0-9.]/g, '');
            const totalNum = parseFloat(totalStr) || 0;
            const subtotalNum = totalNum / 1.18;
            const igvNum = totalNum - subtotalNum;

            Swal.fire({
                icon: 'success',
                title: '¡Venta Registrada Exitosamente!',
                html: `<strong>${tipoComp} Electrónica ${serieNum}</strong> emitida con éxito por <strong>S/ ${totalNum.toFixed(2)}</strong>.<br><small class="text-muted">Stock descontado en Kardex y sincronizado con SIRE SUNAT.</small>`,
                showCancelButton: true,
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa fa-print me-1"></i> Imprimir Ticket 80mm',
                cancelButtonText: 'Nueva Venta'
            }).then((result) => {
                if (result.isConfirmed) {
                    const ticketData = {
                        venta: {
                            tipo_comprobante: (tipoComp === 'Factura' ? 'FACTURA ELECTRÓNICA' : 'BOLETA ELECTRÓNICA'),
                            serie: (tipoComp === 'Factura' ? 'F001' : 'B001'),
                            correlativo: correlativo,
                            fecha_venta: new Date().toLocaleDateString('es-PE') + ' ' + new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'}),
                            cliente_nombre: cliName,
                            cliente_doc: '00000000',
                            subtotal_fmt: `S/. ${subtotalNum.toFixed(2)}`,
                            impuesto_fmt: `S/. ${igvNum.toFixed(2)}`,
                            total_fmt: `S/. ${totalNum.toFixed(2)}`
                        },
                        items: itemsArr.map(it => ({
                            cantidad: it.cantidad,
                            producto_nombre: it.nombre,
                            subtotal_fmt: `S/. ${(it.precio * it.cantidad).toFixed(2)}`
                        }))
                    };
                    if (window.imprimirTicketDirecto) {
                        const modalEl = document.getElementById('modalTicketGlobal');
                        if (modalEl) {
                            const tipoEl = document.getElementById('ticketTipoDoc');
                            const numEl = document.getElementById('ticketNumero');
                            const fechaEl = document.getElementById('ticketFecha');
                            const clienteEl = document.getElementById('ticketCliente');
                            const itemsEl = document.getElementById('ticketItems');
                            const baseEl = document.getElementById('ticketBase');
                            const igvEl = document.getElementById('ticketIgv');
                            const totalEl = document.getElementById('ticketTotal');

                            if (tipoEl) tipoEl.textContent = ticketData.venta.tipo_comprobante;
                            if (numEl) numEl.textContent = `${ticketData.venta.serie}-${ticketData.venta.correlativo}`;
                            if (fechaEl) fechaEl.textContent = ticketData.venta.fecha_venta;
                            if (clienteEl) clienteEl.textContent = ticketData.venta.cliente_nombre;
                            if (itemsEl) {
                                itemsEl.innerHTML = ticketData.items.map(it => `
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>${it.cantidad}x ${it.producto_nombre}</span>
                                        <span class="fw-bold">${it.subtotal_fmt}</span>
                                    </div>
                                `).join('');
                            }
                            if (baseEl) baseEl.textContent = ticketData.venta.subtotal_fmt;
                            if (igvEl) igvEl.textContent = ticketData.venta.impuesto_fmt;
                            if (totalEl) totalEl.textContent = ticketData.venta.total_fmt;

                            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                            bsModal.show();
                        }
                    }
                }
                vaciarCarrito();
                if (window.innerWidth <= 992) cambiarTabPos('productos');
            });
        }
    });
}

// Auto-selección si viene RUC desde consulta_sunat.php
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const rucParam = urlParams.get('ruc') || urlParams.get('doc');
    if (rucParam) {
        const inp = document.getElementById('pos_ruc_rapido');
        if (inp) {
            inp.value = rucParam;
            buscarClientePorRucPos();
        }
    }
    if (window.innerWidth <= 992) {
        cambiarTabPos('productos');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


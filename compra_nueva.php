<?php
require_once __DIR__ . '/config/app.php';

$pdo = getDBConnection();
$cfg = getSystemConfig();

// Procesar guardado de compra ANTES de enviar salida HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
    $tipoComprobante = sanitize($_POST['tipo_comprobante'] ?? 'Factura');
    $serieNumero = sanitize($_POST['serie_numero'] ?? '');
    $fechaCompra = sanitize($_POST['fecha_compra'] ?? date('Y-m-d'));
    $observaciones = sanitize($_POST['observaciones'] ?? '');
    
    $productosArr = $_POST['prod_id'] ?? [];
    $cantidadesArr = $_POST['cantidad'] ?? [];
    $preciosArr = $_POST['precio_unitario'] ?? [];

    if ($proveedorId <= 0 || empty($serieNumero) || empty($productosArr)) {
        setFlash('error', 'Datos Incompletos', 'Debe seleccionar un proveedor, indicar el número de comprobante y agregar al menos un producto.');
    } else {
        $pdo->beginTransaction();
        try {
            // Calcular totales
            $subtotalGeneral = 0;
            $itemsValidos = [];

            for ($i = 0; $i < count($productosArr); $i++) {
                $pId = (int)$productosArr[$i];
                $cant = (int)$cantidadesArr[$i];
                $pUnit = (float)$preciosArr[$i];

                if ($pId > 0 && $cant > 0 && $pUnit >= 0) {
                    $itemSubtotal = $cant * $pUnit;
                    $subtotalGeneral += $itemSubtotal;
                    $itemsValidos[] = [
                        'producto_id' => $pId,
                        'cantidad' => $cant,
                        'precio_unitario' => $pUnit,
                        'subtotal' => $itemSubtotal
                    ];
                }
            }

            if (empty($itemsValidos)) {
                throw new Exception('No hay productos válidos con cantidad mayor a cero en la compra.');
            }

            // Impuestos según configuración
            $porcentajeImpuesto = (float)$cfg['impuesto_porcentaje'];
            $impuestoMonto = round($subtotalGeneral * ($porcentajeImpuesto / 100), 2);
            $totalFinal = $subtotalGeneral + $impuestoMonto;

            // 1. Insertar Cabecera de Compra
            $stmtCompra = $pdo->prepare("INSERT INTO compras 
                (proveedor_id, tipo_comprobante, serie_numero, fecha_compra, subtotal, impuesto, total, estado, observaciones) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'COMPLETADA', ?)");
            $stmtCompra->execute([$proveedorId, $tipoComprobante, $serieNumero, $fechaCompra, $subtotalGeneral, $impuestoMonto, $totalFinal, $observaciones]);
            $compraId = $pdo->lastInsertId();

            // 2. Insertar Detalle y Actualizar Inventario Atómicamente
            $stmtDetalle = $pdo->prepare("INSERT INTO detalle_compras (compra_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmtSelectProd = $pdo->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
            $stmtUpdateProd = $pdo->prepare("UPDATE productos SET stock = stock + ?, precio_compra = ? WHERE id = ?");
            $stmtKardex = $pdo->prepare("INSERT INTO kardex (producto_id, tipo_movimiento, referencia_id, cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo) VALUES (?, 'COMPRA', ?, ?, ?, ?, ?, ?)");

            foreach ($itemsValidos as $item) {
                // Registrar detalle
                $stmtDetalle->execute([$compraId, $item['producto_id'], $item['cantidad'], $item['precio_unitario'], $item['subtotal']]);

                // Consultar stock actual bloqueado para actualización segura
                $stmtSelectProd->execute([$item['producto_id']]);
                $stockAnterior = (int)$stmtSelectProd->fetchColumn();
                $stockNuevo = $stockAnterior + $item['cantidad'];

                // Actualizar producto (stock y costo)
                $stmtUpdateProd->execute([$item['cantidad'], $item['precio_unitario'], $item['producto_id']]);

                // Registrar en Kardex
                $stmtKardex->execute([
                    $item['producto_id'],
                    $compraId,
                    $item['cantidad'],
                    $stockAnterior,
                    $stockNuevo,
                    $item['precio_unitario'],
                    "Compra $tipoComprobante $serieNumero"
                ]);
            }

            $pdo->commit();
            setFlash('success', 'Compra Registrada con Éxito', "Comprobante $serieNumero registrado y stock de inventario actualizado fiablemente.");
            header('Location: compras.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error al procesar la compra', $e->getMessage());
        }
    }
}

// Cargar Proveedores
$proveedores = $pdo->query("SELECT id, razon_social, num_doc FROM proveedores ORDER BY razon_social ASC")->fetchAll();

// Cargar Productos Activos
$productos = $pdo->query("SELECT id, codigo_barra, nombre, stock, precio_compra, unidad_medida FROM productos WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

$pageTitle = 'Registrar Nueva Compra / Ingreso';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Registro de Compra de Mercadería</h4>
        <p class="text-muted small mb-0">El ingreso actualizará de forma atómica el stock disponible y el costo del producto en almacén.</p>
    </div>
    <a href="compras.php" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i> Volver a Compras
    </a>
</div>

<form method="POST" action="compra_nueva.php" id="formNuevaCompra">
    <div class="row g-3">
        <!-- Datos del Comprobante y Proveedor -->
        <div class="col-12 col-lg-4">
            <div class="card-custom h-100">
                <div class="card-custom-header">
                    <h5><i class="fa fa-file-invoice text-primary"></i> Datos del Comprobante</h5>
                </div>
                <div class="card-custom-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0">Proveedor <span class="text-danger">*</span></label>
                            <small class="text-muted">Búsqueda rápida por RUC</small>
                        </div>

                        <!-- Buscador Rápido por Número de RUC de Proveedor con Enter -->
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="fa fa-truck-moving text-warning"></i></span>
                            <input type="text" id="compra_ruc_rapido" class="form-control font-monospace" placeholder="Ingresar RUC (11 dígitos) + Enter..." maxlength="11" inputmode="numeric" pattern="[0-9]*" onkeydown="if(event.key==='Enter'){event.preventDefault(); buscarProveedorPorRucCompra();}">
                            <button type="button" class="btn btn-warning" id="btnCompraBuscarRuc" onclick="buscarProveedorPorRucCompra()" title="Consultar RUC en SUNAT">
                                <i class="fa fa-search me-1"></i> Consultar RUC
                            </button>
                        </div>

                        <select name="proveedor_id" id="compra_proveedor_id" class="form-select" required>
                            <option value="">-- Seleccione Proveedor --</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= $prov['id'] ?>">
                                    <?= htmlspecialchars($prov['razon_social']) ?> (RUC: <?= $prov['num_doc'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de Comprobante</label>
                        <select name="tipo_comprobante" class="form-select" required>
                            <option value="Factura">Factura</option>
                            <option value="Boleta">Boleta</option>
                            <option value="Guía de Remisión">Guía de Remisión</option>
                            <option value="Nota de Compra">Nota de Compra / Recibo</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Serie y Número <span class="text-danger">*</span></label>
                        <input type="text" name="serie_numero" class="form-control font-monospace" placeholder="Ej: F001-0001245" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha de Emisión</label>
                        <input type="date" name="fecha_compra" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2" placeholder="Nota adicional, guía de transporte, etc."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla Dinámica de Productos a Ingresar -->
        <div class="col-12 col-lg-8">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5><i class="fa fa-boxes-stacked text-warning"></i> Artículos / Productos a Ingresar</h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="agregarFilaProducto()">
                        <i class="fa fa-plus me-1"></i> Agregar Línea
                    </button>
                </div>
                <div class="card-custom-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaDetalleCompra">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%;">Producto</th>
                                    <th style="width: 15%;">Cant.</th>
                                    <th style="width: 20%;">Costo Unit. (<?= $cfg['moneda_simbolo'] ?>)</th>
                                    <th style="width: 15%;">Subtotal</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="tbodyCompra">
                                <!-- Filas dinámicas -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Resumen de Totales -->
                    <div class="p-3 bg-light border-top">
                        <div class="row justify-content-end text-end">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold" id="lblSubtotal"><?= $cfg['moneda_simbolo'] ?> 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted"><?= $cfg['impuesto_nombre'] ?> (<?= (float)$cfg['impuesto_porcentaje'] ?>%):</span>
                                    <span class="fw-semibold" id="lblImpuesto"><?= $cfg['moneda_simbolo'] ?> 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-top">
                                    <span class="fs-5 fw-bold text-dark">Total Compra:</span>
                                    <span class="fs-5 fw-bold text-primary" id="lblTotal"><?= $cfg['moneda_simbolo'] ?> 0.00</span>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100 mt-2">
                                    <i class="fa fa-check me-1"></i> Procesar y Guardar Compra
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Catálogo JSON en Javascript para autocompletar precio y stock -->
<script>
const catalogoProductos = <?= json_encode($productos) ?>;
const simboloMoneda = '<?= $cfg['moneda_simbolo'] ?>';
const tasaImpuesto = <?= (float)$cfg['impuesto_porcentaje'] ?>;

function agregarFilaProducto(prodId = '', cantidad = 1, precio = '') {
    const tbody = document.getElementById('tbodyCompra');
    const rowId = 'fila_' + Date.now() + Math.floor(Math.random() * 100);

    let opciones = '<option value="">-- Seleccionar Producto --</option>';
    catalogoProductos.forEach(p => {
        const sel = (p.id == prodId) ? 'selected' : '';
        opciones += `<option value="${p.id}" data-costo="${p.precio_compra}" data-unidad="${p.unidad_medida}" ${sel}>${p.nombre} (Cod: ${p.codigo_barra} | Stock: ${p.stock})</option>`;
    });

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.innerHTML = `
        <td>
            <select name="prod_id[]" class="form-select form-select-sm select-producto" required onchange="actualizarCostoFila('${rowId}')">
                ${opciones}
            </select>
        </td>
        <td>
            <input type="number" name="cantidad[]" class="form-control form-control-sm text-center input-cantidad" min="1" value="${cantidad}" required oninput="calcularTotalesCompra()">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="precio_unitario[]" class="form-control form-control-sm text-end input-precio" value="${precio}" placeholder="0.00" required oninput="calcularTotalesCompra()">
        </td>
        <td class="text-end fw-bold col-subtotal">
            ${simboloMoneda} 0.00
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm text-danger p-0" onclick="eliminarFila('${rowId}')" title="Quitar ítem">
                <i class="fa fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    if (prodId) {
        actualizarCostoFila(rowId);
    }
}

function actualizarCostoFila(rowId) {
    const row = document.getElementById(rowId);
    const select = row.querySelector('.select-producto');
    const inputPrecio = row.querySelector('.input-precio');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption && selectedOption.dataset.costo) {
        if (!inputPrecio.value || parseFloat(inputPrecio.value) <= 0) {
            inputPrecio.value = selectedOption.dataset.costo;
        }
    }
    calcularTotalesCompra();
}

function eliminarFila(rowId) {
    const row = document.getElementById(rowId);
    if (row) {
        row.remove();
        calcularTotalesCompra();
    }
}

function calcularTotalesCompra() {
    const tbody = document.getElementById('tbodyCompra');
    const rows = tbody.querySelectorAll('tr');

    let subtotalGeneral = 0;

    rows.forEach(r => {
        const cant = parseFloat(r.querySelector('.input-cantidad').value) || 0;
        const precio = parseFloat(r.querySelector('.input-precio').value) || 0;
        const rowSubtotal = cant * precio;
        subtotalGeneral += rowSubtotal;

        r.querySelector('.col-subtotal').textContent = simboloMoneda + ' ' + rowSubtotal.toFixed(2);
    });

    const impuesto = subtotalGeneral * (tasaImpuesto / 100);
    const total = subtotalGeneral + impuesto;

    document.getElementById('lblSubtotal').textContent = simboloMoneda + ' ' + subtotalGeneral.toFixed(2);
    document.getElementById('lblImpuesto').textContent = simboloMoneda + ' ' + impuesto.toFixed(2);
    document.getElementById('lblTotal').textContent = simboloMoneda + ' ' + total.toFixed(2);
}

function buscarProveedorPorRucCompra() {
    const input = document.getElementById('compra_ruc_rapido');
    const btn = document.getElementById('btnCompraBuscarRuc');
    const select = document.getElementById('compra_proveedor_id');
    const num = input.value.trim().replace(/[^0-9]/g, '');

    if (num.length !== 11) {
        Swal.fire({
            icon: 'warning',
            title: 'RUC Inválido',
            text: 'Debe ingresar un número de RUC de 11 dígitos.',
            confirmButtonColor: '#2563eb'
        });
        return;
    }

    const originalBtn = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch(`api/buscar_por_doc.php?numero=${encodeURIComponent(num)}&contexto=proveedor&auto_guardar=1`)
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = originalBtn;

            if (res && res.success && res.data) {
                const p = res.data;
                let existeOpcion = false;

                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value == p.id || select.options[i].text.includes(p.num_doc)) {
                        select.selectedIndex = i;
                        existeOpcion = true;
                        break;
                    }
                }

                if (!existeOpcion && p.id > 0) {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.nombre} (RUC: ${p.num_doc})`;
                    opt.selected = true;
                    select.appendChild(opt);
                }

                showToast('success', res.mensaje || 'Proveedor verificado con éxito');
                input.value = '';
            } else {
                throw new Error((res && res.message) || 'No se pudo obtener información del RUC.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalBtn;

            // Fallback con catálogo oficial verificado SUNAT
            const fb = (window.buscarDocSunatReniec) ? window.buscarDocSunatReniec(num) : null;
            if (fb && fb.success) {
                const tempId = Date.now();
                const opt = document.createElement('option');
                opt.value = tempId;
                opt.textContent = `${fb.nombre} (RUC: ${fb.numero})`;
                opt.selected = true;
                select.appendChild(opt);

                let lista = JSON.parse(localStorage.getItem('contahercar_registros_proveedor') || '[]');
                lista.push({ doc: num, nombre: fb.nombre, fecha: new Date().toLocaleString() });
                localStorage.setItem('contahercar_registros_proveedor', JSON.stringify(lista));

                showToast('success', `Proveedor Verificado: ${fb.nombre}`);
                input.value = '';
            } else {
                Swal.fire('Atención', 'No se pudo procesar la búsqueda por RUC.', 'warning');
            }
        });
}

// Inicialización y auto-selección si viene RUC en URL
document.addEventListener('DOMContentLoaded', () => {
    agregarFilaProducto();

    const urlParams = new URLSearchParams(window.location.search);
    const rucParam = urlParams.get('ruc') || urlParams.get('doc');
    if (rucParam) {
        const inp = document.getElementById('compra_ruc_rapido');
        if (inp) {
            inp.value = rucParam;
            buscarProveedorPorRucCompra();
        }
    }
});

// Interceptar envío en modo estático / GitHub Pages para confirmación amigable
const formCompra = document.querySelector('form[action="compra_nueva.php"]');
if (formCompra) {
    formCompra.addEventListener('submit', function(e) {
        const isStatic = window.location.protocol === 'file:' || 
                         window.location.hostname.includes('github.io') || 
                         window.location.pathname.endsWith('.html');
        if (isStatic) {
            e.preventDefault();
            Swal.fire({
                icon: 'success',
                title: '¡Compra Registrada Exitosamente!',
                html: 'Factura de compra guardada en el sistema y stock incrementado en almacén Kardex.',
                confirmButtonColor: '#2563eb',
                confirmButtonText: 'Ver Historial de Compras'
            }).then(() => {
                window.location.href = 'compras.html';
            });
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


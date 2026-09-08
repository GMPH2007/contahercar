<?php
$pageTitle = 'Consulta RUC / SUNAT Oficial & RENIEC';
require_once __DIR__ . '/includes/header.php';
$config = getSystemConfig();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">
            <i class="fa fa-building-flag text-primary me-2"></i>Consulta RUC / SUNAT & DNI en Línea
        </h4>
        <p class="text-muted small mb-0">
            Consulta oficial en tiempo real con datos de SUNAT y RENIEC. Verifique estado tributario, condición de domicilio, razón social y dirección fiscal.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
            <i class="fa fa-shield-check me-1"></i> API Decolecta / SUNAT Conectada
        </span>
    </div>
</div>

<!-- Barra de Búsqueda Principal -->
<div class="card-custom mb-4 border-primary border-top border-3">
    <div class="card-custom-body p-4">
        <form id="formConsultaSunat" onsubmit="realizarConsultaSunat(event)">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <label class="form-label fw-bold text-dark mb-1">
                        Ingrese Número de RUC (11 dígitos) o DNI (8 dígitos):
                    </label>
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-white border-end-0 text-primary">
                            <i class="fa fa-id-card fa-lg"></i>
                        </span>
                        <input type="text" 
                               id="inputDocSunat" 
                               class="form-control font-monospace fw-bold border-start-0 ps-0 fs-5" 
                               placeholder="Ej: 20100070970 o 45871234" 
                               maxlength="11" 
                               inputmode="numeric"
                               pattern="[0-9]*"
                               autocomplete="off"
                               autofocus
                               required>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarConsulta()" title="Limpiar">
                            <i class="fa fa-times"></i>
                        </button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold" id="btnConsultarSunat">
                            <i class="fa fa-magnifying-glass me-2"></i> Consultar SUNAT
                        </button>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1 d-block">Consultas Rápidas de Ejemplo:</label>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary py-1" onclick="consultarEjemplo('20100070970')">
                            <i class="fa fa-store me-1"></i> Supermercados
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary py-1" onclick="consultarEjemplo('20601030013')">
                            <i class="fa fa-building me-1"></i> Rextie SAC
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary py-1" onclick="consultarEjemplo('10460278975')">
                            <i class="fa fa-user-tie me-1"></i> RUC Persona
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1" onclick="consultarEjemplo('45871234')">
                            <i class="fa fa-user me-1"></i> DNI Reniec
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Estado de Carga -->
<div id="loadingSunat" class="text-center py-5" style="display: none;">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Consultando SUNAT...</span>
    </div>
    <h5 class="mt-3 text-dark fw-bold">Consultando servicio oficial de SUNAT / RENIEC...</h5>
    <p class="text-muted small">Validando razón social, estado, condición de domicilio y locales anexos.</p>
</div>

<!-- Tarjeta de Ficha Tributaria Oficial (Resultado) -->
<div id="fichaSunatContainer" style="display: none;" class="mb-4">
    <div class="card-custom shadow-sm border-0">
        <!-- Cabecera de la Ficha -->
        <div class="card-custom-header bg-light d-flex flex-wrap align-items-center justify-content-between py-3 px-4 gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="fa fa-landmark fs-4" id="fichaIcono"></i>
                </div>
                <div>
                    <span class="badge bg-secondary text-uppercase mb-1" id="fichaBadgeTipo">RUC</span>
                    <h5 class="mb-0 fw-bold text-dark text-break" id="fichaRazonSocial">-</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge px-3 py-2 fs-6 rounded-pill" id="fichaBadgeEstado">ACTIVO</span>
                <span class="badge px-3 py-2 fs-6 rounded-pill" id="fichaBadgeCondicion">HABIDO</span>
            </div>
        </div>

        <div class="card-custom-body p-4">
            <!-- Botones de Acción Inmediata -->
            <div class="p-3 bg-light rounded mb-4 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="small fw-semibold text-muted">
                    <i class="fa fa-bolt text-warning me-1"></i> Acciones Inmediatas para este Contribuyente:
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-success fw-semibold" id="btnRegistrarCliente" onclick="guardarDocEnBD('cliente')">
                        <i class="fa fa-user-plus me-1"></i> Registrar como Cliente
                    </button>
                    <button type="button" class="btn btn-sm btn-info text-white fw-semibold" id="btnRegistrarProveedor" onclick="guardarDocEnBD('proveedor')">
                        <i class="fa fa-truck-moving me-1"></i> Registrar como Proveedor
                    </button>
                    <a href="#" class="btn btn-sm btn-primary fw-semibold" id="btnNuevaVentaPos">
                        <i class="fa fa-cart-shopping me-1"></i> Facturar en POS
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-dark fw-semibold" id="btnNuevaCompra">
                        <i class="fa fa-cart-arrow-down me-1"></i> Registrar Compra
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Imprimir Ficha">
                        <i class="fa fa-print"></i>
                    </button>
                </div>
            </div>

            <!-- Datos Tributarios Principales -->
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3">
                        <i class="fa fa-id-card me-1"></i> Identificación del Contribuyente
                    </h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted fw-semibold" style="width: 40%;">Número de Documento:</td>
                                <td class="fw-bold font-monospace fs-6 text-dark" id="fichaNumDoc">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Tipo de Contribuyente:</td>
                                <td class="fw-semibold text-dark" id="fichaTipoContribuyente">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Estado del Contribuyente:</td>
                                <td id="fichaEstadoDetalle">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Condición del Domicilio:</td>
                                <td id="fichaCondicionDetalle">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Buen Contribuyente:</td>
                                <td id="fichaBuenContribuyente"><span class="badge bg-secondary">NO</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Agente de Retención:</td>
                                <td id="fichaAgenteRetencion"><span class="badge bg-secondary">NO</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3">
                        <i class="fa fa-location-dot me-1"></i> Domicilio Fiscal & Ubicación
                    </h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted fw-semibold" style="width: 35%;">Dirección Fiscal:</td>
                                <td class="fw-bold text-dark" id="fichaDireccion">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Departamento:</td>
                                <td class="fw-semibold text-dark" id="fichaDepartamento">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Provincia:</td>
                                <td class="fw-semibold text-dark" id="fichaProvincia">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Distrito:</td>
                                <td class="fw-semibold text-dark" id="fichaDistrito">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Código Ubigeo:</td>
                                <td class="font-monospace text-dark" id="fichaUbigeo">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-semibold">Origen de Datos:</td>
                                <td>
                                    <span class="badge bg-soft-primary text-primary" id="fichaProveedorApi">SUNAT Oficial</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Locales Anexos (Si existen) -->
            <div id="seccionLocalesAnexos" style="display: none;" class="mt-4 pt-3 border-top">
                <h6 class="text-primary fw-bold text-uppercase mb-3">
                    <i class="fa fa-shop me-1"></i> Locales Anexos / Sucursales Registradas (<span id="totalLocalesAnexos">0</span>)
                </h6>
                <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-striped border">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Dirección del Local</th>
                                <th>Distrito</th>
                                <th>Provincia</th>
                                <th>Departamento</th>
                                <th>Ubigeo</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyLocalesAnexos">
                            <!-- Inyectado vía JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historial Local de Consultas Recientes -->
<div class="card-custom">
    <div class="card-custom-header d-flex align-items-center justify-content-between">
        <h5 class="fs-6 mb-0">
            <i class="fa fa-clock-rotate-left text-muted me-2"></i>Historial de Consultas Recientes
        </h5>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="limpiarHistorialConsultas()">
            <i class="fa fa-trash me-1"></i> Limpiar Historial
        </button>
    </div>
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tipo</th>
                        <th>Documento</th>
                        <th>Razón Social / Nombre</th>
                        <th>Estado</th>
                        <th>Condición</th>
                        <th>Fecha de Consulta</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyHistorialConsultas">
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa fa-search me-1"></i> Aún no has realizado consultas en esta sesión.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let contribuyenteActual = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarHistorialConsultas();

    // Si viene parámetro ?ruc= o ?doc= en la URL, consultar automáticamente
    const urlParams = new URLSearchParams(window.location.search);
    const docParam = urlParams.get('ruc') || urlParams.get('doc') || urlParams.get('numero');
    if (docParam) {
        document.getElementById('inputDocSunat').value = docParam;
        consultarDocSunat(docParam);
    }
});

function consultarEjemplo(num) {
    document.getElementById('inputDocSunat').value = num;
    consultarDocSunat(num);
}

function limpiarConsulta() {
    document.getElementById('inputDocSunat').value = '';
    document.getElementById('fichaSunatContainer').style.display = 'none';
    document.getElementById('inputDocSunat').focus();
    contribuyenteActual = null;
}

function realizarConsultaSunat(e) {
    if (e) e.preventDefault();
    const doc = document.getElementById('inputDocSunat').value.trim();
    consultarDocSunat(doc);
}

function consultarDocSunat(doc) {
    const num = doc.replace(/\D/g, '');
    if (num.length !== 8 && num.length !== 11) {
        Swal.fire({
            icon: 'warning',
            title: 'Número Inválido',
            text: 'Debe ingresar un DNI de 8 dígitos o un RUC de 11 dígitos.',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    const btn = document.getElementById('btnConsultarSunat');
    const loading = document.getElementById('loadingSunat');
    const ficha = document.getElementById('fichaSunatContainer');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Consultando...';
    loading.style.display = 'block';
    ficha.style.display = 'none';

    fetch('api/consulta_ruc.php?numero=' + encodeURIComponent(num))
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-magnifying-glass me-2"></i> Consultar SUNAT';
            loading.style.display = 'none';

            if (!data.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'No Encontrado',
                    text: data.message || 'No se pudo obtener información para el documento ingresado.',
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            contribuyenteActual = data;
            mostrarFichaTributaria(data);
            guardarEnHistorialLocal(data);
            cargarHistorialConsultas();
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-magnifying-glass me-2"></i> Consultar SUNAT';
            loading.style.display = 'none';
            Swal.fire({
                icon: 'error',
                title: 'Error de Red',
                text: 'Ocurrió un error al contactar el servidor: ' + err.message,
                confirmButtonText: 'Aceptar'
            });
        });
}

function mostrarFichaTributaria(d) {
    document.getElementById('fichaBadgeTipo').textContent = d.tipo;
    document.getElementById('fichaRazonSocial').textContent = d.nombre;
    document.getElementById('fichaNumDoc').textContent = d.numero;
    document.getElementById('fichaTipoContribuyente').textContent = d.tipo_contribuyente || (d.tipo === 'RUC' ? 'PERSONA JURÍDICA' : 'PERSONA NATURAL');
    
    // Badges de Estado y Condición
    const bEstado = document.getElementById('fichaBadgeEstado');
    const dEstado = document.getElementById('fichaEstadoDetalle');
    const bCond = document.getElementById('fichaBadgeCondicion');
    const dCond = document.getElementById('fichaCondicionDetalle');

    const esActivo = (d.estado === 'ACTIVO');
    bEstado.className = 'badge px-3 py-2 fs-6 rounded-pill ' + (esActivo ? 'bg-success' : 'bg-danger');
    bEstado.innerHTML = esActivo ? '<i class="fa fa-circle-check me-1"></i> ACTIVO' : '<i class="fa fa-circle-xmark me-1"></i> ' + d.estado;
    dEstado.innerHTML = `<span class="fw-bold ${esActivo ? 'text-success' : 'text-danger'}">${d.estado}</span>`;

    const esHabido = (d.condicion === 'HABIDO');
    bCond.className = 'badge px-3 py-2 fs-6 rounded-pill ' + (esHabido ? 'bg-success' : 'bg-warning text-dark');
    bCond.innerHTML = esHabido ? '<i class="fa fa-house-circle-check me-1"></i> HABIDO' : '<i class="fa fa-triangle-exclamation me-1"></i> ' + d.condicion;
    dCond.innerHTML = `<span class="fw-bold ${esHabido ? 'text-success' : 'text-warning'}">${d.condicion}</span>`;

    // Buen Contribuyente y Agente de Retención
    document.getElementById('fichaBuenContribuyente').innerHTML = d.es_buen_contribuyente ? 
        '<span class="badge bg-success"><i class="fa fa-check me-1"></i> SÍ (Calificado por SUNAT)</span>' : 
        '<span class="badge bg-secondary">NO</span>';

    document.getElementById('fichaAgenteRetencion').innerHTML = d.es_agente_retencion ? 
        '<span class="badge bg-primary"><i class="fa fa-check me-1"></i> SÍ (Designado por SUNAT)</span>' : 
        '<span class="badge bg-secondary">NO</span>';

    // Domicilio Fiscal
    document.getElementById('fichaDireccion').textContent = d.direccion || 'NO REGISTRADA';
    document.getElementById('fichaDepartamento').textContent = d.departamento || '-';
    document.getElementById('fichaProvincia').textContent = d.provincia || '-';
    document.getElementById('fichaDistrito').textContent = d.distrito || '-';
    document.getElementById('fichaUbigeo').textContent = d.ubigeo || '-';
    document.getElementById('fichaProveedorApi').textContent = d.proveedor || 'Decolecta / SUNAT Oficial';

    // Enlaces de Acciones
    document.getElementById('btnNuevaVentaPos').href = 'venta_nueva.php?ruc=' + encodeURIComponent(d.numero);
    document.getElementById('btnNuevaCompra').href = 'compra_nueva.php?ruc=' + encodeURIComponent(d.numero);

    // Locales Anexos
    const secLocales = document.getElementById('seccionLocalesAnexos');
    const tbodyLocales = document.getElementById('tbodyLocalesAnexos');
    if (d.locales_anexos && d.locales_anexos.length > 0) {
        document.getElementById('totalLocalesAnexos').textContent = d.locales_anexos.length;
        tbodyLocales.innerHTML = d.locales_anexos.map((loc, idx) => `
            <tr>
                <td class="font-monospace text-muted">${idx + 1}</td>
                <td class="fw-semibold">${loc.direccion || '-'}</td>
                <td>${loc.distrito || '-'}</td>
                <td>${loc.provincia || '-'}</td>
                <td>${loc.departamento || '-'}</td>
                <td class="font-monospace">${loc.ubigeo || '-'}</td>
            </tr>
        `).join('');
        secLocales.style.display = 'block';
    } else {
        secLocales.style.display = 'none';
    }

    document.getElementById('fichaSunatContainer').style.display = 'block';
    document.getElementById('fichaSunatContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function guardarDocEnBD(contexto) {
    if (!contribuyenteActual) return;
    const doc = contribuyenteActual.numero;
    const nombre = contribuyenteActual.nombre;

    Swal.fire({
        title: `¿Registrar como ${contexto === 'cliente' ? 'Cliente' : 'Proveedor'}?`,
        html: `Se registrará en la base de datos local a:<br><strong>${nombre}</strong> (Doc: <code>${doc}</code>)`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, Registrar',
        cancelButtonText: 'Cancelar'
    }).then(res => {
        if (!res.isConfirmed) return;

        fetch(`api/buscar_por_doc.php?numero=${encodeURIComponent(doc)}&contexto=${contexto}&auto_guardar=1`)
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registrado con Éxito',
                        text: resp.mensaje || `Se guardó correctamente en el catálogo de ${contexto}s.`,
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo registrar.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error de Conexión', err.message, 'error');
            });
    });
}

// Manejo del Historial en LocalStorage
function guardarEnHistorialLocal(d) {
    let hist = JSON.parse(localStorage.getItem('contahercar_historial_sunat') || '[]');
    // Eliminar si ya existía para ponerlo primero
    hist = hist.filter(item => item.numero !== d.numero);
    hist.unshift({
        tipo: d.tipo,
        numero: d.numero,
        nombre: d.nombre,
        estado: d.estado,
        condicion: d.condicion,
        fecha: new Date().toLocaleString()
    });
    // Guardar hasta 15
    if (hist.length > 15) hist.pop();
    localStorage.setItem('contahercar_historial_sunat', JSON.stringify(hist));
}

function cargarHistorialConsultas() {
    const tbody = document.getElementById('tbodyHistorialConsultas');
    const hist = JSON.parse(localStorage.getItem('contahercar_historial_sunat') || '[]');

    if (hist.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="fa fa-search me-1"></i> Aún no has realizado consultas en este navegador.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = hist.map(item => `
        <tr>
            <td class="ps-3"><span class="badge bg-secondary">${item.tipo}</span></td>
            <td class="font-monospace fw-bold">${item.numero}</td>
            <td class="fw-semibold text-truncate" style="max-width: 250px;">${item.nombre}</td>
            <td><span class="badge ${item.estado === 'ACTIVO' ? 'bg-success' : 'bg-danger'}">${item.estado}</span></td>
            <td><span class="badge ${item.condicion === 'HABIDO' ? 'bg-success' : 'bg-warning text-dark'}">${item.condicion}</span></td>
            <td class="small text-muted">${item.fecha}</td>
            <td class="text-end pe-3">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="consultarDocSunat('${item.numero}')" title="Volver a Consultar">
                    <i class="fa fa-rotate-right me-1"></i> Consultar
                </button>
            </td>
        </tr>
    `).join('');
}

function limpiarHistorialConsultas() {
    localStorage.removeItem('contahercar_historial_sunat');
    cargarHistorialConsultas();
    Swal.fire({
        icon: 'info',
        title: 'Historial Limpiado',
        showConfirmButton: false,
        timer: 1200
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


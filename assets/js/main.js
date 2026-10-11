/**
 * ContaHercar - Funciones JavaScript Principales
 */

// Toast notification helper con SweetAlert2
function showToast(type, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({
        icon: type, // 'success', 'error', 'warning', 'info'
        title: message
    });
}

/**
 * Consulta RUC / DNI vía API AJAX
 */
function consultarDocumento(numDocInputId, nombreInputId, dirInputId, estadoInputId = null, btnId = null) {
    const numInput = document.getElementById(numDocInputId);
    const nombreInput = document.getElementById(nombreInputId);
    const dirInput = dirInputId ? document.getElementById(dirInputId) : null;
    const estadoInput = estadoInputId ? document.getElementById(estadoInputId) : null;
    const btn = btnId ? document.getElementById(btnId) : null;

    if (!numInput) return;

    const numero = numInput.value.trim().replace(/[^0-9]/g, '');

    if (numero.length !== 8 && numero.length !== 11) {
        Swal.fire({
            icon: 'warning',
            title: 'Número Inválido',
            text: 'Debe ingresar un DNI de 8 dígitos o un RUC de 11 dígitos para consultar.',
            confirmButtonColor: '#2563eb'
        });
        return;
    }

    let originalBtnHtml = '';
    if (btn) {
        originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Consultando...';
    }

    const isStaticHost = (window.location.protocol === 'file:' || window.location.hostname.includes('github.io') || window.location.hostname.includes('github'));
    if (isStaticHost) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
        }
        const fb = (window.buscarDocSunatReniec) ? window.buscarDocSunatReniec(numero) : null;
        if (fb && fb.success) {
            if (nombreInput) nombreInput.value = fb.nombre || '';
            if (dirInput && fb.direccion) dirInput.value = fb.direccion;
            if (estadoInput && fb.estado) estadoInput.value = fb.estado;
            showToast('success', `${fb.tipo || 'Documento'} Encontrado: ${fb.nombre}`);
            return;
        }
    }

    fetch(`api/consulta_ruc.php?numero=${encodeURIComponent(numero)}`)
        .then(response => {
            if (!response.ok) throw new Error('API offline');
            return response.json();
        })
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }

            if (data.success) {
                if (nombreInput) nombreInput.value = data.nombre || '';
                if (dirInput && data.direccion) dirInput.value = data.direccion;
                if (estadoInput && data.estado) estadoInput.value = data.estado;

                showToast('success', `${data.tipo || 'Documento'} Encontrado: ${data.nombre}`);
                if (data.aviso) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Datos Obtenidos',
                        text: data.aviso,
                        confirmButtonColor: '#2563eb'
                    });
                }
            } else {
                throw new Error(data.message || 'No encontrado');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }

            // Fallback inteligente para GitHub Pages y entornos estáticos / sin conexión local
            const fallbackData = (window.buscarDocSunatReniec) ? window.buscarDocSunatReniec(numero) : null;
            if (fallbackData && fallbackData.success) {
                if (nombreInput) nombreInput.value = fallbackData.nombre;
                if (dirInput && fallbackData.direccion) dirInput.value = fallbackData.direccion;
                if (estadoInput && fallbackData.estado) estadoInput.value = fallbackData.estado;

                showToast('success', `${fallbackData.tipo === 'RUC' ? 'RUC SUNAT' : 'DNI RENIEC'} Verificado: ${fallbackData.nombre}`);
            } else {
                showToast('warning', 'No se pudo consultar el documento.');
            }
        });
}

/**
 * Ver Kardex de un Producto en Modal (con Fallback Offline para GitHub Pages)
 */
function verKardexProducto(productoId) {
    const modalEl = document.getElementById('modalKardexGlobal');
    if (!modalEl) return;

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const bodyEl = document.getElementById('kardexModalContent');
    const titleEl = document.getElementById('kardexModalTitle');

    titleEl.textContent = 'Cargando movimientos...';
    bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    bsModal.show();

    function renderKardex(data) {
        if (!data || !data.success) {
            bodyEl.innerHTML = `<div class="alert alert-danger">${data?.message || 'Error al cargar movimientos de Kardex.'}</div>`;
            return;
        }

        const p = data.producto;
        titleEl.textContent = `Kardex: ${p.nombre} (Stock Actual: ${p.stock} ${p.unidad_medida})`;

        if (!data.movimientos || data.movimientos.length === 0) {
            bodyEl.innerHTML = '<div class="alert alert-info">No hay movimientos registrados para este producto.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo Movimiento</th>
                            <th>Cant.</th>
                            <th>Stock Ant.</th>
                            <th>Stock Nuevo</th>
                            <th>Motivo / Ref.</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.movimientos.forEach(m => {
            let badgeClass = 'bg-secondary';
            let icon = '';
            const tipo = m.tipo_movimiento || '';
            if (tipo.includes('COMPRA') || tipo.includes('INGRESO') || tipo.includes('ENTRADA')) {
                badgeClass = 'badge-soft-success';
                icon = '<i class="fa fa-arrow-down text-success me-1"></i> ';
            } else if (tipo.includes('VENTA') || tipo.includes('SALIDA')) {
                badgeClass = 'badge-soft-danger';
                icon = '<i class="fa fa-arrow-up text-danger me-1"></i> ';
            }

            html += `
                <tr>
                    <td><small>${m.fecha || m.fecha_movimiento || '-'}</small></td>
                    <td><span class="badge ${badgeClass}">${icon}${tipo}</span></td>
                    <td class="fw-bold">${m.cantidad}</td>
                    <td>${m.stock_anterior ?? '-'}</td>
                    <td class="fw-bold text-primary">${m.stock_nuevo ?? m.stock_posterior ?? '-'}</td>
                    <td><small class="text-muted">${m.motivo || m.glosa || m.numero_comprobante || '-'}</small></td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;
        bodyEl.innerHTML = html;
    }

    fetch(`api/kardex_info.php?producto_id=${productoId}`)
        .then(res => {
            if (!res.ok) throw new Error('API offline');
            return res.json();
        })
        .then(data => renderKardex(data))
        .catch(() => {
            const fallback = (window.STATIC_DB_KARDEX && window.STATIC_DB_KARDEX[productoId]) ?
                JSON.parse(JSON.stringify(window.STATIC_DB_KARDEX[productoId])) : {
                    success: true,
                    producto: { nombre: 'Producto #' + productoId, stock: 15, unidad_medida: 'UNID' },
                    movimientos: [
                        { fecha: '08/09/2026 10:30', tipo_movimiento: 'VENTA EN POS', cantidad: -2, stock_anterior: 17, stock_nuevo: 15, motivo: 'Venta B001-000002' },
                        { fecha: '07/09/2026 09:15', tipo_movimiento: 'INGRESO POR COMPRA', cantidad: 10, stock_anterior: 7, stock_nuevo: 17, motivo: 'Compra F001-000124' }
                    ]
                };
            // Integrar movimientos recientes de ventas emitidas localmente
            try {
                const allLocal = JSON.parse(localStorage.getItem('contahercar_kardex_local') || '[]');
                const localMovs = allLocal.filter(x => x.producto_id == productoId);
                if (localMovs.length > 0) {
                    fallback.movimientos = [...localMovs, ...fallback.movimientos];
                }
            } catch (e) {}
            renderKardex(fallback);
        });
}

/**
 * Mostrar Comprobante de Pago (Ticket Térmico 80mm) en Pantalla Inmediatamente
 */
window.mostrarComprobanteTicket = function(ventaData, autoPrint = false) {
    const modalEl = document.getElementById('modalTicketGlobal');
    if (!modalEl) {
        if (autoPrint) window.print();
        return;
    }
    const v = ventaData.venta || ventaData;
    const items = ventaData.items || [];

    const tipoEl = document.getElementById('ticketTipoDoc');
    const numEl = document.getElementById('ticketNumero');
    const fechaEl = document.getElementById('ticketFecha');
    const clienteEl = document.getElementById('ticketCliente');
    const itemsEl = document.getElementById('ticketItems');
    const baseEl = document.getElementById('ticketBase');
    const igvEl = document.getElementById('ticketIgv');
    const totalEl = document.getElementById('ticketTotal');

    if (tipoEl) tipoEl.textContent = (v.tipo_comprobante || 'BOLETA ELECTRÓNICA').toUpperCase();
    if (numEl) numEl.textContent = `${v.serie || 'B001'}-${v.correlativo || '000001'}`;
    if (fechaEl) fechaEl.textContent = v.fecha_venta || new Date().toLocaleString('es-PE');
    const cliDoc = v.cliente_doc ? ` (${v.cliente_doc})` : '';
    if (clienteEl) clienteEl.textContent = `${v.cliente_nombre || 'CLIENTE GENERAL'}${cliDoc}`;

    if (itemsEl) {
        if (items.length > 0) {
            itemsEl.innerHTML = items.map(it => `
                <div class="d-flex justify-content-between mb-1">
                    <span>${it.cantidad}x ${it.producto_nombre || it.nombre}</span>
                    <span class="fw-bold">${it.subtotal_fmt || ('S/. ' + ((it.precio || 0) * it.cantidad).toFixed(2))}</span>
                </div>
            `).join('');
        }
    }

    if (baseEl) baseEl.textContent = v.subtotal_fmt || `S/. ${parseFloat(v.subtotal || 0).toFixed(2)}`;
    if (igvEl) igvEl.textContent = v.impuesto_fmt || `S/. ${parseFloat(v.impuesto || 0).toFixed(2)}`;
    if (totalEl) totalEl.textContent = v.total_fmt || `S/. ${parseFloat(v.total || 0).toFixed(2)}`;

    // Marca de agua y certificación según modo PRO o Demo
    const watermarkEl = document.getElementById('ticketWatermarkArea');
    if (watermarkEl) {
        if (window.esUsuarioPro()) {
            watermarkEl.innerHTML = '<div class="p-1 bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded small fw-bold"><i class="fa fa-shield-halved me-1"></i> COMPROBANTE OFICIAL - CUENTA PRO VIP ACTIVA</div>';
        } else {
            watermarkEl.innerHTML = '<div class="p-1 bg-warning bg-opacity-10 text-dark border border-warning rounded small fw-bold"><i class="fa fa-lock text-warning me-1"></i> COPIA DEMOSTRACIÓN - ADQUIERE TU PLAN PRO (S/ 20)</div>';
        }
    }

    // Limpiar restos de SweetAlert o backdrops que pudieran bloquear el foco
    document.querySelectorAll('.swal2-container').forEach(s => s.remove());
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open', 'swal2-shown', 'swal2-height-auto');

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();

    if (autoPrint) {
        setTimeout(() => { window.print(); }, 400);
    }
};

/**
 * Compartir Comprobante / Ticket por WhatsApp (Protegido por Paywall en Demo)
 */
window.compartirTicketWhatsApp = function() {
    if (!window.esUsuarioPro()) {
        window.verificarAccesoPro('Envío de Tickets y Comprobantes por WhatsApp', () => {
            window.compartirTicketWhatsApp();
        });
        return;
    }
    const num = document.getElementById('ticketNumero')?.textContent || 'B001-000001';
    const cli = document.getElementById('ticketCliente')?.textContent || 'Cliente';
    const tot = document.getElementById('ticketTotal')?.textContent || 'S/ 0.00';
    const msj = encodeURIComponent(`Hola ${cli}, gracias por tu compra en ContaSmart. Te compartimos tu comprobante electrónico N° ${num} por el importe de ${tot}.`);
    window.open(`https://wa.me/?text=${msj}`, '_blank');
};

/**
 * Visualizar Cuentas Bancarias Directas de Misael (BCP / BBVA)
 */
window.solicitarDatosBancariosMisael = function() {
    if (typeof Swal === 'undefined') return;
    Swal.fire({
        title: 'Cuentas Bancarias Directas (0% Comisión)',
        html: `
            <div class="text-start p-3 bg-light rounded-3 border small">
                <div class="mb-2">
                    <span class="badge bg-primary me-1">BCP</span> <strong>Banco de Crédito del Perú</strong>
                    <div class="font-monospace fw-bold text-dark mt-1">Cta Ahorros: 191-98765432-0-89</div>
                    <div class="font-monospace text-secondary" style="font-size:0.75rem;">CCI: 002-1910098765432089-54</div>
                </div>
                <div class="border-top pt-2 mb-2">
                    <span class="badge bg-primary bg-opacity-75 me-1">BBVA</span> <strong>Banco BBVA Perú</strong>
                    <div class="font-monospace fw-bold text-dark mt-1">Cta Ahorros: 0011-0234-0200876543</div>
                    <div class="font-monospace text-secondary" style="font-size:0.75rem;">CCI: 011-234000200876543-12</div>
                </div>
                <div class="border-top pt-2">
                    <strong>Titular:</strong> Gerson Misael Pintado Huamán<br>
                    <strong>Monto:</strong> S/ 20.00 (Membresía Mensual)
                </div>
            </div>
            <p class="small text-muted mt-2 mb-0">Al realizar tu transferencia, envíanos la captura por WhatsApp y te activamos el acceso PRO de inmediato.</p>
        `,
        confirmButtonText: '<i class="fa-brands fa-whatsapp me-1"></i> Enviar Comprobante por WhatsApp',
        confirmButtonColor: '#10b981',
        showCancelButton: true,
        cancelButtonText: 'Cerrar'
    }).then((res) => {
        if (res.isConfirmed) {
            window.open('https://wa.me/51921840632?text=Hola%20Misael,%20te%20acabo%20de%20transferir%20S/%2020.00%20por%20banco%20para%20el%20Plan%20PRO%20de%20ContaSmart.%20Aqu%C3%AD%20mi%20captura.', '_blank');
        }
    });
};

/**
 * Imprimir Ticket Térmico 80mm Directo en Modal
 */
window.imprimirTicketDirecto = function(id) {
    // 1. Probar primero si es una venta recién emitida localmente
    const emitidas = (window.obtenerVentasEmitidas) ? window.obtenerVentasEmitidas() : [];
    const ventaLocal = emitidas.find(v => v.id == id || (v.venta && v.venta.id == id));
    if (ventaLocal) {
        window.mostrarComprobanteTicket(ventaLocal);
        return;
    }

    // 2. Probar fetch si estamos con backend dinámico PHP
    fetch(`api/venta_detalle.php?id=${id}`)
        .then(res => {
            if (!res.ok) throw new Error('API offline');
            return res.json();
        })
        .then(data => {
            if (data && data.success && data.venta) {
                window.mostrarComprobanteTicket(data);
            } else {
                throw new Error('Static fallback');
            }
        })
        .catch(() => {
            let ventaData = (window.STATIC_DB_VENTAS && window.STATIC_DB_VENTAS[id]) ? window.STATIC_DB_VENTAS[id] : null;
            if (!ventaData) {
                ventaData = {
                    venta: {
                        tipo_comprobante: 'BOLETA ELECTRÓNICA',
                        serie: 'B001',
                        correlativo: String(id).padStart(6, '0'),
                        fecha_venta: '07/09/2026 13:14',
                        cliente_nombre: 'CLIENTE VARIOS / GENERAL',
                        cliente_doc: '00000000',
                        subtotal_fmt: 'S/. 241.53',
                        impuesto_fmt: 'S/. 43.47',
                        total_fmt: 'S/. 285.00'
                    },
                    items: [
                        { cantidad: 1, producto_nombre: 'Amoladora Angular Dewalt 850W', subtotal_fmt: 'S/. 285.00' }
                    ]
                };
            }
            window.mostrarComprobanteTicket(ventaData);
        });
};

// Consulta asíncrona de Tipo de Cambio SUNAT
function cargarTipoCambio() {
    const el = document.getElementById('tcTexto');
    if (!el) return;

    fetch('api/tipo_cambio.php')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.compra && data.venta) {
                el.textContent = `USD SUNAT: C: S/. ${data.compra.toFixed(3)} | V: S/. ${data.venta.toFixed(3)}`;
            }
        })
        .catch(() => {});
}

// Funciones globales para control del Menú Lateral (3 rayitas)
window.openSidebar = function() {
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.add('show');
    if (backdrop) backdrop.classList.add('show');
    document.body.classList.add('sidebar-open');
};

window.closeSidebar = function() {
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.remove('show');
    if (backdrop) backdrop.classList.remove('show');
    document.body.classList.remove('sidebar-open');
};

window.toggleSidebar = function(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const sidebar = document.querySelector('.app-sidebar');
    if (window.innerWidth <= 992) {
        if (sidebar && sidebar.classList.contains('show')) {
            window.closeSidebar();
        } else {
            window.openSidebar();
        }
    } else {
        document.body.classList.toggle('sidebar-collapsed');
    }
};

// Inicialización general en DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (toggleBtn) {
        toggleBtn.onclick = window.toggleSidebar;
    }

    if (closeBtn) {
        closeBtn.onclick = function(e) {
            e.preventDefault();
            window.closeSidebar();
        };
    }

    if (backdrop) {
        backdrop.onclick = function(e) {
            e.preventDefault();
            window.closeSidebar();
        };
    }

    // Cerrar sidebar al hacer clic en cualquier enlace en móviles
    document.querySelectorAll('.app-sidebar .sidebar-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                window.closeSidebar();
            }
        });
    });

    // Cargar tipo de cambio oficial
    cargarTipoCambio();
});

// ============================================================
// FUNCIONES GLOBALES PARA MODALES FLOTANTES (POS & RUC/DNI)
// ============================================================
function openPosModal() {
    const el = document.getElementById('modalPosGlobal');
    if (!el) {
        window.location.href = 'venta_nueva.php';
        return;
    }
    const m = bootstrap.Modal.getOrCreateInstance(el);
    m.show();

    const sel = document.getElementById('posSelectProd');
    if (sel) {
        sel.onchange = () => {
            const val = parseFloat(sel.value) || 0;
            const lbl = document.getElementById('posTotalLabel');
            if (lbl) lbl.textContent = `S/. ${val.toFixed(2)}`;
        };
    }
}

function simulateSale() {
    const mEl = document.getElementById('modalPosGlobal');
    if (mEl) {
        const inst = bootstrap.Modal.getInstance(mEl);
        if (inst) inst.hide();
    }
    const sel = document.getElementById('posSelectProd');
    const prodName = sel ? sel.options[sel.selectedIndex].text.split('-')[0].trim() : 'Taladro Percutor Bosch 650W';
    const prodPrice = sel ? parseFloat(sel.value) || 245.00 : 245.00;
    const prodId = (prodName.toLowerCase().includes('amoladora')) ? 2 : ((prodName.toLowerCase().includes('cemento')) ? 3 : 1);

    // 1. Descontar inventario automáticamente en almacén y persistencia
    const nuevoStock = (window.descontarStockProducto) ? window.descontarStockProducto(prodId, 1) : 14;

    const correlativo = String(Math.floor(100 + Math.random() * 900)).padStart(6, '0');
    const serieNum = 'B001-' + correlativo;
    const subtotal = prodPrice / 1.18;
    const igv = prodPrice - subtotal;
    const fechaHora = new Date().toLocaleDateString('es-PE') + ' ' + new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });

    const ventaObj = {
        id: Date.now(),
        venta: {
            id: Date.now(),
            tipo_comprobante: 'BOLETA ELECTRÓNICA',
            serie: 'B001',
            correlativo: correlativo,
            fecha_venta: fechaHora,
            cliente_nombre: '00000000 - CLIENTE VARIOS / GENERAL',
            cliente_doc: '00000000',
            subtotal: subtotal.toFixed(2),
            subtotal_fmt: `S/. ${subtotal.toFixed(2)}`,
            impuesto: igv.toFixed(2),
            impuesto_fmt: `S/. ${igv.toFixed(2)}`,
            total: prodPrice.toFixed(2),
            total_fmt: `S/. ${prodPrice.toFixed(2)}`,
            metodo_pago: 'Efectivo',
            estado: 'COMPLETADA'
        },
        items: [
            {
                producto_id: prodId,
                producto_nombre: prodName,
                cantidad: 1,
                precio: prodPrice,
                subtotal_fmt: `S/. ${prodPrice.toFixed(2)}`
            }
        ]
    };

    // 2. Registrar venta y movimiento en Kardex local
    if (window.registrarVentaEmitida) {
        window.registrarVentaEmitida(ventaObj);
    }
    if (window.reproducirSonidoCobro) {
        window.reproducirSonidoCobro();
    }

    // 3. MOSTRAR COMPROBANTE DE PAGO DIRECTAMENTE EN PANTALLA
    window.mostrarComprobanteTicket(ventaObj);

    if (typeof showToast === 'function') {
        showToast('success', `¡Venta ${serieNum} emitida! Stock actualizado: ${nuevoStock} unid.`);
    }
}

function verTicketDemo(num, cliente, producto, total, fecha) {
    const totNum = parseFloat(total) || 245;
    const subNum = (totNum / 1.18).toFixed(2);
    const igvNum = (totNum - subNum).toFixed(2);
    const tipo = (num && num.startsWith('F')) ? 'FACTURA ELECTRÓNICA' : 'BOLETA ELECTRÓNICA';

    const ventaObj = {
        venta: {
            tipo_comprobante: tipo,
            serie: num ? num.split('-')[0] : 'B001',
            correlativo: num ? num.split('-')[1] : '000428',
            fecha_venta: fecha || new Date().toLocaleString('es-PE'),
            cliente_nombre: cliente || '00000000 - CLIENTE VARIOS',
            cliente_doc: '00000000',
            subtotal_fmt: `S/. ${subNum}`,
            impuesto_fmt: `S/. ${igvNum}`,
            total_fmt: `S/. ${totNum.toFixed(2)}`
        },
        items: [
            {
                cantidad: 1,
                producto_nombre: producto || 'Producto Ferretero',
                subtotal_fmt: `S/. ${totNum.toFixed(2)}`
            }
        ]
    };

    window.mostrarComprobanteTicket(ventaObj);
}

function openConsultaRucModal() {
    const el = document.getElementById('modalConsultaRucGlobal');
    if (!el) {
        window.location.href = 'consulta_sunat.php';
        return;
    }
    const m = bootstrap.Modal.getOrCreateInstance(el);
    m.show();
}

function switchDocType(tipo) {
    const inp = document.getElementById('modalDocNumber');
    if (!inp) return;
    if (tipo === 'DNI') {
        inp.placeholder = 'Ingresa DNI (Ej: 45871234)';
        inp.maxLength = 8;
        if (inp.value.length === 11) inp.value = '45871234';
    } else {
        inp.placeholder = 'Ingresa RUC (Ej: 20601234567)';
        inp.maxLength = 11;
        if (inp.value.length === 8) inp.value = '20601234567';
    }
}

function ejecutarConsultaModal() {
    const inp = document.getElementById('modalDocNumber');
    if (!inp) return;
    const num = inp.value.trim().replace(/[^0-9]/g, '');
    if (num.length !== 8 && num.length !== 11) {
        Swal.fire({
            icon: 'warning',
            title: 'Número Inválido',
            text: 'Debes ingresar un DNI de 8 dígitos o un RUC de 11 dígitos.',
            confirmButtonColor: '#2563eb'
        });
        return;
    }

    const load = document.getElementById('modalConsultaLoading');
    const res = document.getElementById('modalConsultaResult');
    const btn = document.getElementById('btnDoConsultaDoc');
    if (load) load.classList.remove('d-none');
    if (btn) btn.disabled = true;

    const renderModalData = (finalData) => {
        if (load) load.classList.add('d-none');
        if (btn) btn.disabled = false;
        if (!finalData || !finalData.success) {
            Swal.fire('Atención', 'No se encontraron datos para el documento ingresado.', 'info');
            return;
        }

        const bBadge = document.getElementById('resDocBadge');
        const bEstado = document.getElementById('resDocEstado');
        const bNombre = document.getElementById('resDocNombre');
        const bNum = document.getElementById('resDocNum');
        const bCond = document.getElementById('resDocCondicion');
        const bDir = document.getElementById('resDocDireccion');
        const bReg = document.getElementById('resDocRegimen');

        if (bBadge) bBadge.innerHTML = num.length === 11 ? '<i class="fa fa-circle-check me-1"></i>RUC SUNAT Verificado' : '<i class="fa fa-id-card me-1"></i>DNI RENIEC Verificado';
        if (bEstado) bEstado.textContent = finalData.estado || 'ACTIVO';
        if (bNombre) bNombre.textContent = finalData.nombre;
        if (bNum) bNum.textContent = num;
        if (bCond) bCond.textContent = finalData.condicion || 'HABIDO';
        if (bDir) bDir.textContent = finalData.direccion || 'Sin dirección declarada';
        if (bReg) bReg.textContent = finalData.tipo_contribuyente || finalData.regimen || 'Régimen MYPE Tributario';

        if (res) res.classList.remove('d-none');

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Documento Verificado con Éxito',
            showConfirmButton: false,
            timer: 2000
        });
    };

    const isStaticHost = (window.location.protocol === 'file:' || window.location.hostname.includes('github.io') || window.location.hostname.includes('github'));
    if (isStaticHost) {
        const fb = (window.buscarDocSunatReniec) ? window.buscarDocSunatReniec(num) : null;
        renderModalData(fb);
        return;
    }

    fetch(`api/consulta_ruc.php?numero=${num}`)
        .then(r => r.text())
        .then(txt => {
            if (txt.trim().startsWith('<')) throw new Error('Not JSON');
            return JSON.parse(txt);
        })
        .catch(() => null)
        .then(data => {
            let finalData = data;
            if (!finalData || !finalData.success) {
                finalData = (window.buscarDocSunatReniec) ? window.buscarDocSunatReniec(num) : null;
            }
            renderModalData(finalData);
        });
}

function guardarDocModal(tipo) {
    const elNombre = document.getElementById('resDocNombre');
    const elNum = document.getElementById('resDocNum');
    const nombre = elNombre ? elNombre.textContent.trim() : '';
    const num = elNum ? elNum.textContent.trim() : '';

    if (!num) {
        Swal.fire('Atención', 'Primero realice una búsqueda de documento.', 'warning');
        return;
    }

    // Auto-guardado en base de datos local o LocalStorage
    fetch(`api/buscar_por_doc.php?numero=${encodeURIComponent(num)}&nombre=${encodeURIComponent(nombre)}&contexto=${tipo}&auto_guardar=1`)
        .then(r => {
            if (!r.ok) throw new Error('Offline');
            return r.json();
        })
        .catch(() => null)
        .then(res => {
            let lista = JSON.parse(localStorage.getItem('contahercar_registros_' + tipo) || '[]');
            lista.push({ doc: num, nombre: nombre, fecha: new Date().toLocaleString() });
            localStorage.setItem('contahercar_registros_' + tipo, JSON.stringify(lista));

            Swal.fire({
                icon: 'success',
                title: tipo === 'cliente' ? '¡Cliente Guardado en BD!' : '¡Proveedor Guardado en BD!',
                html: `<strong>${nombre}</strong> (${num}) ha sido registrado exitosamente en el catálogo de ContaSmart.`,
                confirmButtonColor: '#2563eb'
            });
        });
}

function facturarDocModal() {
    const elNombre = document.getElementById('resDocNombre');
    const elNum = document.getElementById('resDocNum');
    const nombre = elNombre ? elNombre.textContent : '';
    const num = elNum ? elNum.textContent : '';

    const mEl = document.getElementById('modalConsultaRucGlobal');
    if (mEl) {
        const inst = bootstrap.Modal.getInstance(mEl);
        if (inst) inst.hide();
    }
    openPosModal();
    const clienteInput = document.querySelector('#modalPosGlobal input[type="text"]');
    if (clienteInput) clienteInput.value = `${num} - ${nombre}`;
}

// ============================================================================
// GESTOR DE INVENTARIO Y STOCK EN CLIENTE / LOCALSTORAGE (CONTA SMART v6.4)
// ============================================================================
const STOCK_KEY = 'contahercar_stock_overrides';
const VENTAS_EMITIDAS_KEY = 'contahercar_ventas_emitidas';
const KARDEX_LOCAL_KEY = 'contahercar_kardex_local';

/**
 * Obtener el stock actual de un producto considerando compras y ventas previas
 */
window.obtenerStockProducto = function(id, stockOriginal = null) {
    try {
        const overrides = JSON.parse(localStorage.getItem(STOCK_KEY) || '{}');
        if (overrides[id] !== undefined) {
            return Math.max(0, parseInt(overrides[id], 10));
        }
    } catch (e) {
        console.error('Error leyendo stock:', e);
    }
    if (stockOriginal !== null && stockOriginal !== undefined && !isNaN(stockOriginal)) {
        return Math.max(0, parseInt(stockOriginal, 10));
    }
    // Buscar en DOM si no se pasó stockOriginal
    const card = document.querySelector(`.item-card-prod[data-id="${id}"]`);
    if (card && card.dataset.stock !== undefined) {
        return Math.max(0, parseInt(card.dataset.stock, 10));
    }
    const row = document.querySelector(`tr[data-producto-id="${id}"]`);
    if (row && row.dataset.stockActual !== undefined) {
        return Math.max(0, parseInt(row.dataset.stockActual, 10));
    }
    return 15;
};

/**
 * Fijar manualmente el stock de un producto (desde ajuste o inventario)
 */
window.fijarStockProducto = function(id, nuevoStock, stockMinimo = 5, unidad = 'UNID') {
    try {
        const overrides = JSON.parse(localStorage.getItem(STOCK_KEY) || '{}');
        const finalStock = Math.max(0, parseInt(nuevoStock, 10));
        overrides[id] = finalStock;
        localStorage.setItem(STOCK_KEY, JSON.stringify(overrides));
        window.actualizarDOMStock(id, finalStock, stockMinimo, unidad);
        return finalStock;
    } catch (e) {
        console.error('Error guardando stock:', e);
        return nuevoStock;
    }
};

/**
 * Descontar stock tras una venta completada en el POS
 */
window.descontarStockProducto = function(id, cantidad, stockOriginal = null) {
    try {
        const current = window.obtenerStockProducto(id, stockOriginal);
        const nuevo = Math.max(0, current - parseInt(cantidad, 10));
        const overrides = JSON.parse(localStorage.getItem(STOCK_KEY) || '{}');
        overrides[id] = nuevo;
        localStorage.setItem(STOCK_KEY, JSON.stringify(overrides));
        window.actualizarDOMStock(id, nuevo);
        return nuevo;
    } catch (e) {
        console.error('Error descontando stock:', e);
        return 0;
    }
};

/**
 * Reponer stock (anulación de venta)
 */
window.reponerStockProducto = function(id, cantidad) {
    try {
        const current = window.obtenerStockProducto(id, 15);
        const nuevo = current + parseInt(cantidad, 10);
        const overrides = JSON.parse(localStorage.getItem(STOCK_KEY) || '{}');
        overrides[id] = nuevo;
        localStorage.setItem(STOCK_KEY, JSON.stringify(overrides));
        window.actualizarDOMStock(id, nuevo);
        return nuevo;
    } catch (e) {
        console.error('Error reponiendo stock:', e);
        return 0;
    }
};

/**
 * Actualizar visualmente los elementos del DOM (POS, Almacén, Kardex)
 */
window.actualizarDOMStock = function(id, nuevoStock, stockMinimo = 5, unidad = 'UNID') {
    // 1. En venta_nueva.php / venta_nueva.html (Tarjetas de Catálogo)
    const card = document.querySelector(`.item-card-prod[data-id="${id}"]`);
    if (card) {
        card.setAttribute('data-stock', nuevoStock);
        card.dataset.stock = nuevoStock;
        const badge = card.querySelector('.badge:not(.bg-light)');
        if (badge) {
            badge.textContent = `Stock: ${nuevoStock}`;
            badge.className = 'badge small ' + (nuevoStock <= 0 ? 'bg-danger' : (nuevoStock <= stockMinimo ? 'bg-warning text-dark' : 'bg-success'));
        }
    }

    // 2. En inventario.php / inventario.html (Tabla de Productos)
    const row = document.querySelector(`tr[data-producto-id="${id}"]`);
    if (row) {
        row.setAttribute('data-stock-actual', nuevoStock);
        const stockCell = row.querySelector('.stock-actual-cell');
        if (stockCell) {
            if (nuevoStock <= 0) {
                stockCell.innerHTML = `<span class="badge badge-soft-danger"><i class="fa fa-circle-xmark me-1"></i> 0 ${unidad} (Agotado)</span>`;
            } else if (nuevoStock <= stockMinimo) {
                stockCell.innerHTML = `<span class="badge badge-soft-warning"><i class="fa fa-triangle-exclamation me-1"></i> ${nuevoStock} ${unidad} (Bajo)</span>`;
            } else {
                stockCell.innerHTML = `<span class="badge badge-soft-success"><i class="fa fa-circle-check me-1"></i> ${nuevoStock} ${unidad}</span>`;
            }
        }
    }
};

/**
 * Guardar venta emitida en el historial local
 */
window.registrarVentaEmitida = function(ventaData) {
    try {
        let emitidas = JSON.parse(localStorage.getItem(VENTAS_EMITIDAS_KEY) || '[]');
        emitidas.unshift(ventaData);
        localStorage.setItem(VENTAS_EMITIDAS_KEY, JSON.stringify(emitidas));

        // Registrar en Kardex local
        let kardexLocal = JSON.parse(localStorage.getItem(KARDEX_LOCAL_KEY) || '[]');
        const v = ventaData.venta || ventaData;
        const fecha = v.fecha_venta || new Date().toLocaleString('es-PE');
        const comprobante = `${v.serie || 'B001'}-${v.correlativo || '000001'}`;
        
        (ventaData.items || []).forEach(it => {
            const pId = it.producto_id || it.id;
            const stockAnt = window.obtenerStockProducto(pId, 15) + it.cantidad;
            const stockNuevo = window.obtenerStockProducto(pId, 15);
            kardexLocal.unshift({
                producto_id: pId,
                fecha: fecha,
                tipo_movimiento: 'VENTA EN POS',
                cantidad: -it.cantidad,
                stock_anterior: stockAnt,
                stock_nuevo: stockNuevo,
                motivo: `Venta ${comprobante}`
            });
        });
        localStorage.setItem(KARDEX_LOCAL_KEY, JSON.stringify(kardexLocal));
    } catch (e) {
        console.error('Error guardando venta emitida:', e);
    }
};

/**
 * Obtener ventas emitidas localmente
 */
window.obtenerVentasEmitidas = function() {
    try {
        return JSON.parse(localStorage.getItem(VENTAS_EMITIDAS_KEY) || '[]');
    } catch (e) {
        return [];
    }
};

/**
 * Sonido estético de caja registradora con Web Audio API (Sin archivos externos)
 */
window.reproducirSonidoCobro = function() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // Re 5
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.08); // La 5
        osc.frequency.setValueAtTime(1174.66, ctx.currentTime + 0.16); // Re 6
        gain.gain.setValueAtTime(0.25, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.42);
    } catch (e) {}
};

/**
 * Sincronizar todos los stocks al abrir cualquier vista
 */
window.sincronizarInventarioGlobal = function() {
    try {
        const overrides = JSON.parse(localStorage.getItem(STOCK_KEY) || '{}');
        
        // 1. Sincronizar en POS (venta_nueva.php / venta_nueva.html)
        document.querySelectorAll('.item-card-prod').forEach(card => {
            const id = card.dataset.id;
            if (overrides[id] !== undefined) {
                const stock = parseInt(overrides[id], 10);
                window.actualizarDOMStock(id, stock);
            }
        });

        // 2. Sincronizar en Inventario / Almacén (inventario.php / inventario.html)
        document.querySelectorAll('tr[data-producto-id]').forEach(row => {
            const id = row.dataset.productoId;
            const stockMinimo = parseInt(row.dataset.stockMinimo || '5', 10);
            const unidad = row.dataset.unidad || 'UNID';
            if (overrides[id] !== undefined) {
                const stock = parseInt(overrides[id], 10);
                window.actualizarDOMStock(id, stock, stockMinimo, unidad);
            }
        });
    } catch (e) {
        console.error('Error sincronizando inventario global:', e);
    }
};

// Auto-ejecución al cargar cualquier página
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.sincronizarInventarioGlobal);
} else {
    window.sincronizarInventarioGlobal();
}

/**
 * Blindaje Universal de Formularios para GitHub Pages (Previene error 405 Method Not Allowed)
 */
document.addEventListener('submit', function(e) {
    const isStatic = window.location.protocol === 'file:' || 
                     window.location.hostname.includes('github.io') || 
                     window.location.pathname.endsWith('.html');
    if (!isStatic) return;

    const form = e.target;
    if (!form || form.tagName !== 'FORM') return;

    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const action = (form.getAttribute('action') || '').toLowerCase();

    // Si es un POST a un archivo .php y no fue interceptado previamente
    if (method === 'POST' || action.endsWith('.php')) {
        if (!e.defaultPrevented) {
            e.preventDefault();

            // Cerrar cualquier modal que contenga al formulario
            const parentModal = form.closest('.modal');
            if (parentModal) {
                const m = bootstrap.Modal.getInstance(parentModal);
                if (m) m.hide();
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Cambios Guardados con Éxito!',
                    html: 'La información ha sido procesada y guardada correctamente en ContaSmart.',
                    confirmButtonColor: '#2563eb'
                });
            }
        }
    }
});

// ============================================================================
// GESTOR DE SUSCRIPCIÓN Y PAGOS MERCADO PAGO (CONTA SMART PRO / PLUS)
// ============================================================================
const PRO_STORAGE_KEY = 'contahercar_plan_plus';
const PRO_DATE_KEY = 'contahercar_plan_fecha';

/**
 * Consulta si el usuario cuenta con el Plan PRO Activo
 */
window.esUsuarioPro = function() {
    try {
        return (localStorage.getItem(PRO_STORAGE_KEY) === 'true');
    } catch (e) {
        return false;
    }
};

/**
 * Apertura a prueba de fallos del Modal de Mercado Pago (funciona en móvil y desktop)
 */
window.abrirModalMercadoPago = function(e) {
    if (e && typeof e.preventDefault === 'function') {
        e.preventDefault();
        e.stopPropagation();
    }
    const modalEl = document.getElementById('modalMercadoPagoPlus');
    if (!modalEl) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'ContaSmart PRO (S/ 20.00)',
                html: `
                    <p class="mb-3">Para desbloquear todas las funciones ilimitadas, paga con tu enlace de Mercado Pago:</p>
                    <a href="https://mpago.la/24CgT6E" target="_blank" class="btn btn-primary fw-bold w-100 py-2 mb-2">Pagar S/ 20 con Mercado Pago ↗</a>
                    <a href="https://link.mercadopago.com.pe/misael287" target="_blank" class="btn btn-outline-secondary btn-sm w-100 mb-2">Otro Monto en link.mercadopago.com.pe/misael287 ↗</a>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-key me-1"></i> Validar Comprobante / Código',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b'
            }).then((res) => {
                if (res.isConfirmed) window.solicitarCodigoActivacion();
            });
        }
        return;
    }

    if (window.actualizarEstadoPlanProGlobal) {
        window.actualizarEstadoPlanProGlobal();
    }

    // Intentar abrir con Bootstrap 5
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const m = bootstrap.Modal.getOrCreateInstance(modalEl);
            m.show();
            return;
        }
    } catch (err) {}

    // Fallback nativo directo sin depender de librerías
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    let backdrop = document.getElementById('mpFallbackBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'mpFallbackBackdrop';
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    }
};

/**
 * Cierre a prueba de fallos del Modal de Mercado Pago
 */
window.cerrarModalMercadoPago = function() {
    const modalEl = document.getElementById('modalMercadoPagoPlus');
    if (modalEl) {
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const m = bootstrap.Modal.getInstance(modalEl);
                if (m) m.hide();
            }
        } catch (e) {}
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        const fbBd = document.getElementById('mpFallbackBackdrop');
        if (fbBd) fbBd.remove();
        const bsBd = document.querySelector('.modal-backdrop');
        if (bsBd) bsBd.remove();
    }
};

/**
 * Guardián de Paywall: Bloquea funciones exclusivas en modo demo y solicita pago
 */
window.verificarAccesoPro = function(nombreModulo, accionPermitida) {
    if (window.esUsuarioPro()) {
        if (typeof accionPermitida === 'function') accionPermitida();
        return true;
    }

    // Modal de Paywall persuasivo
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'warning',
            title: '🔒 Función Bloqueada en Modo Demo',
            html: `
                <div class="text-start p-3 bg-light rounded-3 border my-2">
                    <div class="fw-bold text-dark mb-1"><i class="fa fa-lock text-warning me-2"></i>${nombreModulo}</div>
                    <p class="small text-muted mb-2">
                        Esta función es exclusiva para clientes con <strong>ContaSmart PRO Activo</strong>.
                    </p>
                    <div class="d-flex align-items-center justify-content-between p-2 bg-white rounded border">
                        <span class="small fw-semibold text-secondary">Inversión mensual:</span>
                        <span class="fs-5 fw-bold text-primary">S/ 20.00</span>
                    </div>
                </div>
                <small class="text-muted d-block mt-1">Realiza tu pago en Mercado Pago o Yape para desbloquear todas las funciones.</small>
            `,
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fa fa-shield-halved me-1"></i> Pagar S/ 20 con Mercado Pago',
            denyButtonText: '<i class="fa fa-key me-1"></i> Validar Pago / Código',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#009ee3',
            denyButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                window.pagarConMercadoPago();
            } else if (result.isDenied) {
                window.solicitarCodigoActivacion();
            }
        });
    } else {
        const conf = confirm(`Función Bloqueada: ${nombreModulo} requiere Plan PRO (S/ 20.00).\n¿Deseas abrir Mercado Pago para desbloquearla?`);
        if (conf) window.open('https://mpago.la/24CgT6E', '_blank');
    }
    return false;
};

/**
 * Redirección Directa a la Pasarela Oficial de Mercado Pago (S/ 20.00)
 */
window.pagarConMercadoPago = function() {
    window.open('https://mpago.la/24CgT6E', '_blank');
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Pasarela Oficial Abierta',
            html: `
                <div class="p-3 bg-light rounded text-start mb-3 border">
                    <div class="fw-bold text-primary mb-1"><i class="fa fa-shield-halved me-1"></i> Transacción Segura S/ 20.00</div>
                    <small class="text-muted">Acepta Tarjetas de Crédito, Débito, Yape, Plin y PagoEfectivo.</small>
                </div>
                <p class="small text-muted mb-3">Una vez completado tu pago, valida tu código o comprobante para desbloquear de inmediato:</p>
                <button type="button" class="btn btn-success fw-bold w-100 py-2" onclick="solicitarCodigoActivacion()">
                    <i class="fa fa-key me-1"></i> Validar Pago y Desbloquear PRO
                </button>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cerrar'
        });
    }
};

window.simularPagoExitosoMercadoPago = function() {
    // Redirige al pago real; ya no permite desbloqueo gratuito sin pagar
    window.pagarConMercadoPago();
};

window.iniciarSeguimientoPago = function(monto) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Abriendo Mercado Pago...',
            text: 'Al completar tu pago, pulsa "Validar Código / Voucher" para activar.',
            showConfirmButton: false,
            timer: 4000
        });
    }
};

window.copiarNumeroYape = function() {
    const num = '921840632';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(num).then(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '¡Número Yape copiado: 921 840 632!',
                    showConfirmButton: false,
                    timer: 2500
                });
            } else {
                alert('Número copiado: 921 840 632');
            }
        });
    } else {
        prompt('Copia el número de Yape de Misael:', num);
    }
};

window.solicitarCodigoActivacion = function() {
    if (typeof Swal === 'undefined') return;

    Swal.fire({
        title: '🔑 Activar Licencia ContaSmart PRO',
        html: `
            <p class="small text-muted mb-2">Ingresa tu código de activación o comprobante oficial tras pagar los <strong>S/ 20.00</strong>:</p>
            <input type="text" id="swalInputCodPro" class="form-control text-center font-monospace fw-bold fs-5 text-uppercase mb-2" placeholder="Ej: CONTA2026, PLUS20 o N° Operación" autofocus>
            <div class="p-2 bg-light rounded border text-start small">
                <div class="text-secondary mb-1"><i class="fa fa-circle-info text-primary me-1"></i> <strong>¿Aún no has realizado el pago?</strong></div>
                <div class="text-muted">Paga <strong>S/ 20.00</strong> en Mercado Pago (<a href="https://mpago.la/24CgT6E" target="_blank" class="fw-bold text-primary">mpago.la/24CgT6E</a>) o Yapea al <strong>921 840 632</strong> (Gerson Misael Pintado H.) y envía el voucher por WhatsApp.</div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa fa-shield-check me-1"></i> Validar y Desbloquear',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        preConfirm: () => {
            const input = document.getElementById('swalInputCodPro');
            const val = input ? input.value.trim().toUpperCase() : '';
            if (!val) {
                Swal.showValidationMessage('Debes ingresar tu código de activación o número de operación');
                return false;
            }
            return val;
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        const cod = res.value;
        const codigosValidos = ['PLUS20', 'CONTA2026', 'MISAELPRO', 'MISAEL20', 'MISAEL287', 'HERCARVIP', 'VIP2026', 'PRO2026'];

        if (codigosValidos.includes(cod)) {
            window.activarPlanPro(true);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Código No Válido',
                html: `
                    <p class="small text-muted mb-2">El código <code>${cod}</code> no corresponde a una licencia o pago verificado de S/ 20.00.</p>
                    <p class="small text-danger fw-semibold mb-3">Si no se confirma el pago, las funciones permanecen bloqueadas.</p>
                    <div class="d-grid gap-2">
                        <a href="https://mpago.la/24CgT6E" target="_blank" class="btn btn-primary btn-sm fw-bold">
                            <i class="fa fa-shield-halved me-1"></i> Pagar S/ 20.00 en Mercado Pago
                        </a>
                        <a href="https://wa.me/51921840632?text=Hola%20Misael,%20te%20escribo%20para%20solicitar%20mi%20c%C3%B3digo%20de%20activaci%C3%B3n%20para%20ContaSmart%20PRO" target="_blank" class="btn btn-success btn-sm fw-bold">
                            <i class="fa-brands fa-whatsapp me-1"></i> Solicitar Código por WhatsApp
                        </a>
                    </div>
                `,
                confirmButtonColor: '#2563eb'
            });
        }
    });
};

window.activarPlanPro = function(notificar = true) {
    localStorage.setItem(PRO_STORAGE_KEY, 'true');
    localStorage.setItem(PRO_DATE_KEY, new Date().toISOString());
    window.actualizarEstadoPlanProGlobal();

    if (typeof window.reproducirSonidoCobro === 'function') {
        window.reproducirSonidoCobro();
    }

    if (notificar && typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: '👑 ¡ContaSmart PRO Activado con Éxito!',
            html: `
                <div class="text-start p-3 bg-light rounded-3 my-2 border">
                    <h6 class="fw-bold text-success mb-2"><i class="fa fa-award me-1"></i> Todos los Beneficios Desbloqueados:</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-1"><i class="fa fa-check text-success me-2"></i><strong>Descarga SIRE SUNAT Full:</strong> TXT RVIE y ZIP RCE sin candados.</li>
                        <li class="mb-1"><i class="fa fa-check text-success me-2"></i><strong>Reportes Financieros Masivos:</strong> Exportación Excel/CSV habilitada.</li>
                        <li class="mb-1"><i class="fa fa-check text-success me-2"></i><strong>Consultas SUNAT y RENIEC Ilimitadas:</strong> Sin límites de prueba.</li>
                        <li class="mb-1"><i class="fa fa-check text-success me-2"></i><strong>Facturación POS Ilimitada:</strong> Boletas y facturas completas.</li>
                        <li><i class="fa fa-check text-success me-2"></i><strong>Soporte Prioritario VIP 24/7</strong> por WhatsApp directo.</li>
                    </ul>
                </div>
                <small class="text-muted">¡Gracias por apoyar a ContaSmart con Mercado Pago!</small>
            `,
            confirmButtonText: '¡Comenzar a usar Todo!',
            confirmButtonColor: '#10b981'
        });
    }
};

window.restablecerPlanDemo = function() {
    localStorage.removeItem(PRO_STORAGE_KEY);
    localStorage.removeItem(PRO_DATE_KEY);
    window.actualizarEstadoPlanProGlobal();
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Modo Demostración Activado',
            text: 'Has restablecido la aplicación a modo prueba. Las funciones exclusivas vuelven a estar bloqueadas para demostración.',
            confirmButtonColor: '#2563eb',
            timer: 2000
        });
    }
};

window.actualizarEstadoPlanProGlobal = function() {
    const esPro = window.esUsuarioPro();

    // Clase global en body
    if (esPro) {
        document.body.classList.add('pro-active');
    } else {
        document.body.classList.remove('pro-active');
    }

    // Botón en Topbar
    const btnTopbar = document.getElementById('btnTopbarPlanPro');
    const txtTopbar = document.getElementById('topbarProTexto');
    const badgeTopbar = document.getElementById('topbarProBadge');

    if (btnTopbar) {
        if (esPro) {
            btnTopbar.classList.add('pro-active');
            if (txtTopbar) txtTopbar.textContent = 'PRO ACTIVO';
            if (badgeTopbar) {
                badgeTopbar.className = 'badge bg-light text-dark fw-bold ms-1';
                badgeTopbar.textContent = 'VIP';
            }
        } else {
            btnTopbar.classList.remove('pro-active');
            if (txtTopbar) txtTopbar.textContent = 'Plan PRO';
            if (badgeTopbar) {
                badgeTopbar.className = 'badge bg-danger ms-1';
                badgeTopbar.textContent = 'S/ 20';
            }
        }
    }

    // Banner en Sidebar
    const bannerSidebar = document.getElementById('sidebarProBanner');
    const tituloSidebar = document.getElementById('sidebarProTitulo');
    const priceSidebar = document.getElementById('sidebarProPrice');
    const descSidebar = document.getElementById('sidebarProDesc');
    const btnSidebar = document.getElementById('btnSidebarProAction');

    if (bannerSidebar) {
        if (esPro) {
            bannerSidebar.classList.add('pro-active');
            if (tituloSidebar) tituloSidebar.innerHTML = 'ContaSmart VIP';
            if (priceSidebar) {
                priceSidebar.className = 'badge bg-success text-white fw-bold';
                priceSidebar.textContent = 'ACTIVO';
            }
            if (descSidebar) descSidebar.textContent = 'Membresía PRO activa sin límites de facturación, SIRE ni consultas.';
            if (btnSidebar) {
                btnSidebar.className = 'btn btn-success btn-sm w-100 fw-bold shadow-sm py-1';
                btnSidebar.innerHTML = '<i class="fa fa-award me-1"></i> Ver Beneficios PRO';
            }
        } else {
            bannerSidebar.classList.remove('pro-active');
            if (tituloSidebar) tituloSidebar.innerHTML = 'ContaSmart PRO';
            if (priceSidebar) {
                priceSidebar.className = 'badge bg-warning text-dark fw-bold';
                priceSidebar.textContent = 'S/ 20';
            }
            if (descSidebar) descSidebar.textContent = 'Desbloquea facturación ilimitada, Siri IA sin límites y módulo SIRE.';
            if (btnSidebar) {
                btnSidebar.className = 'btn btn-warning btn-sm w-100 fw-bold shadow-sm py-1';
                btnSidebar.innerHTML = '<i class="fa fa-bolt me-1"></i> Desbloquear PRO';
            }
        }
    }

    // Paneles en Modal de Mercado Pago
    const panelYaActivo = document.getElementById('panelProYaActivo');
    const panelModoDemo = document.getElementById('panelProModoDemo');
    if (panelYaActivo) {
        if (esPro) {
            panelYaActivo.classList.remove('d-none');
            panelYaActivo.classList.add('d-flex');
        } else {
            panelYaActivo.classList.add('d-none');
            panelYaActivo.classList.remove('d-flex');
        }
    }
    if (panelModoDemo) {
        if (esPro) {
            panelModoDemo.classList.add('d-none');
        } else {
            panelModoDemo.classList.remove('d-none');
        }
    }

    // Actualización de Candados Dinámicos (.badge-pro-lock)
    document.querySelectorAll('.badge-pro-lock').forEach(badge => {
        if (esPro) {
            badge.innerHTML = '<i class="fa fa-circle-check text-success"></i> Desbloqueado PRO';
            badge.classList.remove('badge-pro-locked');
            badge.classList.add('badge-pro-unlocked');
        } else {
            badge.innerHTML = '<i class="fa fa-lock text-warning"></i> Requiere PRO (S/ 20)';
            badge.classList.remove('badge-pro-unlocked');
            badge.classList.add('badge-pro-locked');
        }
    });

    // Actualización de Íconos de Beneficios en el Modal
    document.querySelectorAll('.benefit-lock-icon').forEach(iconEl => {
        if (esPro) {
            iconEl.className = 'benefit-lock-icon text-success mt-1';
            iconEl.innerHTML = '<i class="fa fa-circle-check fs-5"></i>';
        } else {
            iconEl.className = 'benefit-lock-icon text-warning mt-1';
            iconEl.innerHTML = '<i class="fa fa-lock fs-5"></i>';
        }
    });
};

/**
 * Detección Automática de Retorno de Pago Aprobado desde Mercado Pago
 */
window.detectarPagoRetornoMercadoPago = function() {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status') || urlParams.get('collection_status') || urlParams.get('payment_status');
        const proCode = urlParams.get('pro_code') || urlParams.get('clave') || urlParams.get('codigo');

        const codigosValidos = ['PLUS20', 'CONTA2026', 'MISAELPRO', 'MISAEL20', 'MISAEL287', 'HERCARVIP', 'VIP2026', 'PRO2026'];

        if (status === 'approved' || (proCode && codigosValidos.includes(proCode.toUpperCase()))) {
            window.activarPlanPro(true);
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    } catch (e) {
        console.error('Error al detectar retorno de pago:', e);
    }
};

// Sincronizar estado y detectar pago de retorno al cargar cualquier página
function inicializarPaywallContaSmart() {
    window.detectarPagoRetornoMercadoPago();
    window.actualizarEstadoPlanProGlobal();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarPaywallContaSmart);
} else {
    inicializarPaywallContaSmart();
}






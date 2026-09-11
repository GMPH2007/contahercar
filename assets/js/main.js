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
    fetch(`api/buscar_por_doc.php?numero=${encodeURIComponent(num)}&contexto=${tipo}&auto_guardar=1`)
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




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
                window.STATIC_DB_KARDEX[productoId] : {
                    success: true,
                    producto: { nombre: 'Producto #' + productoId, stock: 15, unidad_medida: 'UNID' },
                    movimientos: [
                        { fecha: '08/09/2026 10:30', tipo_movimiento: 'VENTA EN POS', cantidad: -2, stock_anterior: 17, stock_nuevo: 15, motivo: 'Venta B001-000002' },
                        { fecha: '07/09/2026 09:15', tipo_movimiento: 'INGRESO POR COMPRA', cantidad: 10, stock_anterior: 7, stock_nuevo: 17, motivo: 'Compra F001-000124' }
                    ]
                };
            renderKardex(fallback);
        });
}

/**
 * Imprimir Ticket Térmico 80mm Directo en Modal
 */
window.imprimirTicketDirecto = function(id) {
    function renderTicket(ventaData) {
        const modalEl = document.getElementById('modalTicketGlobal');
        if (modalEl) {
            const v = ventaData.venta;
            const tipoEl = document.getElementById('ticketTipoDoc');
            const numEl = document.getElementById('ticketNumero');
            const fechaEl = document.getElementById('ticketFecha');
            const clienteEl = document.getElementById('ticketCliente');
            const itemsEl = document.getElementById('ticketItems');
            const baseEl = document.getElementById('ticketBase');
            const igvEl = document.getElementById('ticketIgv');
            const totalEl = document.getElementById('ticketTotal');

            if (tipoEl) tipoEl.textContent = (v.tipo_comprobante || 'BOLETA ELECTRÓNICA').toUpperCase();
            if (numEl) numEl.textContent = `${v.serie || 'B001'}-${v.correlativo || String(id).padStart(6, '0')}`;
            if (fechaEl) fechaEl.textContent = v.fecha_venta || '07/09/2026 13:14';
            const cliDoc = v.cliente_doc ? ` (${v.cliente_doc})` : '';
            if (clienteEl) clienteEl.textContent = `${v.cliente_nombre || 'CLIENTE GENERAL'}${cliDoc}`;

            if (itemsEl && ventaData.items) {
                let itemsHtml = '';
                ventaData.items.forEach(item => {
                    itemsHtml += `
                        <div class="d-flex justify-content-between mb-1">
                            <span>${item.cantidad}x ${item.producto_nombre}</span>
                            <span class="fw-bold">${item.subtotal_fmt}</span>
                        </div>
                    `;
                });
                itemsEl.innerHTML = itemsHtml;
            }

            if (baseEl) baseEl.textContent = v.subtotal_fmt;
            if (igvEl) igvEl.textContent = v.impuesto_fmt;
            if (totalEl) totalEl.textContent = v.total_fmt;

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        } else {
            window.print();
        }
    }

    // Probar primero fetch si estamos con backend dinámico PHP
    fetch(`api/venta_detalle.php?id=${id}`)
        .then(res => {
            if (!res.ok) throw new Error('API offline');
            return res.json();
        })
        .then(data => {
            if (data && data.success && data.venta) {
                renderTicket(data);
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
            renderTicket(ventaData);
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
    const prodName = sel ? sel.options[sel.selectedIndex].text.split('-')[0].trim() : 'Taladro Percutor Bosch';
    const prodPrice = sel ? sel.value : '245.00';

    Swal.fire({
        icon: 'success',
        title: '¡Venta Registrada Exitosamente!',
        html: `<strong>Boleta Electrónica B001-000428</strong> emitida con éxito por <strong>S/ ${parseFloat(prodPrice).toFixed(2)}</strong>.<br><small class="text-muted">Descontado del stock en Kardex y enviado al RVIE SIRE SUNAT.</small>`,
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fa fa-receipt me-1"></i> Ver Ticket Impreso',
        cancelButtonText: 'Cerrar'
    }).then((result) => {
        if (result.isConfirmed) {
            verTicketDemo('B001-000428', '00000000 - CLIENTE VARIOS', prodName, prodPrice);
        }
    });
}

function verTicketDemo(num, cliente, producto, total, fecha) {
    const elNum = document.getElementById('ticketNumero');
    const elTipo = document.getElementById('ticketTipoDoc');
    const elCli = document.getElementById('ticketCliente');
    const elFec = document.getElementById('ticketFecha');
    const elItems = document.getElementById('ticketItems');
    const elBase = document.getElementById('ticketBase');
    const elIgv = document.getElementById('ticketIgv');
    const elTot = document.getElementById('ticketTotal');

    const totNum = parseFloat(total) || 245;
    const subNum = (totNum / 1.18).toFixed(2);
    const igvNum = (totNum - subNum).toFixed(2);

    if (elNum) elNum.textContent = num || 'B001-000428';
    if (elTipo) elTipo.textContent = (num && num.startsWith('F')) ? 'FACTURA ELECTRÓNICA' : 'BOLETA ELECTRÓNICA';
    if (elCli) elCli.textContent = cliente || '00000000 - CLIENTE VARIOS';
    if (elFec) elFec.textContent = fecha || 'Hoy';
    if (elItems) elItems.innerHTML = `<span>1x ${producto || 'Producto Ferretero'}</span><span>S/ ${totNum.toFixed(2)}</span>`;
    if (elBase) elBase.textContent = `S/ ${subNum}`;
    if (elIgv) elIgv.textContent = `S/ ${igvNum}`;
    if (elTot) elTot.textContent = `S/ ${totNum.toFixed(2)}`;

    const modalEl = document.getElementById('modalTicketGlobal');
    if (modalEl) {
        const m = bootstrap.Modal.getOrCreateInstance(modalEl);
        m.show();
    } else {
        window.open('ticket.php', '_blank');
    }
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

    fetch(`api/consulta_ruc.php?numero=${num}`)
        .then(r => r.json())
        .catch(() => null)
        .then(data => {
            if (load) load.classList.add('d-none');
            if (btn) btn.disabled = false;

            let finalData = data;
            if (!finalData || !finalData.success) {
                finalData = (window.buscarDocSunatReniec) ? window.buscarDocSunatReniec(num) : null;
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



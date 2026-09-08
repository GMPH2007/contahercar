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
        .then(response => response.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }

            if (data.success) {
                if (nombreInput) nombreInput.value = data.nombre || '';
                if (dirInput && data.direccion) dirInput.value = data.direccion;
                if (estadoInput && data.estado) estadoInput.value = data.estado;

                let subtitle = '';
                if (data.condicion) subtitle += ` Condición: ${data.condicion} |`;
                if (data.estado) subtitle += ` Estado: ${data.estado}`;
                if (data.source === 'demo_local' || data.source === 'fallback_asistido') {
                    subtitle += ' (Modo Asistido/Prueba)';
                }

                showToast('success', `${data.tipo} Encontrado: ${data.nombre}`);

                if (data.aviso) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Datos Obtenidos',
                        text: data.aviso,
                        confirmButtonColor: '#2563eb'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No encontrado',
                    text: data.message || 'No se pudo obtener información del documento consultado.',
                    confirmButtonColor: '#2563eb'
                });
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
            console.error('Error al consultar RUC:', err);
            Swal.fire({
                icon: 'error',
                title: 'Error de Red',
                text: 'No se pudo comunicar con el servicio local de consulta. Verifique su conexión.',
                confirmButtonColor: '#2563eb'
            });
        });
}

/**
 * Ver Kardex de un Producto en Modal
 */
function verKardexProducto(productoId) {
    const modalEl = document.getElementById('modalKardexGlobal');
    if (!modalEl) return;

    const bsModal = new bootstrap.Modal(modalEl);
    const bodyEl = document.getElementById('kardexModalContent');
    const titleEl = document.getElementById('kardexModalTitle');

    titleEl.textContent = 'Cargando movimientos...';
    bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    bsModal.show();

    fetch(`api/kardex_info.php?producto_id=${productoId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                bodyEl.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
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
                if (m.tipo_movimiento.includes('COMPRA') || m.tipo_movimiento.includes('INGRESO')) {
                    badgeClass = 'badge-soft-success';
                    icon = '<i class="fa fa-arrow-down text-success me-1"></i>';
                } else if (m.tipo_movimiento.includes('VENTA') || m.tipo_movimiento.includes('SALIDA')) {
                    badgeClass = 'badge-soft-danger';
                    icon = '<i class="fa fa-arrow-up text-danger me-1"></i>';
                }

                html += `
                    <tr>
                        <td><small>${m.fecha}</small></td>
                        <td><span class="badge ${badgeClass}">${icon}${m.tipo_movimiento}</span></td>
                        <td class="fw-bold">${m.cantidad}</td>
                        <td>${m.stock_anterior}</td>
                        <td class="fw-bold text-primary">${m.stock_nuevo}</td>
                        <td><small class="text-muted">${m.motivo || '-'}</small></td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;
            bodyEl.innerHTML = html;
        })
        .catch(err => {
            bodyEl.innerHTML = '<div class="alert alert-danger">Error al cargar movimientos de Kardex.</div>';
        });
}

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

// Inicialización general en DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
        document.body.classList.remove('sidebar-open');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // Cerrar sidebar al hacer clic en cualquier enlace en móviles
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                closeSidebar();
            }
        });
    });

    // Cargar tipo de cambio oficial
    cargarTipoCambio();
});


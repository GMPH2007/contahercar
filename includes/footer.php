        </main> <!-- Fin .app-content -->
    </div> <!-- Fin .app-main -->
</div> <!-- Fin .app-wrapper -->

<!-- Barra Flotante Inferior Moderna para Celular (Dock Móvil de Alta Ergonomía) -->
<?php $curPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<nav class="mobile-bottom-dock d-lg-none" aria-label="Navegación Móvil Rápida">
    <a href="index.php" class="dock-item <?= ($curPage == 'index.php' || $curPage == '') ? 'active' : '' ?>">
        <i class="fa fa-chart-pie"></i>
        <span>Inicio</span>
    </a>
    <a href="consulta_sunat.php" class="dock-item <?= ($curPage == 'consulta_sunat.php') ? 'active' : '' ?>" title="Consultar RUC y DNI SUNAT/RENIEC">
        <i class="fa fa-building-flag"></i>
        <span>RUC/DNI</span>
    </a>
    <a href="venta_nueva.php" class="dock-item dock-item-pos <?= ($curPage == 'venta_nueva.php') ? 'active' : '' ?>" title="Punto de Venta POS Móvil">
        <div class="dock-pos-icon">
            <i class="fa fa-cash-register"></i>
        </div>
        <span>POS</span>
    </a>
    <button type="button" class="dock-item" onclick="if(window.openSiri) openSiri(); else if(window.ContaSmartAI) ContaSmartAI.open();" title="Hablar con Siri ContaSmart">
        <i class="fa fa-microphone-lines text-info"></i>
        <span>Siri AI</span>
    </button>
    <button type="button" class="dock-item" onclick="window.openSidebar ? window.openSidebar() : (document.getElementById('sidebarToggleBtn') ? document.getElementById('sidebarToggleBtn').click() : null)" title="Menú Principal (3 Rayitas)">
        <i class="fa fa-bars"></i>
        <span>Menú</span>
    </button>
</nav>

<!-- Modal Global para Kardex de Productos -->
<div class="modal fade" id="modalKardexGlobal" tabindex="-1" aria-labelledby="kardexModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fs-6" id="kardexModalTitle">Historial de Kardex</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="kardexModalContent">
                <!-- Se llena vía AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Global Interactivo para POS Rápido / Flotante -->
<div class="modal fade" id="modalPosGlobal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fs-6"><i class="fa fa-cash-register me-2"></i>Punto de Venta (POS) Rápido Flotante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Cliente:</label>
                    <input type="text" class="form-control" value="00000000 - CLIENTE VARIOS / GENERAL">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Producto a Facturar:</label>
                    <select class="form-select" id="posSelectProd">
                        <option value="245">Taladro Percutor Bosch - S/. 245.00</option>
                        <option value="285">Amoladora Angular Dewalt - S/. 285.00</option>
                        <option value="24">Cinta Métrica 5m Stanley - S/. 24.00</option>
                        <option value="160">Cable Mellizo 2x14 Indeco - S/. 160.00</option>
                    </select>
                </div>
                <div class="d-flex justify-content-between p-3 bg-light rounded-3 mb-3 border">
                    <span class="fw-bold">Total a Cobrar:</span>
                    <strong class="text-success fs-5" id="posTotalLabel">S/. 245.00</strong>
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success py-2 fw-bold" onclick="simulateSale()">
                        <i class="fa fa-print me-1"></i> Emitir Boleta Rápida
                    </button>
                    <a href="venta_nueva.php" class="btn btn-outline-primary py-2 fw-semibold">
                        <i class="fa fa-arrow-up-right-from-square me-1"></i> Ir a Pantalla Completa de POS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Interactivo Global: Consulta RUC / DNI SUNAT & RENIEC -->
<div class="modal fade" id="modalConsultaRucGlobal" tabindex="-1" aria-labelledby="modalRucTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg border-0">
            <div class="modal-header bg-dark text-white p-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-warning bg-opacity-25 rounded-3 text-warning">
                        <i class="fa fa-building-flag fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fs-6 fw-bold mb-0" id="modalRucTitle">Consulta Oficial RUC & DNI</h5>
                        <small class="text-white-50" style="font-size: 0.72rem;">Sincronizado con SUNAT & RENIEC (En Vivo)</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="docTypeRadio" id="docTypeRuc" checked onchange="switchDocType('RUC')">
                    <label class="btn btn-outline-primary fw-semibold" for="docTypeRuc">RUC (11 dígitos)</label>
                    
                    <input type="radio" class="btn-check" name="docTypeRadio" id="docTypeDni" onchange="switchDocType('DNI')">
                    <label class="btn btn-outline-primary fw-semibold" for="docTypeDni">DNI (8 dígitos)</label>
                </div>

                <div class="input-group mb-3 shadow-sm">
                    <span class="input-group-text bg-light text-muted"><i class="fa fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control form-control-lg fs-6 font-monospace" id="modalDocNumber" placeholder="Ingresa RUC (Ej: 20601234567)" maxlength="11" inputmode="numeric" value="20601234567">
                    <button class="btn btn-primary fw-bold px-3" type="button" id="btnDoConsultaDoc" onclick="ejecutarConsultaModal()">
                        <i class="fa fa-search me-1"></i> Consultar
                    </button>
                </div>

                <div id="modalConsultaLoading" class="text-center py-3 d-none">
                    <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                    <span class="small text-muted">Consultando padrón tributario oficial...</span>
                </div>

                <!-- Resultado de la Consulta -->
                <div id="modalConsultaResult" class="p-3 bg-light rounded-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-success" id="resDocBadge"><i class="fa fa-circle-check me-1"></i>RUC SUNAT Verificado</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold" id="resDocEstado">ACTIVO</span>
                    </div>
                    <h6 class="fw-bold text-dark mb-1" id="resDocNombre">CORPORACIÓN INDUSTRIAL HERCAR S.A.C.</h6>
                    <div class="small text-muted mb-2" id="resDocDetalles">
                        <div><strong>Número:</strong> <span id="resDocNum">20601234567</span> | <strong>Condición:</strong> <span class="text-success fw-bold" id="resDocCondicion">HABIDO</span></div>
                        <div><strong>Dirección:</strong> <span id="resDocDireccion">Av. Nicolás Arriola 1450, Urb. Santa Catalina, La Victoria, Lima</span></div>
                        <div><strong>Régimen:</strong> <span id="resDocRegimen">Régimen MYPE Tributario</span></div>
                    </div>
                    <div class="d-grid gap-2 pt-2 border-top">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-primary w-50 fw-bold" onclick="guardarDocModal('cliente')">
                                <i class="fa fa-user-plus me-1"></i> Guardar Cliente
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary w-50 fw-bold" onclick="guardarDocModal('proveedor')">
                                <i class="fa fa-truck-moving me-1"></i> Guardar Proveedor
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-success fw-bold" onclick="facturarDocModal()">
                            <i class="fa fa-cash-register me-1"></i> Facturar en POS a este Contribuyente
                        </button>
                    </div>
                </div>
            </div>
        </div>
<!-- Modal Global: Ticket Térmico Impreso Interactivo -->
<div class="modal fade" id="modalTicketGlobal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
        <div class="modal-content rounded-4 shadow-lg border-0 overflow-hidden">
            <div class="modal-header bg-dark text-white p-2">
                <h6 class="modal-title small fw-bold mb-0"><i class="fa fa-receipt me-1"></i>Ticket Electrónico Impreso</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-white" id="ticketPrintArea" style="font-family: 'Courier New', Courier, monospace; font-size: 0.82rem; color: #111;">
                <div class="text-center mb-3">
                    <div class="fw-bold fs-6">HERCAR COMERCIAL S.A.C.</div>
                    <div>RUC: 20601234567</div>
                    <div class="small">Av. Principal 123 - Lima, Perú</div>
                    <div class="border-bottom my-2"></div>
                    <div class="fw-bold text-uppercase" id="ticketTipoDoc">BOLETA ELECTRÓNICA</div>
                    <div class="fw-bold" id="ticketNumero">B001-000428</div>
                    <div class="text-muted small" id="ticketFecha">09/09/2026 18:40</div>
                </div>
                <div class="mb-2">
                    <strong>Cliente:</strong> <span id="ticketCliente">CLIENTE VARIOS</span>
                </div>
                <div class="border-bottom border-top py-2 my-2">
                    <div class="d-flex justify-content-between fw-bold mb-1">
                        <span>Cant. / Detalle</span>
                        <span>Total</span>
                    </div>
                    <div class="d-flex justify-content-between" id="ticketItems">
                        <span>1x Taladro Bosch 650W</span>
                        <span>S/ 245.00</span>
                    </div>
                </div>
                <div class="text-end mb-3">
                    <div>Op. Gravada: <span id="ticketBase">S/ 207.63</span></div>
                    <div>IGV (18%): <span id="ticketIgv">S/ 37.37</span></div>
                    <div class="fs-6 fw-bold border-top pt-1 mt-1">TOTAL: <span id="ticketTotal">S/ 245.00</span></div>
                </div>
                <div class="text-center">
                    <div class="p-2 border rounded d-inline-block bg-light mb-2">
                        <i class="fa fa-qrcode fa-4x text-dark"></i>
                    </div>
                    <div class="small text-muted" style="font-size: 0.65rem;">
                        Representación impresa de Comprobante Electrónico.<br>
                        Autorizado por SUNAT mediante RS 097-2012.<br>
                        <strong>Hash: a7F8e9K2mP9==</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-2 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success btn-sm fw-bold" onclick="window.print()">
                    <i class="fa fa-print me-1"></i> Imprimir Ticket
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom Main JS -->
<script src="assets/js/main.js?v=<?= time() ?>"></script>

<!-- ContaSmart AI Assistant Siri (Cache-busting) -->
<script src="assets/js/conta_ai.js?v=<?= time() ?>"></script>

<?php
// Mostrar alertas flash automáticas si existen
$flash = getFlash();
if ($flash):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $flash['type'] ?>',
        title: '<?= addslashes($flash['title']) ?>',
        text: '<?= addslashes($flash['message']) ?>',
        confirmButtonColor: '#2563eb'
    });
});
</script>
<?php endif; ?>

</body>
</html>


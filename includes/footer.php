        </main> <!-- Fin .app-content -->
    </div> <!-- Fin .app-main -->
</div> <!-- Fin .app-wrapper -->

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

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom Main JS -->
<script src="assets/js/main.js?v=<?= time() ?>"></script>

<!-- ContaSmart Onboarding Tour & AI Assistant (Cache-busting) -->
<script src="assets/js/tour.js?v=<?= time() ?>"></script>
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


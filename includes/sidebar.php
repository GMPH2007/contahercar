<?php
// Asegurar variables
$currentPage = basename($_SERVER['PHP_SELF']);
$lowStockBadge = ($quickCounters['low_stock'] > 0) ? '<span class="badge bg-danger ms-auto">' . $quickCounters['low_stock'] . '</span>' : '';
?>
<aside class="app-sidebar">
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="index.php" class="brand-logo">
            <i class="fa fa-cubes"></i>
            <span>ContaHercar</span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" id="sidebarCloseBtn" aria-label="Cerrar"></button>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-header">Principal</li>
        <li class="sidebar-item">
            <a href="index.php" class="sidebar-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
                <i class="fa fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="consulta_sunat.php" class="sidebar-link <?= ($currentPage === 'consulta_sunat.php') ? 'active' : '' ?>">
                <i class="fa fa-building-flag text-warning"></i>
                <span>Consulta RUC / SUNAT</span>
                <span class="badge bg-primary ms-auto">OFICIAL</span>
            </a>
        </li>

        <li class="sidebar-header">Operaciones</li>
        <li class="sidebar-item">
            <a href="venta_nueva.php" class="sidebar-link <?= ($currentPage === 'venta_nueva.php') ? 'active' : '' ?>">
                <i class="fa fa-cash-register"></i>
                <span>Punto de Venta</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="ventas.php" class="sidebar-link <?= ($currentPage === 'ventas.php') ? 'active' : '' ?>">
                <i class="fa fa-receipt"></i>
                <span>Historial de Ventas</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="compras.php" class="sidebar-link <?= ($currentPage === 'compras.php' || $currentPage === 'compra_nueva.php') ? 'active' : '' ?>">
                <i class="fa fa-cart-arrow-down"></i>
                <span>Compras & Gastos</span>
            </a>
        </li>

        <li class="sidebar-header">Almacén & Catálogo</li>
        <li class="sidebar-item">
            <a href="inventario.php" class="sidebar-link <?= ($currentPage === 'inventario.php') ? 'active' : '' ?>">
                <i class="fa fa-boxes-stacked"></i>
                <span>Inventario</span>
                <?= $lowStockBadge ?>
            </a>
        </li>

        <li class="sidebar-header">Contactos & Directorio</li>
        <li class="sidebar-item">
            <a href="clientes.php" class="sidebar-link <?= ($currentPage === 'clientes.php') ? 'active' : '' ?>">
                <i class="fa fa-users"></i>
                <span>Clientes (RUC/DNI)</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="proveedores.php" class="sidebar-link <?= ($currentPage === 'proveedores.php') ? 'active' : '' ?>">
                <i class="fa fa-truck-moving"></i>
                <span>Proveedores (RUC)</span>
            </a>
        </li>

        <li class="sidebar-header">Libros & Análisis</li>
        <li class="sidebar-item">
            <a href="sire.php" class="sidebar-link <?= ($currentPage === 'sire.php') ? 'active' : '' ?>">
                <i class="fa fa-book-bookmark text-primary"></i>
                <span>SIRE SUNAT (RVIE/RCE)</span>
                <span class="badge bg-success ms-auto">SIRE</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="reportes.php" class="sidebar-link <?= ($currentPage === 'reportes.php') ? 'active' : '' ?>">
                <i class="fa fa-file-invoice-dollar"></i>
                <span>Reportes & Cierres</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="configuracion.php" class="sidebar-link <?= ($currentPage === 'configuracion.php') ? 'active' : '' ?>">
                <i class="fa fa-sliders"></i>
                <span>Configuración & API</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <i class="fa fa-shield-halved text-success"></i>
        <span>ContaHercar v1.0 • Fiable</span>
    </div>
</aside>


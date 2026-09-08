<?php
require_once __DIR__ . '/../config/app.php';
$cfg = getSystemConfig();
$quickCounters = getQuickCounters();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Panel de Control') ?> - <?= htmlspecialchars($cfg['nombre_empresa']) ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-wrapper">
    <!-- Barra Lateral (Sidebar) -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Telón de Fondo para Móviles (Backdrop) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Contenido Principal -->
    <div class="app-main">
        <!-- Barra Superior (Topbar) -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggleBtn" aria-label="Abrir Menú">
                    <i class="fa fa-bars"></i>
                </button>
                <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Panel de Control') ?></h1>
            </div>

            <div class="topbar-right">
                <!-- Indicador de Tipo de Cambio SUNAT (Decolecta) -->
                <div class="badge bg-light text-secondary border d-none d-lg-flex align-items-center gap-2 py-2 px-3" id="topbarTipoCambio" title="Tipo de Cambio Oficial SUNAT del día">
                    <i class="fa fa-dollar-sign text-success"></i>
                    <span class="small fw-semibold" id="tcTexto">USD SUNAT: C: S/. ... | V: S/. ...</span>
                </div>

                <!-- Indicador de Stock Bajo si hay alertas -->
                <?php if ($quickCounters['low_stock'] > 0): ?>
                    <a href="inventario.php?filtro=stock_bajo" class="btn btn-sm btn-outline-warning d-flex align-items-center gap-2" title="Productos con stock bajo">
                        <i class="fa fa-exclamation-triangle text-warning"></i>
                        <span class="d-none d-md-inline">Stock Crítico:</span>
                        <span class="badge bg-warning text-dark"><?= $quickCounters['low_stock'] ?></span>
                    </a>
                <?php endif; ?>

                <!-- Acceso Rápido a Nueva Venta / POS -->
                <a href="venta_nueva.php" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                    <i class="fa fa-cash-register"></i>
                    <span class="d-none d-sm-inline">Punto de Venta</span>
                </a>

                <!-- Acceso Rápido a Nueva Compra -->
                <a href="compra_nueva.php" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                    <i class="fa fa-cart-arrow-down"></i>
                    <span class="d-none d-sm-inline">+ Compra</span>
                </a>
            </div>
        </header>

        <!-- Contenido de la Página -->
        <main class="app-content">


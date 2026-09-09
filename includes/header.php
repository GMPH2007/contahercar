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

    <!-- Custom CSS (con cache-buster dinámico) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
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
                <button type="button" class="sidebar-toggle" id="sidebarToggleBtn" onclick="if(window.toggleSidebar) window.toggleSidebar(event)" aria-label="Abrir Menú (3 rayitas)">
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

                <!-- Acceso a Siri ContaSmart (Asistente de Voz Inteligente) -->
                <button type="button" class="btn btn-sm btn-primary btn-open-ai d-flex align-items-center gap-1 shadow-sm" id="tourAiAssistantBtn" title="Hablar con Siri ContaSmart (Comandos de Voz)">
                    <i class="fa fa-microphone text-warning"></i>
                    <span class="fw-bold d-none d-sm-inline">Siri</span>
                </button>

                <!-- Acceso Rápido a Nueva Venta / POS -->
                <a href="venta_nueva.php" class="btn btn-sm btn-success d-flex align-items-center gap-1 shadow-sm">
                    <i class="fa fa-cash-register"></i>
                    <span class="d-none d-sm-inline">POS</span>
                </a>

                <!-- Acceso Rápido a Nueva Compra -->
                <a href="compra_nueva.php" class="btn btn-sm btn-outline-secondary d-none d-lg-flex align-items-center gap-1">
                    <i class="fa fa-cart-arrow-down"></i>
                    <span>+ Compra</span>
                </a>

                <!-- Menú de 3 Puntos (Opciones Rápidas) -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center p-2 rounded-3 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Más opciones del sistema (3 puntos)" style="width: 36px; height: 36px;">
                        <i class="fa fa-ellipsis-vertical text-dark"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-1">
                        <li>
                            <a class="dropdown-item py-2" href="consulta_sunat.php">
                                <i class="fa fa-building-flag text-warning me-2"></i> Consultar RUC / DNI
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="javascript:void(0)" onclick="ContaSmartAI.open()">
                                <i class="fa fa-microphone text-primary me-2"></i> Hablar con Siri
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="venta_nueva.php">
                                <i class="fa fa-cash-register text-success me-2"></i> Punto de Venta POS
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="inventario.php?filtro=stock_bajo">
                                <i class="fa fa-boxes-stacked text-danger me-2"></i> Ver Stock Crítico
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2" href="https://sire.sunat.gob.pe/" target="_blank">
                                <i class="fa fa-globe text-primary me-2"></i> Portal SIRE SUNAT
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Botón Flotante Móvil Siri / ContaVoz (FAB para Celulares y Tablets) -->
        <button type="button" class="btn-fab-siri btn-open-ai" title="Hablar con Siri ContaSmart (ContaVoz)" onclick="ContaSmartAI.open()">
            <i class="fa fa-microphone"></i>
        </button>

        <!-- Contenido de la Página -->
        <main class="app-content">


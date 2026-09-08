<?php
require_once __DIR__ . '/config/app.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Comprobante no especificado.");
}

$pdo = getDBConnection();
$cfg = getSystemConfig();

$stmtV = $pdo->prepare("SELECT v.*, c.nombre_razon_social as cliente_nombre, c.num_doc as cliente_doc, c.direccion as cliente_dir, c.tipo_doc as cliente_tipo_doc 
    FROM ventas v 
    INNER JOIN clientes c ON v.cliente_id = c.id 
    WHERE v.id = ?");
$stmtV->execute([$id]);
$venta = $stmtV->fetch();

if (!$venta) {
    die("Comprobante de venta no encontrado.");
}

$stmtD = $pdo->prepare("SELECT dv.*, p.nombre as producto_nombre, p.codigo_barra 
    FROM detalle_ventas dv 
    INNER JOIN productos p ON dv.producto_id = p.id 
    WHERE dv.venta_id = ?");
$stmtD->execute([$id]);
$items = $stmtD->fetchAll();

$autoPrint = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= htmlspecialchars($venta['serie']) ?>-<?= str_pad($venta['correlativo'], 6, '0', STR_PAD_LEFT) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Courier New', Courier, monospace;
            color: #000;
        }
        .ticket-container {
            width: 80mm;
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        .ticket-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .ticket-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .ticket-info {
            font-size: 11px;
            margin: 2px 0;
        }
        .ticket-table {
            width: 100%;
            font-size: 11px;
            border-collapse: collapse;
            margin: 8px 0;
        }
        .ticket-table th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            text-align: left;
        }
        .ticket-table td {
            padding: 4px 0;
            vertical-align: top;
        }
        .ticket-totals {
            border-top: 1px dashed #000;
            padding-top: 6px;
            font-size: 12px;
        }
        .ticket-footer {
            border-top: 1px dashed #000;
            padding-top: 8px;
            margin-top: 8px;
            text-align: center;
            font-size: 10px;
        }
        @media print {
            body {
                background: #fff;
            }
            .no-print {
                display: none !important;
            }
            .ticket-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container text-center mt-3 no-print">
    <div class="btn-group">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="fa fa-print me-1"></i> Imprimir Ticket
        </button>
        <a href="venta_nueva.php" class="btn btn-success">
            <i class="fa fa-plus me-1"></i> Nueva Venta
        </a>
        <a href="ventas.php" class="btn btn-outline-secondary">
            <i class="fa fa-list me-1"></i> Ver Historial
        </a>
    </div>
</div>

<div class="ticket-container" id="printable-ticket">
    <div class="ticket-header">
        <h4 class="ticket-title"><?= htmlspecialchars($cfg['nombre_empresa']) ?></h4>
        <div class="ticket-info">RUC: <?= htmlspecialchars($cfg['ruc_empresa']) ?></div>
        <div class="ticket-info"><?= htmlspecialchars($cfg['direccion']) ?></div>
        <div class="ticket-info">Tel: <?= htmlspecialchars($cfg['telefono']) ?></div>
    </div>

    <div class="text-center my-2">
        <div class="fw-bold" style="font-size: 13px; text-transform: uppercase;">
            <?= htmlspecialchars($venta['tipo_comprobante']) ?> ELECTRÓNICA
        </div>
        <div class="fw-bold fs-6">
            <?= htmlspecialchars($venta['serie']) ?>-<?= str_pad($venta['correlativo'], 6, '0', STR_PAD_LEFT) ?>
        </div>
        <div class="ticket-info">Fecha: <?= formatDateTime($venta['fecha_venta']) ?></div>
    </div>

    <div class="ticket-info border-top pt-1 mt-1">
        <div><strong>Cliente:</strong> <?= htmlspecialchars($venta['cliente_nombre']) ?></div>
        <div><strong><?= $venta['cliente_tipo_doc'] ?>:</strong> <?= htmlspecialchars($venta['cliente_doc']) ?></div>
        <?php if (!empty($venta['cliente_dir'])): ?>
            <div><strong>Dir:</strong> <?= htmlspecialchars($venta['cliente_dir']) ?></div>
        <?php endif; ?>
        <div><strong>Medio de Pago:</strong> <?= htmlspecialchars($venta['metodo_pago']) ?></div>
    </div>

    <table class="ticket-table">
        <thead>
            <tr>
                <th style="width: 15%;">Cant</th>
                <th style="width: 55%;">Descrip</th>
                <th style="width: 30%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= $it['cantidad'] ?></td>
                    <td>
                        <?= htmlspecialchars($it['producto_nombre']) ?><br>
                        <small style="font-size: 9px;">@ <?= formatMoney($it['precio_unitario']) ?></small>
                    </td>
                    <td style="text-align: right;"><?= formatMoney($it['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="ticket-totals">
        <div class="d-flex justify-content-between">
            <span>Op. Gravada:</span>
            <span><?= formatMoney($venta['subtotal']) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($cfg['impuesto_nombre']) ?> (<?= (float)$cfg['impuesto_porcentaje'] ?>%):</span>
            <span><?= formatMoney($venta['impuesto']) ?></span>
        </div>
        <div class="d-flex justify-content-between fw-bold fs-6 mt-1 border-top pt-1">
            <span>TOTAL:</span>
            <span><?= formatMoney($venta['total']) ?></span>
        </div>
    </div>

    <div class="ticket-footer">
        <div>¡GRACIAS POR SU COMPRA!</div>
        <div>Representación impresa de comprobante de venta</div>
        <div>ContaHercar • Sistema Confiable</div>
    </div>
</div>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
    window.print();
});
</script>
<?php endif; ?>

</body>
</html>


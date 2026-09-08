<?php
/**
 * ContaHercar - Generador y Exportador Oficial de Libros SIRE SUNAT (RVIE & RCE)
 * Estructuras oficiales SUNAT: RVIE (140400) y RCE (080400)
 */

require_once __DIR__ . '/../config/app.php';

$pdo = getDBConnection();
$cfg = getSystemConfig();

$tipo = isset($_GET['tipo']) ? strtolower(trim($_GET['tipo'])) : 'rvie'; // 'rvie' o 'rce'
$periodo = isset($_GET['periodo']) ? trim($_GET['periodo']) : date('Y-m'); // Formato YYYY-MM
$formato = isset($_GET['formato']) ? strtolower(trim($_GET['formato'])) : 'txt'; // 'txt' o 'zip'

// Validar periodo YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = date('Y-m');
}

$partesPeriodo = explode('-', $periodo);
$anio = $partesPeriodo[0];
$mes = $partesPeriodo[1];
$periodoKey = $anio . $mes; // YYYYMM

$rucEmpresa = preg_replace('/[^0-9]/', '', $cfg['ruc_empresa']);
if (strlen($rucEmpresa) !== 11) {
    $rucEmpresa = '20601234567';
}
$razonEmpresa = mb_strtoupper($cfg['nombre_empresa'], 'UTF-8');

$fechaIni = "$periodo-01 00:00:00";
$fechaFin = date('Y-m-t 23:59:59', strtotime("$periodo-01"));

// ==========================================================
// 1. RVIE - REGISTRO DE VENTAS E INGRESOS ELECTRÓNICO (140400)
// ==========================================================
if ($tipo === 'rvie') {
    $libroCodigo = '140400';
    $nombreArchivoBase = "LE{$rucEmpresa}{$periodoKey}00{$libroCodigo}021111_1";

    $stmt = $pdo->prepare("SELECT v.*, c.nombre_razon_social as cliente_nombre, c.num_doc as cliente_doc, c.tipo_doc as cliente_tipo_doc
        FROM ventas v
        INNER JOIN clientes c ON v.cliente_id = c.id
        WHERE v.fecha_venta BETWEEN ? AND ?
        ORDER BY v.fecha_venta ASC, v.id ASC");
    $stmt->execute([$fechaIni, $fechaFin]);
    $ventas = $stmt->fetchAll();

    $lineas = [];
    foreach ($ventas as $idx => $v) {
        $correlativoFmt = str_pad($v['correlativo'], 8, '0', STR_PAD_LEFT);
        
        // Código tipo comprobante SUNAT (01 Factura, 03 Boleta, etc.)
        $tipoCompSunat = '03'; // Boleta
        if ($v['tipo_comprobante'] === 'Factura') {
            $tipoCompSunat = '01';
        } elseif ($v['tipo_comprobante'] === 'Nota de Credito') {
            $tipoCompSunat = '07';
        } elseif ($v['tipo_comprobante'] === 'Nota de Debito') {
            $tipoCompSunat = '08';
        }

        // Tipo Documento de Identidad del Cliente (6 RUC, 1 DNI, 0 Sin doc/varios)
        $tipoDocCliente = '1';
        if ($v['cliente_tipo_doc'] === 'RUC' || strlen($v['cliente_doc']) === 11) {
            $tipoDocCliente = '6';
        } elseif ($v['cliente_doc'] === '00000000' || empty($v['cliente_doc'])) {
            $tipoDocCliente = '0';
        }

        // CAR: Código de Anotación de Registro (27 caracteres únicos para SIRE)
        // Estructura: RUC emisor (11) + Tipo Comprobante (2) + Serie (4) + Correlativo (10)
        $serieFmt = str_pad(substr($v['serie'], 0, 4), 4, '0', STR_PAD_RIGHT);
        $car = $rucEmpresa . $tipoCompSunat . $serieFmt . str_pad($v['correlativo'], 10, '0', STR_PAD_LEFT);

        $fechaEmision = date('d/m/Y', strtotime($v['fecha_venta']));
        $fechaVenc = $fechaEmision;

        $estadoComp = ($v['estado'] === 'COMPLETADA') ? '1' : '2'; // 1 Activo, 2 Anulado
        $baseImponible = ($v['estado'] === 'COMPLETADA') ? number_format($v['subtotal'], 2, '.', '') : '0.00';
        $igv = ($v['estado'] === 'COMPLETADA') ? number_format($v['impuesto'], 2, '.', '') : '0.00';
        $total = ($v['estado'] === 'COMPLETADA') ? number_format($v['total'], 2, '.', '') : '0.00';

        // Columnas oficiales SIRE RVIE (separadas por pipe |)
        $cols = [
            $rucEmpresa,                                      // 1. RUC Emisor
            $razonEmpresa,                                    // 2. Apellidos y Nombres / Razón Social
            $periodoKey . '00',                               // 3. Periodo
            $car,                                             // 4. CAR (Código de Anotación de Registro)
            $fechaEmision,                                    // 5. Fecha de emisión
            $fechaVenc,                                       // 6. Fecha de vencimiento
            $tipoCompSunat,                                   // 7. Tipo de Comprobante
            $v['serie'],                                      // 8. Serie del Comprobante
            $correlativoFmt,                                  // 9. Número del Comprobante
            '',                                               // 10. Número final (rango)
            $tipoDocCliente,                                  // 11. Tipo de documento cliente
            $v['cliente_doc'],                                // 12. Número documento cliente
            mb_strtoupper($v['cliente_nombre'], 'UTF-8'),      // 13. Razón social cliente
            '0.00',                                           // 14. Valor facturado exportación
            $baseImponible,                                   // 15. Base imponible gravada
            '0.00',                                           // 16. Descuento base imponible
            $igv,                                             // 17. Impuesto General a las Ventas (IGV)
            '0.00',                                           // 18. Descuento IGV
            '0.00',                                           // 19. Monto exonerado
            '0.00',                                           // 20. Monto inafecto
            '0.00',                                           // 21. ISC
            '0.00',                                           // 22. Base arroz pilado
            '0.00',                                           // 23. Impuesto arroz pilado
            '0.00',                                           // 24. ICBPER (bolsas)
            '0.00',                                           // 25. Otros tributos
            $total,                                           // 26. Importe total
            'PEN',                                            // 27. Moneda
            '1.000',                                          // 28. Tipo de cambio
            '',                                               // 29. Fecha emision comp modifica
            '',                                               // 30. Tipo comp modifica
            '',                                               // 31. Serie comp modifica
            '',                                               // 32. Numero comp modifica
            $estadoComp                                       // 33. Estado comprobante (1 activo, 2 anulado)
        ];

        $lineas[] = implode('|', $cols) . '|';
    }

    $contenidoTxt = implode("\r\n", $lineas);
} 
// ==========================================================
// 2. RCE - REGISTRO DE COMPRAS ELECTRÓNICO (080400)
// ==========================================================
else {
    $libroCodigo = '080400';
    $nombreArchivoBase = "LE{$rucEmpresa}{$periodoKey}00{$libroCodigo}021111_1";

    $stmt = $pdo->prepare("SELECT c.*, p.razon_social as proveedor_nombre, p.num_doc as proveedor_doc, p.tipo_doc as proveedor_tipo_doc
        FROM compras c
        INNER JOIN proveedores p ON c.proveedor_id = p.id
        WHERE c.fecha_compra BETWEEN ? AND ?
        ORDER BY c.fecha_compra ASC, c.id ASC");
    $stmt->execute([$periodo . '-01', date('Y-m-t', strtotime("$periodo-01"))]);
    $compras = $stmt->fetchAll();

    $lineas = [];
    foreach ($compras as $idx => $c) {
        $correlativoFmt = str_pad($c['id'], 8, '0', STR_PAD_LEFT);
        
        $tipoCompSunat = '01'; // Factura por defecto
        if (stripos($c['tipo_comprobante'], 'Boleta') !== false) {
            $tipoCompSunat = '03';
        } elseif (stripos($c['tipo_comprobante'], 'Recibo') !== false) {
            $tipoCompSunat = '02';
        } elseif (stripos($c['tipo_comprobante'], 'Nota') !== false) {
            $tipoCompSunat = '07';
        }

        // Partir serie y número de la compra si están juntos (Ej: F001-000456)
        $partesSerie = explode('-', $c['serie_numero']);
        $serieComp = $partesSerie[0] ?? 'F001';
        $numComp = $partesSerie[1] ?? str_pad($c['id'], 8, '0', STR_PAD_LEFT);

        // CAR del proveedor o generado
        $serieFmt = str_pad(substr($serieComp, 0, 4), 4, '0', STR_PAD_RIGHT);
        $car = $c['proveedor_doc'] . $tipoCompSunat . $serieFmt . str_pad(preg_replace('/[^0-9]/', '', $numComp), 10, '0', STR_PAD_LEFT);

        $fechaEmision = date('d/m/Y', strtotime($c['fecha_compra']));
        $fechaVenc = $fechaEmision;

        $estadoComp = ($c['estado'] === 'COMPLETADA') ? '1' : '2';
        $baseImponible = ($c['estado'] === 'COMPLETADA') ? number_format($c['subtotal'], 2, '.', '') : '0.00';
        $igv = ($c['estado'] === 'COMPLETADA') ? number_format($c['impuesto'], 2, '.', '') : '0.00';
        $total = ($c['estado'] === 'COMPLETADA') ? number_format($c['total'], 2, '.', '') : '0.00';

        // Estructura oficial SIRE RCE (38 columnas separadas por pipe |)
        $cols = [
            $rucEmpresa,                                      // 1. RUC Adquiriente
            $razonEmpresa,                                    // 2. Razón Social Adquiriente
            $periodoKey . '00',                               // 3. Periodo
            $car,                                             // 4. CAR
            $fechaEmision,                                    // 5. Fecha Emisión
            $fechaVenc,                                       // 6. Fecha Vencimiento
            $tipoCompSunat,                                   // 7. Tipo Comprobante
            $serieComp,                                       // 8. Serie Comprobante
            '',                                               // 9. Año DUA
            $numComp,                                         // 10. Número Comprobante
            '',                                               // 11. Número Final
            '6',                                              // 12. Tipo Doc Proveedor (6: RUC)
            $c['proveedor_doc'],                              // 13. Número Doc Proveedor
            mb_strtoupper($c['proveedor_nombre'], 'UTF-8'),    // 14. Razón Social Proveedor
            $baseImponible,                                   // 15. Base Gravada Credito Fiscal
            $igv,                                             // 16. IGV Credito Fiscal
            '0.00',                                           // 17. Base Gravada Mixta
            '0.00',                                           // 18. IGV Mixta
            '0.00',                                           // 19. Base Sin Credito
            '0.00',                                           // 20. IGV Sin Credito
            '0.00',                                           // 21. No Gravadas
            '0.00',                                           // 22. ISC
            '0.00',                                           // 23. ICBPER
            '0.00',                                           // 24. Otros tributos
            $total,                                           // 25. Total
            'PEN',                                            // 26. Moneda
            '1.000',                                          // 27. Tipo Cambio
            '',                                               // 28. Fecha comp modifica
            '',                                               // 29. Tipo comp modifica
            '',                                               // 30. Serie comp modifica
            '',                                               // 31. Código DUA
            '',                                               // 32. Número comp modifica
            '',                                               // 33. Fecha Emisión Detracción
            '',                                               // 34. Número Constancia Detracción
            '',                                               // 35. Marca Retención
            '1',                                              // 36. Clasificación de bienes y servicios
            '',                                               // 37. Identificación del Contrato
            $estadoComp                                       // 38. Estado de la anotación
        ];

        $lineas[] = implode('|', $cols) . '|';
    }

    $contenidoTxt = implode("\r\n", $lineas);
}

// ==========================================================
// SALIDA: DESCARGA DE ARCHIVO TXT O ZIP
// ==========================================================

if ($formato === 'zip' && class_exists('ZipArchive')) {
    $zipFilename = $nombreArchivoBase . '.zip';
    $txtFilename = $nombreArchivoBase . '.txt';

    $zip = new ZipArchive();
    $tempZipPath = sys_get_temp_dir() . '/' . $zipFilename;
    if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $zip->addFromString($txtFilename, $contenidoTxt);
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($tempZipPath));
        readfile($tempZipPath);
        unlink($tempZipPath);
        exit;
    }
}

// Salida por defecto: Archivo TXT plano oficial SUNAT
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivoBase . '.txt"');
echo $contenidoTxt;
exit;

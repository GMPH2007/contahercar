<?php
/**
 * ContaHercar - API Backend para SIRE SUNAT (RVIE / RCE)
 * Conexión OAuth2 y sincronización de propuestas electrónicas
 */

require_once __DIR__ . '/../config/app.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = getDBConnection();
$cfg = getSystemConfig();

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// 1. Guardar credenciales de API SIRE SUNAT
if ($action === 'guardar_credenciales') {
    $clientId = trim($_POST['sire_client_id'] ?? '');
    $clientSecret = trim($_POST['sire_client_secret'] ?? '');
    $usuarioSol = trim($_POST['sire_usuario_sol'] ?? '');
    $claveSol = trim($_POST['sire_clave_sol'] ?? '');
    $ambiente = trim($_POST['sire_ambiente'] ?? 'beta');

    try {
        $stmt = $pdo->prepare("UPDATE configuracion SET 
            sire_client_id = ?, 
            sire_client_secret = ?, 
            sire_usuario_sol = ?, 
            sire_clave_sol = ?, 
            sire_ambiente = ? 
            WHERE id = 1");
        $stmt->execute([$clientId, $clientSecret, $usuarioSol, $claveSol, $ambiente]);

        jsonResponse([
            'success' => true,
            'message' => 'Credenciales del SIRE SUNAT actualizadas correctamente.'
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// 2. Probar conexión y obtener Token OAuth2 de SUNAT
if ($action === 'probar_token') {
    $clientId = trim($_POST['sire_client_id'] ?? $cfg['sire_client_id'] ?? '');
    $clientSecret = trim($_POST['sire_client_secret'] ?? $cfg['sire_client_secret'] ?? '');
    $usuarioSol = trim($_POST['sire_usuario_sol'] ?? $cfg['sire_usuario_sol'] ?? '');
    $claveSol = trim($_POST['sire_clave_sol'] ?? $cfg['sire_clave_sol'] ?? '');
    $ambiente = trim($_POST['sire_ambiente'] ?? $cfg['sire_ambiente'] ?? 'beta');
    $ruc = preg_replace('/[^0-9]/', '', $cfg['ruc_empresa']);

    if (empty($clientId) || empty($clientSecret) || empty($usuarioSol) || empty($claveSol)) {
        jsonResponse([
            'success' => false,
            'message' => 'Debe ingresar Client ID, Client Secret, Usuario SOL y Clave SOL para probar la conexión con SUNAT.'
        ], 400);
    }

    $tokenUrl = "https://api-seguridad.sunat.gob.pe/v1/clientessol/" . urlencode($clientId) . "/oauth2/token/";
    $scope = "https://api-sire.sunat.gob.pe";
    $username = $ruc . $usuarioSol;

    $postData = http_build_query([
        'grant_type' => 'password',
        'scope' => $scope,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'username' => $username,
        'password' => $claveSol
    ]);

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $json = json_decode($res, true);

    if ($code === 200 && isset($json['access_token'])) {
        jsonResponse([
            'success' => true,
            'message' => '¡Conexión Exitosa con el servidor OAuth2 de la SUNAT!',
            'token_type' => $json['token_type'] ?? 'Bearer',
            'expires_in' => $json['expires_in'] ?? 3600,
            'access_token' => substr($json['access_token'], 0, 15) . '... (Oculto por seguridad)'
        ]);
    } else {
        $msg = $json['error_description'] ?? $json['message'] ?? $json['error'] ?? 'No se pudo autenticar con SUNAT. Verifique sus credenciales SOL y Client ID.';
        jsonResponse([
            'success' => false,
            'message' => "Error SUNAT ($code): $msg",
            'raw_response' => $json ?: $res
        ], 400);
    }
}

// 3. Obtener propuesta local para el visor
if ($action === 'obtener_propuesta_local') {
    $tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'rvie';
    $periodo = isset($_GET['periodo']) ? trim($_GET['periodo']) : date('Y-m');

    $fechaIni = "$periodo-01 00:00:00";
    $fechaFin = date('Y-m-t 23:59:59', strtotime("$periodo-01"));

    if ($tipo === 'rvie') {
        $stmt = $pdo->prepare("SELECT v.*, c.nombre_razon_social, c.num_doc, c.tipo_doc
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
            WHERE v.fecha_venta BETWEEN ? AND ?
            ORDER BY v.fecha_venta DESC");
        $stmt->execute([$fechaIni, $fechaFin]);
        $items = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT c.*, p.razon_social as nombre_razon_social, p.num_doc, p.tipo_doc
            FROM compras c
            INNER JOIN proveedores p ON c.proveedor_id = p.id
            WHERE c.fecha_compra BETWEEN ? AND ?
            ORDER BY c.fecha_compra DESC");
        $stmt->execute([$periodo . '-01', date('Y-m-t', strtotime("$periodo-01"))]);
        $items = $stmt->fetchAll();
    }

    $totalBase = 0;
    $totalIgv = 0;
    $totalMonto = 0;
    foreach ($items as $it) {
        if ($it['estado'] === 'COMPLETADA') {
            $totalBase += (float)$it['subtotal'];
            $totalIgv += (float)$it['impuesto'];
            $totalMonto += (float)$it['total'];
        }
    }

    jsonResponse([
        'success' => true,
        'tipo' => $tipo,
        'periodo' => $periodo,
        'total_registros' => count($items),
        'total_base' => $totalBase,
        'total_igv' => $totalIgv,
        'total_monto' => $totalMonto,
        'registros' => $items
    ]);
}

jsonResponse(['success' => false, 'message' => 'Acción no válida.'], 400);

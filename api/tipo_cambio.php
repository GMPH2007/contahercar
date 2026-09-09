<?php
/**
 * ContaHercar - Consulta Tipo de Cambio SUNAT (Decolecta / APIS.net.pe)
 */

require_once __DIR__ . '/../config/app.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$config = getSystemConfig();
$apiToken = trim($config['api_ruc_token'] ?? '');
$hoy = date('Y-m-d');

// Directorio y archivo de cache diario para no agotar cuotas
$cacheFile = sys_get_temp_dir() . '/contahercar_tc_' . $hoy . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600 * 4)) {
    $cachedData = json_decode(file_get_contents($cacheFile), true);
    if ($cachedData && isset($cachedData['compra'])) {
        jsonResponse([
            'success' => true,
            'compra' => (float)$cachedData['compra'],
            'venta' => (float)$cachedData['venta'],
            'fecha' => $cachedData['fecha'] ?? $hoy,
            'origen' => 'SUNAT (Cache Local)',
            'moneda' => 'USD'
        ]);
    }
}

$provider = $config['api_ruc_provider'] ?? 'decolecta';
if ($provider === 'peruapi') {
    $url = 'https://peruapi.com/api/tipo-cambio';
    if (!empty($apiToken)) {
        $url .= '?api_token=' . urlencode($apiToken);
    }
} else {
    $url = 'https://api.decolecta.com/v1/tipo-cambio/sunat?date=' . $hoy;
}

$tcData = null;

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = [
        'Accept: application/json',
        'User-Agent: ContaHercar-App/1.0'
    ];

    if (!empty($apiToken)) {
        $headers[] = 'Authorization: Bearer ' . $apiToken;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw && $httpCode >= 200 && $httpCode < 300) {
        $json = json_decode($raw, true);
        $d = isset($json['data']) ? $json['data'] : $json;
        if (isset($d['compra']) && isset($d['venta'])) {
            $tcData = [
                'compra' => (float)$d['compra'],
                'venta' => (float)$d['venta'],
                'fecha' => $d['fecha'] ?? $hoy
            ];
            file_put_contents($cacheFile, json_encode($tcData));
        }
    }
}

if ($tcData) {
    jsonResponse([
        'success' => true,
        'compra' => $tcData['compra'],
        'venta' => $tcData['venta'],
        'fecha' => $tcData['fecha'],
        'origen' => 'SUNAT (Decolecta en vivo)',
        'moneda' => 'USD'
    ]);
} else {
    // Estimación / Tasa de referencia diaria si no hay conexión externa
    $compraEstimada = 3.752;
    $ventaEstimada = 3.761;
    jsonResponse([
        'success' => true,
        'compra' => $compraEstimada,
        'venta' => $ventaEstimada,
        'fecha' => $hoy,
        'origen' => 'SUNAT (Referencial)',
        'moneda' => 'USD',
        'nota' => 'Tipo de cambio referencial. Para datos en tiempo real de SUNAT configure su Token Decolecta.'
    ]);
}

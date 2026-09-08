<?php
/**
 * ContaHercar - Proxy de Consulta RUC / DNI / RUT
 * Compatible con Decolecta / APIS.net.pe (https://api.decolecta.com)
 */

require_once __DIR__ . '/../config/app.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$numero = isset($_GET['numero']) ? trim($_GET['numero']) : (isset($_POST['numero']) ? trim($_POST['numero']) : '');
$numero = preg_replace('/[^0-9]/', '', $numero);

if (empty($numero)) {
    jsonResponse([
        'success' => false,
        'message' => 'Debe proporcionar un número de documento válido (DNI de 8 dígitos o RUC de 11 dígitos).'
    ], 400);
}

$len = strlen($numero);
if ($len !== 8 && $len !== 11) {
    jsonResponse([
        'success' => false,
        'message' => "Longitud de documento inválida ($len dígitos). Debe ser DNI (8 dígitos) o RUC/RUT (11 dígitos)."
    ], 400);
}

$tipo = ($len === 8) ? 'DNI' : 'RUC';
$config = getSystemConfig();

$apiUrlTemplate = ($tipo === 'DNI') ? $config['api_dni_url'] : $config['api_ruc_url'];
$apiToken = trim($config['api_ruc_token'] ?? '');

// Si la URL tiene placeholders {numero}, {ruc}, {dni} o {token}
$url = str_replace(
    ['{numero}', '{ruc}', '{dni}', '{token}'], 
    [$numero, $numero, $numero, $apiToken], 
    $apiUrlTemplate
);

// Si la URL no tenía placeholder de número, adjuntar como query param
if (strpos($url, $numero) === false) {
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    $url .= $separator . 'numero=' . $numero;
}

// Para proveedores como PeruAPI que aceptan api_token como parámetro GET
if (!empty($apiToken) && strpos($url, 'peruapi.com') !== false && strpos($url, 'api_token=') === false) {
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    $url .= $separator . 'api_token=' . urlencode($apiToken);
}

$response = null;
$httpCode = 0;
$curlError = null;

// Ejecutar llamada Backend cURL
if (function_exists('curl_init') && !empty($url)) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = [
        'Accept: application/json',
        'User-Agent: ContaHercar-App/1.0 (RUC-Client)'
    ];

    if (!empty($apiToken)) {
        $headers[] = 'Authorization: Bearer ' . $apiToken;
        $headers[] = 'X-Api-Key: ' . $apiToken;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw && $httpCode >= 200 && $httpCode < 300) {
        $response = json_decode($raw, true);
    }
}

// Analizar y normalizar respuesta de Decolecta / apis.net.pe
if ($response && is_array($response)) {
    $data = isset($response['data']) ? $response['data'] : $response;

    $nombre = '';
    $direccion = '';
    $estado = 'ACTIVO';
    $condicion = 'HABIDO';
    $departamento = $data['departamento'] ?? '';
    $provincia = $data['provincia'] ?? '';
    $distrito = $data['distrito'] ?? '';
    $ubigeo = $data['ubigeo'] ?? '';
    $localesAnexos = $data['locales_anexos'] ?? [];
    $esAgenteRetencion = !empty($data['es_agente_retencion']);
    $esBuenContribuyente = !empty($data['es_buen_contribuyente']);
    $tipoContribuyente = '';

    if ($tipo === 'RUC') {
        // Decolecta / apis.net.pe maneja 'razon_social', 'razonSocial' o 'nombre'
        $nombre = $data['razon_social'] ?? $data['razonSocial'] ?? $data['nombre_o_razon_social'] ?? $data['nombre'] ?? '';
        $estado = $data['estado'] ?? 'ACTIVO';
        $condicion = $data['condicion'] ?? 'HABIDO';
        $direccion = $data['direccion'] ?? $data['direccion_completa'] ?? '';

        // Si la dirección vino vacía pero vienen componentes detallados (Decolecta /full)
        if (empty(trim($direccion))) {
            $partesDir = array_filter([
                $data['via_tipo'] ?? ($data['viaTipo'] ?? ''),
                $data['via_nombre'] ?? ($data['viaNombre'] ?? ''),
                !empty($data['numero']) && $data['numero'] !== '-' ? 'NRO. ' . $data['numero'] : '',
                !empty($data['interior']) && $data['interior'] !== '-' ? 'INT. ' . $data['interior'] : '',
                !empty($data['zona_tipo']) && $data['zona_tipo'] !== '-' ? ($data['zona_tipo'] . ' ' . ($data['zona_codigo'] ?? '')) : '',
                $distrito,
                $provincia,
                $departamento
            ]);
            $direccion = implode(' ', $partesDir);
        }

        // Determinar Tipo de Contribuyente
        $pref = substr($numero, 0, 2);
        if ($pref === '20') {
            $tipoContribuyente = 'PERSONA JURÍDICA (SOCIEDAD O EMPRESA)';
        } elseif ($pref === '10') {
            $tipoContribuyente = 'PERSONA NATURAL CON NEGOCIO';
        } elseif ($pref === '15' || $pref === '17') {
            $tipoContribuyente = 'PERSONA NATURAL SIN NEGOCIO / SUCESIÓN';
        } else {
            $tipoContribuyente = 'CONTRIBUYENTE REGISTRADO SUNAT';
        }
    } else {
        // DNI (Reniec - Decolecta devuelve full_name, first_name, first_last_name, second_last_name)
        $fullName = $data['full_name'] ?? ($data['nombre_completo'] ?? '');
        $firstName = $data['first_name'] ?? ($data['nombres'] ?? '');
        $firstLast = $data['first_last_name'] ?? ($data['apellidoPaterno'] ?? ($data['apellido_paterno'] ?? ''));
        $secondLast = $data['second_last_name'] ?? ($data['apellidoMaterno'] ?? ($data['apellido_materno'] ?? ''));

        if (!empty($fullName)) {
            $nombre = $fullName;
        } elseif (!empty($firstName) || !empty($firstLast)) {
            $nombre = trim($firstName . ' ' . $firstLast . ' ' . $secondLast);
        } else {
            $nombre = $data['nombre'] ?? '';
        }

        $direccion = $data['direccion'] ?? 'DOMICILIO SEGÚN RENIEC';
        $tipoContribuyente = 'PERSONA NATURAL (DOCUMENTO NACIONAL DE IDENTIDAD)';
    }

    if (!empty(trim($nombre))) {
        jsonResponse([
            'success' => true,
            'tipo' => $tipo,
            'numero' => $numero,
            'nombre' => mb_strtoupper(trim($nombre), 'UTF-8'),
            'direccion' => mb_strtoupper(trim($direccion), 'UTF-8'),
            'tipo_contribuyente' => $tipoContribuyente,
            'departamento' => mb_strtoupper(trim($departamento), 'UTF-8'),
            'provincia' => mb_strtoupper(trim($provincia), 'UTF-8'),
            'distrito' => mb_strtoupper(trim($distrito), 'UTF-8'),
            'ubigeo' => $ubigeo,
            'estado' => mb_strtoupper($estado, 'UTF-8'),
            'condicion' => mb_strtoupper($condicion, 'UTF-8'),
            'es_agente_retencion' => $esAgenteRetencion,
            'es_buen_contribuyente' => $esBuenContribuyente,
            'locales_anexos' => $localesAnexos,
            'source' => 'api_externa',
            'api_url' => $url,
            'proveedor' => 'Decolecta / APIS.net.pe (Oficial SUNAT)'
        ]);
    }
}

// ==========================================================
// FALLBACK INTELIGENTE / MOCK LOCAL DE PRUEBA
// ==========================================================
// Incluye los ejemplos oficiales citados en la documentación
// de Decolecta (10460278975, 20601030013) y ejemplos comerciales.
$mockData = [
    // RUC oficial de la documentación de PeruAPI (peruapi.com)
    '20100017491' => [
        'nombre' => 'INTEGRATEL PERÚ S.A.A.',
        'direccion' => 'JR. DOMINGO MARTINEZ LUJAN NRO. 1130, SURQUILLO - LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    // RUCs de la documentación oficial de Decolecta / APIS.net.pe
    '10460278975' => [
        'nombre' => 'HUAMANI MENDOZA ERACLEO JUAN',
        'direccion' => 'CAL. GARCILASO NRO. 210 - CUSCO',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    '20601030013' => [
        'nombre' => 'DECOLECTA TECNOLOGIAS DIGITALES S.A.C.',
        'direccion' => 'AV. PARDO NRO. 601, MIRAFLORES - LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    '20100070970' => [
        'nombre' => 'SUPERMERCADOS PERUANOS S.A.',
        'direccion' => 'CAL. MORELLI NRO. 181 URB. SAN BORJA - LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    '20100128218' => [
        'nombre' => 'SAGA FALABELLA S.A.',
        'direccion' => 'AV. PASEO DE LA REPUBLICA NRO. 3220 - SAN ISIDRO',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    '20601234567' => [
        'nombre' => 'CONTAHERCAR SOLUCIONES COMERCIALES S.A.C.',
        'direccion' => 'AV. PRINCIPAL 123, SAN ISIDRO - LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    '20501234589' => [
        'nombre' => 'IMPORTADORA INDUSTRIAL HERCAR E.I.R.L.',
        'direccion' => 'JR. PARURO 1024, LIMA CENTRO',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    // DNIs
    '45891234' => [
        'nombre' => 'JUAN CARLOS PÉREZ RÍOS',
        'direccion' => 'AV. AREQUIPA 1420, LINCE - LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ],
    '12345678' => [
        'nombre' => 'MARÍA ELENA GONZALES RAMOS',
        'direccion' => 'JR. HUANCAVELICA 450, LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO'
    ]
];

if (isset($mockData[$numero])) {
    $item = $mockData[$numero];
    jsonResponse([
        'success' => true,
        'tipo' => $tipo,
        'numero' => $numero,
        'nombre' => $item['nombre'],
        'direccion' => $item['direccion'],
        'estado' => $item['estado'],
        'condicion' => $item['condicion'],
        'source' => 'demo_local',
        'nota' => 'Datos obtenidos del catálogo local de demostración (Decolecta Mock).'
    ]);
}

// Si la API externa retornó un error o requiere Token de Decolecta
$apiStatusMessage = ($httpCode > 0) ? "HTTP $httpCode" : ($curlError ?: 'Sin respuesta del servidor externo');
if ($httpCode == 401) {
    $apiStatusMessage = "401 No Autorizado (Requiere Token de Decolecta.com en Configuración)";
}

if ($tipo === 'RUC') {
    jsonResponse([
        'success' => true,
        'tipo' => 'RUC',
        'numero' => $numero,
        'nombre' => "EMPRESA / RUC $numero S.A.C.",
        'direccion' => 'AV. COMERCIAL NRO. ' . substr($numero, -3) . ', LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO',
        'source' => 'fallback_asistido',
        'aviso' => "Respuesta del servicio: $apiStatusMessage. Se autocompletó con plantilla editable. Para consultas directas en vivo a SUNAT, ingresa tu Token de Decolecta en Configuración."
    ]);
} else {
    jsonResponse([
        'success' => true,
        'tipo' => 'DNI',
        'numero' => $numero,
        'nombre' => "CIUDADANO DNI $numero",
        'direccion' => 'DIRECCIÓN REGISTRADA - LIMA',
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO',
        'source' => 'fallback_asistido',
        'aviso' => "Respuesta del servicio: $apiStatusMessage. Se autocompletó con plantilla editable. Para consultas directas, ingresa tu Token en Configuración."
    ]);
}

<?php
/**
 * api.php - VERSIÓN DEBUG
 */
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit();
}

function call_external_api($method, $data = null, $queryString = "")
{
    // Intentamos 127.0.0.1 que es lo más seguro en Linux
    $url = "http://127.0.0.1:8001/api_externa/index.php" . $queryString;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    if (($method === 'POST' || $method === 'PUT') && $data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        return ['error' => $error, 'url' => $url];
    }

    return ['status_code' => $httpCode, 'body' => $result];
}

$rolesArr = (str_starts_with($_SESSION['role'] ?? '', '[')) ? json_decode($_SESSION['role'], true) : [$_SESSION['role'] ?? ''];
$isAdmin = is_array($rolesArr) && count(array_intersect($rolesArr, ['super_admin', 'admin'])) > 0;

$effectiveAdminId = $isAdmin ? $_SESSION['user_id'] : $_SESSION['vinculado_a_admin_id'];

$requestStats = isset($_GET['stats']) && $_GET['stats'] === 'true';
$requestModule = isset($_GET['module']) ? $_GET['module'] : 'incidents';
$queryString = "?admin_id=" . ($effectiveAdminId ?? 'NULL');
if ($requestStats) $queryString .= "&stats=true";
if ($requestModule !== 'incidents') $queryString .= "&module=" . urlencode($requestModule);

if (!$isAdmin) {
    if (!empty($_SESSION['empresa'])) $queryString .= "&empresa=" . urlencode($_SESSION['empresa']);
}

$method = $_SERVER['REQUEST_METHOD'];
$postData = null;

if ($method === 'POST' || $method === 'PUT') {
    $rawBody = file_get_contents('php://input');
    $postData = json_decode($rawBody, true) ?? [];
    // Inyectar admin_id y empresa reales desde la sesión (seguridad: no confiar en el cliente)
    $postData['admin_id'] = $effectiveAdminId;
    if (!empty($_SESSION['empresa'])) {
        $postData['empresa'] = $_SESSION['empresa'];
    }
}

$response = call_external_api($method, $postData, $queryString);

if (isset($response['error'])) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "debug_error" => $response['error'],
        "debug_url" => $response['url'],
        "message" => "Error de conexión interna entre servidores."
    ]);
} else {
    http_response_code($response['status_code']);
    echo $response['body'];
}
exit();

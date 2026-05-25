<?php
// auth/login.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../db_connect.php';

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar a la base de datos']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input_json = file_get_contents('php://input');
$data = json_decode($input_json, true);

if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan credenciales']);
    exit();
}

$username = trim($data['username']);
$password = $data['password'];

try {
    // Buscar usuario por username (incluyendo la empresa)
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.password_hash, u.role, c.name AS empresa, u.company_id FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // ISO 27001: Prevenir enumeración de usuarios, siempre devolver el mismo mensaje genérico.
    // password_verify previene ataques de timing checking cryptographic hash
    if ($user && password_verify($password, $user['password_hash'])) {
        // Generar nuevo ID de sesión para prevenir Session Fixation (Requisito OWASP / ISO 27001 Sec. Comunicaciones)
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['empresa'] = $user['empresa'];
        $_SESSION['company_id'] = $user['company_id'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
            'username' => $user['username'],
            'role' => $user['role'],
            'empresa' => $user['empresa'],
            'company_id' => $user['company_id']
        ]);
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    // Log the error internally, but do not expose details to the client
    error_log('Database error in login.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno en la base de datos']);
}

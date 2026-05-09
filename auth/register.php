<?php
// auth/register.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input_json = file_get_contents('php://input');
$data = json_decode($input_json, true);

if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos (usuario, email, contraseña)']);
    exit();
}

$username = trim($data['username']);
$email = trim($data['email']);
$password = $data['password'];

if (empty($username) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Los campos no pueden estar vacíos']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El formato del email no es válido']);
    exit();
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres (Requisito ISO)']);
    exit();
}

try {
    // Validación ISO 27001: Prevenir duplicados
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'El usuario o el email ya están registrados']);
        exit();
    }

    // Validación ISO 27001: Almacenar la clave hasheada mediante BCRYPT
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // [VINCULACIÓN POR CÓDIGO]
    $inviteCode = isset($data['invite_code']) ? trim($data['invite_code']) : null;
    $vinculadoId = null;
    $empresaInicial = null;

    if ($inviteCode) {
        $stmt = $pdo->prepare("SELECT id, empresa FROM users WHERE codigo_admin = ? AND role IN ('super_admin', 'admin')");
        $stmt->execute([$inviteCode]);
        $adminReferido = $stmt->fetch();
        if ($adminReferido) {
            $vinculadoId = $adminReferido['id'];
            // Si el admin ya tiene empresa, vinculamos al usuario a esa empresa también
            // $empresaInicial = $adminReferido['empresa']; // El usuario prefiere que el admin lo asigne manual?
            // "nicolas tenga un id aleatoria si la pone pues nicolas pone que empresa"
            // Re-leyendo: El usuario quiere que Nicolas ponga la empresa DESPUÉS.
        }
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, vinculado_a_admin_id, empresa) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $password_hash, $vinculadoId, $empresaInicial]);

    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => 'Usuario registrado exitosamente']);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno en la base de datos']);
}
?>

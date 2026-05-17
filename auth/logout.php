<?php
// auth/logout.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, borre también la cookie de sesión (ISO 27001 Control de acceso/Sesiones)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

echo json_encode(['success' => true, 'message' => 'Sesión cerrada exitosamente']);

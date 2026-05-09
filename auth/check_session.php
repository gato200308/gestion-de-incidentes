<?php
// auth/check_session.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../db_connect.php';

if (isset($_SESSION['user_id'])) {
    try {
        // Fetch latest data from DB to ensure roles/companies are current (ISO 27001 Access Control)
        $stmt = $pdo->prepare("SELECT username, role, empresa, codigo_admin, vinculado_a_admin_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $role = $user['role'];
            $codigo_admin = $user['codigo_admin'];
            $vinculado = $user['vinculado_a_admin_id'];

            $rolesArr = (str_starts_with($role, '[')) ? json_decode($role, true) : [$role];
            $isAdmin = is_array($rolesArr) && count(array_intersect($rolesArr, ['super_admin', 'admin'])) > 0;

            // AUTO-GENERACIÓN: Si es admin pero no tiene código (ej. migración), generarlo al vuelo
            if ($isAdmin && empty($codigo_admin)) {
                $codigo_admin = 'SGI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                $upd = $pdo->prepare("UPDATE users SET codigo_admin = ? WHERE id = ?");
                $upd->execute([$codigo_admin, $_SESSION['user_id']]);
            }

            // Update session data with latest DB values
            $_SESSION['role'] = $role;
            $_SESSION['empresa'] = $user['empresa'];
            $_SESSION['codigo_admin'] = $codigo_admin;
            $_SESSION['vinculado_a_admin_id'] = $vinculado;
            
            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'user_id' => $_SESSION['user_id'],
                'username' => $user['username'],
                'role' => $role,
                'empresa' => $user['empresa'],
                'codigo_admin' => $codigo_admin,
                'vinculado_a_admin_id' => $vinculado
            ]);
        } else {
            // Session exists but user not in DB (deleted?)
            session_destroy();
            http_response_code(401);
            echo json_encode(['success' => false, 'logged_in' => false, 'message' => 'Usuario no encontrado.']);
        }
    } catch (\PDOException $e) {
        // Default to session values if DB error
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'empresa' => $_SESSION['empresa']
        ]);
    }
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => 'No hay sesión activa'
    ]);
}
?>

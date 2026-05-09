<?php
require '../db_connect.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

function hasAnyRole($roleStr, $allowedRoles) {
    $roles = (str_starts_with($roleStr, '[')) ? json_decode($roleStr, true) : [$roleStr];
    return is_array($roles) && count(array_intersect($roles, $allowedRoles)) > 0;
}

// Control estricto de acceso (Rol Admin o Super Admin requerido)
if (!isset($_SESSION['user_id']) || !hasAnyRole($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$isSuperAdmin = ($_SESSION['role'] === 'super_admin');

if ($method === 'GET') {
    // [LISTAR USUARIOS]
    try {
        // Filtro Estricto: Solo ves a los usuarios vinculados directamente a tu cuenta (Privacidad Total)
        $stmt = $pdo->prepare("SELECT id, username, email, role, empresa, vinculado_a_admin_id, created_at FROM users 
                             WHERE vinculado_a_admin_id = ? 
                             ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener usuarios']);
    }
}
 elseif ($method === 'PUT') {
    // [ACTUALIZAR USUARIO]
    $input_json = file_get_contents('php://input');
    $data = json_decode($input_json, true);

    if (!isset($data['id']) || !isset($data['empresa'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
        exit();
    }

    try {
        $id = $data['id'];
        $requestedRole = $data['role'];
        $requestedEmpresa = trim($data['empresa']) === '' ? null : trim($data['empresa']);

        // 1. Obtener datos actuales del usuario objetivo
        $stmt = $pdo->prepare("SELECT role, empresa, vinculado_a_admin_id, codigo_admin FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit();
        }

        // 2. Validaciones de Jerarquía (ISO 27001 - Control de Acceso)
        if (!$isSuperAdmin) {
            // Si eres Admin regular:
            // a. No puedes editar usuarios de otras empresas A MENOS QUE estén vinculados a ti directamente
            $esVinculadoMio = ($targetUser['vinculado_a_admin_id'] == $_SESSION['user_id']);
            
            if ($targetUser['empresa'] !== $_SESSION['empresa'] && !$esVinculadoMio) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar este usuario.']);
                exit();
            }
            // b. No puedes editar a un Super Admin
            if (hasAnyRole($targetUser['role'], ['super_admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Restricción de seguridad: Dueño intocable.']);
                exit();
            }
            // c. No puedes CREAR otros Admins o Super Admins
            if (in_array($requestedRole, ['super_admin', 'admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No tienes niveles de autorización para crear gestores.']);
                exit();
            }
            // d. Si cambias la empresa y NO es un vinculado directo, solo puedes poner la TUYA
            if (!$esVinculadoMio) {
                if ($requestedEmpresa !== $_SESSION['empresa'] && $requestedEmpresa !== null) {
                    $requestedEmpresa = $_SESSION['empresa'];
                }
            }
            // Si ES un vinculado, permitimos que el Admin le ponga CUALQUIER empresa (Flexibilidad Multi-tenant)
        }

        // 3. Validar rol válido (Sanitización ISO 27001)
        if ($requestedRole !== null) {
            $validSingle = ['super_admin', 'admin', 'analyst', 'user', 'capacitador', 'implementador', 'auditor', 'completo'];
            // Puede ser un string JSON como '["capacitador","auditor"]'
            if (is_string($requestedRole) && str_starts_with($requestedRole, '[')) {
                $rolesArr = json_decode($requestedRole, true);
                if (!is_array($rolesArr)) $requestedRole = '[]';
                else {
                    // Filtrar solo roles válidos del array
                    $rolesArr = array_values(array_filter($rolesArr, fn($r) => in_array($r, $validSingle)));
                    $requestedRole = json_encode($rolesArr);
                }
            } elseif (!in_array($requestedRole, $validSingle)) {
                $requestedRole = 'user';
            }
        } else {
            // role null = no cambiar
            $requestedRole = $targetUser['role'];
        }

        // 4. GENERACIÓN AUTOMÁTICA DE CÓDIGO (Si se convierte en Admin y no tiene uno)
        $newAdminCode = $targetUser['codigo_admin'];
        if (hasAnyRole($requestedRole, ['super_admin', 'admin']) && (empty($newAdminCode))) {
            $newAdminCode = 'SGI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        }

        // 5. ASIGNACIÓN DE DUEÑO (Si el usuario era "huérfano" y el Super Admin lo está editando)
        $newVinculadoId = $targetUser['vinculado_a_admin_id'];
        if ($isSuperAdmin && $newVinculadoId === null) {
            $newVinculadoId = $_SESSION['user_id'];
        }

        $stmt = $pdo->prepare("UPDATE users SET role = ?, empresa = ?, codigo_admin = ?, vinculado_a_admin_id = ? WHERE id = ?");
        $stmt->execute([$requestedRole, $requestedEmpresa, $newAdminCode, $newVinculadoId, $id]);

        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

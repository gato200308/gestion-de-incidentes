<?php
// auth/admin_users.php
require_once '../db_connect.php';
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
$isSuperAdmin = (is_array(json_decode($_SESSION['role'], true)) 
    ? in_array('super_admin', json_decode($_SESSION['role'], true)) 
    : $_SESSION['role'] === 'super_admin');

if ($method === 'GET') {
    // [LISTAR USUARIOS Y EMPRESAS REGISTRADAS]
    try {
        if ($isSuperAdmin) {
            // Super Admin ve a absolutamente todos los usuarios
            $stmt = $pdo->prepare("SELECT u.id, u.username, u.email, u.role, c.name AS empresa, u.company_id, u.vinculado_a_admin_id, u.created_at 
                                   FROM users u 
                                   LEFT JOIN companies c ON u.company_id = c.id 
                                   ORDER BY u.created_at DESC");
            $stmt->execute();
        } else {
            // Admin regular solo ve los vinculados directamente o a sí mismo (ISO 27001 - Privacidad y Control de Acceso)
            $stmt = $pdo->prepare("SELECT u.id, u.username, u.email, u.role, c.name AS empresa, u.company_id, u.vinculado_a_admin_id, u.created_at 
                                   FROM users u 
                                   LEFT JOIN companies c ON u.company_id = c.id 
                                   WHERE u.vinculado_a_admin_id = ? OR u.id = ?
                                   ORDER BY u.created_at DESC");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        }
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Listar todas las empresas registradas para poblar el dropdown de asignación en el panel administrativo
        $compStmt = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC");
        $companies = $compStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'data' => $users, 
            'companies' => $companies
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener usuarios: ' . $e->getMessage()]);
    }
}
elseif ($method === 'PUT') {
    // [ACTUALIZAR USUARIO EN PANEL SAAS]
    $input_json = file_get_contents('php://input');
    $data = json_decode($input_json, true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta el ID del usuario']);
        exit();
    }

    try {
        $id = $data['id'];
        $requestedRole = $data['role'] ?? null;
        $requestedCompanyId = isset($data['company_id']) && $data['company_id'] !== '' ? intval($data['company_id']) : null;

        // 1. Obtener datos actuales del usuario objetivo
        $stmt = $pdo->prepare("SELECT role, company_id, vinculado_a_admin_id, codigo_admin FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit();
        }

        // 2. Validaciones de Jerarquía y Aislamiento (ISO 27001)
        if (!$isSuperAdmin) {
            $esVinculadoMio = ($targetUser['vinculado_a_admin_id'] == $_SESSION['user_id']);
            
            if ($targetUser['company_id'] !== $_SESSION['company_id'] && !$esVinculadoMio) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar este usuario.']);
                exit();
            }
            
            if (hasAnyRole($targetUser['role'], ['super_admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Restricción de seguridad: Rol Super Admin intocable.']);
                exit();
            }
            
            if (in_array($requestedRole, ['super_admin', 'admin'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No posees nivel de autorización para crear gestores.']);
                exit();
            }

            // Si cambias la empresa y no es un vinculado directo, solo puedes poner tu propia empresa
            if (!$esVinculadoMio && $requestedCompanyId !== $_SESSION['company_id'] && $requestedCompanyId !== null) {
                $requestedCompanyId = $_SESSION['company_id'];
            }
        }

        // 3. Validar y sanitizar roles
        if ($requestedRole !== null) {
            $validSingle = ['super_admin', 'admin', 'analyst', 'user', 'capacitador', 'implementador', 'auditor', 'completo'];
            if (is_string($requestedRole) && str_starts_with($requestedRole, '[')) {
                $rolesArr = json_decode($requestedRole, true);
                if (!is_array($rolesArr)) {
                    $requestedRole = '[]';
                } else {
                    $rolesArr = array_values(array_filter($rolesArr, fn($r) => in_array($r, $validSingle)));
                    $requestedRole = json_encode($rolesArr);
                }
            } elseif (!in_array($requestedRole, $validSingle)) {
                $requestedRole = 'user';
            }
        } else {
            $requestedRole = $targetUser['role'];
        }

        // 4. Generación de código de invitación administrativo
        $newAdminCode = $targetUser['codigo_admin'];
        if (hasAnyRole($requestedRole, ['super_admin', 'admin']) && empty($newAdminCode)) {
            $newAdminCode = 'SGI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        }

        // 5. Asignación de dueño
        $newVinculadoId = $targetUser['vinculado_a_admin_id'];
        if ($isSuperAdmin && $newVinculadoId === null) {
            $newVinculadoId = $_SESSION['user_id'];
        }

        $stmt = $pdo->prepare("UPDATE users SET role = ?, company_id = ?, codigo_admin = ?, vinculado_a_admin_id = ? WHERE id = ?");
        $stmt->execute([$requestedRole, $requestedCompanyId, $newAdminCode, $newVinculadoId, $id]);

        echo json_encode(['success' => true, 'message' => 'Usuario asignado y actualizado correctamente.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

<?php
/**
 * api.php - MOTOR PRINCIPAL DE BASE DE DATOS (MIGRADO DE ARCHIVOS JSON A MYSQL RELACIONAL)
 * Cumplimiento ISO 27001 y Aislamiento SaaS Multi-Tenant.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';

define('PHP_INPUT', 'php://input');
define('UNASSIGNED', 'Sin asignar');

// 1. Control de Autenticación (ISO 27001 Control de Acceso)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado. Inicie sesión.']);
    exit();
}

$userId = $_SESSION['user_id'];
$rolesArr = (str_starts_with($_SESSION['role'] ?? '', '[')) ? json_decode($_SESSION['role'], true) : [$_SESSION['role'] ?? ''];
$isAdmin = is_array($rolesArr) && count(array_intersect($rolesArr, ['super_admin', 'admin'])) > 0;
$isSuperAdmin = is_array($rolesArr) && in_array('super_admin', $rolesArr);

// Obtener el company_id real del usuario desde la sesión
$companyId = $_SESSION['company_id'] ?? null;

// Si el usuario no es admin y no está asignado a ninguna empresa, restringir operaciones (Tenant Aislado)
if (!$isAdmin && $companyId === null) {
    echo json_encode([]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$requestModule = $_GET['module'] ?? 'incidents';

// Función auxiliar para calcular el nivel de riesgo bajo la metodología ISO 27001 (Matriz 3x3)
function calculateRisk($prob, $imp) {
    $probMap = ['Baja'=>1, 'Media'=>2, 'Alta'=>3];
    $impMap = ['Bajo'=>1, 'Medio'=>2, 'Alto'=>3];
    $p = $probMap[$prob] ?? 1;
    $i = $impMap[$imp] ?? 1;
    $score = $p * $i;
    if ($score >= 6) {
        return 'Alto';
    }
    if ($score >= 3) {
        return 'Medio';
    }
    return 'Bajo';
}

try {
    // ============================================================================
    // MÓDULO 1: COMPANÍAS / EMPRESAS (Registro y consulta)
    // ============================================================================
    if ($requestModule === 'companies') {
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC");
            $companies = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $companies]);
            exit();
        }
        elseif ($method === 'POST') {
            // Solo Admins/SuperAdmins pueden crear nuevas empresas (Hardening)
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para registrar empresas.']);
                exit();
            }
            $rawBody = file_get_contents(PHP_INPUT);
            $data = json_decode($rawBody, true);
            $name = trim($data['name'] ?? '');

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre de la empresa es requerido.']);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
            $stmt->execute([htmlspecialchars($name)]);
            echo json_encode(['success' => true, 'message' => 'Empresa registrada con éxito.']);
            exit();
        }
    }

    // ============================================================================
    // MÓDULO 2: CAPACITACIÓN (Progress Checklist & Sessions)
    // ============================================================================
    if ($requestModule === 'training') {
        if ($method === 'GET') {
            $stmt = $pdo->prepare("SELECT check_video, check_policy, check_assets, check_incidents, check_access FROM training_progress WHERE user_id = ?");
            $stmt->execute([$userId]);
            $progress = $stmt->fetch();
            echo json_encode($progress ?: [
                'check_video' => 0, 'check_policy' => 0, 'check_assets' => 0, 'check_incidents' => 0, 'check_access' => 0
            ]);
            exit();
        }
        elseif ($method === 'POST') {
            $rawBody = file_get_contents(PHP_INPUT);
            $data = json_decode($rawBody, true);
            $state = $data['state'] ?? [];

            $checkVideo = isset($state['check_video']) && $state['check_video'] ? 1 : 0;
            $checkPolicy = isset($state['check_policy']) && $state['check_policy'] ? 1 : 0;
            $checkAssets = isset($state['check_assets']) && $state['check_assets'] ? 1 : 0;
            $checkIncidents = isset($state['check_incidents']) && $state['check_incidents'] ? 1 : 0;
            $checkAccess = isset($state['check_access']) && $state['check_access'] ? 1 : 0;

            // Guardar o Actualizar progreso (Metodología ON DUPLICATE KEY UPDATE)
            $stmt = $pdo->prepare("INSERT INTO training_progress (user_id, check_video, check_policy, check_assets, check_incidents, check_access)
                                   VALUES (?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE check_video=?, check_policy=?, check_assets=?, check_incidents=?, check_access=?");
            $stmt->execute([$userId, $checkVideo, $checkPolicy, $checkAssets, $checkIncidents, $checkAccess, $checkVideo, $checkPolicy, $checkAssets, $checkIncidents, $checkAccess]);
            echo json_encode(['success' => true, 'message' => 'Progreso guardado.']);
            exit();
        }
    }

    if ($requestModule === 'training_sessions' && $method === 'GET') {
        // Filtrar sesiones de capacitación del tenant actual
        $stmt = $pdo->prepare("SELECT code, title, instructor, attendees, topics, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS timestamp FROM training_sessions WHERE company_id = ? ORDER BY created_at DESC");
        $stmt->execute([$companyId]);
        echo json_encode($stmt->fetchAll());
        exit();
    }
    
    if ($requestModule === 'training_session' && $method === 'POST') {
        $rawBody = file_get_contents(PHP_INPUT);
        $data = json_decode($rawBody, true);

        $title = trim($data['title'] ?? '');
        $instructor = trim($data['instructor'] ?? UNASSIGNED);
        $attendees = intval($data['attendees'] ?? 0);
        $topics = trim($data['topics'] ?? '');
        $code = uniqid('CAP-');

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Título de la sesión requerido.']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO training_sessions (code, title, instructor, attendees, topics, admin_id, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, htmlspecialchars($title), htmlspecialchars($instructor), $attendees, htmlspecialchars($topics), $userId, $companyId]);
        echo json_encode(['success' => true, 'message' => 'Sesión registrada.']);
        exit();
    }

    // ============================================================================
    // MÓDULO 3: IMPLEMENTACIÓN (ISO 27001 Checklist & Meetings)
    // ============================================================================
    if ($requestModule === 'implementation') {
        if ($method === 'GET') {
            $stmt = $pdo->prepare("SELECT control_id, status, last_revised FROM impl_controls WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $rows = $stmt->fetchAll();

            $state = [];
            $dates = [];
            foreach ($rows as $r) {
                $state[$r['control_id']] = $r['status'];
                if ($r['last_revised']) {
                    $dates[$r['control_id']] = $r['last_revised'];
                }
            }
            $state['_dates'] = $dates;
            echo json_encode($state);
            exit();
        }
        elseif ($method === 'POST') {
            $rawBody = file_get_contents(PHP_INPUT);
            $data = json_decode($rawBody, true);
            $incomingState = $data['state'] ?? [];

            foreach ($incomingState as $ctrl => $status) {
                if ($ctrl === '_dates') {
                    continue;
                }
                
                $lastRevised = ($status === 'Cumplido') ? date('Y-m-d H:i:s') : null;

                $stmt = $pdo->prepare("INSERT INTO impl_controls (control_id, status, last_revised, admin_id, company_id)
                                       VALUES (?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE status = ?, last_revised = COALESCE(?, last_revised)");
                $stmt->execute([$ctrl, $status, $lastRevised, $userId, $companyId, $status, $lastRevised]);
            }
            echo json_encode(['success' => true, 'message' => 'Estado de implementación guardado.']);
            exit();
        }
    }

    if ($requestModule === 'impl_meetings' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT code, title, responsible, controls, status, notes, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS timestamp FROM impl_meetings WHERE company_id = ? ORDER BY created_at DESC");
        $stmt->execute([$companyId]);
        echo json_encode($stmt->fetchAll());
        exit();
    }
    
    if ($requestModule === 'impl_meeting' && $method === 'POST') {
        $rawBody = file_get_contents(PHP_INPUT);
        $data = json_decode($rawBody, true);

        $title = trim($data['title'] ?? '');
        $responsible = trim($data['responsible'] ?? UNASSIGNED);
        $controls = trim($data['controls'] ?? '');
        $status = trim($data['status'] ?? 'Planificada');
        $notes = trim($data['notes'] ?? '');
        $code = uniqid('IMP-');

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Título requerido.']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO impl_meetings (code, title, responsible, controls, status, notes, admin_id, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, htmlspecialchars($title), htmlspecialchars($responsible), htmlspecialchars($controls), htmlspecialchars($status), htmlspecialchars($notes), $userId, $companyId]);
        echo json_encode(['success' => true, 'message' => 'Reunión registrada.']);
        exit();
    }

    // ============================================================================
    // MÓDULO 4: AUDITORÍA (Audit Actas)
    // ============================================================================
    if ($requestModule === 'audit') {
        if ($method === 'GET') {
            $stmt = $pdo->prepare("SELECT code, type, responsible, topics, findings, status, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS timestamp FROM audit_meetings WHERE company_id = ? ORDER BY created_at DESC");
            $stmt->execute([$companyId]);
            echo json_encode($stmt->fetchAll());
            exit();
        }
        elseif ($method === 'POST') {
            $rawBody = file_get_contents(PHP_INPUT);
            $data = json_decode($rawBody, true);

            $type = trim($data['type'] ?? 'Apertura');
            $responsible = trim($data['responsible'] ?? UNASSIGNED);
            $topics = trim($data['topics'] ?? '');
            $findings = trim($data['findings'] ?? '');
            $status = trim($data['status'] ?? 'Planificada');
            $code = uniqid('ACT-');

            if (empty($topics)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Temas tratados requeridos.']);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO audit_meetings (code, type, responsible, topics, findings, status, admin_id, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, htmlspecialchars($type), htmlspecialchars($responsible), htmlspecialchars($topics), htmlspecialchars($findings), htmlspecialchars($status), $userId, $companyId]);
            echo json_encode(['success' => true, 'message' => 'Acta de auditoría guardada.']);
            exit();
        }
    }

    // ============================================================================
    // MÓDULO 5: GESTIÓN DE INCIDENTES (ISO 27001 Trazabilidad & Matriz de Riesgo)
    // ============================================================================
    if ($requestModule === 'incidents') {
        // A. ESTADÍSTICAS DEL DASHBOARD
        if ($method === 'GET' && isset($_GET['stats']) && $_GET['stats'] === 'true') {
            // Filtrar incidentes de la empresa del usuario
            $stmt = $pdo->prepare("SELECT status, classification, risk, created_at, resolved_at FROM incidents WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $incidents = $stmt->fetchAll();

            $total = count($incidents);
            $byStatus = ['Abierto' => 0, 'En Proceso' => 0, 'Resuelto' => 0, 'Cerrado' => 0];
            $byClass = [];
            $byDay = [];
            $criticalCount = 0;
            $resolvedCount = 0;
            $totalResolvedTime = 0;

            foreach ($incidents as $inc) {
                $status = $inc['status'] ?? 'Abierto';
                if (isset($byStatus[$status])) {
                    $byStatus[$status]++;
                }

                $class = $inc['classification'] ?? 'Otros';
                $byClass[$class] = ($byClass[$class] ?? 0) + 1;

                $day = substr($inc['created_at'], 0, 10);
                $byDay[$day] = ($byDay[$day] ?? 0) + 1;

                if ($inc['risk'] === 'Alto') {
                    $criticalCount++;
                }

                if (($status === 'Resuelto' || $status === 'Cerrado') && !empty($inc['resolved_at'])) {
                    $resolvedCount++;
                    $start = strtotime($inc['created_at']);
                    $end = strtotime($inc['resolved_at']);
                    $totalResolvedTime += ($end - $start);
                }
            }

            $resolvedPercent = $total > 0 ? round(($resolvedCount / $total) * 100, 1) : 0;
            $avgResolutionHours = $resolvedCount > 0 ? round(($totalResolvedTime / $resolvedCount) / 3600, 1) : 0;

            ksort($byDay);
            $byDay = array_slice($byDay, -10, 10, true); // Últimos 10 días

            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_status' => $byStatus,
                    'by_class' => $byClass,
                    'by_day' => $byDay,
                    'kpis' => [
                        'resolved_percent' => $resolvedPercent,
                        'avg_resolution_hours' => $avgResolutionHours,
                        'critical_count' => $criticalCount
                    ]
                ]
            ]);
            exit();
        }

        // B. LISTAR INCIDENTES
        elseif ($method === 'GET') {
            $stmt = $pdo->prepare("SELECT id, code, title, description, probability, impact, risk, classification, affected_assets, evidence_hash, lessons_learned, reporter, assignee, status, mitigation_plan, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at, DATE_FORMAT(resolved_at, '%Y-%m-%d %H:%i:%s') AS resolved_at FROM incidents WHERE company_id = ? ORDER BY created_at DESC");
            $stmt->execute([$companyId]);
            $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cargar historia para cada incidente
            foreach ($incidents as &$inc) {
                $histStmt = $pdo->prepare("SELECT DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s') AS timestamp, user, action FROM incident_history WHERE incident_id = ? ORDER BY timestamp ASC");
                $histStmt->execute([$inc['id']]);
                $inc['history'] = $histStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Formatear el id en base a su código para mantener la compatibilidad en frontend
                $inc['id'] = $inc['code'];
            }

            echo json_encode($incidents, JSON_PRETTY_PRINT);
            exit();
        }

        // C. REPORTAR NUEVO INCIDENTE (ISO 27001 A.5.24)
        elseif ($method === 'POST') {
            $rawBody = file_get_contents(PHP_INPUT);
            $data = json_decode($rawBody, true);

            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $probability = trim($data['probability'] ?? 'Media');
            $impact = trim($data['impact'] ?? 'Medio');
            $classification = trim($data['classification'] ?? 'Seguridad');
            
            // Campos ISO 27001 nuevos opcionales en el reporte inicial
            $affectedAssets = trim($data['affected_assets'] ?? 'No especificados');
            $evidenceHash = trim($data['evidence_hash'] ?? null);

            if (empty($title) || empty($description)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Título y descripción obligatorios.']);
                exit();
            }

            $risk = calculateRisk($probability, $impact);
            $reporter = $_SESSION['username'] ?? 'Sistema';

            // Autogenerar código de incidente secuencial (INC-001, INC-002...)
            $cStmt = $pdo->query("SELECT COUNT(*) FROM incidents");
            $count = $cStmt->fetchColumn();
            $newCode = "INC-" . str_pad($count + 1, 3, "0", STR_PAD_LEFT);

            // Insertar incidente
            $stmt = $pdo->prepare("INSERT INTO incidents (code, title, description, probability, impact, risk, classification, affected_assets, evidence_hash, company_id, admin_id, reporter)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newCode, htmlspecialchars($title), htmlspecialchars($description), htmlspecialchars($probability), htmlspecialchars($impact), $risk, htmlspecialchars($classification), htmlspecialchars($affectedAssets), htmlspecialchars($evidenceHash), $companyId, $userId, $reporter]);
            
            $incidentId = $pdo->lastInsertId();

            // Insertar auditoría inicial
            $hist = $pdo->prepare("INSERT INTO incident_history (incident_id, user, action) VALUES (?, ?, ?)");
            $hist->execute([$incidentId, $reporter, "Incidente reportado e identificado bajo norma ISO 27001 (Riesgo Calculado: $risk)"]);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Creado con éxito']);
            exit();
        }

        // D. ACTUALIZAR INCIDENTE (MITIGACIÓN, RESOLUCIÓN & CIERRE - A.5.26 & A.5.27)
        elseif ($method === 'PUT') {
            $rawBody = file_get_contents(PHP_INPUT);
            $data = json_decode($rawBody, true);

            $code = $data['id'] ?? null;
            if (!$code) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Código de incidente requerido.']);
                exit();
            }

            // Encontrar ID por código
            $stmt = $pdo->prepare("SELECT id, status, mitigation_plan, assignee, lessons_learned, evidence_hash FROM incidents WHERE code = ? AND company_id = ?");
            $stmt->execute([$code, $companyId]);
            $incident = $stmt->fetch();

            if (!$incident) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Incidente no encontrado.']);
                exit();
            }

            $incidentId = $incident['id'];
            $modifier = $_SESSION['username'] ?? 'Sistema';
            $changes = [];

            $newStatus = $data['status'] ?? $incident['status'];
            $newMitigation = $data['mitigation_plan'] ?? $incident['mitigation_plan'];
            $newAssignee = $data['assignee'] ?? $incident['assignee'];
            
            // Campos ISO 27001 de cierre
            $newLessons = $data['lessons_learned'] ?? $incident['lessons_learned'];
            $newEvHash = $data['evidence_hash'] ?? $incident['evidence_hash'];

            // Trazar cambios para el historial de auditoría
            if ($newStatus !== $incident['status']) {
                $changes[] = "Estado de '{$incident['status']}' a '$newStatus'";
            }
            if ($newMitigation !== $incident['mitigation_plan']) {
                $changes[] = "Plan de mitigación actualizado";
            }
            if ($newAssignee !== $incident['assignee']) {
                $changes[] = "Analista asignado: $newAssignee";
            }
            if ($newLessons !== $incident['lessons_learned']) {
                $changes[] = "Análisis de lecciones aprendidas (ISO 27001 A.5.27)";
            }
            if ($newEvHash !== $incident['evidence_hash']) {
                $changes[] = "Registro de integridad de evidencia (Hash actualizado)";
            }

            $resolvedAt = $incident['resolved_at'] ?? null;
            if (($newStatus === 'Resuelto' || $newStatus === 'Cerrado') && !$resolvedAt) {
                $resolvedAt = date('Y-m-d H:i:s');
            }

            // Actualizar tabla
            $upd = $pdo->prepare("UPDATE incidents SET status = ?, mitigation_plan = ?, assignee = ?, lessons_learned = ?, evidence_hash = ?, resolved_at = ? WHERE id = ?");
            $upd->execute([htmlspecialchars($newStatus), htmlspecialchars($newMitigation), htmlspecialchars($newAssignee), htmlspecialchars($newLessons), htmlspecialchars($newEvHash), $resolvedAt, $incidentId]);

            // Registrar en historial si hay cambios
            if (!empty($changes)) {
                $hist = $pdo->prepare("INSERT INTO incident_history (incident_id, user, action) VALUES (?, ?, ?)");
                $hist->execute([$incidentId, $modifier, "Actualización: " . implode(" | ", $changes)]);
            }

            echo json_encode(['success' => true, 'message' => 'Incidente actualizado correctamente.']);
            exit();
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de servidor: ' . $e->getMessage()
    ]);
    exit();
}

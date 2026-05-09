<?php
/**
 * api_externa/index.php
 * Motor de ISO 27001 - Gestiona el análisis de riesgo y timeline.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$db_file = __DIR__ . '/incidents_db.json';

function calculateRisk($prob, $imp) {
    if (!$prob || !$imp) return 'Desconocido';
    $probMap = ['Baja'=>1, 'Media'=>2, 'Alta'=>3];
    $impMap = ['Bajo'=>1, 'Medio'=>2, 'Alto'=>3];
    $p = isset($probMap[$prob]) ? $probMap[$prob] : 1;
    $i = isset($impMap[$imp]) ? $impMap[$imp] : 1;
    
    $score = $p * $i;
    if ($score >= 6) return 'Alto';
    if ($score >= 3) return 'Medio';
    return 'Bajo';
}

if (!file_exists($db_file)) {
    if (file_put_contents($db_file, json_encode([], JSON_PRETTY_PRINT)) === false) {
        http_response_code(500);
        echo json_encode(["success"=>false, "message" => "Error crítico: No se pudo inicializar la base de datos de incidentes."]);
        exit();
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$current_data = json_decode(file_get_contents($db_file), true);
if (!is_array($current_data)) $current_data = [];

if ($method === 'GET') {
    $adminFiltro = isset($_GET['admin_id']) ? $_GET['admin_id'] : 'global';
    $empresaFiltro = isset($_GET['empresa']) ? $_GET['empresa'] : null;
    $safeEmpresa = $empresaFiltro ? '_' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($empresaFiltro)) : '';
    $requestStats = isset($_GET['stats']) && $_GET['stats'] === 'true';
    $requestModule = isset($_GET['module']) ? $_GET['module'] : 'incidents';

    if ($requestModule === 'training') {
        $db = __DIR__ . "/training_db_{$adminFiltro}{$safeEmpresa}.json";
        echo file_exists($db) ? file_get_contents($db) : json_encode([]);
        exit();
    }
    if ($requestModule === 'training_sessions') {
        $db = __DIR__ . "/training_sessions_{$adminFiltro}{$safeEmpresa}.json";
        echo file_exists($db) ? file_get_contents($db) : json_encode([]);
        exit();
    }
    if ($requestModule === 'impl_meetings') {
        $db = __DIR__ . "/impl_meetings_{$adminFiltro}{$safeEmpresa}.json";
        echo file_exists($db) ? file_get_contents($db) : json_encode([]);
        exit();
    }
    if ($requestModule === 'implementation') {
        $db = __DIR__ . "/impl_db_{$adminFiltro}{$safeEmpresa}.json";
        echo file_exists($db) ? file_get_contents($db) : json_encode([]);
        exit();
    }
    if ($requestModule === 'audit') {
        $db = __DIR__ . "/audit_db_{$adminFiltro}{$safeEmpresa}.json";
        echo file_exists($db) ? file_get_contents($db) : json_encode([]);
        exit();
    }

    $filtered_data = array_values(array_filter($current_data, function($inc) use ($adminFiltro, $empresaFiltro) {
        $matchesAdmin = true;
        $matchesEmpresa = true;

        if ($adminFiltro !== null && $adminFiltro !== 'NULL') {
            $matchesAdmin = isset($inc['admin_id']) && (string)$inc['admin_id'] === (string)$adminFiltro;
        }
        if ($empresaFiltro !== null) {
            $matchesEmpresa = isset($inc['empresa']) && strcasecmp($inc['empresa'], $empresaFiltro) === 0;
        }

        return $matchesAdmin && $matchesEmpresa;
    }));

    if ($requestStats) {
        $stats = [
            'total' => count($filtered_data),
            'by_status' => ['Abierto' => 0, 'En Proceso' => 0, 'Resuelto' => 0, 'Cerrado' => 0],
            'by_class' => [],
            'by_day' => [],
            'kpis' => [
                'resolved_percent' => 0,
                'avg_resolution_hours' => 0,
                'critical_count' => 0
            ]
        ];

        $totalResolvedTime = 0;
        $resolvedCount = 0;

        foreach ($filtered_data as $inc) {
            $status = $inc['status'] ?? 'Abierto';
            if (isset($stats['by_status'][$status])) $stats['by_status'][$status]++;

            $class = $inc['classification'] ?? 'Otros';
            $stats['by_class'][$class] = ($stats['by_class'][$class] ?? 0) + 1;

            $day = substr($inc['created_at'], 0, 10);
            $stats['by_day'][$day] = ($stats['by_day'][$day] ?? 0) + 1;

            if (($inc['risk'] ?? '') === 'Alto') $stats['kpis']['critical_count']++;
            
            if (($status === 'Resuelto' || $status === 'Cerrado') && !empty($inc['resolved_at'])) {
                $resolvedCount++;
                $start = strtotime($inc['created_at']);
                $end = strtotime($inc['resolved_at']);
                $totalResolvedTime += ($end - $start);
            }
        }

        if ($stats['total'] > 0) {
            $stats['kpis']['resolved_percent'] = round(($resolvedCount / $stats['total']) * 100, 1);
        }
        if ($resolvedCount > 0) {
            $stats['kpis']['avg_resolution_hours'] = round(($totalResolvedTime / $resolvedCount) / 3600, 1);
        }

        ksort($stats['by_day']);
        $stats['by_day'] = array_slice($stats['by_day'], -10, 10, true);

        echo json_encode(['success' => true, 'data' => $stats]);
    } else {
        echo json_encode($filtered_data, JSON_PRETTY_PRINT);
    }
    exit();
} elseif ($method === 'POST') {
    $input_json = file_get_contents('php://input');
    $data = json_decode($input_json, true);

    // DETERMINAR RUTA SEGÚN EL CAMPO "module"
    $module = isset($data['module']) ? $data['module'] : 'incidents';
    $adminId = isset($data['admin_id']) ? $data['admin_id'] : 'global';
    $empresa = isset($data['empresa']) ? $data['empresa'] : null;
    $safeEmpresa = $empresa ? '_' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($empresa)) : '';

    if ($module === 'training') {
        $db = __DIR__ . "/training_db_{$adminId}{$safeEmpresa}.json";
        file_put_contents($db, json_encode($data['state'], JSON_PRETTY_PRINT));
        echo json_encode(["success" => true, "message" => "Progreso de capacitación guardado"]);
        exit();
    }

    if ($module === 'training_session') {
        $db = __DIR__ . "/training_sessions_{$adminId}{$safeEmpresa}.json";
        $current = file_exists($db) ? json_decode(file_get_contents($db), true) : [];
        if (!is_array($current)) $current = [];
        date_default_timezone_set('America/Bogota');
        array_unshift($current, [
            "id"         => uniqid('CAP-'),
            "timestamp"  => date('Y-m-d H:i:s'),
            "title"      => htmlspecialchars($data['title'] ?? 'Sin título'),
            "instructor" => htmlspecialchars($data['instructor'] ?? 'Sin asignar'),
            "attendees"  => intval($data['attendees'] ?? 0),
            "topics"     => htmlspecialchars($data['topics'] ?? ''),
            "admin_id"   => $adminId
        ]);
        file_put_contents($db, json_encode($current, JSON_PRETTY_PRINT));
        echo json_encode(["success" => true, "message" => "Sesión de capacitación registrada"]);
        exit();
    }

    if ($module === 'impl_meeting') {
        $db = __DIR__ . "/impl_meetings_{$adminId}{$safeEmpresa}.json";
        $current = file_exists($db) ? json_decode(file_get_contents($db), true) : [];
        if (!is_array($current)) $current = [];
        date_default_timezone_set('America/Bogota');
        array_unshift($current, [
            "id"          => uniqid('IMP-'),
            "timestamp"   => date('Y-m-d H:i:s'),
            "title"       => htmlspecialchars($data['title'] ?? 'Sin título'),
            "responsible" => htmlspecialchars($data['responsible'] ?? ''),
            "controls"    => htmlspecialchars($data['controls'] ?? ''),
            "status"      => htmlspecialchars($data['status'] ?? 'Pendiente'),
            "notes"       => htmlspecialchars($data['notes'] ?? ''),
            "admin_id"    => $adminId
        ]);
        file_put_contents($db, json_encode($current, JSON_PRETTY_PRINT));
        echo json_encode(["success" => true, "message" => "Reunión de implementación registrada"]);
        exit();
    }

    if ($module === 'implementation') {
        $db = __DIR__ . "/impl_db_{$adminId}{$safeEmpresa}.json";
        date_default_timezone_set('America/Bogota');
        $now = date('Y-m-d H:i:s');
        $existing = file_exists($db) ? json_decode(file_get_contents($db), true) : [];
        if (!is_array($existing)) $existing = [];
        if (!isset($existing['_dates'])) $existing['_dates'] = [];
        $incomingState = (isset($data['state']) && is_array($data['state'])) ? $data['state'] : [];
        $stateToSave = array_merge($existing, $incomingState);
        
        foreach ($incomingState as $ctrl => $val) {
            if ($ctrl === '_dates') continue;
            if ($val === 'Cumplido' && !isset($existing['_dates'][$ctrl])) {
                $existing['_dates'][$ctrl] = $now;
            } elseif ($val !== 'Cumplido') {
                unset($existing['_dates'][$ctrl]);
            }
        }
        
        $merged = array_merge($stateToSave, ['_dates' => $existing['_dates']]);
        file_put_contents($db, json_encode($merged, JSON_PRETTY_PRINT));
        echo json_encode(["success" => true, "message" => "Estado de implementación actualizado"]);
        exit();
    }

    if ($module === 'audit') {
        $db = __DIR__ . "/audit_db_{$adminId}{$safeEmpresa}.json";
        $current = file_exists($db) ? json_decode(file_get_contents($db), true) : [];
        if (!is_array($current)) $current = [];
        date_default_timezone_set('America/Bogota');
        array_unshift($current, [
            "id"          => uniqid('ACT-'),
            "timestamp"   => date('Y-m-d H:i:s'),
            "type"        => $data['type'],
            "topics"      => $data['topics'],
            "findings"    => $data['findings'] ?? '',
            "status"      => $data['status'] ?? 'Planificada',
            "responsible" => $data['responsible'] ?? '',
            "admin_id"    => $adminId
        ]);
        file_put_contents($db, json_encode($current, JSON_PRETTY_PRINT));
        echo json_encode(["success" => true, "message" => "Acta registrada"]);
        exit();
    }

    // Generación de ID Robusta para Incidentes (Lógica original se mantiene)
    $max_id_num = 0;
    foreach ($current_data as $inc) {
        if (isset($inc['id']) && preg_match('/INC-(\d+)/', $inc['id'], $matches)) {
            $num = (int)$matches[1];
            if ($num > $max_id_num) $max_id_num = $num;
        }
    }
    $new_id_number = $max_id_num + 1;
    $new_id = "INC-" . str_pad($new_id_number, 3, "0", STR_PAD_LEFT);
    
    $risk = calculateRisk($data['probability'], $data['impact']);
    
    date_default_timezone_set('America/Bogota');
    $now = date('Y-m-d H:i:s');
    
    $reporter = isset($data['reporter']) ? htmlspecialchars($data['reporter']) : 'Sistema';
    
    $new_incident = [
        "id" => $new_id,
        "title" => htmlspecialchars($data['title']),
        "description" => htmlspecialchars($data['description']),
        "probability" => htmlspecialchars($data['probability']),
        "impact" => htmlspecialchars($data['impact']),
        "risk" => $risk,
        "classification" => htmlspecialchars($data['classification']),
        "empresa" => isset($data['empresa']) ? htmlspecialchars($data['empresa']) : 'Sin Empresa',
        "admin_id" => isset($data['admin_id']) ? $data['admin_id'] : null,
        "status" => "Abierto",
        "reporter" => $reporter,
        "assignee" => "Sin Asignar",
        "mitigation_plan" => "",
        "created_at" => $now,
        "resolved_at" => null,
        "history" => [
            [
                "timestamp" => $now,
                "user" => $reporter,
                "action" => "Incidente reportado y clasificado con Riesgo: $risk"
            ]
        ]
    ];
    
    array_unshift($current_data, $new_incident);
    if (file_put_contents($db_file, json_encode($current_data, JSON_PRETTY_PRINT)) === false) {
        http_response_code(500);
        echo json_encode(["success"=>false, "message" => "Error al guardar evidencia en disco."]);
        exit();
    }
    
    http_response_code(201);
    echo json_encode(["success"=>true, "message" => "Creado con éxito", "data" => $new_incident]);
    exit();
} elseif ($method === 'PUT') {
    // ... (Mantener lógica de incidentes)
    $input_json = file_get_contents('php://input');
    $data = json_decode($input_json, true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID Requerido"]);
        exit();
    }
    
    $incident_found = false;
    date_default_timezone_set('America/Bogota');
    $now = date('Y-m-d H:i:s');
    $modifier = isset($data['modified_by']) ? htmlspecialchars($data['modified_by']) : 'Sistema';
    
    foreach ($current_data as &$incident) {
        if ($incident['id'] === $data['id']) {
            $incident_found = true;
            $changes = [];
            
            if (isset($data['status']) && $data['status'] !== $incident['status']) {
                $old_st = $incident['status'];
                $incident['status'] = htmlspecialchars($data['status']);
                $changes[] = "Estado de '$old_st' a '{$incident['status']}'";
                
                if ($incident['status'] === 'Resuelto' || $incident['status'] === 'Cerrado') {
                    if (!$incident['resolved_at']) $incident['resolved_at'] = $now;
                }
            }
            if (isset($data['mitigation_plan']) && $data['mitigation_plan'] !== $incident['mitigation_plan']) {
                $incident['mitigation_plan'] = htmlspecialchars($data['mitigation_plan']);
                $changes[] = "Plan de Mitigación actualizado";
            }
            if (isset($data['assignee']) && $data['assignee'] !== $incident['assignee']) {
                $incident['assignee'] = htmlspecialchars($data['assignee']);
                $changes[] = "Asignado a: {$incident['assignee']}";
            }
            
            if (count($changes) > 0) {
                $incident['history'][] = [
                    "timestamp" => $now,
                    "user" => $modifier,
                    "action" => "Actualización: " . implode(" | ", $changes)
                ];
            }
            
            break;
        }
    }
    
    if ($incident_found) {
        if (file_put_contents($db_file, json_encode($current_data, JSON_PRETTY_PRINT)) === false) {
            http_response_code(500);
            echo json_encode(["success"=>false, "message" => "Error al guardar los cambios en la auditoría."]);
            exit();
        }
        http_response_code(200);
        echo json_encode(["success"=>true, "message" => "Incidente actualizado correctamente"]);
    } else {
        http_response_code(404);
        echo json_encode(["success"=>false, "message" => "Incidente no encontrado"]);
    }
    exit();
} elseif ($method === 'PATCH') {
    // NUEVO: GET para otros módulos usando PATCH o un parámetro en GET
    // Por simplicidad en la entrega, usaremos GET con parámetros adicionales
    exit();
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit();
}

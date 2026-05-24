<?php
// Script de deploy automático desde GitHub
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log file
$logFile = '/home/u9708588862/public_html/deploy.log';
$projectDir = '/home/u9708588862/public_html';

// Obtener el evento de GitHub
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Función para escribir logs
function writeLog($message) {
    global $logFile;
    $log = date('Y-m-d H:i:s') . " - " . $message . "\n";
    @file_put_contents($logFile, $log, FILE_APPEND);
}

try {
    // Si es un ping de prueba, responder OK
    if (isset($event['zen'])) {
        writeLog('Ping recibido de GitHub');
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Webhook ping recibido']);
        exit;
    }

    // Verificar que sea un push a main
    if (isset($event['ref']) && $event['ref'] === 'refs/heads/main') {
        writeLog('Push detectado en main branch');
        
        // Cambiar al directorio del proyecto
        if (!chdir($projectDir)) {
            throw new Exception('No se pudo cambiar al directorio del proyecto');
        }
        
        writeLog('Ejecutando git pull...');
        
        // Ejecutar git pull con salida completa
        $output = shell_exec('cd ' . escapeshellarg($projectDir) . ' && git pull 2>&1');
        
        writeLog('Resultado del git pull: ' . $output);
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Deploy completado']);
    } else {
        writeLog('Evento ignorado - no es un push a main');
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'Evento no es un push a main']);
    }
} catch (Exception $e) {
    writeLog('Error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

<?php
// Script de deploy automático desde GitHub

// Obtener el evento de GitHub
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Si es un ping de prueba, responder OK
if (isset($event['zen'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Webhook ping recibido']);
    exit;
}

// Verificar que sea un push a main
if (isset($event['ref']) && $event['ref'] === 'refs/heads/main') {
    // Cambiar al directorio del proyecto
    chdir('/home/u9708588862/public_html');
    
    // Ejecutar git pull
    $output = shell_exec('git pull 2>&1');
    
    // Registrar el deploy en un archivo de log
    $log = date('Y-m-d H:i:s') . " - Deploy ejecutado\n";
    $log .= "Output: " . $output . "\n\n";
    
    file_put_contents('/home/u9708588862/public_html/deploy.log', $log, FILE_APPEND);
    
    // Responder con éxito
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Deploy completado']);
} else {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => 'Evento no es un push a main']);
}
?>

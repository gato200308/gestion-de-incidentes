<?php
// Auto-deploy webhook desde GitHub
// Simple y funcional sin dependencias complicadas

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Si es ping, responder OK
if (isset($event['zen'])) {
    http_response_code(200);
    exit;
}

// Si es push a main, ejecutar comando
if (isset($event['ref']) && $event['ref'] === 'refs/heads/main') {
    // Cambiar a directorio del proyecto
    $projectDir = '/home/u9708588862/public_html';
    chdir($projectDir);
    
    // Ejecutar comando para descargar cambios
    // Usar wget para descargar y descomprimir
    $cmd = 'cd ' . escapeshellarg($projectDir) . ' && wget -q https://github.com/gato200308/gestion-de-incidentes/archive/refs/heads/main.zip -O /tmp/repo.zip && unzip -q -o /tmp/repo.zip -d /tmp && cp -r /tmp/gestion-de-incidentes-main/* . && rm -rf /tmp/repo.zip /tmp/gestion-de-incidentes-main';
    
    shell_exec($cmd);
    
    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(200);
    echo 'OK';
}
?>

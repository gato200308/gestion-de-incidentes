<?php
// Script de deploy automático desde GitHub
// Descarga los archivos directamente sin usar git

error_reporting(E_ALL);
ini_set('display_errors', 0);

$logFile = '/home/u9708588862/public_html/deploy.log';
$projectDir = '/home/u9708588862/public_html';

// Función para escribir logs
function writeLog($message) {
    global $logFile;
    $log = date('Y-m-d H:i:s') . " - " . $message . "\n";
    @file_put_contents($logFile, $log, FILE_APPEND);
}

try {
    // Obtener el evento de GitHub
    $payload = file_get_contents('php://input');
    $event = json_decode($payload, true);

    // Si es un ping de prueba, responder OK
    if (isset($event['zen'])) {
        writeLog('✓ Ping recibido de GitHub');
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Webhook ping recibido']);
        exit;
    }

    // Verificar que sea un push a main
    if (!isset($event['ref']) || $event['ref'] !== 'refs/heads/main') {
        writeLog('⊘ Evento ignorado - no es un push a main');
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        exit;
    }

    writeLog('→ Push detectado en main branch');

    // Descargar el ZIP del repositorio
    $zipUrl = 'https://github.com/gato200308/gestion-de-incidentes/archive/refs/heads/main.zip';
    $zipFile = '/tmp/gestion-incidentes-main.zip';
    
    writeLog('Descargando: ' . $zipUrl);
    
    $zipContent = @file_get_contents($zipUrl, false, stream_context_create([
        'http' => ['timeout' => 30]
    ]));
    
    if ($zipContent === false) {
        throw new Exception('No se pudo descargar el repositorio');
    }
    
    if (!file_put_contents($zipFile, $zipContent)) {
        throw new Exception('No se pudo escribir el archivo ZIP');
    }
    
    writeLog('✓ ZIP descargado (' . filesize($zipFile) . ' bytes)');

    // Extraer el ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new Exception('No se pudo abrir el ZIP');
    }
    
    // Crear directorio temporal
    $tmpDir = '/tmp/gestion-incidentes-extract';
    @mkdir($tmpDir, 0755, true);
    
    $zip->extractTo($tmpDir);
    $zip->close();
    
    writeLog('✓ ZIP extraído');

    // Copiar archivos (excepto .git)
    $sourceDir = $tmpDir . '/gestion-de-incidentes-main';
    
    if (!is_dir($sourceDir)) {
        throw new Exception('Estructura del ZIP no es válida');
    }

    // Copiar archivos recursivamente
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $copiedFiles = 0;
    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
        
        // Saltar carpeta .git
        if (strpos($relativePath, '.git') === 0) {
            continue;
        }
        
        $destPath = $projectDir . '/' . $relativePath;
        
        if ($item->isDir()) {
            @mkdir($destPath, 0755, true);
        } else {
            @mkdir(dirname($destPath), 0755, true);
            if (copy($item->getPathname(), $destPath)) {
                $copiedFiles++;
            }
        }
    }

    writeLog('✓ Copiados ' . $copiedFiles . ' archivos');

    // Limpiar
    @unlink($zipFile);
    // Función nativa de PHP para borrar carpetas recursivamente (ya que exec está bloqueado en Hostinger)
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($tmpDir);

    writeLog('✓ Deploy completado exitosamente');
    
    http_response_code(200);
    echo json_encode(['status' => 'success', 'files' => $copiedFiles]);

} catch (Exception $e) {
    writeLog('✗ Error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

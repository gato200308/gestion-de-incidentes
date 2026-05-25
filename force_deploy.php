<?php
// force_deploy.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Iniciando Despliegue Forzado...</h2>";

$zipUrl = 'https://github.com/gato200308/gestion-de-incidentes/archive/refs/heads/main.zip';
$zipFile = __DIR__ . '/temp_force.zip';

echo "Descargando ZIP de GitHub...<br>";

$ch = curl_init($zipUrl);
$fp = fopen($zipFile, 'w+');
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 50);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if ($httpCode != 200) {
    die("<b>Error crítico:</b> HTTP Code $httpCode al descargar el ZIP.");
}

$filesize = filesize($zipFile);
echo "ZIP descargado exitosamente. Tamaño: " . $filesize . " bytes.<br>";

if ($filesize < 1000) {
    die("<b>Error crítico:</b> El archivo ZIP es muy pequeño, la descarga falló o está vacío.");
}

echo "Extrayendo ZIP...<br>";
$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    die("<b>Error crítico:</b> No se pudo abrir el archivo ZIP.");
}

$tmpDir = __DIR__ . '/tmp_force_extract';
@mkdir($tmpDir, 0755, true);

$zip->extractTo($tmpDir);
$zip->close();
echo "Archivos extraídos en directorio temporal.<br>";

$sourceDir = $tmpDir . '/gestion-de-incidentes-main';
if (!is_dir($sourceDir)) {
    die("<b>Error crítico:</b> No se encontró la carpeta 'gestion-de-incidentes-main' dentro del ZIP.");
}

$projectDir = __DIR__;
echo "Copiando archivos a: $projectDir <br>";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$copiedFiles = 0;
$failedFiles = 0;

foreach ($iterator as $item) {
    $relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
    
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
        } else {
            $failedFiles++;
            echo "<span style='color:red;'>Falló: $relativePath</span><br>";
        }
    }
}

echo "<h3>¡Proceso Terminado!</h3>";
echo "Archivos copiados con éxito: <b>$copiedFiles</b><br>";
if ($failedFiles > 0) {
    echo "Archivos que fallaron: <b>$failedFiles</b><br>";
}

// Limpieza
@unlink($zipFile);
// Limpieza basica
$dirIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($dirIterator as $file) {
    $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
}
@rmdir($tmpDir);
echo "Archivos temporales eliminados.<br>";
?>

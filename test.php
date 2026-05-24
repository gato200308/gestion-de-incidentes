<?php
$tmpDir = '/tmp/gestion-incidentes-extract';
exec('rm -rf ' . $tmpDir);
mkdir($tmpDir);
$zip = new ZipArchive();
if ($zip->open('test.zip') === true) {
    $zip->extractTo($tmpDir);
    $zip->close();
    
    $sourceDir = $tmpDir . '/gestion-de-incidentes-main';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $count = 0;
    foreach ($iterator as $item) {
        if (!$item->isDir()) {
            $count++;
        }
    }
    echo "Count: $count\n";
} else {
    echo "Failed to open zip\n";
}

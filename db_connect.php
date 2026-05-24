<?php
// db_connect.php

$host = 'localhost';
$db = 'u9708588862_incidentes1';
$user = 'u9708588862_santiago1';
$pass = 'Gemma08*';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false, // Importante para seguridad contra Inyecciones SQL (ISO 27001)
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Para entornos de producción (ISO 27001) no se debe mostrar el error real de DB.
    // Como esto es un proyecto de prueba, lo dejaremos visible en el error_log
    error_log($e->getMessage());
}
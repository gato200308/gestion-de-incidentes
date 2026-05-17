<?php
// db_connect.php

$host = '127.0.0.1';
$db = 'gestion_incidentes';
$user = getenv('DB_USER') ?: 'root'; // Usuario por defecto de XAMPP
$pass = getenv('DB_PASS') ?: '0525';     // Contraseña por defecto de XAMPP
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
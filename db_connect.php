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
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed');
}
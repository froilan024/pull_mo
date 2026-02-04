<?php
// Simple PDO connection for local XAMPP — edit these if your MySQL root has a password
$DB_HOST = '127.0.0.1';
$DB_NAME = 'fileasy';
$DB_USER = 'root';
$DB_PASS = ''; // set to 'your_root_password' if needed

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}
<?php
// Hostinger Database Configuration
$host = 'localhost';
$db_name = 'u870495195_admission';
$username = 'u870495195_admission';
$password = '8uJs293cjJB';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // For production, you might want to log this instead of displaying it
    die('Connection failed: ' . $e->getMessage());
}

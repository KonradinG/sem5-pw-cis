<?php
// db.php - returns PDO connection

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? 'db';
$db   = $_ENV['DB_NAME'] ?? 'appdb';
$user = $_ENV['DB_USER'] ?? 'appuser';
$pass = $_ENV['DB_PASS'] ?? 'apppass';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    error_log("DB connection error: " . $e->getMessage());
    http_response_code(500);
    echo "Database connection error";
    exit;
}

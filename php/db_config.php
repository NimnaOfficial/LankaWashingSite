<?php
declare(strict_types=1);

// ✅ BACK TO LOCALHOST
$host = "localhost";
$port = "3307"; // Change to 3306 if that is your default XAMPP port
$db   = "production_db";
$user = "root";
$pass = ""; // Default XAMPP has no password
$charset = "utf8mb4";

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Local Connection Failed: " . $e->getMessage()]);
    exit;
}
?>
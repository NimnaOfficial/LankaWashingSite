<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

header("Content-Type: application/json; charset=utf-8");

// FIX: Correct path to DB Config
require_once __DIR__ . "/db_config.php";

if (!isset($_SESSION["customer_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$customerId = (int)$_SESSION["customer_id"];

try {
    // 1. Get Customer Details
    $stmt = $pdo->prepare("
        SELECT name, email, phone, address
        FROM customer
        WHERE customerId = ?
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    // 2. Get Order Count
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orders
        WHERE customerId = ?
    ");
    $stmt2->execute([$customerId]);
    $user["totalOrders"] = (int)$stmt2->fetchColumn();

    echo json_encode($user);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
?>
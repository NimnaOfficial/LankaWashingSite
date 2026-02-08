<?php
declare(strict_types=1);
ini_set('session.cookie_path', '/');
session_start();
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/db_config.php";

if (!isset($_SESSION["customer_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$customerId = (int)$_SESSION["customer_id"];

try {
    // 1. Total Owed (Orders)
    $stmtOrders = $pdo->prepare("SELECT COALESCE(SUM(totalAmount), 0) AS total FROM orders WHERE customerId = ? AND LOWER(orderStatus) NOT IN ('cancelled')");
    $stmtOrders->execute([$customerId]);
    $ordersTotal = (float)$stmtOrders->fetch()["total"];

    // 2. Total Paid (Payments)
    $stmtPaid = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE customerId = ? AND LOWER(status) IN ('success', 'paid', 'completed', 'pending')");
    $stmtPaid->execute([$customerId]);
    $paidTotal = (float)$stmtPaid->fetch()["total"];

    $due = max(0.0, $ordersTotal - $paidTotal);

    echo json_encode(["success" => true, "due" => $due]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>
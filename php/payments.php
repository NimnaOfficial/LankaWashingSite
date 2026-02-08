<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

header("Content-Type: application/json; charset=utf-8");

// FIX: Ensure this points to your actual config file
require_once __DIR__ . "/db_config.php";

// 1. Check Login
if (!isset($_SESSION["customer_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$customerId = (int)$_SESSION["customer_id"];

try {
    // 2. Fetch Real Data from Database
    // We use the exact column names from your SQL file: payment_date, paymentId, etc.
    $stmt = $pdo->prepare("
        SELECT
            paymentId,
            orderId,
            amount,
            payment_date, 
            method,
            status,
            transaction_ref
        FROM payments
        WHERE customerId = ?
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$customerId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format Data for the Frontend
    $data = array_map(function ($r) {
        return [
            "transaction" => "TXN-" . str_pad((string)$r["paymentId"], 6, "0", STR_PAD_LEFT),
            "date"        => $r["payment_date"],
            "orderRef"    => $r["orderId"],
            "method"      => $r["method"],
            "amount"      => (float)$r["amount"],
            "status"      => ucfirst($r["status"]) // e.g. 'success' -> 'Success'
        ];
    }, $rows);

    echo json_encode([
        "success" => true,
        "count" => count($data),
        "data"  => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Server error",
        "details" => $e->getMessage() 
    ]);
}
?>
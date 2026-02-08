<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Check Authentication
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$customerId = (int)$_SESSION['customer_id'];

try {
    // 2. Get Customer Name
    $stmt = $pdo->prepare("SELECT name FROM customer WHERE customerId = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $name = $stmt->fetchColumn() ?: "Customer";

    // 3. Stats: Total Orders (All time)
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customerId = ?");
    $stmtTotal->execute([$customerId]);
    $totalOrders = (int)$stmtTotal->fetchColumn();

    // 4. Stats: Pending/Active Orders
    // Includes: Pending, Processing, In Progress, Approved
    $stmtPending = $pdo->prepare("
        SELECT COUNT(*) FROM orders 
        WHERE customerId = ? 
        AND LOWER(orderStatus) IN ('pending', 'processing', 'in progress', 'approved')
    ");
    $stmtPending->execute([$customerId]);
    $pendingOrders = (int)$stmtPending->fetchColumn();

    // 5. Stats: Completed Orders (✅ FIXED: Expanded Status List)
    // Includes: Completed, Complete, Delivered, Done, Shipped
    $stmtCompleted = $pdo->prepare("
        SELECT COUNT(*) FROM orders 
        WHERE customerId = ? 
        AND LOWER(orderStatus) IN ('completed', 'complete', 'delivered', 'done', 'shipped')
    ");
    $stmtCompleted->execute([$customerId]);
    $completedOrders = (int)$stmtCompleted->fetchColumn();

    // 6. Stats: Payment Due
    // Logic: Sum of 'totalAmount' for orders that are NOT Completed/Cancelled
    $stmtDue = $pdo->prepare("
        SELECT COALESCE(SUM(totalAmount), 0)
        FROM orders
        WHERE customerId = ?
        AND LOWER(orderStatus) NOT IN ('completed', 'complete', 'delivered', 'done', 'shipped', 'cancelled', 'rejected')
    ");
    $stmtDue->execute([$customerId]);
    $paymentDue = (float)$stmtDue->fetchColumn();

    // 7. Return JSON
    echo json_encode([
        'success' => true,
        'name' => $name,
        'totalOrders' => $totalOrders,
        'pendingOrders' => $pendingOrders,
        'completedOrders' => $completedOrders, 
        'paymentDue' => number_format($paymentDue, 2, '.', '')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
?>
<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/db_config.php';
header('Content-Type: application/json; charset=utf-8');

// 1. Check Login
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 2. Validate Input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['resourceName'], $input['quantity'], $input['unitPrice'], $input['expectedDate'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$supplierId   = (int)$_SESSION['supplier_id'];
$resourceName = trim($input['resourceName']);
$quantity     = (double)$input['quantity'];
$unitPrice    = (double)$input['unitPrice'];
$expectedDate = $input['expectedDate']; // Format: YYYY-MM-DD

try {
    $pdo->beginTransaction();

    // 3. Insert into purchaseorder (The "Header")
    $stmtPO = $pdo->prepare("
        INSERT INTO purchaseorder (supplierId, orderDate, expectedDate, status) 
        VALUES (?, CURDATE(), ?, 'Pending')
    ");
    $stmtPO->execute([$supplierId, $expectedDate]);
    $newPOId = $pdo->lastInsertId();

    // 4. Insert into purchaseorderitem (The "Items")
    $stmtItem = $pdo->prepare("
        INSERT INTO purchaseorderitem (purchaseOrderId, resourceName, unitPrice, quantity) 
        VALUES (?, ?, ?, ?)
    ");
    $stmtItem->execute([$newPOId, $resourceName, $unitPrice, $quantity]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'PO Created Successfully', 'po_id' => $newPOId]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supplierId = (int)$_SESSION['supplier_id'];
$id = (int)($_POST['purchaseOrderId'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');
$type = trim($_POST['type'] ?? 'PO'); 

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$allowedStatus = ['Accepted', 'Rejected'];
if (!in_array($newStatus, $allowedStatus, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

try {
    $pdo->beginTransaction();
    $updated = false;

    if ($type === 'DELIVERY') {
        // Update Stock Transaction
        $stmt = $pdo->prepare("
            UPDATE stock_transaction st
            JOIN resource r ON st.resourceId = r.resourceId
            SET st.status = ?
            WHERE st.transactionId = ? 
              AND r.supplierId = ? 
              AND LOWER(st.status) = 'pending'
        ");
        $stmt->execute([$newStatus, $id, $supplierId]);
        $updated = ($stmt->rowCount() > 0);

    } else {
        // 1. Update Purchase Order Status
        $stmt = $pdo->prepare("
            UPDATE purchaseorder
            SET status = ?
            WHERE purchaseOrderId = ?
              AND supplierId = ?
              AND LOWER(status) = 'pending'
        ");
        $stmt->execute([$newStatus, $id, $supplierId]);
        $updated = ($stmt->rowCount() > 0);

        // ❌ REMOVED: The logic to auto-create invoices has been deleted.
        // Now, "Accept" ONLY changes the status.
    }

    if (!$updated) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Cannot update (Already updated or not yours)']);
        exit;
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
?>
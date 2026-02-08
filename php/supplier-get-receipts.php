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
$monthFilter = $_GET['month'] ?? ''; // Format: YYYY-MM

try {
    // Build Query
    // We link receipt_log -> stock_transaction -> resource to find the supplier's receipts
    $sql = "
        SELECT 
            rl.receiptId,
            rl.refNo,
            rl.receiptDate,
            rl.cost AS amount,
            rl.stockDetails,
            rl.driveLink,
            st.transactionId
        FROM receipt_log rl
        JOIN stock_transaction st ON rl.transactionId = st.transactionId
        JOIN resource r ON st.resourceId = r.resourceId
        WHERE r.supplierId = ?
    ";

    $params = [$supplierId];

    // Apply Month Filter if selected
    if (!empty($monthFilter)) {
        $sql .= " AND DATE_FORMAT(rl.receiptDate, '%Y-%m') = ?";
        $params[] = $monthFilter;
    }

    $sql .= " ORDER BY rl.receiptDate DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'rows' => $rows]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
?>
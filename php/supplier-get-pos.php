<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$supplierId = (int)$_SESSION['supplier_id'];
$status = trim($_GET['status'] ?? 'all');

try {
    // The query must use standard column names that your JavaScript expects
    $baseQuery = "
        SELECT * FROM (
            SELECT 
                po.purchaseOrderId AS id,
                po.orderDate AS orderDate,
                po.status AS status,
                COALESCE(SUM(poi.unitPrice * poi.quantity), 0) AS orderTotal,
                GROUP_CONCAT(CONCAT(poi.resourceName, ' (Qty: ', poi.quantity, ')') SEPARATOR ' | ') AS itemsSummary,
                'PO' AS type
            FROM purchaseorder po
            LEFT JOIN purchaseorderitem poi ON poi.purchaseOrderId = po.purchaseOrderId
            WHERE po.supplierId = ?
            GROUP BY po.purchaseOrderId

            UNION ALL

            SELECT 
                st.transactionId AS id,
                st.transactionDate AS orderDate,
                st.status AS status,
                (st.quantity * r.unitPrice) AS orderTotal,
                CONCAT(r.resourceName, ' (Qty: ', st.quantity, ')') AS itemsSummary,
                'DELIVERY' AS type
            FROM stock_transaction st
            JOIN resource r ON st.resourceId = r.resourceId
            WHERE r.supplierId = ? AND st.transactionType = 'IN'
        ) AS combined_results
    ";

    $params = [$supplierId, $supplierId];

    if ($status !== 'all') {
        // Use a case-insensitive match to ensure "pending" matches "Pending"
        $baseQuery .= " WHERE LOWER(status) = LOWER(?)";
        $params[] = $status;
    }

    $baseQuery .= " ORDER BY orderDate DESC";

    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Always return a 'success' flag for your frontend to check
    echo json_encode([
        'success' => true, 
        'ok' => true,
        'rows' => $rows
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
?>
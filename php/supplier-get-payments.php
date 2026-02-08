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
$filter = trim($_GET['filter'] ?? 'all'); 

try {
    // 1. FILTER LOGIC
    $whereClause = "WHERE supplier_id = ?";
    $params = [$supplierId];

    if ($filter === 'Paid') {
        $whereClause .= " AND LOWER(status) = 'paid'";
    } elseif ($filter === 'Unpaid') {
        $whereClause .= " AND LOWER(status) NOT IN ('paid', 'cancelled', 'rejected')";
    }

    // 2. FETCH INVOICES
    // ⚠️ FIXED: Changed 'submitted_date' to 'created_at' matching your DB schema
    $stmt = $pdo->prepare("
        SELECT 
            id AS invoiceId,
            invoice_number,
            po_reference,
            invoice_date,
            COALESCE(status, 'Pending') AS status,
            COALESCE(total_amount, 0) AS totalAmount,
            created_at AS submittedDate, 
            DATE_ADD(invoice_date, INTERVAL 30 DAY) AS estimatedPayDate
        FROM invoices
        $whereClause
        ORDER BY invoice_date DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. SUMMARIES
    // Pending Clearing (Unpaid & Not Cancelled)
    $stmtPending = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM invoices
        WHERE supplier_id = ? 
        AND LOWER(status) NOT IN ('paid', 'cancelled', 'rejected')
    ");
    $stmtPending->execute([$supplierId]);
    $pendingTotal = (float)$stmtPending->fetchColumn();

    // Settled This Month
    $stmtSettled = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM invoices
        WHERE supplier_id = ? 
        AND LOWER(status) = 'paid'
        AND invoice_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $stmtSettled->execute([$supplierId]);
    $settledMonth = (float)$stmtSettled->fetchColumn();

    echo json_encode([
        'ok' => true,
        'summary' => [
            'pendingClearing' => $pendingTotal,
            'settledThisMonth' => $settledMonth
        ],
        'rows' => $rows
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
?>
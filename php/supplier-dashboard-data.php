<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/db_config.php";

if (!isset($_SESSION["supplier_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$supplierId = (int)$_SESSION["supplier_id"];

try {
    // 1. Supplier Name
    $stmt = $pdo->prepare("SELECT name, company FROM supplier WHERE supplierId = ? LIMIT 1");
    $stmt->execute([$supplierId]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    $supplierName = $s ? ($s["company"] ?: $s["name"]) : "Supplier";

    // 2. PO Stats
    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM purchaseorder WHERE supplierId = ?");
    $stmt1->execute([$supplierId]);
    $totalPOs = (int)$stmt1->fetchColumn();

    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM purchaseorder WHERE supplierId = ? AND LOWER(status) = 'pending'");
    $stmt2->execute([$supplierId]);
    $newRequests = (int)$stmt2->fetchColumn();

    // 3. Invoice Stats
    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE supplier_id = ?");
    $stmt3->execute([$supplierId]);
    $invoicesSent = (int)$stmt3->fetchColumn();

    // --- 4. FIXED LOGIC: Calculate Total Pending Payments ---
    
    // ✅ ONLY Count Invoices. Do NOT count Accepted POs separately.
    // Since accepting a PO automatically creates an invoice, counting POs would double the amount.
    $stmtInv = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM invoices 
        WHERE supplier_id = ? 
        AND LOWER(status) NOT IN ('paid', 'cancelled', 'rejected')
    ");
    $stmtInv->execute([$supplierId]);
    
    // This is the SINGLE source of truth for pending money
    $totalPending = (float)$stmtInv->fetchColumn();

    echo json_encode([
        "supplierName" => $supplierName,
        "totalPOs" => $totalPOs,
        "newRequests" => $newRequests,
        "invoicesSent" => $invoicesSent,
        "pendingPayments" => number_format($totalPending, 2, '.', ''), 
        "pendingNote" => $totalPending > 0 ? "Outstanding Balance" : "All settled"
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error", "details" => $e->getMessage()]);
}
?>
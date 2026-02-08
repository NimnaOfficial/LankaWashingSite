<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Check Auth
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supplierId = (int)$_SESSION['supplier_id'];
$invoiceId  = (int)($_POST['invoiceId'] ?? 0);

// 2. Smart Input: Check both 'status' and 'action' keys
$input = trim($_POST['status'] ?? $_POST['action'] ?? '');

if ($invoiceId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid invoiceId']);
    exit;
}

// 3. Normalize Status Logic
$newStatus = null;
$inputLower = strtolower($input);

// Map different terms to standard Database Enums
switch ($inputLower) {
    case 'done':
    case 'paid':
    case 'approve':
    case 'success':
        $newStatus = 'Paid';
        break;

    case 'reject':
    case 'rejected':
    case 'decline':
        $newStatus = 'Rejected';
        break;

    case 'cancel':
    case 'cancelled':
        $newStatus = 'Cancelled';
        break;
        
    case 'verifying': // Handled if coming from App
        $newStatus = 'Verifying';
        break;
}

if (!$newStatus) {
    http_response_code(422);
    echo json_encode(['error' => "Invalid status/action provided: '$input'"]);
    exit;
}

try {
    // 4. Verify Ownership
    $check = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND supplier_id = ? LIMIT 1");
    $check->execute([$invoiceId, $supplierId]);
    
    if (!$check->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invoice not found or access denied']);
        exit;
    }

    // 5. Execute Update
    $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $invoiceId]);

    echo json_encode(['ok' => true, 'success' => true, 'newStatus' => $newStatus]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
?>
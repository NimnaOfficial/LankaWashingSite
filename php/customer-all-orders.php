<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();
require_once __DIR__ . "/db_config.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["customer_id"])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$customerId = (int)$_SESSION["customer_id"];
$statusParam = strtolower(trim($_GET["status"] ?? "all"));

$reqWhere = ""; 
$ordWhere = ""; 
$reqParams = [$customerId]; 
$ordParams = [$customerId];

if ($statusParam !== "all") {
    $map = [
        "pending" => ["pending"], // Only look for Pending in Requests
        "processing" => ["processing", "in progress", "inprogress"],
        "completed" => ["completed", "delivered", "done"],
        "cancelled" => ["cancelled", "canceled", "rejected"] // Include Rejected here
    ];
    $allowed = $map[$statusParam] ?? [];
    
    if($allowed) {
        $in = implode(",", array_fill(0, count($allowed), "?"));
        $reqWhere = " AND LOWER(cr.status) IN ($in)";
        $ordWhere = " AND LOWER(o.orderStatus) IN ($in)";
        foreach ($allowed as $s) { $reqParams[] = $s; $ordParams[] = $s; }
    }
} else {
    // ✅ FIX: When fetching "All", only show Requests that are NOT Approved
    // (Approved requests are already inside the 'orders' table, so we skip them here to avoid duplicates)
    $reqWhere = " AND cr.status IN ('Pending', 'Rejected')"; 
}

try {
    // 1. GET REQUESTS (Only Pending or Rejected)
    $stmt1 = $pdo->prepare("
        SELECT 
            requestId AS id, 
            CONCAT('REQ-', requestId) AS code,
            productName, 
            description,
            quantity,
            expectedDate, 
            priority, 
            status, 
            requestDate AS createdAt
        FROM customerrequest cr
        WHERE customerId = ? $reqWhere
    ");
    $stmt1->execute($reqParams);
    $requests = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // 2. GET ORDERS (Processing, Completed, etc.)
    $stmt2 = $pdo->prepare("
        SELECT 
            o.orderId AS id, 
            CONCAT('ORD-', o.orderId) AS code,
            cr.productName, 
            cr.description,
            cr.quantity,
            o.expectedDate, 
            cr.priority, 
            o.orderStatus AS status, 
            o.orderDate AS createdAt
        FROM orders o
        LEFT JOIN customerrequest cr ON cr.requestId = o.requestId
        WHERE o.customerId = ? $ordWhere
    ");
    $stmt2->execute($ordParams);
    $orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // 3. MERGE
    $rows = array_merge($requests, $orders);
    
    // Sort by Date (Newest First)
    usort($rows, function ($a, $b) {
        return strtotime($b["createdAt"]) <=> strtotime($a["createdAt"]);
    });

    echo json_encode(["success" => true, "rows" => $rows]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>
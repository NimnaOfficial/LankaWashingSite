<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();
require_once __DIR__ . "/db_config.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["customer_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$customerId = (int)$_SESSION["customer_id"];
$requestId  = (int)($_POST["requestId"] ?? 0);

// Get Values
$product  = trim($_POST["productName"] ?? ""); 
$desc     = trim($_POST["description"] ?? "");
$qty      = (float)($_POST["quantity"] ?? 0);
$priority = trim($_POST["priority"] ?? "Medium");
$date     = trim($_POST["expectedDate"] ?? "");

if ($requestId <= 0 || empty($product) || $qty <= 0) {
    echo json_encode(["error" => "Invalid Product Name or Quantity."]);
    exit;
}

try {
    // 1. Check if Pending
    $check = $pdo->prepare("SELECT status FROM customerrequest WHERE requestId = ? AND customerId = ? LIMIT 1");
    $check->execute([$requestId, $customerId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row || strtolower($row["status"]) !== "pending") {
        echo json_encode(["error" => "Cannot edit orders that are already processing."]);
        exit;
    }

    // 2. Update Database
    $upd = $pdo->prepare("
        UPDATE customerrequest 
        SET productName = ?, description = ?, quantity = ?, priority = ?, expectedDate = ?
        WHERE requestId = ? AND customerId = ?
    ");
    $upd->execute([$product, $desc, $qty, $priority, $date, $requestId, $customerId]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error: " . $e->getMessage()]);
}
?>
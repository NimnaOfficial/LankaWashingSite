<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();
require_once __DIR__ . "/db_config.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// 1. CHECK LOGIN
if (!isset($_SESSION["customer_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

try {
    // 2. GET INPUTS
    $customerId = (int)$_SESSION["customer_id"];
    $productName = trim($_POST["productName"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $priority    = trim($_POST["priority"] ?? "Medium");
    $quantity    = (float)($_POST["quantity"] ?? 0);
    $expected    = trim($_POST["expectedDate"] ?? date("Y-m-d"));

    if ($productName === "" || $quantity <= 0) {
        throw new Exception("Please provide a product name and valid quantity.");
    }

    // 3. INSERT (Matches your new schema perfectly)
    $stmt = $pdo->prepare("
        INSERT INTO customerrequest 
        (customerId, productName, description, quantity, priority, expectedDate, requestDate, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'Pending')
    ");
    
    $stmt->execute([
        $customerId, 
        $productName, 
        $description, 
        $quantity, 
        $priority, 
        $expected
    ]);

    echo json_encode(["success" => true, "message" => "Order created successfully"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
}
?>
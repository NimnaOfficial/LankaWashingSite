<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/db_config.php";

if (!isset($_SESSION["customer_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$name    = trim($_POST["name"] ?? "");
$phone   = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");

if ($name === "") {
    http_response_code(400);
    echo json_encode(["error" => "Name cannot be empty"]);
    exit;
}

$customerId = (int)$_SESSION["customer_id"];

try {
    $stmt = $pdo->prepare("
        UPDATE customer
        SET name = ?, phone = ?, address = ?
        WHERE customerId = ?
    ");
    $stmt->execute([$name, $phone, $address, $customerId]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Update failed: " . $e->getMessage()]);
}
?>
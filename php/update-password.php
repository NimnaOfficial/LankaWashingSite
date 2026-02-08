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

$current = (string)($_POST["currentPassword"] ?? "");
$new     = (string)($_POST["newPassword"] ?? "");

if ($current === "" || $new === "") {
    http_response_code(400);
    echo json_encode(["error" => "All fields are required"]);
    exit;
}

$customerId = (int)$_SESSION["customer_id"];

try {
    // 1. Verify Current Password
    $stmt = $pdo->prepare("SELECT password FROM customer WHERE customerId = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    // NOTE: If you store plain text passwords (as seen in your SQL), use simple comparison.
    // If hashed, use password_verify($current, $row['password']);
    if ($current !== $row["password"]) {
        echo json_encode(["error" => "Current password is incorrect"]);
        exit;
    }

    // 2. Update Password
    $stmt = $pdo->prepare("UPDATE customer SET password = ? WHERE customerId = ?");
    $stmt->execute([$new, $customerId]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
?>
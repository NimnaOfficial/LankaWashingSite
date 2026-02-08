<?php
declare(strict_types=1);
session_start();

// 1. FIX: Connect to database in the same folder
require_once __DIR__ . "/db_config.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$login = trim($_POST["login"] ?? "");
$password = trim($_POST["password"] ?? "");

if ($login === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["error" => "Please fill all fields."]);
    exit;
}

try {
    // 2. QUERY: Find user by Username OR Email
    $stmt = $pdo->prepare("
        SELECT customerId, name, password
        FROM customer
        WHERE username = ? OR email = ?
        LIMIT 1
    ");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    // 3. CHECK PASSWORD (Plain Text)
    if (!$user || $password !== $user["password"]) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid username/email or password."]);
        exit;
    }

    // 4. SET SESSION
    $_SESSION["customer_id"] = (int)$user["customerId"];
    $_SESSION["customer_name"] = (string)$user["name"];

    // 5. SUCCESS RESPONSE
    echo json_encode([
        "success" => true,
        "message" => "Login Successful",
        "redirect" => "customer-dashboard.html" // <--- Tells JS where to go
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
}
exit;
?>
<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();
require_once __DIR__ . "/db_config.php";

header("Content-Type: application/json; charset=utf-8");

// 1. CHECK LOGIN
if (!isset($_SESSION["customer_id"])) {
  http_response_code(401);
  echo json_encode(["error" => "Unauthorized"]);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

$customerId = (int)$_SESSION["customer_id"];
$requestId = (int)($_POST["requestId"] ?? 0);

if ($requestId <= 0) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid requestId"]);
  exit;
}

try {
  // 2. CHECK OWNERSHIP & STATUS
  $check = $pdo->prepare("
    SELECT status
    FROM customerrequest
    WHERE requestId = ? AND customerId = ?
    LIMIT 1
  ");
  $check->execute([$requestId, $customerId]);
  $row = $check->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    echo json_encode(["error" => "Request not found."]);
    exit;
  }

  // 3. BUSINESS RULE: Only Pending can be cancelled
  if (strtolower((string)$row["status"]) !== "pending") {
    http_response_code(403);
    echo json_encode(["error" => "Only pending requests can be cancelled."]);
    exit;
  }

  // 4. PERFORM CANCELLATION
  $upd = $pdo->prepare("
    UPDATE customerrequest
    SET status = 'Cancelled'
    WHERE requestId = ? AND customerId = ?
    LIMIT 1
  ");
  $upd->execute([$requestId, $customerId]);

  echo json_encode(["success" => true]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "details" => $e->getMessage()]);
  exit;
}
?>
<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();
require_once __DIR__ . "/db_config.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["supplier_id"])) {
  http_response_code(401);
  echo json_encode(["error" => "Unauthorized"]);
  exit;
}

$supplierId   = (int)$_SESSION["supplier_id"];
$newPassword  = trim($_POST["newPassword"] ?? "");

if (strlen($newPassword) < 3) {
  http_response_code(422);
  echo json_encode(["error" => "Password must be at least 6 characters"]);
  exit;
}

try {
  $hash = password_hash($newPassword, PASSWORD_BCRYPT);

  $stmt = $pdo->prepare("UPDATE supplier SET password = ? WHERE supplierId = ?");
  $stmt->execute([$hash, $supplierId]);

  echo json_encode(["ok" => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "detail" => $e->getMessage()]);
}
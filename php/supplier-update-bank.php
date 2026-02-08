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

$supplierId = (int)$_SESSION["supplier_id"];

$bankName  = trim($_POST["bankName"] ?? "");
$accountNo = trim($_POST["accountNo"] ?? "");
$branch    = trim($_POST["branch"] ?? "");
$remarks   = trim($_POST["remarks"] ?? "");

try {
  $stmt = $pdo->prepare("
    UPDATE supplier
    SET bankName = ?, accountNo = ?, branch = ?, remarks = ?
    WHERE supplierId = ?
  ");
  $stmt->execute([$bankName, $accountNo, $branch, $remarks, $supplierId]);

  echo json_encode(["ok" => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "detail" => $e->getMessage()]);
}
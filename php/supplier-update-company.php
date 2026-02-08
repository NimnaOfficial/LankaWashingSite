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

$company = trim($_POST["company"] ?? "");
$email   = trim($_POST["email"] ?? "");
$phone   = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");

if ($company === "" || $email === "") {
  http_response_code(422);
  echo json_encode(["error" => "Company and Email are required"]);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(["error" => "Invalid email"]);
  exit;
}

try {
  $stmt = $pdo->prepare("
    UPDATE supplier
    SET company = ?, email = ?, phone = ?, address = ?
    WHERE supplierId = ?
  ");
  $stmt->execute([$company, $email, $phone, $address, $supplierId]);

  echo json_encode(["ok" => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "detail" => $e->getMessage()]);
}
<?php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/db_config.php";

$login    = trim((string)($_POST["login"] ?? ""));
$password = trim((string)($_POST["password"] ?? ""));

if ($login === "" || $password === "") {
  http_response_code(400);
  echo json_encode(["error" => "Supplier ID / Email and password are required"]);
  exit;
}

try {
  // If numeric -> treat as supplierId, else treat as email
  $isId = ctype_digit($login);

  if ($isId) {
    $stmt = $pdo->prepare("
      SELECT supplierId, name, company, password
      FROM supplier
      WHERE supplierId = ?
      LIMIT 1
    ");
    $stmt->execute([(int)$login]);
  } else {
    $stmt = $pdo->prepare("
      SELECT supplierId, name, company, password
      FROM supplier
      WHERE LOWER(email) = LOWER(?)
      LIMIT 1
    ");
    $stmt->execute([$login]);
  }

  $s = $stmt->fetch(PDO::FETCH_ASSOC);

  // ✅ 1) Supplier not found
  if (!$s) {
    http_response_code(401);
    echo json_encode(["error" => "No supplier found for this ID/Email"]);
    exit;
  }

  // ✅ Always trim DB password (fixes trailing space issues)
  $dbPass = trim((string)($s["password"] ?? ""));

  // ✅ 2) Password empty in DB
  if ($dbPass === "") {
    http_response_code(401);
    echo json_encode(["error" => "This supplier has no password in database (password is NULL/empty)"]);
    exit;
  }

  // allow both hashed and plain
  $ok = false;
  if (
    str_starts_with($dbPass, '$2y$') ||
    str_starts_with($dbPass, '$argon2')
  ) {
    $ok = password_verify($password, $dbPass);
  } else {
    $ok = hash_equals($dbPass, $password);
  }

  // ✅ 3) Password mismatch
  if (!$ok) {
    http_response_code(401);
    echo json_encode([
      "error" => "Password does not match",
      // remove these two lines later (debug only)
      "debug_login" => $login,
      "debug_dbPass_len" => strlen($dbPass)
    ]);
    exit;
  }

  $_SESSION["supplier_id"] = (int)$s["supplierId"];

  echo json_encode([
    "success" => true,
    "supplierId" => (int)$s["supplierId"],
    "name" => ($s["company"] ?: $s["name"])
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "details" => $e->getMessage()]);
  exit;
}
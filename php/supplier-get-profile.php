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

try {
    // Matches 'supplier' table columns in production_db (8).sql
    $stmt = $pdo->prepare("
        SELECT supplierId, name, company, email, phone, address, 
               bankName, accountNo, branch, remarks
        FROM supplier
        WHERE supplierId = ?
        LIMIT 1
    ");
    $stmt->execute([$supplierId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(["error" => "Supplier not found"]);
        exit;
    }

    echo json_encode($row);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error", "detail" => $e->getMessage()]);
}
?>
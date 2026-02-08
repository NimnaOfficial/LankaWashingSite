<?php
declare(strict_types=1);
session_start();

header("Content-Type: application/json; charset=utf-8");

// Disable error display in the output (prevents invalid JSON)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// --- 1. SMART DATABASE CONNECTION ---
// We check multiple common locations for the database file
$possible_paths = [
    __DIR__ . "/../db_config.php",       // Main folder (name: db_config.php)
    __DIR__ . "/../config/db.php",       // Config folder (name: db.php)
    __DIR__ . "/../db.php",              // Main folder (name: db.php)
    __DIR__ . "/db_config.php",          // Same folder
    __DIR__ . "/config/db_config.php"    // Config folder (name: db_config.php)
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    http_response_code(500);
    echo json_encode(["error" => "Server Error: Could not find database connection file (db_config.php or db.php)."]);
    exit;
}
// -------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use the form in your browser."]);
    exit;
}

// 2. Capture Inputs
$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");
$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";
$confirm = $_POST["confirm_password"] ?? "";

// 3. Validation
if ($name === "" || $email === "" || $phone === "" || $address === "" || $username === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["error" => "Please fill all fields."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email address."]);
    exit;
}

if ($password !== $confirm) {
    http_response_code(400);
    echo json_encode(["error" => "Passwords do not match."]);
    exit;
}

try {
    // 4. Check for Duplicates
    $check = $pdo->prepare("SELECT 1 FROM customer WHERE email = ? OR username = ? LIMIT 1");
    $check->execute([$email, $username]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Email or username already exists."]);
        exit;
    }

    // 5. Insert Data
    $stmt = $pdo->prepare("
        INSERT INTO customer (name, email, phone, address, username, password)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if ($stmt->execute([$name, $email, $phone, $address, $username, $password])) {
        echo json_encode(["success" => true]);
    } else {
        $errorInfo = $stmt->errorInfo();
        http_response_code(500);
        echo json_encode(["error" => "Database Insert Failed: " . $errorInfo[2]]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "SQL Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "System Error: " . $e->getMessage()]);
}
?>
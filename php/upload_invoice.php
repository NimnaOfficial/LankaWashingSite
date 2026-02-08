<?php
// Enable CORS for frontend access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header('Content-Type: application/json');

// 1. START SESSION TO GET SUPPLIER ID
ini_set('session.cookie_path', '/');
session_start();

require __DIR__ . '/vendor/autoload.php';
require 'db_config.php'; 

// Check Authentication
if (!isset($_SESSION['supplier_id'])) {
    echo json_encode(['error' => 'Unauthorized: Please log in.']);
    exit();
}

$supplierId = (int)$_SESSION['supplier_id'];

// 2. GOOGLE DRIVE SETUP
$folderId = '1wpehkRMNpJ5U6hgcNBaypOVZT_tUeHuD'; 

$client = new Google\Client();
$client->setAuthConfig('invoice.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);

if (file_exists('token.json')) {
    $accessToken = json_decode(file_get_contents('token.json'), true);
    $client->setAccessToken($accessToken);
} else {
    echo json_encode(['error' => 'Token not found. Run setup_auth.php first.']);
    exit();
}

if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents('token.json', json_encode($client->getAccessToken()));
    } else {
        echo json_encode(['error' => 'Token expired. Run setup_auth.php.']);
        exit();
    }
}

$service = new Google\Service\Drive($client);

// 3. HANDLE FILE UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoiceFile'])) {
    
    try {
        $poNumber = $_POST['poNumber']; // e.g., "PO-0024" or "DEL-0005"
        $invoiceNum = $_POST['invoiceNum'];
        $invoiceDate = $_POST['invoiceDate'];
        $notes = $_POST['notes'];
        
        // --- 🛑 NEW: CALCULATE TOTAL AMOUNT ---
        $calculatedAmount = 0.00;
        
        if (strpos($poNumber, 'PO-') === 0) {
            // It's a Purchase Order -> Sum items from purchaseorderitem
            $poId = (int)str_replace('PO-', '', $poNumber);
            $stmtCalc = $pdo->prepare("SELECT COALESCE(SUM(quantity * unitPrice), 0) FROM purchaseorderitem WHERE purchaseOrderId = ?");
            $stmtCalc->execute([$poId]);
            $calculatedAmount = (float)$stmtCalc->fetchColumn();
            
        } elseif (strpos($poNumber, 'DEL-') === 0) {
            // It's a Delivery -> Get cost from stock_transaction * resource
            $transId = (int)str_replace('DEL-', '', $poNumber);
            $stmtCalc = $pdo->prepare("
                SELECT COALESCE((st.quantity * r.unitPrice), 0) 
                FROM stock_transaction st 
                JOIN resource r ON st.resourceId = r.resourceId 
                WHERE st.transactionId = ?
            ");
            $stmtCalc->execute([$transId]);
            $calculatedAmount = (float)$stmtCalc->fetchColumn();
        }
        // -------------------------------------

        $file_tmp = $_FILES['invoiceFile']['tmp_name'];
        $cleanInvoiceNum = preg_replace('/[^a-zA-Z0-9_-]/', '', $invoiceNum);
        $file_name = "INV_" . $cleanInvoiceNum . "_" . basename($_FILES['invoiceFile']['name']); 
        $mime_type = $_FILES['invoiceFile']['type'];

        // A. Upload to Google Drive
        $fileMetadata = new Google\Service\Drive\DriveFile([
            'name' => $file_name,
            'parents' => [$folderId]
        ]);

        $content = file_get_contents($file_tmp);
        
        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mime_type,
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink'
        ]);

        $googleFileId = $file->id;
        $googleLink = $file->webViewLink;

        // B. Set Permissions
        $permission = new Google\Service\Drive\Permission();
        $permission->setRole('reader');
        $permission->setType('anyone');
        $service->permissions->create($googleFileId, $permission);

        // C. SAVE TO DATABASE (With Check & Amount)
        
        // Check if invoice already exists
        $check = $pdo->prepare("SELECT id FROM invoices WHERE po_reference = ? AND supplier_id = ?");
        $check->execute([$poNumber, $supplierId]);
        $existing = $check->fetch();

        if ($existing) {
            // ✅ UPDATE (Now includes total_amount)
            $stmt = $pdo->prepare("
                UPDATE invoices 
                SET invoice_number = ?, 
                    invoice_date = ?, 
                    total_amount = ?, 
                    notes = ?, 
                    file_link = ?, 
                    google_file_id = ?, 
                    status = 'Pending' 
                WHERE id = ?
            ");
            $stmt->execute([$invoiceNum, $invoiceDate, $calculatedAmount, $notes, $googleLink, $googleFileId, $existing['id']]);
            $message = "Invoice updated successfully!";
        } else {
            // ✅ INSERT (Now includes total_amount)
            $stmt = $pdo->prepare("
                INSERT INTO invoices 
                (supplier_id, po_reference, invoice_number, invoice_date, total_amount, notes, file_link, google_file_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([
                $supplierId, 
                $poNumber, 
                $invoiceNum, 
                $invoiceDate, 
                $calculatedAmount, // Saving the calculated money!
                $notes, 
                $googleLink, 
                $googleFileId
            ]);
            $message = "Invoice uploaded successfully!";
        }

        echo json_encode([
            'success' => true, 
            'message' => $message,
            'link' => $googleLink,
            'amount' => $calculatedAmount
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['error' => 'No file received or invalid request']);
}
?>
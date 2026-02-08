<?php
// php/process_payment.php
declare(strict_types=1);

ini_set('session.cookie_path', '/');
session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");

// 1. ENABLE ERROR REPORTING
ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php'; 
require __DIR__ . '/db_config.php'; // Correct path

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$response = [];

// 2. CHECK LOGIN (Database requires customerId)
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

try {
    $customerId = (int)$_SESSION['customer_id'];

    // 3. GET INPUT
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("No data received");

    $orderId = $input['order_id'];
    $amount  = $input['amount'];
    $method  = $input['method'];     // 'Credit Card' or 'Bank Transfer'
    $ref     = $input['reference'];  // Stripe Token or Drive Link
    $email   = $input['email']; 

    // Determine Status
    $status = ($method === 'Credit Card') ? 'Success' : 'Pending';

    // 4. INSERT INTO DATABASE (Matches production_db structure)
    // Table: payments
    // Columns: orderId, customerId, amount, method, transaction_ref, status
    $sql = "INSERT INTO payments (orderId, customerId, amount, method, transaction_ref, status) 
            VALUES (:oid, :cid, :amt, :mth, :ref, :sts)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':oid' => $orderId,
        ':cid' => $customerId,
        ':amt' => $amount,
        ':mth' => $method,
        ':ref' => $ref,
        ':sts' => $status
    ]);
    
    $paymentId = $pdo->lastInsertId();
    $response['success'] = true;
    $response['id'] = $paymentId;
    $response['message'] = "Payment Saved!";

    // 5. SEND EMAIL
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'nimnakgls980@gmail.com'; 
        $mail->Password   = 'qjkmmjlbmvwrqdiz';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('nimnakgls980@gmail.com', 'Washing Unit Accounts'); 
        $mail->addAddress($email); 

        $mail->isHTML(true);
        $mail->Subject = "Payment Receipt: #$orderId";
        
        // Dynamic Email Body
        $color = ($status === 'Success') ? '#16a34a' : '#ea580c';
        
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px;'>
                <h2 style='color: $color;'>Payment $status</h2>
                <p>Thank you! Your transaction has been recorded.</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr style='border-bottom: 1px solid #ddd;'>
                        <td style='padding: 8px;'><strong>Transaction ID:</strong></td>
                        <td style='padding: 8px;'>TXN-$paymentId</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #ddd;'>
                        <td style='padding: 8px;'><strong>Order Ref:</strong></td>
                        <td style='padding: 8px;'>$orderId</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #ddd;'>
                        <td style='padding: 8px;'><strong>Amount:</strong></td>
                        <td style='padding: 8px; font-weight: bold;'>$$amount</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px;'><strong>Method:</strong></td>
                        <td style='padding: 8px;'>$method</td>
                    </tr>
                </table>
            </div>
        ";

        $mail->send();
        $response['email_status'] = 'sent';

    } catch (Exception $e) {
        $response['email_status'] = 'failed';
        $response['mail_error'] = $mail->ErrorInfo;
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
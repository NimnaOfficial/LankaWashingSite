<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', '0'); 
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['receiptFile'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid Request']);
    exit;
}

// Your Drive Folder ID
$folderId = '1uvaYsQ6mKYpzwZBoDH1hjS_plMDkasHe'; 

try {
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/invoice.json'); 
    $client->addScope(Google\Service\Drive::DRIVE_FILE);
    
    if (file_exists(__DIR__ . '/token.json')) {
        $client->setAccessToken(json_decode(file_get_contents(__DIR__ . '/token.json'), true));
    }
    
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents(__DIR__ . '/token.json', json_encode($client->getAccessToken()));
        } else {
            throw new Exception('Token expired.');
        }
    }

    $service = new Google\Service\Drive($client);
    $fileMetadata = new Google\Service\Drive\DriveFile(['name' => "RECEIPT_" . time() . "_" . basename($_FILES['receiptFile']['name']), 'parents' => [$folderId]]);
    
    $file = $service->files->create($fileMetadata, [
        'data' => file_get_contents($_FILES['receiptFile']['tmp_name']),
        'mimeType' => $_FILES['receiptFile']['type'],
        'uploadType' => 'multipart',
        'fields' => 'id, webViewLink'
    ]);

    $permission = new Google\Service\Drive\Permission(['type' => 'anyone', 'role' => 'reader']);
    $service->permissions->create($file->id, $permission);

    echo json_encode(['success' => true, 'link' => $file->webViewLink]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
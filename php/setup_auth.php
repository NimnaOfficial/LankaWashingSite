<?php
require __DIR__ . '/vendor/autoload.php';

session_start();

$client = new Google\Client();
$client->setAuthConfig('invoice.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setRedirectUri('http://localhost/AFinal/php/setup_auth.php'); // Adjust path if needed
$client->setAccessType('offline'); // Crucial for getting a refresh token
$client->setPrompt('select_account consent');

if (isset($_GET['code'])) {
    // Exchange the auth code for a token
    $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    // Check for errors
    if (array_key_exists('error', $accessToken)) {
        throw new Exception(join(', ', $accessToken));
    }

    // Save the token to a file
    file_put_contents('token.json', json_encode($client->getAccessToken()));
    
    echo "<h1>Success!</h1>";
    echo "Token saved to <b>token.json</b>. You can now use the upload form.";
} else {
    // Generate the login URL
    $authUrl = $client->createAuthUrl();
    echo "<h3>One-Time Setup</h3>";
    echo "<a href='$authUrl'>Click here to authorize Google Drive Access</a>";
}
?>
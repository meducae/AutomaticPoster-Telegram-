<?php
require_once 'config.php';

// Check if a URL was provided via GET parameter
$webhookUrl = $_GET['url'] ?? '';

if (empty($webhookUrl)) {
    // If no URL provided in the query string, output a simple form
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Telegram Webhook Setup</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background-color: #f5f7f9; color: #333; }
            .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            h2 { color: #2c3e50; margin-top: 0; }
            label { display: block; margin-bottom: 8px; font-weight: bold; }
            input[type='url'] { width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
            button { background-color: #3498db; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer; }
            button:hover { background-color: #2980b9; }
            .note { font-size: 13px; color: #7f8c8d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Set Telegram Webhook</h2>
            <form action='' method='GET'>
                <label for='url'>Full Webhook URL (must be HTTPS)</label>
                <input type='url' id='url' name='url' placeholder='https://your-domain.com/bot.php' required>
                <button type='submit'>Set Webhook</button>
            </form>
            <div class='note'>
                <strong>Tip for localhost testing:</strong> Run <code>ngrok http 80</code> and paste the HTTPS Forwarding URL here, followed by <code>/bot.php</code>.
            </div>
        </div>
    </body>
    </html>";
    exit;
}

// Perform the API request to set the webhook
$apiUrl = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook?url=" . urlencode($webhookUrl);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Webhook Result</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background-color: #f5f7f9; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .success { color: #27ae60; }
        .error { color: #c0392b; }
        pre { background: #eee; padding: 10px; border-radius: 4px; overflow-x: auto; }
        a.button { display: inline-block; background-color: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class='container'>";
    
if (isset($responseData['ok']) && $responseData['ok']) {
    echo "<h2 class='success'>✅ Webhook Set Successfully!</h2>";
    echo "<p>Telegram will now send updates to: <strong>" . htmlspecialchars($webhookUrl) . "</strong></p>";
} else {
    echo "<h2 class='error'>❌ Failed to set Webhook</h2>";
    echo "<p>Telegram responded with an error:</p>";
}

echo "<h3>Raw Telegram Response:</h3>";
echo "<pre>" . htmlspecialchars(print_r($responseData, true)) . "</pre>";

echo "<a href='set_webhook.php' class='button'>← Back</a>";
echo "</div></body></html>";
?>

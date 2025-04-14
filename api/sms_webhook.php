<?php
// This is a simple webhook to handle incoming SMS responses
// You would need to configure your SMS provider to forward messages to this endpoint

// Include fraud detection service
require_once '../services/fraud_detection_service.php';

// Log incoming request for debugging
file_put_contents('sms_webhook_log.txt', date('Y-m-d H:i:s') . ' - ' . file_get_contents("php://input") . "\n", FILE_APPEND);

// Different SMS providers have different formats for incoming messages
// This is a simplified example that assumes the message contains:
// - A phone number
// - A message body with YES/NO followed by a transaction ID

// Get the incoming SMS data (format will vary by provider)
$from = $_POST['from'] ?? $_GET['from'] ?? '';
$message = $_POST['message'] ?? $_POST['body'] ?? $_GET['message'] ?? $_GET['body'] ?? '';

// Simple parsing logic - extract response (YES/NO) and transaction ID
// Example message: "YES 123456" or "NO 123456"
if (preg_match('/^(YES|NO)\s+(\d+)$/i', trim($message), $matches)) {
    $response = strtoupper($matches[1]);
    $transactionId = $matches[2];
    
    // Initialize fraud detection service
    $fraudDetectionService = new FraudDetectionService();
    
    // Process the user response
    $result = $fraudDetectionService->processUserResponse(
        $transactionId,
        $response
    );
    
    // Log the result
    file_put_contents('sms_response_log.txt', date('Y-m-d H:i:s') . " - From: $from, Response: $response, Transaction: $transactionId, Result: " . json_encode($result) . "\n", FILE_APPEND);
    
    // Return a success response to the SMS provider
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
} else {
    // Invalid format
    file_put_contents('sms_error_log.txt', date('Y-m-d H:i:s') . " - Invalid format from $from: $message\n", FILE_APPEND);
    
    // Return an error response
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response><Message>Invalid format. Please reply with YES or NO followed by the transaction ID.</Message></Response>';
}
?>

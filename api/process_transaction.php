<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include fraud detection service
require_once '../services/fraud_detection_service.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

// Get posted data
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
if (
    !isset($data['user_id']) || 
    !isset($data['atm_id']) || 
    !isset($data['amount']) || 
    !isset($data['transaction_type'])
) {
    http_response_code(400); // Bad Request
    echo json_encode(['message' => 'Missing required fields']);
    exit;
}

// Initialize fraud detection service
$fraudDetectionService = new FraudDetectionService();

// Process the transaction
$result = $fraudDetectionService->processTransaction(
    $data['user_id'],
    $data['atm_id'],
    $data['amount'],
    $data['transaction_type']
);

// Return result
http_response_code($result['success'] ? 200 : 500);
echo json_encode($result);
?>

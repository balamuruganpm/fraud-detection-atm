<?php
// Include fraud detection service
require_once 'services/fraud_detection_service.php';

// Get parameters
$token = $_GET['token'] ?? '';
$transactionId = $_GET['transaction'] ?? '';
$response = $_GET['response'] ?? '';

// Validate parameters
if (empty($token) || empty($transactionId)) {
    $error = 'Invalid request. Missing required parameters.';
} elseif (empty($response) || !in_array($response, ['approve', 'deny'])) {
    // Show confirmation page
    $showConfirmation = true;
} else {
    // Process the response
    $fraudDetectionService = new FraudDetectionService();
    $result = $fraudDetectionService->processUserResponse($transactionId, $response);
    
    if ($result['success']) {
        $message = "Your response has been processed successfully. The transaction has been {$result['transaction_status']}.";
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM Transaction Response</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .container {
            max-width: 600px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-approve {
            background-color: #28a745;
            color: white;
        }
        .btn-deny {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">ATM Transaction Verification</h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?php echo $error; ?>
                    </div>
                    <p>Please contact customer support for assistance.</p>
                <?php elseif (isset($message)): ?>
                    <div class="alert alert-success">
                        <strong>Success!</strong> <?php echo $message; ?>
                    </div>
                <?php elseif (isset($showConfirmation)): ?>
                    <h4>Transaction #<?php echo htmlspecialchars($transactionId); ?></h4>
                    <p>Please confirm your response to this transaction alert:</p>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="?token=<?php echo htmlspecialchars($token); ?>&transaction=<?php echo htmlspecialchars($transactionId); ?>&response=approve" class="btn btn-approve btn-lg">
                            ✅ Approve Transaction
                        </a>
                        <p class="text-center my-2">or</p>
                        <a href="?token=<?php echo htmlspecialchars($token); ?>&transaction=<?php echo htmlspecialchars($transactionId); ?>&response=deny" class="btn btn-deny btn-lg">
                            ❌ Deny Transaction
                        </a>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <strong>Warning:</strong> If you did not initiate this transaction, please click "Deny Transaction" immediately.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">ATM Fraud Detection System</p>
            </div>
        </div>
    </div>
</body>
</html>

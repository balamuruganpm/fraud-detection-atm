<?php
require_once '../config/database.php';
require_once '../services/fraud_detection_service.php';

// Check if user is authenticated at ATM
if (!isset($_SESSION['atm_card_id']) || !isset($_SESSION['atm_user_id']) || !isset($_SESSION['atm_id'])) {
    header('Location: authenticate.php');
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get card information
$cardId = $_SESSION['atm_card_id'];
$userId = $_SESSION['atm_user_id'];
$userName = $_SESSION['atm_user_name'];
$atmId = $_SESSION['atm_id'];

// Get card details
$query = "SELECT c.*, a.account_id, a.balance 
          FROM atm_cards c 
          JOIN bank_accounts a ON c.account_id = a.account_id 
          WHERE c.card_id = :card_id AND c.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':card_id', $cardId);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    // Invalid card, redirect to authentication
    session_unset();
    header('Location: authenticate.php?error=invalid_card');
    exit;
}

// Get ATM information
$query = "SELECT * FROM atm_locations WHERE atm_id = :atm_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':atm_id', $atmId);
$stmt->execute();
$atm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atm) {
    // Invalid ATM, redirect to authentication
    session_unset();
    header('Location: authenticate.php?error=invalid_atm');
    exit;
}

$error = '';
$success = '';
$transactionResult = null;

// Process transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? '';
    $transactionType = $_POST['transaction_type'] ?? '';
    
    if (empty($amount) && $transactionType !== 'BALANCE_CHECK') {
        $error = 'Amount is required for this transaction type.';
    } elseif (!empty($amount) && $amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif ($transactionType === 'WITHDRAWAL' && $amount > $card['daily_limit']) {
        $error = 'Amount exceeds your daily limit of $' . number_format($card['daily_limit'], 2);
    } elseif ($transactionType === 'WITHDRAWAL' && $amount > $card['balance']) {
        $error = 'Insufficient funds. Your balance is $' . number_format($card['balance'], 2);
    } else {
        // Initialize fraud detection service
        $fraudDetectionService = new FraudDetectionService();
        
        // Set amount to 0 for balance check
        if ($transactionType === 'BALANCE_CHECK') {
            $amount = 0;
        }
        
        // Process the transaction
        $transactionResult = $fraudDetectionService->processTransaction(
            $userId,
            $card['account_id'],
            $cardId,
            $atmId,
            $amount,
            $transactionType
        );
        
        if ($transactionResult['success']) {
            if (isset($transactionResult['fraud_detected']) && $transactionResult['fraud_detected']) {
                $success = 'Transaction flagged for verification. Please check your email or Telegram for approval.';
            } else {
                $success = 'Transaction processed successfully.';
                
                if ($transactionType === 'BALANCE_CHECK') {
                    $success .= ' Your current balance is $' . number_format($transactionResult['new_balance'], 2);
                } else {
                    $success .= ' New balance: $' . number_format($transactionResult['new_balance'], 2);
                }
            }
        } else {
            $error = 'Transaction failed: ' . ($transactionResult['message'] ?? 'Unknown error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM Transaction - Fraud Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .atm-container {
            max-width: 600px;
            width: 100%;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .btn-primary {
            width: 100%;
        }
        .transaction-option {
            cursor: pointer;
            transition: all 0.3s;
        }
        .transaction-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .card-number {
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="atm-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">ATM Transaction</h3>
                <p class="mb-0">Welcome, <?php echo htmlspecialchars($userName); ?></p>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($transactionResult && isset($transactionResult['fraud_detected']) && $transactionResult['fraud_detected']): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Location Verification Required</h5>
                        <p>This transaction is being made from a location outside your usual areas.</p>
                        <p>A verification request has been sent to your registered contact methods.</p>
                        <p>Transaction ID: <?php echo $transactionResult['transaction_id']; ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h5>Card Information</h5>
                    <p class="card-number mb-0">**** **** **** <?php echo substr($card['card_number'], -4); ?></p>
                    <p class="mb-0">Expires: <?php echo sprintf('%02d/%d', $card['expiry_month'], $card['expiry_year']); ?></p>
                    <p class="mb-0">Daily Limit: $<?php echo number_format($card['daily_limit'], 2); ?></p>
                    <p class="mb-0">Available Balance: $<?php echo number_format($card['balance'], 2); ?></p>
                </div>
                
                <div class="mb-4">
                    <h5>ATM Location</h5>
                    <p class="mb-0"><?php echo htmlspecialchars($atm['atm_name']); ?></p>
                    <p class="mb-0"><?php echo htmlspecialchars($atm['address']); ?></p>
                </div>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="transaction_type" class="form-label">Transaction Type</label>
                        <select class="form-select" id="transaction_type" name="transaction_type" required>
                            <option value="">Select Transaction Type</option>
                            <option value="WITHDRAWAL">Withdrawal</option>
                            <option value="DEPOSIT">Deposit</option>
                            <option value="BALANCE_CHECK">Balance Check</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="amount-group">
                        <label for="amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Process Transaction</button>
                        <a href="logout.php" class="btn btn-secondary">Cancel / Logout</a>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">This is a simulation of an ATM interface</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide amount field based on transaction type
        document.getElementById('transaction_type').addEventListener('change', function() {
            const amountGroup = document.getElementById('amount-group');
            if (this.value === 'BALANCE_CHECK') {
                amountGroup.style.display = 'none';
                document.getElementById('amount').removeAttribute('required');
            } else {
                amountGroup.style.display = 'block';
                document.getElementById('amount').setAttribute('required', 'required');
            }
        });
    </script>
</body>
</html>

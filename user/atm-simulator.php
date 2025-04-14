<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';
require_once '../services/fraud_detection_service.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get user information
$userId = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's ATM cards
$query = "SELECT c.*, a.account_number, a.account_type, a.balance 
          FROM atm_cards c 
          JOIN bank_accounts a ON c.account_id = a.account_id 
          WHERE c.user_id = :user_id AND c.card_status = 'ACTIVE'";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all ATM locations
$query = "SELECT * FROM atm_locations WHERE status = 'ACTIVE'";
$stmt = $db->prepare($query);
$stmt->execute();
$atmLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Google Maps API key
$query = "SELECT setting_value FROM system_settings WHERE setting_name = 'google_maps_api_key'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$googleMapsApiKey = $result ? $result['setting_value'] : '';

// Process transaction
$error = '';
$success = '';
$transactionResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify PIN
    if (isset($_POST['verify_pin'])) {
        $cardId = $_POST['card_id'] ?? '';
        $pin = $_POST['pin'] ?? '';
        $atmId = $_POST['atm_id'] ?? '';
        
        if (empty($cardId) || empty($pin) || empty($atmId)) {
            $error = 'All fields are required.';
        } else {
            // Get card information
            $query = "SELECT * FROM atm_cards WHERE card_id = :card_id AND user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':card_id', $cardId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$card) {
                $error = 'Invalid card.';
            } elseif (!password_verify($pin, $card['pin'])) {
                $error = 'Invalid PIN.';
                
                // Log failed PIN attempt
                $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address) 
                          VALUES (:user_id, 'PIN_ENTRY', 'FAILED', :ip_address)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                $stmt->execute();
            } else {
                // PIN is correct, store in session for transaction
                $_SESSION['verified_card_id'] = $cardId;
                $_SESSION['verified_atm_id'] = $atmId;
                
                // Log successful PIN verification
                $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address) 
                          VALUES (:user_id, 'PIN_ENTRY', 'SUCCESS', :ip_address)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                $stmt->execute();
                
                // Redirect to transaction form
                header('Location: atm-simulator.php?step=transaction');
                exit;
            }
        }
    }
    
    // Process transaction
    if (isset($_POST['process_transaction'])) {
        $cardId = $_SESSION['verified_card_id'] ?? '';
        $atmId = $_SESSION['verified_atm_id'] ?? '';
        $amount = $_POST['amount'] ?? '';
        $transactionType = $_POST['transaction_type'] ?? '';
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        
        if (empty($cardId) || empty($atmId) || empty($amount) || empty($transactionType)) {
            $error = 'All fields are required.';
        } elseif ($amount <= 0) {
            $error = 'Amount must be greater than zero.';
        } else {
            // Get card information
            $query = "SELECT c.*, a.account_id FROM atm_cards c 
                      JOIN bank_accounts a ON c.account_id = a.account_id 
                      WHERE c.card_id = :card_id AND c.user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':card_id', $cardId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$card) {
                $error = 'Invalid card.';
            } else {
                // Initialize fraud detection service
                $fraudDetectionService = new FraudDetectionService();
                
                // Prepare location data
                $locationData = null;
                if ($latitude && $longitude) {
                    $locationData = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'device_id' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                    ];
                }
                
                // Process the transaction
                $transactionResult = $fraudDetectionService->processTransaction(
                    $userId,
                    $card['account_id'],
                    $cardId,
                    $atmId,
                    $amount,
                    $transactionType,
                    $locationData
                );
                
                if ($transactionResult['success']) {
                    if (isset($transactionResult['fraud_detected']) && $transactionResult['fraud_detected']) {
                        $success = 'Transaction flagged for verification. Please check your email or Telegram for approval.';
                    } else {
                        $success = 'Transaction approved successfully.';
                    }
                    
                    // Clear verified card and ATM from session
                    unset($_SESSION['verified_card_id']);
                    unset($_SESSION['verified_atm_id']);
                } else {
                    $error = 'Transaction failed: ' . $transactionResult['message'];
                }
            }
        }
    }
}

// Determine current step
$step = isset($_GET['step']) ? $_GET['step'] : 'select_card';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM Simulator - Fraud Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #002b36;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background-color: #073642 !important;
        }
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .atm-container {
            max-width: 500px;
            width: 100%;
            background-color: #073642;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .atm-header {
            background-color: #002b36;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #0e4b5a;
        }
        .atm-header img {
            height: 40px;
        }
        .atm-body {
            padding: 30px;
        }
        .atm-footer {
            background-color: #002b36;
            padding: 15px;
            text-align: center;
            border-top: 1px solid #0e4b5a;
            font-size: 0.8rem;
        }
        .btn-primary {
            background-color: #2aa198;
            border-color: #2aa198;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: #238c82;
            border-color: #238c82;
        }
        .btn-secondary {
            background-color: #586e75;
            border-color: #586e75;
        }
        .btn-secondary:hover, .btn-secondary:focus {
            background-color: #465459;
            border-color: #465459;
        }
        .btn-danger {
            background-color: #dc322f;
            border-color: #dc322f;
        }
        .form-control, .form-select {
            background-color: #002b36;
            border-color: #0e4b5a;
            color: white;
        }
        .form-control:focus, .form-select:focus {
            background-color: #00232c;
            border-color: #2aa198;
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(42, 161, 152, 0.25);
        }
        .card-select {
            background-color: #002b36;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            border: 1px solid #0e4b5a;
            transition: all 0.3s;
        }
        .card-select:hover {
            border-color: #2aa198;
            transform: translateY(-3px);
        }
        .card-select.selected {
            border-color: #2aa198;
            background-color: rgba(42, 161, 152, 0.1);
        }
        .card-number {
            font-family: monospace;
        }
        .pin-pad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .pin-button {
            background-color: #002b36;
            border: 1px solid #0e4b5a;
            color: white;
            font-size: 1.5rem;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pin-button:hover {
            background-color: #00232c;
            border-color: #2aa198;
        }
        .pin-button:active {
            transform: scale(0.95);
        }
        .pin-display {
            background-color: #002b36;
            border: 1px solid #0e4b5a;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            letter-spacing: 5px;
            font-size: 1.5rem;
        }
        .pin-dot {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background-color: #586e75;
            margin: 0 5px;
        }
        .pin-dot.filled {
            background-color: #2aa198;
        }
        .location-box {
            background-color: #002b36;
            border: 1px solid #0e4b5a;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .location-icon {
            color: #2aa198;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        #map {
            height: 200px;
            border-radius: 10px;
            margin-top: 15px;
        }
        .result-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .result-icon.success {
            color: #2aa198;
        }
        .result-icon.error {
            color: #dc322f;
        }
        .atm-card {
            background: linear-gradient(135deg, #073642, #002b36);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
            border: 1px solid #0e4b5a;
        }
        .atm-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100%;
            background: rgba(255, 255, 255, 0.05);
            transform: skewX(-20deg);
        }
        .atm-card .card-number {
            font-size: 18px;
            letter-spacing: 2px;
        }
        .atm-card .card-holder {
            font-size: 16px;
            text-transform: uppercase;
        }
        .atm-card .expiry {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">ATM Fraud Detection</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="accounts.php">My Accounts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cards.php">My Cards</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="atm-simulator.php">ATM Simulator</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="atm-container">
            <div class="atm-header">
                <h4>ATM Simulator</h4>
            </div>
            
            <div class="atm-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($step === 'select_card'): ?>
                    <!-- Step 1: Select Card and ATM -->
                    <h5 class="mb-4 text-center">Select Your Card and ATM</h5>
                    
                    <form method="post" action="atm-simulator.php" id="card-form">
                        <div class="mb-3">
                            <label for="card_id" class="form-label">Select Your Card</label>
                            <?php if (empty($cards)): ?>
                                <div class="alert alert-warning">You don't have any active cards.</div>
                            <?php else: ?>
                                <?php foreach ($cards as $index => $card): ?>
                                    <div class="card-select" data-card-id="<?php echo $card['card_id']; ?>">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div>
                                                <i class="fab fa-cc-visa"></i>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php echo $card['card_status'] === 'ACTIVE' ? 'success' : 'danger'; ?>">
                                                    <?php echo $card['card_status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="card-number mb-2">
                                            **** **** **** <?php echo substr($card['card_number'], -4); ?>
                                        </p>
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p class="card-holder mb-0"><?php echo htmlspecialchars($card['card_holder']); ?></p>
                                                <p class="expiry mb-0">
                                                    Expires: <?php echo sprintf('%02d/%d', $card['expiry_month'], $card['expiry_year']); ?>
                                                </p>
                                            </div>
                                            <div class="text-end">
                                                <p class="mb-0"><?php echo $card['account_type']; ?></p>
                                                <p class="mb-0">Limit: $<?php echo number_format($card['remaining_limit'], 2); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <input type="hidden" name="card_id" id="card_id" required>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="atm_id" class="form-label">Select ATM Location</label>
                            <select class="form-select" id="atm_id" name="atm_id" required>
                                <option value="">Select ATM</option>
                                <?php foreach ($atmLocations as $atm): ?>
                                    <option value="<?php echo $atm['atm_id']; ?>">
                                        <?php echo htmlspecialchars($atm['atm_name'] . ' - ' . $atm['address']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" id="continue-btn" class="btn btn-primary" disabled>Continue</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                
                <?php elseif ($step === 'enter_pin'): ?>
                    <!-- Step 2: Enter PIN -->
                    <h5 class="mb-4 text-center">Enter your PIN here</h5>
                    
                    <form method="post" action="atm-simulator.php" id="pin-form">
                        <input type="hidden" name="card_id" value="<?php echo $_GET['card_id']; ?>">
                        <input type="hidden" name="atm_id" value="<?php echo $_GET['atm_id']; ?>">
                        <input type="hidden" name="pin" id="pin-input" required>
                        <input type="hidden" name="verify_pin" value="1">
                        
                        <div class="pin-display" id="pin-display">
                            <span class="pin-dot" id="pin-dot-1"></span>
                            <span class="pin-dot" id="pin-dot-2"></span>
                            <span class="pin-dot" id="pin-dot-3"></span>
                            <span class="pin-dot" id="pin-dot-4"></span>
                        </div>
                        
                        <div class="pin-pad">
                            <div class="pin-button" data-value="1">1</div>
                            <div class="pin-button" data-value="2">2</div>
                            <div class="pin-button" data-value="3">3</div>
                            <div class="pin-button" data-value="4">4</div>
                            <div class="pin-button" data-value="5">5</div>
                            <div class="pin-button" data-value="6">6</div>
                            <div class="pin-button" data-value="7">7</div>
                            <div class="pin-button" data-value="8">8</div>
                            <div class="pin-button" data-value="9">9</div>
                            <div class="pin-button" data-value="clear">
                                <i class="fas fa-backspace"></i>
                            </div>
                            <div class="pin-button" data-value="0">0</div>
                            <div class="pin-button" data-value="submit">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <a href="atm-simulator.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                
                <?php elseif ($step === 'transaction'): ?>
                    <!-- Step 3: Transaction Form -->
                    <h5 class="mb-4 text-center">Make Payment</h5>
                    
                    <?php if (!isset($_SESSION['verified_card_id'])): ?>
                        <div class="alert alert-danger">Please verify your PIN first.</div>
                        <div class="d-grid gap-2">
                            <a href="atm-simulator.php" class="btn btn-primary">Start Over</a>
                        </div>
                    <?php else: ?>
                        <div class="location-box mb-4">
                            <div class="location-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h6>Allow Your Client's Device Location Access</h6>
                            <p class="mb-0">Before making payment, please check your client's mobile device location status.</p>
                            
                            <button id="location-btn" class="btn btn-primary mt-3">
                                <i class="fas fa-location-arrow"></i> Get My Location
                            </button>
                            
                            <div id="map" class="mt-3" style="display: none;"></div>
                            <div id="location-status" class="mt-2"></div>
                        </div>
                        
                        <form method="post" action="atm-simulator.php" id="transaction-form">
                            <input type="hidden" name="process_transaction" value="1">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            
                            <div class="mb-3">
                                <label for="transaction_type" class="form-label">Transaction Type</label>
                                <select class="form-select" id="transaction_type" name="transaction_type" required>
                                    <option value="">Select Transaction Type</option>
                                    <option value="WITHDRAWAL">Withdrawal</option>
                                    <option value="DEPOSIT">Deposit</option>
                                    <option value="BALANCE_CHECK">Balance Check</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" id="submit-btn" class="btn btn-primary" disabled>Process Transaction</button>
                                <a href="atm-simulator.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                
                <?php elseif ($step === 'result'): ?>
                    <!-- Step 4: Transaction Result -->
                    <?php if ($transactionResult && $transactionResult['success']): ?>
                        <div class="text-center">
                            <div class="result-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h5 class="mb-3">Payment Success!</h5>
                            <p>Your transaction has been processed successfully.</p>
                            
                            <?php if (isset($transactionResult['new_balance'])): ?>
                                <p>New Balance: $<?php echo number_format($transactionResult['new_balance'], 2); ?></p>
                            <?php endif; ?>
                            
                            <?php if (isset($transactionResult['remaining_limit'])): ?>
                                <p>Remaining Daily Limit: $<?php echo number_format($transactionResult['remaining_limit'], 2); ?></p>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 mt-4">
                                <a href="atm-simulator.php" class="btn btn-primary">Make Another Transaction</a>
                                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <div class="result-icon error">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h5 class="mb-3">Payment Failed!</h5>
                            <p><?php echo $transactionResult ? $transactionResult['message'] : 'An error occurred during the transaction.'; ?></p>
                            
                            <div class="d-grid gap-2 mt-4">
                                <a href="atm-simulator.php" class="btn btn-primary">Try Again</a>
                                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="atm-footer">
                <p class="mb-0">Secure Banking System - Location-Based Fraud Detection</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Card selection
            $('.card-select').click(function() {
                $('.card-select').removeClass('selected');
                $(this).addClass('selected');
                $('#card_id').val($(this).data('card-id'));
                
                // Enable continue button if both card and ATM are selected
                checkContinueButton();
            });
            
            $('#atm_id').change(function() {
                // Enable continue button if both card and ATM are selected
                checkContinueButton();
            });
            
            function checkContinueButton() {
                if ($('#card_id').val() && $('#atm_id').val()) {
                    $('#continue-btn').prop('disabled', false);
                } else {
                    $('#continue-btn').prop('disabled', true);
                }
            }
            
            // Continue button click
            $('#continue-btn').click(function() {
                window.location.href = 'atm-simulator.php?step=enter_pin&card_id=' + $('#card_id').val() + '&atm_id=' + $('#atm_id').val();
            });
            
            // PIN pad
            let pin = '';
            
            $('.pin-button').click(function() {
                const value = $(this).data('value');
                
                if (value === 'clear') {
                    // Clear PIN
                    pin = '';
                    updatePinDisplay();
                } else if (value === 'submit') {
                    // Submit PIN if complete
                    if (pin.length === 4) {
                        $('#pin-input').val(pin);
                        $('#pin-form').submit();
                    }
                } else {
                    // Add digit to PIN
                    if (pin.length < 4) {
                        pin += value;
                        updatePinDisplay();
                        
                        // Auto-submit when PIN is complete
                        if (pin.length === 4) {
                            setTimeout(function() {
                                $('#pin-input').val(pin);
                                $('#pin-form').submit();
                            }, 500);
                        }
                    }
                }
            });
            
            function updatePinDisplay() {
                // Update PIN dots
                for (let i = 1; i <= 4; i++) {
                    if (i <= pin.length) {
                        $('#pin-dot-' + i).addClass('filled');
                    } else {
                        $('#pin-dot-' + i).removeClass('filled');
                    }
                }
            }
            
            // Location button
            $('#location-btn').click(function() {
                if (navigator.geolocation) {
                    $('#location-status').html('<div class="text-info">Getting your location...</div>');
                    
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const latitude = position.coords.latitude;
                            const longitude = position.coords.longitude;
                            
                            $('#latitude').val(latitude);
                            $('#longitude').val(longitude);
                            
                            // Show map
                            $('#map').show();
                            initMap(latitude, longitude);
                            
                            // Update status
                            $('#location-status').html('<div class="text-success">Location verified successfully!</div>');
                            
                            // Enable submit button
                            $('#submit-btn').prop('disabled', false);
                        },
                        function(error) {
                            let errorMessage = 'Error getting location.';
                            
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage = 'Location access denied. Please enable location services.';
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage = 'Location information is unavailable.';
                                    break;
                                case error.TIMEOUT:
                                    errorMessage = 'Location request timed out.';
                                    break;
                            }
                            
                            $('#location-status').html('<div class="text-danger">' + errorMessage + '</div>');
                        }
                    );
                } else {
                    $('#location-status').html('<div class="text-danger">Geolocation is not supported by this browser.</div>');
                }
            });
            
            // Initialize map
            function initMap(latitude, longitude) {
                const position = { lat: latitude, lng: longitude };
                
                const map = new google.maps.Map(document.getElementById('map'), {
                    zoom: 15,
                    center: position,
                    mapTypeId: 'roadmap',
                    styles: [
                        { elementType: 'geometry', stylers: [{ color: '#242f3e' }] },
                        { elementType: 'labels.text.stroke', stylers: [{ color: '#242f3e' }] },
                        { elementType: 'labels.text.fill', stylers: [{ color: '#746855' }] },
                        {
                            featureType: 'administrative.locality',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#d59563' }]
                        },
                        {
                            featureType: 'poi',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#d59563' }]
                        },
                        {
                            featureType: 'poi.park',
                            elementType: 'geometry',
                            stylers: [{ color: '#263c3f' }]
                        },
                        {
                            featureType: 'poi.park',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#6b9a76' }]
                        },
                        {
                            featureType: 'road',
                            elementType: 'geometry',
                            stylers: [{ color: '#38414e' }]
                        },
                        {
                            featureType: 'road',
                            elementType: 'geometry.stroke',
                            stylers: [{ color: '#212a37' }]
                        },
                        {
                            featureType: 'road',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#9ca5b3' }]
                        },
                        {
                            featureType: 'road.highway',
                            elementType: 'geometry',
                            stylers: [{ color: '#746855' }]
                        },
                        {
                            featureType: 'road.highway',
                            elementType: 'geometry.stroke',
                            stylers: [{ color: '#1f2835' }]
                        },
                        {
                            featureType: 'road.highway',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#f3d19c' }]
                        },
                        {
                            featureType: 'transit',
                            elementType: 'geometry',
                            stylers: [{ color: '#2f3948' }]
                        },
                        {
                            featureType: 'transit.station',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#d59563' }]
                        },
                        {
                            featureType: 'water',
                            elementType: 'geometry',
                            stylers: [{ color: '#17263c' }]
                        },
                        {
                            featureType: 'water',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#515c6d' }]
                        },
                        {
                            featureType: 'water',
                            elementType: 'labels.text.stroke',
                            stylers: [{ color: '#17263c' }]
                        }
                    ]
                });
                
                // Add marker for user location
                new google.maps.Marker({
                    position: position,
                    map: map,
                    title: 'Your Location',
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: '#2aa198',
                        fillOpacity: 1,
                        strokeWeight: 2,
                        strokeColor: '#ffffff',
                        scale: 10
                    }
                });
            }
        });
    </script>
    <?php if ($step === 'transaction'): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $googleMapsApiKey; ?>" async defer></script>
    <?php endif; ?>
</body>
</html>

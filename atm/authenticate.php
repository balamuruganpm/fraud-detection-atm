<?php
require_once '../config/database.php';
require_once '../services/fraud_detection_service.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Process ATM card authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber = $_POST['card_number'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $expiryMonth = $_POST['expiry_month'] ?? '';
    $expiryYear = $_POST['expiry_year'] ?? '';
    $atmId = $_POST['atm_id'] ?? '';
    
    if (empty($cardNumber) || empty($cvv) || empty($expiryMonth) || empty($expiryYear) || empty($atmId)) {
        $error = 'All fields are required.';
    } else {
        // Check if card exists and is valid
        $query = "SELECT c.*, u.user_id, u.full_name 
                  FROM atm_cards c 
                  JOIN users u ON c.user_id = u.user_id 
                  WHERE c.card_number = :card_number 
                  AND c.cvv = :cvv 
                  AND c.expiry_month = :expiry_month 
                  AND c.expiry_year = :expiry_year";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':card_number', $cardNumber);
        $stmt->bindParam(':cvv', $cvv);
        $stmt->bindParam(':expiry_month', $expiryMonth);
        $stmt->bindParam(':expiry_year', $expiryYear);
        $stmt->execute();
        
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$card) {
            $error = 'Invalid card details.';
        } elseif ($card['card_status'] !== 'ACTIVE') {
            $error = 'Card is not active.';
        } else {
            // Card is valid, store in session
            $_SESSION['atm_card_id'] = $card['card_id'];
            $_SESSION['atm_user_id'] = $card['user_id'];
            $_SESSION['atm_user_name'] = $card['full_name'];
            $_SESSION['atm_id'] = $atmId;
            
            // Redirect to transaction page
            header('Location: transaction.php');
            exit;
        }
    }
}

// Get all ATM locations
$query = "SELECT * FROM atm_locations";
$stmt = $db->prepare($query);
$stmt->execute();
$atmLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM Authentication - Fraud Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
            max-width: 500px;
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
    </style>
</head>
<body>
    <div class="atm-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">ATM Authentication</h3>
                <p class="mb-0">Enter your card details</p>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
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
                    
                    <div class="mb-3">
                        <label for="card_number" class="form-label">Card Number</label>
                        <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="expiry_month" class="form-label">Expiry Date</label>
                            <div class="input-group">
                                <select class="form-select" id="expiry_month" name="expiry_month" required>
                                    <option value="">MM</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="input-group-text">/</span>
                                <select class="form-select" id="expiry_year" name="expiry_year" required>
                                    <option value="">YY</option>
                                    <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="cvv" class="form-label">CVV</label>
                            <input type="password" class="form-control" id="cvv" name="cvv" placeholder="123" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Authenticate</button>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">This is a simulation of an ATM interface</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get user information
$userId = $_SESSION['user_id'];

// Get user's ATM cards
$query = "SELECT * FROM atm_cards WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected card
$selectedCardId = isset($_GET['card_id']) ? $_GET['card_id'] : (isset($cards[0]) ? $cards[0]['card_id'] : null);

// Get country restrictions for selected card
$countryRestrictions = [];
if ($selectedCardId) {
    $query = "SELECT * FROM country_restrictions WHERE card_id = :card_id ORDER BY country_name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':card_id', $selectedCardId);
    $stmt->execute();
    $countryRestrictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_restrictions'])) {
        $cardId = $_POST['card_id'] ?? '';
        $countries = $_POST['countries'] ?? [];
        
        if (empty($cardId)) {
            $error = 'Card ID is required.';
        } else {
            // Verify card belongs to user
            $query = "SELECT * FROM atm_cards WHERE card_id = :card_id AND user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':card_id', $cardId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$card) {
                $error = 'Invalid card.';
            } else {
                // Get all country restrictions
                $query = "SELECT * FROM country_restrictions WHERE card_id = :card_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':card_id', $cardId);
                $stmt->execute();
                $existingRestrictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Update each country restriction
                foreach ($existingRestrictions as $restriction) {
                    $countryCode = $restriction['country_code'];
                    $isAllowed = in_array($countryCode, $countries) ? 1 : 0;
                    
                    $query = "UPDATE country_restrictions 
                              SET is_allowed = :is_allowed 
                              WHERE card_id = :card_id AND country_code = :country_code";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':is_allowed', $isAllowed);
                    $stmt->bindParam(':card_id', $cardId);
                    $stmt->bindParam(':country_code', $countryCode);
                    $stmt->execute();
                }
                
                $success = 'Country restrictions updated successfully.';
                
                // Refresh country restrictions
                $query = "SELECT * FROM country_restrictions WHERE card_id = :card_id ORDER BY country_name";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':card_id', $cardId);
                $stmt->execute();
                $countryRestrictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Country Restrictions - ATM Fraud Detection</title>
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
            padding: 30px 15px;
        }
        .card {
            background-color: #073642;
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            margin-bottom: 30px;
        }
        .card-header {
            background-color: #002b36;
            border-bottom: 1px solid #0e4b5a;
            padding: 20px;
        }
        .card-body {
            padding: 30px;
        }
        .btn-primary {
            background-color: #2aa198;
            border-color: #2aa198;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: #238c82;
            border-color: #238c82;
        }
        .form-check-input {
            background-color: #002b36;
            border-color: #0e4b5a;
        }
        .form-check-input:checked {
            background-color: #2aa198;
            border-color: #2aa198;
        }
        .form-select {
            background-color: #002b36;
            border-color: #0e4b5a;
            color: white;
        }
        .form-select:focus {
            background-color: #00232c;
            border-color: #2aa198;
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(42, 161, 152, 0.25);
        }
        .country-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .country-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #0e4b5a;
        }
        .country-item:last-child {
            border-bottom: none;
        }
        .country-flag {
            width: 30px;
            height: 20px;
            margin-right: 10px;
            border-radius: 3px;
        }
        .atm-card {
            background: linear-gradient(135deg, #073642, #002b36);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
            border: 1px solid #0e4b5a;
            cursor: pointer;
            transition: all 0.3s;
        }
        .atm-card:hover {
            border-color: #2aa198;
            transform: translateY(-3px);
        }
        .atm-card.selected {
            border-color: #2aa198;
            background: linear-gradient(135deg, #073642, #00232c);
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
            font-family: monospace;
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
                        <a class="nav-link" href="atm-simulator.php">ATM Simulator</a>
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
        <h1 class="mb-4">Country Restriction Mode</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Select Your Credit Card</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cards)): ?>
                            <p>No cards found.</p>
                        <?php else: ?>
                            <?php foreach ($cards as $card): ?>
                                <a href="country-restrictions.php?card_id=<?php echo $card['card_id']; ?>" class="text-decoration-none">
                                    <div class="atm-card <?php echo $card['card_id'] == $selectedCardId ? 'selected' : ''; ?>">
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
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Select Your Credit Card Access Available Country</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($selectedCardId)): ?>
                            <p>Please select a card to manage country restrictions.</p>
                        <?php elseif (empty($countryRestrictions)): ?>
                            <p>No country restrictions found for this card.</p>
                        <?php else: ?>
                            <form method="post" action="">
                                <input type="hidden" name="card_id" value="<?php echo $selectedCardId; ?>">
                                <input type="hidden" name="update_restrictions" value="1">
                                
                                <div class="country-list">
                                    <?php foreach ($countryRestrictions as $restriction): ?>
                                        <div class="country-item">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="countries[]" value="<?php echo $restriction['country_code']; ?>" id="country-<?php echo $restriction['country_code']; ?>" <?php echo $restriction['is_allowed'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="country-<?php echo $restriction['country_code']; ?>">
                                                    <img src="https://flagcdn.com/w40/<?php echo strtolower($restriction['country_code']); ?>.png" alt="<?php echo $restriction['country_name']; ?> flag" class="country-flag">
                                                    <?php echo $restriction['country_name']; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">About Country Restrictions</h5>
                    </div>
                    <div class="card-body">
                        <p>Country restrictions allow you to control where your card can be used. By default, your card is enabled for use in your home country.</p>
                        <p>If you plan to travel or make purchases in other countries, you should enable those countries before your trip to avoid transaction denials.</p>
                        <p>This feature helps protect your card from fraudulent use in countries where you don't expect to make transactions.</p>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> For security reasons, some high-risk countries may be permanently restricted by your bank.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

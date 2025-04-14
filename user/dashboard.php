<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user information
$userId = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's bank accounts
$query = "SELECT * FROM bank_accounts WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's ATM cards
$query = "SELECT c.*, a.account_number, a.account_type 
          FROM atm_cards c 
          JOIN bank_accounts a ON c.account_id = a.account_id 
          WHERE c.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$query = "SELECT t.*, a.atm_name, a.address 
          FROM transactions t 
          LEFT JOIN atm_locations a ON t.atm_id = a.atm_id 
          WHERE t.user_id = :user_id 
          ORDER BY t.transaction_date DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get geo-fenced regions
$query = "SELECT * FROM geo_fenced_regions WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ATM Fraud Detection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.5rem 1rem;
            margin: 0.2rem 0;
            border-radius: 0.25rem;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: #0d6efd;
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            font-weight: 600;
        }
        .account-card {
            border-left: 4px solid #0d6efd;
        }
        .account-card.savings {
            border-left-color: #198754;
        }
        .account-card.checking {
            border-left-color: #0d6efd;
        }
        .account-card.credit {
            border-left-color: #dc3545;
        }
        .account-balance {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .account-number {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .atm-card {
            background: linear-gradient(45deg, #0a0a0a, #3a3a3a);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .atm-card .card-number {
            font-size: 1.25rem;
            letter-spacing: 2px;
            margin: 20px 0;
        }
        .atm-card .card-details {
            display: flex;
            justify-content: space-between;
        }
        .atm-card .card-holder {
            text-transform: uppercase;
            font-size: 0.875rem;
        }
        .atm-card .card-expiry {
            font-size: 0.875rem;
        }
        .atm-card .card-type {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 1.5rem;
        }
        .badge-approved {
            background-color: #198754;
        }
        .badge-pending {
            background-color: #ffc107;
        }
        .badge-denied {
            background-color: #dc3545;
        }
        .badge-flagged {
            background-color: #fd7e14;
        }
        .map-container {
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="d-flex align-items-center justify-content-center mb-4">
                        <h5 class="mb-0">ATM Fraud Detection</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="accounts.php">
                                <i class="fas fa-wallet"></i> Accounts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cards.php">
                                <i class="fas fa-credit-card"></i> Cards
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">
                                <i class="fas fa-exchange-alt"></i> Transactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="regions.php">
                                <i class="fas fa-map-marked-alt"></i> Geo Regions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="atm-simulator.php">
                                <i class="fas fa-landmark"></i> ATM Simulator
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refresh-location">
                            <i class="fas fa-location-arrow"></i> Update Location
                        </button>
                    </div>
                </div>

                <!-- Accounts section -->
                <h4 class="mb-3">Your Accounts</h4>
                <div class="row">
                    <?php foreach ($accounts as $account): ?>
                        <div class="col-md-4">
                            <div class="card account-card <?php echo strtolower($account['account_type']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $account['account_type']; ?> Account</h5>
                                    <div class="account-balance">
                                        $<?php echo number_format($account['balance'], 2); ?>
                                    </div>
                                    <div class="account-number">
                                        <?php echo substr($account['account_number'], 0, 4) . ' **** **** ' . substr($account['account_number'], -4); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ATM Cards section -->
                <h4 class="mt-4 mb-3">Your ATM Cards</h4>
                <div class="row">
                    <?php foreach ($cards as $card): ?>
                        <div class="col-md-6">
                            <div class="atm-card position-relative">
                                <div class="card-type">
                                    <?php if (substr($card['card_number'], 0, 1) == '4'): ?>
                                        <i class="fab fa-cc-visa"></i>
                                    <?php elseif (substr($card['card_number'], 0, 1) == '5'): ?>
                                        <i class="fab fa-cc-mastercard"></i>
                                    <?php elseif (substr($card['card_number'], 0, 1) == '3'): ?>
                                        <i class="fab fa-cc-amex"></i>
                                    <?php else: ?>
                                        <i class="fas fa-credit-card"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card-number">
                                    <?php 
                                    $masked = substr($card['card_number'], 0, 4) . ' **** **** ' . substr($card['card_number'], -4);
                                    echo $masked;
                                    ?>
                                </div>
                                <div class="card-details">
                                    <div class="card-holder">
                                        <?php echo $card['card_holder']; ?>
                                    </div>
                                    <div class="card-expiry">
                                        <?php echo sprintf('%02d/%d', $card['expiry_month'], $card['expiry_year']); ?>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">Daily Limit: $<?php echo number_format($card['daily_limit'], 2); ?></small>
                                    <div class="progress mt-1" style="height: 5px;">
                                        <?php 
                                        $percentUsed = 100 - (($card['remaining_limit'] / $card['daily_limit']) * 100);
                                        ?>
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentUsed; ?>%" aria-valuenow="<?php echo $percentUsed; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">Remaining: $<?php echo number_format($card['remaining_limit'], 2); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent Transactions -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Recent Transactions</span>
                        <a href="transactions.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No transactions found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                                <td><?php echo $transaction['transaction_type']; ?></td>
                                                <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($transaction['atm_id'] && isset($transaction['atm_name'])) {
                                                        echo $transaction['atm_name'];
                                                    } elseif ($transaction['location_latitude'] && $transaction['location_longitude']) {
                                                        echo 'Lat: ' . $transaction['location_latitude'] . ', Lng: ' . $transaction['location_longitude'];
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($transaction['transaction_status']) {
                                                        case 'APPROVED':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                        case 'PENDING':
                                                            $statusClass = 'bg-warning';
                                                            break;
                                                        case 'DENIED':
                                                            $statusClass = 'bg-danger';
                                                            break;
                                                        case 'FLAGGED':
                                                            $statusClass = 'bg-warning text-dark';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo $transaction['transaction_status']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Map with Geo-fenced Regions -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Your Geo-fenced Regions</span>
                        <a href="regions.php" class="btn btn-sm btn-primary">Manage Regions</a>
                    </div>
                    <div class="card-body">
                        <div id="map" class="map-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&callback=initMap" async defer></script>
    <script>
        // Initialize map
        let map;
        let userMarker;
        const regions = <?php echo json_encode($regions); ?>;
        
        function initMap() {
            // Default center (will be updated with user's location)
            const defaultCenter = { lat: 40.7128, lng: -74.0060 };
            
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 10,
                center: defaultCenter,
            });
            
            // Try to get user's current location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        
                        // Center map on user location
                        map.setCenter(userLocation);
                        
                        // Add marker for user location
                        userMarker = new google.maps.Marker({
                            position: userLocation,
                            map: map,
                            title: "Your Location",
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 10,
                                fillColor: "#4285F4",
                                fillOpacity: 1,
                                strokeColor: "#FFFFFF",
                                strokeWeight: 2,
                            },
                        });
                        
                        // Send location to server
                        updateUserLocation(userLocation.lat, userLocation.lng);
                    },
                    () => {
                        // Handle location error
                        console.log("Error: The Geolocation service failed.");
                    }
                );
            }
            
            // Add geo-fenced regions to map
            regions.forEach((region) => {
                const center = { 
                    lat: parseFloat(region.center_latitude), 
                    lng: parseFloat(region.center_longitude) 
                };
                
                // Add circle for region
                const regionCircle = new google.maps.Circle({
                    strokeColor: "#FF0000",
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: "#FF0000",
                    fillOpacity: 0.35,
                    map: map,
                    center: center,
                    radius: parseFloat(region.radius_km) * 1000, // Convert km to meters
                });
                
                // Add marker for region center
                const regionMarker = new google.maps.Marker({
                    position: center,
                    map: map,
                    title: region.region_name,
                });
                
                // Add info window
                const infoWindow = new google.maps.InfoWindow({
                    content: `<strong>${region.region_name}</strong><br>Radius: ${region.radius_km} km`,
                });
                
                regionMarker.addListener("click", () => {
                    infoWindow.open(map, regionMarker);
                });
            });
        }
        
        // Update user location on server
        function updateUserLocation(latitude, longitude) {
            $.ajax({
                url: 'update_location.php',
                type: 'POST',
                data: {
                    latitude: latitude,
                    longitude: longitude
                },
                success: function(response) {
                    console.log('Location updated successfully');
                },
                error: function(xhr, status, error) {
                    console.error('Error updating location:', error);
                }
            });
        }
        
        // Refresh location button
        document.getElementById('refresh-location').addEventListener('click', function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        
                        // Update marker position
                        if (userMarker) {
                            userMarker.setPosition(userLocation);
                        }
                        
                        // Center map on user location
                        map.setCenter(userLocation);
                        
                        // Send location to server
                        updateUserLocation(userLocation.lat, userLocation.lng);
                        
                        // Show success message
                        alert('Your location has been updated successfully.');
                    },
                    () => {
                        alert('Error: Unable to retrieve your location.');
                    }
                );
            } else {
                alert('Error: Your browser doesn\'t support geolocation.');
            }
        });
    </script>
</body>
</html>

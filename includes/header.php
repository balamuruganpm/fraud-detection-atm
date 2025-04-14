<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Check for session timeout (30 minutes of inactivity)
$sessionTimeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Session has expired, destroy it and redirect to login
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php?timeout=1');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Get database connection
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

// Check if user exists
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php?error=invalid_user');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'ATM Fraud Detection'; ?></title>
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
        .session-timer {
            font-size: 0.8rem;
            color: #93a1a1;
            margin-right: 15px;
        }
        /* Additional custom styles can be added here */
        <?php echo $customStyles ?? ''; ?>
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
                        <a class="nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'accounts' ? 'active' : ''; ?>" href="accounts.php">My Accounts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'cards' ? 'active' : ''; ?>" href="cards.php">My Cards</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'regions' ? 'active' : ''; ?>" href="regions.php">Geo Regions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'country-restrictions' ? 'active' : ''; ?>" href="country-restrictions.php">Country Restrictions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'atm-simulator' ? 'active' : ''; ?>" href="atm-simulator.php">ATM Simulator</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'activity' ? 'active' : ''; ?>" href="activity.php">Activity</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link session-timer" id="session-timer">
                            Session: 30:00
                        </span>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="mfa.php">Security</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page content will be inserted here -->

<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/firebase.php';
require_once '../services/notification_service.php';

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

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enable_mfa'])) {
        // Enable MFA
        $query = "UPDATE users SET mfa_enabled = 1 WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            $success = 'Multi-factor authentication has been enabled.';
            
            // Log MFA change
            $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address) 
                      VALUES (:user_id, 'MFA', 'SUCCESS', :ip_address)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->execute();
            
            // Update user object
            $user['mfa_enabled'] = 1;
        } else {
            $error = 'Failed to enable multi-factor authentication.';
        }
    } elseif (isset($_POST['disable_mfa'])) {
        // Disable MFA
        $query = "UPDATE users SET mfa_enabled = 0 WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            $success = 'Multi-factor authentication has been disabled.';
            
            // Log MFA change
            $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address) 
                      VALUES (:user_id, 'MFA', 'SUCCESS', :ip_address)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->execute();
            
            // Update user object
            $user['mfa_enabled'] = 0;
        } else {
            $error = 'Failed to disable multi-factor authentication.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Factor Authentication - ATM Fraud Detection</title>
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
        .btn-danger {
            background-color: #dc322f;
            border-color: #dc322f;
        }
        .btn-danger:hover, .btn-danger:focus {
            background-color: #c42e2b;
            border-color: #c42e2b;
        }
        .mfa-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #2aa198;
        }
        .progress {
            height: 8px;
            background-color: #002b36;
        }
        .progress-bar {
            background-color: #2aa198;
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
        <h1 class="mb-4">Multi-Factor Authentication</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Multi-Factor Authentication</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mfa-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        
                        <h4 class="mb-3">You Can Activate Or Deactivate Multi-Factor Authentication</h4>
                        
                        <p class="mb-4">
                            Multi-factor authentication adds an extra layer of security to your account by requiring a verification code in addition to your password when you log in.
                        </p>
                        
                        <form method="post" action="">
                            <?php if ($user['mfa_enabled']): ?>
                                <button type="submit" name="disable_mfa" class="btn btn-danger btn-lg">
                                    <i class="fas fa-times-circle"></i> Deactivate
                                </button>
                            <?php else: ?>
                                <button type="submit" name="enable_mfa" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check-circle"></i> Activate
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Security Status</h5>
                    </div>
                    <div class="card-body">
                        <h6>Account Security Level</h6>
                        <div class="progress mb-3">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $user['mfa_enabled'] ? '100%' : '50%'; ?>" aria-valuenow="<?php echo $user['mfa_enabled'] ? '100' : '50'; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p class="mb-4">
                            <?php if ($user['mfa_enabled']): ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i> Your account is well protected with multi-factor authentication.</span>
                            <?php else: ?>
                                <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Your account security could be improved by enabling multi-factor authentication.</span>
                            <?php endif; ?>
                        </p>
                        
                        <h6>Security Features</h6>
                        <ul class="list-group list-group-flush bg-transparent">
                            <li class="list-group-item bg-transparent text-white border-bottom border-dark">
                                <i class="fas fa-<?php echo $user['mfa_enabled'] ? 'check text-success' : 'times text-danger'; ?>"></i>
                                Multi-Factor Authentication
                            </li>
                            <li class="list-group-item bg-transparent text-white border-bottom border-dark">
                                <i class="fas fa-check text-success"></i>
                                Location-Based Fraud Detection
                            </li>
                            <li class="list-group-item bg-transparent text-white border-bottom border-dark">
                                <i class="fas fa-check text-success"></i>
                                Real-time Transaction Monitoring
                            </li>
                            <li class="list-group-item bg-transparent text-white">
                                <i class="fas fa-check text-success"></i>
                                Instant Fraud Alerts
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">How Multi-Factor Authentication Works</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center mb-4 mb-md-0">
                                <div class="mb-3">
                                    <i class="fas fa-user-lock fa-3x text-primary"></i>
                                </div>
                                <h5>Step 1: Login</h5>
                                <p>Enter your username and password as usual.</p>
                            </div>
                            <div class="col-md-4 text-center mb-4 mb-md-0">
                                <div class="mb-3">
                                    <i class="fas fa-mobile-alt fa-3x text-primary"></i>
                                </div>
                                <h5>Step 2: Verification</h5>
                                <p>A verification code will be sent to your registered email or phone.</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <i class="fas fa-check-double fa-3x text-primary"></i>
                                </div>
                                <h5>Step 3: Confirmation</h5>
                                <p>Enter the verification code to complete your login.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

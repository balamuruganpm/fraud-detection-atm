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
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's recent activity
$query = "SELECT * FROM auth_logs 
          WHERE user_id = :user_id 
          ORDER BY created_at DESC 
          LIMIT 50";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's recent transactions
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

// Get user's recent alerts
$query = "SELECT al.*, t.transaction_id, t.amount, a.atm_name, a.address 
          FROM alerts al 
          JOIN transactions t ON al.transaction_id = t.transaction_id 
          LEFT JOIN atm_locations a ON t.atm_id = a.atm_id 
          WHERE t.user_id = :user_id 
          ORDER BY al.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Activity - ATM Fraud Detection</title>
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
        .table {
            color: white;
        }
        .table th {
            border-color: #0e4b5a;
        }
        .table td {
            border-color: #0e4b5a;
        }
        .activity-item {
            background-color: #002b36;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid #2aa198;
        }
        .activity-item.success {
            border-left-color: #859900;
        }
        .activity-item.warning {
            border-left-color: #b58900;
        }
        .activity-item.danger {
            border-left-color: #dc322f;
        }
        .activity-icon {
            font-size: 1.5rem;
            margin-right: 15px;
        }
        .activity-time {
            color: #93a1a1;
            font-size: 0.9rem;
        }
        .activity-details {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #93a1a1;
        }
        .nav-pills .nav-link {
            color: white;
            border-radius: 10px;
        }
        .nav-pills .nav-link.active {
            background-color: #2aa198;
        }
        .nav-pills .nav-link:hover:not(.active) {
            background-color: rgba(42, 161, 152, 0.2);
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
                    <li class="nav-item">
                        <a class="nav-link active" href="activity.php">Activity</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
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
        <h1 class="mb-4">Recent Activity</h1>
        
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills card-header-pills">
                    <li class="nav-item">
                        <a class="nav-link active" id="all-tab" data-bs-toggle="pill" href="#all">All Activity</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="logins-tab" data-bs-toggle="pill" href="#logins">Logins</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="transactions-tab" data-bs-toggle="pill" href="#transactions">Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="alerts-tab" data-bs-toggle="pill" href="#alerts">Alerts</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="all">
                        <h5 class="mb-4">All Recent Activity</h5>
                        
                        <?php if (empty($activities) && empty($transactions) && empty($alerts)): ?>
                            <p>No recent activity found.</p>
                        <?php else: ?>
                            <?php 
                            // Combine all activities
                            $allActivities = [];
                            
                            // Add auth logs
                            foreach ($activities as $activity) {
                                $allActivities[] = [
                                    'type' => 'auth',
                                    'date' => $activity['created_at'],
                                    'data' => $activity
                                ];
                            }
                            
                            // Add transactions
                            foreach ($transactions as $transaction) {
                                $allActivities[] = [
                                    'type' => 'transaction',
                                    'date' => $transaction['transaction_date'],
                                    'data' => $transaction
                                ];
                            }
                            
                            // Add alerts
                            foreach ($alerts as $alert) {
                                $allActivities[] = [
                                    'type' => 'alert',
                                    'date' => $alert['created_at'],
                                    'data' => $alert
                                ];
                            }
                            
                            // Sort by date (newest first)
                            usort($allActivities, function($a, $b) {
                                return strtotime($b['date']) - strtotime($a['date']);
                            });
                            
                            // Display activities
                            foreach ($allActivities as $activity):
                                if ($activity['type'] === 'auth'):
                                    $auth = $activity['data'];
                                    $statusClass = $auth['status'] === 'SUCCESS' ? 'success' : 'danger';
                                    $icon = '';
                                    $title = '';
                                    
                                    switch ($auth['auth_type']) {
                                        case 'LOGIN':
                                            $icon = 'fa-sign-in-alt';
                                            $title = 'Login ' . ($auth['status'] === 'SUCCESS' ? 'Successful' : 'Failed');
                                            break;
                                        case 'LOGOUT':
                                            $icon = 'fa-sign-out-alt';
                                            $title = 'Logout';
                                            break;
                                        case 'MFA':
                                            $icon = 'fa-shield-alt';
                                            $title = 'Two-Factor Authentication ' . ($auth['status'] === 'SUCCESS' ? 'Successful' : 'Failed');
                                            break;
                                        case 'PIN_ENTRY':
                                            $icon = 'fa-key';
                                            $title = 'PIN Entry ' . ($auth['status'] === 'SUCCESS' ? 'Successful' : 'Failed');
                                            break;
                                        case 'PASSWORD_CHANGE':
                                            $icon = 'fa-lock';
                                            $title = 'Password Change';
                                            break;
                                    }
                            ?>
                                <div class="activity-item <?php echo $statusClass; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo $title; ?></div>
                                            <div class="activity-time"><?php echo date('M d, Y h:i A', strtotime($auth['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="activity-details">
                                        <div>IP Address: <?php echo $auth['ip_address']; ?></div>
                                        <div>Device: <?php echo $auth['device_info']; ?></div>
                                    </div>
                                </div>
                            <?php 
                                elseif ($activity['type'] === 'transaction'):
                                    $transaction = $activity['data'];
                                    $statusClass = '';
                                    
                                    switch ($transaction['transaction_status']) {
                                        case 'APPROVED':
                                            $statusClass = 'success';
                                            break;
                                        case 'DENIED':
                                            $statusClass = 'danger';
                                            break;
                                        case 'FLAGGED':
                                            $statusClass = 'warning';
                                            break;
                                        default:
                                            $statusClass = '';
                                    }
                            ?>
                                <div class="activity-item <?php echo $statusClass; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon">
                                            <?php if ($transaction['transaction_type'] === 'WITHDRAWAL'): ?>
                                                <i class="fas fa-money-bill-wave"></i>
                                            <?php elseif ($transaction['transaction_type'] === 'DEPOSIT'): ?>
                                                <i class="fas fa-piggy-bank"></i>
                                            <?php elseif ($transaction['transaction_type'] === 'BALANCE_CHECK'): ?>
                                                <i class="fas fa-search-dollar"></i>
                                            <?php else: ?>
                                                <i class="fas fa-exchange-alt"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo $transaction['transaction_type']; ?> - $<?php echo number_format($transaction['amount'], 2); ?></div>
                                            <div class="activity-time"><?php echo date('M d, Y h:i A', strtotime($transaction['transaction_date'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="activity-details">
                                        <div>Status: <?php echo $transaction['transaction_status']; ?></div>
                                        <?php if ($transaction['atm_name']): ?>
                                            <div>ATM: <?php echo $transaction['atm_name']; ?> (<?php echo $transaction['address']; ?>)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                elseif ($activity['type'] === 'alert'):
                                    $alert = $activity['data'];
                                    $responseClass = '';
                                    
                                    switch ($alert['user_response']) {
                                        case 'APPROVED':
                                            $responseClass = 'success';
                                            break;
                                        case 'DENIED':
                                            $responseClass = 'danger';
                                            break;
                                        default:
                                            $responseClass = 'warning';
                                    }
                            ?>
                                <div class="activity-item <?php echo $responseClass; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">Alert: <?php echo $alert['alert_type']; ?></div>
                                            <div class="activity-time"><?php echo date('M d, Y h:i A', strtotime($alert['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="activity-details">
                                        <div>Transaction: #<?php echo $alert['transaction_id']; ?> - $<?php echo number_format($alert['amount'], 2); ?></div>
                                        <div>Response: <?php echo $alert['user_response']; ?></div>
                                        <?php if ($alert['atm_name']): ?>
                                            <div>ATM: <?php echo $alert['atm_name']; ?> (<?php echo $alert['address']; ?>)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach;
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="logins">
                        <h5 class="mb-4">Login Activity</h5>
                        
                        <?php if (empty($activities)): ?>
                            <p>No login activity found.</p>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <?php if ($activity['auth_type'] === 'LOGIN' || $activity['auth_type'] === 'MFA'): ?>
                                    <?php
                                        $statusClass = $activity['status'] === 'SUCCESS' ? 'success' : 'danger';
                                        $icon = $activity['auth_type'] === 'LOGIN' ? 'fa-sign-in-alt' : 'fa-shield-alt';
                                        $title = $activity['auth_type'] === 'LOGIN' ? 'Login' : 'Two-Factor Authentication';
                                        $title .= ' ' . ($activity['status'] === 'SUCCESS' ? 'Successful' : 'Failed');
                                    ?>
                                    <div class="activity-item <?php echo $statusClass; ?>">
                                        <div class="d-flex align-items-center">
                                            <div class="activity-icon">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo $title; ?></div>
                                                <div class="activity-time"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="activity-details">
                                            <div>IP Address: <?php echo $activity['ip_address']; ?></div>
                                            <div>Device: <?php echo $activity['device_info']; ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="transactions">
                        <h5 class="mb-4">Transaction History</h5>
                        
                        <?php if (empty($transactions)): ?>
                            <p>No transactions found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>ATM</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y h:i A', strtotime($transaction['transaction_date'])); ?></td>
                                                <td><?php echo $transaction['transaction_type']; ?></td>
                                                <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                                <td>
                                                    <?php if ($transaction['atm_name']): ?>
                                                        <?php echo $transaction['atm_name']; ?><br>
                                                        <small class="text-muted"><?php echo $transaction['address']; ?></small>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $transaction['transaction_status'] === 'APPROVED' ? 'success' : 
                                                            ($transaction['transaction_status'] === 'FLAGGED' ? 'warning' : 
                                                                ($transaction['transaction_status'] === 'DENIED' ? 'danger' : 'secondary')); 
                                                    ?>">
                                                        <?php echo $transaction['transaction_status']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="alerts">
                        <h5 class="mb-4">Security Alerts</h5>
                        
                        <?php if (empty($alerts)): ?>
                            <p>No security alerts found.</p>
                        <?php else: ?>
                            <?php foreach ($alerts as $alert): ?>
                                <?php
                                    $responseClass = '';
                                    
                                    switch ($alert['user_response']) {
                                        case 'APPROVED':
                                            $responseClass = 'success';
                                            break;
                                        case 'DENIED':
                                            $responseClass = 'danger';
                                            break;
                                        default:
                                            $responseClass = 'warning';
                                    }
                                ?>
                                <div class="activity-item <?php echo $responseClass; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">Alert: <?php echo $alert['alert_type']; ?></div>
                                            <div class="activity-time"><?php echo date('M d, Y h:i A', strtotime($alert['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="activity-details">
                                        <div>Transaction: #<?php echo $alert['transaction_id']; ?> - $<?php echo number_format($alert['amount'], 2); ?></div>
                                        <div>Message: <?php echo $alert['alert_message']; ?></div>
                                        <div>Response: <?php echo $alert['user_response']; ?></div>
                                        <?php if ($alert['atm_name']): ?>
                                            <div>ATM: <?php echo $alert['atm_name']; ?> (<?php echo $alert['address']; ?>)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Security Tips</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="text-center mb-3">
                            <i class="fas fa-lock fa-3x" style="color: #2aa198;"></i>
                        </div>
                        <h5 class="text-center">Strong Passwords</h5>
                        <p>Use unique, complex passwords for all your accounts. Consider using a password manager to generate and store strong passwords securely.</p>
                    </div>
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="text-center mb-3">
                            <i class="fas fa-shield-alt fa-3x" style="color: #2aa198;"></i>
                        </div>
                        <h5 class="text-center">Two-Factor Authentication</h5>
                        <p>Enable two-factor authentication whenever possible. It adds an extra layer of security to your accounts.</p>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-eye-slash fa-3x" style="color: #2aa198;"></i>
                        </div>
                        <h5 class="text-center">Monitor Your Accounts</h5>
                        <p>Regularly check your account activity and report any suspicious transactions immediately.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get counts for dashboard
$counts = [
    'users' => 0,
    'transactions' => 0,
    'alerts' => 0,
    'atms' => 0
];

// Get user count
$query = "SELECT COUNT(*) as count FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$counts['users'] = $result['count'];

// Get transaction count
$query = "SELECT COUNT(*) as count FROM transactions";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$counts['transactions'] = $result['count'];

// Get alert count
$query = "SELECT COUNT(*) as count FROM alerts";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$counts['alerts'] = $result['count'];

// Get ATM count
$query = "SELECT COUNT(*) as count FROM atm_locations";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$counts['atms'] = $result['count'];

// Get recent transactions
$query = "SELECT t.transaction_id, t.amount, t.transaction_type, t.transaction_status, 
                 t.transaction_date, u.full_name 
          FROM transactions t 
          JOIN users u ON t.user_id = u.user_id 
          ORDER BY t.transaction_date DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent alerts
$query = "SELECT a.alert_id, a.alert_type, a.alert_status, a.user_response, 
                 a.created_at, t.transaction_id 
          FROM alerts a 
          JOIN transactions t ON a.transaction_id = t.transaction_id 
          ORDER BY a.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recentAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ATM Fraud Detection</title>
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
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .stat-card .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .table th {
            font-weight: 600;
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
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">
                                <i class="fas fa-exchange-alt"></i> Transactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="alerts.php">
                                <i class="fas fa-bell"></i> Alerts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="atms.php">
                                <i class="fas fa-landmark"></i> ATM Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="regions.php">
                                <i class="fas fa-map-marked-alt"></i> Geo Regions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar"></i> This week
                        </button>
                    </div>
                </div>

                <!-- Stats cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <i class="fas fa-users text-primary"></i>
                            <div class="stat-value"><?php echo $counts['users']; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <i class="fas fa-exchange-alt text-success"></i>
                            <div class="stat-value"><?php echo $counts['transactions']; ?></div>
                            <div class="stat-label">Transactions</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <i class="fas fa-bell text-warning"></i>
                            <div class="stat-value"><?php echo $counts['alerts']; ?></div>
                            <div class="stat-label">Alerts</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <i class="fas fa-landmark text-info"></i>
                            <div class="stat-value"><?php echo $counts['atms']; ?></div>
                            <div class="stat-label">ATM Locations</div>
                        </div>
                    </div>
                </div>

                <!-- Recent transactions -->
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
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentTransactions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No transactions found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentTransactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo $transaction['transaction_id']; ?></td>
                                                <td><?php echo $transaction['full_name']; ?></td>
                                                <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                                <td><?php echo $transaction['transaction_type']; ?></td>
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
                                                <td><?php echo date('M d, Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent alerts -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Recent Alerts</span>
                        <a href="alerts.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Transaction</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Response</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentAlerts)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No alerts found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentAlerts as $alert): ?>
                                            <tr>
                                                <td><?php echo $alert['alert_id']; ?></td>
                                                <td><?php echo $alert['transaction_id']; ?></td>
                                                <td><?php echo $alert['alert_type']; ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($alert['alert_status']) {
                                                        case 'SENT':
                                                            $statusClass = 'bg-info';
                                                            break;
                                                        case 'DELIVERED':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                        case 'FAILED':
                                                            $statusClass = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo $alert['alert_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $responseClass = '';
                                                    switch ($alert['user_response']) {
                                                        case 'APPROVED':
                                                            $responseClass = 'bg-success';
                                                            break;
                                                        case 'DENIED':
                                                            $responseClass = 'bg-danger';
                                                            break;
                                                        case 'NO_RESPONSE':
                                                            $responseClass = 'bg-secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $responseClass; ?>">
                                                        <?php echo $alert['user_response']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Auto-refresh dashboard data every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>

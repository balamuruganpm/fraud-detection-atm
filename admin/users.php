<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Process user actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $userId = $_POST['user_id'] ?? 0;
                $isActive = $_POST['is_active'] ?? 0;
                $newStatus = $isActive ? 0 : 1;
                
                $query = "UPDATE users SET is_active = :is_active WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':is_active', $newStatus);
                $stmt->bindParam(':user_id', $userId);
                
                if ($stmt->execute()) {
                    $message = 'User status updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update user status';
                    $messageType = 'danger';
                }
                break;
                
            case 'toggle_mfa':
                $userId = $_POST['user_id'] ?? 0;
                $mfaEnabled = $_POST['mfa_enabled'] ?? 0;
                $newStatus = $mfaEnabled ? 0 : 1;
                
                $query = "UPDATE users SET mfa_enabled = :mfa_enabled WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':mfa_enabled', $newStatus);
                $stmt->bindParam(':user_id', $userId);
                
                if ($stmt->execute()) {
                    $message = 'MFA status updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update MFA status';
                    $messageType = 'danger';
                }
                break;
                
            case 'reset_password':
                $userId = $_POST['user_id'] ?? 0;
                $newPassword = 'password123'; // Default reset password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $query = "UPDATE users SET password = :password WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':user_id', $userId);
                
                if ($stmt->execute()) {
                    $message = 'Password reset successfully to: ' . $newPassword;
                    $messageType = 'success';
                } else {
                    $message = 'Failed to reset password';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get users list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$params = [];

if (!empty($search)) {
    $searchCondition = " WHERE full_name LIKE :search OR username LIKE :search OR email LIKE :search OR phone_number LIKE :search";
    $params[':search'] = "%$search%";
}

// Get total users count
$countQuery = "SELECT COUNT(*) as total FROM users" . $searchCondition;
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalUsers = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users for current page
$query = "SELECT * FROM users" . $searchCondition . " ORDER BY user_id DESC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - ATM Fraud Detection</title>
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
        .table th {
            font-weight: 600;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .pagination {
            margin-bottom: 0;
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
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
                    <h1 class="h2">User Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add-user.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-user-plus"></i> Add New User
                        </a>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Users List</span>
                        <form class="d-flex" method="GET" action="">
                            <input class="form-control me-2" type="search" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-primary" type="submit">Search</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Contact</th>
                                        <th>Telegram</th>
                                        <th>Role</th>
                                        <th>MFA</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No users found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['user_id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-2" style="background-color: <?php echo '#' . substr(md5($user['username']), 0, 6); ?>">
                                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                        </div>  0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($user['username']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($user['phone_number']); ?></div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($user['telegram_chat_id'])): ?>
                                                        <span class="badge bg-success">Connected</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Not Connected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['is_admin']): ?>
                                                        <span class="badge bg-primary">Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">User</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="post" action="" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_mfa">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="mfa_enabled" value="<?php echo $user['mfa_enabled']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-<?php echo $user['mfa_enabled'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $user['mfa_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="view-user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit-user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to reset this user\'s password?');">
                                                            <input type="hidden" name="action" value="reset_password">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

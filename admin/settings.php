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

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_telegram'])) {
        $telegramBotToken = $_POST['telegram_bot_token'] ?? '';
        
        // Update Telegram bot token in system settings
        $query = "UPDATE system_settings SET setting_value = :value WHERE setting_name = 'telegram_bot_token'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':value', $telegramBotToken);
        
        if ($stmt->execute()) {
            $message = 'Telegram bot token updated successfully';
            $messageType = 'success';
            
            // Log the update
            error_log('Telegram bot token updated by admin: ' . $_SESSION['username']);
        } else {
            $message = 'Failed to update Telegram bot token';
            $messageType = 'danger';
        }
    }
    
    if (isset($_POST['test_telegram'])) {
        $chatId = $_POST['test_chat_id'] ?? '';
        
        if (empty($chatId)) {
            $message = 'Please enter a chat ID to test';
            $messageType = 'warning';
        } else {
            // Get Telegram bot token
            $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'telegram_bot_token'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $telegramBotToken = $result ? $result['setting_value'] : '';
            
            if (empty($telegramBotToken)) {
                $message = 'Telegram bot token is not configured';
                $messageType = 'danger';
            } else {
                // Send test message
                require_once '../services/notification_service.php';
                $notificationService = new NotificationService(['telegram_bot_token' => $telegramBotToken]);
                
                $testMessage = "🔔 This is a test message from ATM Fraud Detection System.\n\n";
                $testMessage .= "If you received this message, your Telegram bot is configured correctly.\n";
                $testMessage .= "Time: " . date('Y-m-d H:i:s');
                
                $result = $notificationService->sendTelegram($chatId, $testMessage);
                
                if ($result['success']) {
                    $message = 'Test message sent successfully to Telegram';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to send test message: ' . $result['message'];
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Get current settings
$query = "SELECT * FROM system_settings";
$stmt = $db->prepare($query);
$stmt->execute();
$settings = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - ATM Fraud Detection</title>
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
        .settings-section {
            margin-bottom: 30px;
        }
        .settings-section h5 {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .telegram-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .telegram-info ol {
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
                            <a class="nav-link active" href="settings.php">
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
                    <h1 class="h2">System Settings</h1>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="settings-section">
                            <h5>Telegram Bot Configuration</h5>
                            
                            <div class="telegram-info">
                                <h6><i class="fab fa-telegram text-primary"></i> Telegram Bot Setup Guide</h6>
                                <ol>
                                    <li>Open Telegram and search for <strong>@BotFather</strong></li>
                                    <li>Start a chat and send <code>/newbot</code> command</li>
                                    <li>Follow the instructions to create a new bot</li>
                                    <li>Copy the API token provided by BotFather</li>
                                    <li>Paste the token in the field below</li>
                                    <li>Users need to start a chat with your bot before they can receive messages</li>
                                    <li>To get a user's chat ID, they should send a message to your bot, then check <code>https://api.telegram.org/bot[YOUR_BOT_TOKEN]/getUpdates</code></li>
                                </ol>
                            </div>
                            
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="telegram_bot_token" class="form-label">Telegram Bot Token</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="telegram_bot_token" name="telegram_bot_token" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? ''); ?>" placeholder="Enter your Telegram bot token">
                                        <button class="btn btn-primary" type="submit" name="update_telegram">Update</button>
                                    </div>
                                    <div class="form-text">Current token: <?php echo !empty($settings['telegram_bot_token']) ? substr($settings['telegram_bot_token'], 0, 10) . '...' : 'Not set'; ?></div>
                                </div>
                            </form>
                            
                            <form method="post" action="" class="mt-4">
                                <div class="mb-3">
                                    <label for="test_chat_id" class="form-label">Test Telegram Notification</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="test_chat_id" name="test_chat_id" placeholder="Enter Telegram chat ID to test">
                                        <button class="btn btn-success" type="submit" name="test_telegram">Send Test Message</button>
                                    </div>
                                    <div class="form-text">Enter a Telegram chat ID to send a test message. The user must have started a chat with your bot.</div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="settings-section">
                            <h5>Development Mode</h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="dev_mode" checked>
                                <label class="form-check-label" for="dev_mode">Enable Development Mode</label>
                            </div>
                            <div class="form-text">In development mode, all notifications are logged to the console, and Telegram errors are handled gracefully.</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Server Information</h6>
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <th>PHP Version</th>
                                            <td><?php echo phpversion(); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Server Software</th>
                                            <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Database Type</th>
                                            <td>MySQL</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Application Information</h6>
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <th>Version</th>
                                            <td>1.0.0</td>
                                        </tr>
                                        <tr>
                                            <th>Environment</th>
                                            <td>Development</td>
                                        </tr>
                                        <tr>
                                            <th>Last Updated</th>
                                            <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('#toggle-password');
            const password = document.querySelector('#telegram_bot_token');
            
            if (togglePassword && password) {
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
        });
    </script>
</body>
</html>

<?php
session_start();
require_once '../config/database.php';
require_once '../services/notification_service.php';

// Check if MFA verification is pending
if (!isset($_SESSION['mfa_pending']) || !isset($_SESSION['mfa_user_id'])) {
  header('Location: login.php');
  exit;
}

$error = '';
$success = '';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get user information
$userId = $_SESSION['mfa_user_id'];
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header('Location: login.php');
  exit;
}

// Generate MFA code if not already generated
if (!isset($_SESSION['mfa_code']) && empty($error)) {
  $_SESSION['mfa_code'] = sprintf('%06d', mt_rand(100000, 999999));
  $_SESSION['mfa_code_expiry'] = time() + 300; // 5 minutes
  
  // Log MFA code to console for development
  error_log('==================================================');
  error_log('MFA CODE FOR USER ' . $user['username'] . ': ' . $_SESSION['mfa_code']);
  error_log('==================================================');
  
  // Send MFA code to user via Telegram
  $notificationService = new NotificationService();
  
  $message = "🔐 Your verification code for ATM Fraud Detection System is: *{$_SESSION['mfa_code']}*\n\n";
  $message .= "This code will expire in 5 minutes.\n";
  $message .= "If you did not request this code, please ignore this message and secure your account.";
  
  $result = $notificationService->sendTelegram($user['telegram_chat_id'] ?? '0', $message);
  
  if (!$result['success']) {
      // Don't show error in development mode, just log it
      error_log('Failed to send MFA code via Telegram: ' . $result['message']);
      $error = 'Failed to send verification code via Telegram. Please try again or contact support.';
      
      // For development, we'll show the code on screen
      $success = 'DEVELOPMENT MODE: Your verification code is: ' . $_SESSION['mfa_code'];
  } else {
      $success = 'Verification code has been sent to your Telegram.';
  }
}

// Process verification form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = $_POST['code'] ?? '';
  
  if (empty($code)) {
      $error = 'Please enter the verification code.';
  } elseif (!isset($_SESSION['mfa_code']) || !isset($_SESSION['mfa_code_expiry'])) {
      $error = 'Verification code has expired. Please try again.';
  } elseif (time() > $_SESSION['mfa_code_expiry']) {
      $error = 'Verification code has expired. Please try again.';
      unset($_SESSION['mfa_code']);
      unset($_SESSION['mfa_code_expiry']);
  } elseif ($code !== $_SESSION['mfa_code']) {
      $error = 'Invalid verification code.';
      
      // Log failed MFA attempt
      $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address, device_info) 
                VALUES (:user_id, 'MFA', 'FAILED', :ip_address, :device_info)";
      $stmt = $db->prepare($query);
      $stmt->bindParam(':user_id', $userId);
      $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
      $stmt->bindParam(':device_info', $_SERVER['HTTP_USER_AGENT']);
      $stmt->execute();
  } else {
      // Verification successful
      unset($_SESSION['mfa_pending']);
      unset($_SESSION['mfa_user_id']);
      unset($_SESSION['mfa_code']);
      unset($_SESSION['mfa_code_expiry']);
      
      // Complete login
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['full_name'] = $user['full_name'];
      $_SESSION['is_admin'] = $user['is_admin'];
      $_SESSION['last_activity'] = time();
      
      // Log successful MFA verification
      $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address, device_info) 
                VALUES (:user_id, 'MFA', 'SUCCESS', :ip_address, :device_info)";
      $stmt = $db->prepare($query);
      $stmt->bindParam(':user_id', $userId);
      $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
      $stmt->bindParam(':device_info', $_SERVER['HTTP_USER_AGENT']);
      $stmt->execute();
      
      // Redirect to dashboard
      if ($user['is_admin']) {
          header('Location: ../admin/dashboard.php');
      } else {
          header('Location: ../user/dashboard.php');
      }
      exit;
  }
}

// Calculate remaining time for code expiry
$remainingTime = isset($_SESSION['mfa_code_expiry']) ? $_SESSION['mfa_code_expiry'] - time() : 0;
$minutes = floor($remainingTime / 60);
$seconds = $remainingTime % 60;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication - ATM Fraud Detection</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
      body {
          background-color: #002b36;
          color: white;
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100vh;
      }
      .mfa-container {
          max-width: 400px;
          width: 100%;
          padding: 15px;
      }
      .card {
          background-color: #073642;
          border-radius: 15px;
          border: none;
          box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
          overflow: hidden;
      }
      .card-header {
          background-color: #002b36;
          color: white;
          text-align: center;
          border-radius: 15px 15px 0 0 !important;
          padding: 20px;
          border-bottom: 1px solid #0e4b5a;
      }
      .card-body {
          padding: 30px;
      }
      .card-footer {
          background-color: #002b36;
          border-top: 1px solid #0e4b5a;
          text-align: center;
          padding: 15px;
      }
      .btn-primary {
          background-color: #2aa198;
          border-color: #2aa198;
          width: 100%;
      }
      .btn-primary:hover, .btn-primary:focus {
          background-color: #238c82;
          border-color: #238c82;
      }
      .form-control {
          background-color: #002b36;
          border-color: #0e4b5a;
          color: white;
          text-align: center;
          font-size: 1.5rem;
          letter-spacing: 0.5rem;
      }
      .form-control:focus {
          background-color: #00232c;
          border-color: #2aa198;
          color: white;
          box-shadow: 0 0 0 0.25rem rgba(42, 161, 152, 0.25);
      }
      .alert-danger {
          background-color: rgba(220, 50, 47, 0.2);
          border-color: #dc322f;
          color: #fff;
      }
      .alert-success {
          background-color: rgba(38, 139, 210, 0.2);
          border-color: #268bd2;
          color: #fff;
      }
      .mfa-icon {
          font-size: 3rem;
          color: #2aa198;
          margin-bottom: 20px;
      }
      .timer {
          font-size: 0.9rem;
          color: #93a1a1;
          margin-top: 10px;
          text-align: center;
      }
      .resend-link {
          color: #2aa198;
          text-decoration: none;
      }
      .resend-link:hover {
          text-decoration: underline;
      }
      .telegram-info {
          background-color: rgba(38, 139, 210, 0.1);
          border-radius: 10px;
          padding: 15px;
          margin-bottom: 20px;
          display: flex;
          align-items: center;
      }
      .telegram-info i {
          font-size: 2rem;
          color: #0088cc;
          margin-right: 15px;
      }
      .dev-mode-notice {
          background-color: rgba(181, 137, 0, 0.2);
          border: 1px solid #b58900;
          color: #fff;
          padding: 10px;
          margin-bottom: 20px;
          border-radius: 5px;
          text-align: center;
      }
  </style>
</head>
<body>
  <div class="mfa-container">
      <div class="card">
          <div class="card-header">
              <h3 class="mb-0">Two-Factor Authentication</h3>
              <p class="mb-0">Verify your identity</p>
          </div>
          <div class="card-body text-center">
              <div class="mfa-icon">
                  <i class="fas fa-shield-alt"></i>
              </div>
              
              <?php if (!empty($error)): ?>
                  <div class="alert alert-danger"><?php echo $error; ?></div>
              <?php endif; ?>
              
              <?php if (!empty($success)): ?>
                  <div class="alert alert-success"><?php echo $success; ?></div>
              <?php endif; ?>
              
              <?php if (isset($_SESSION['mfa_code'])): ?>
                  <div class="dev-mode-notice">
                      <strong>DEVELOPMENT MODE</strong><br>
                      Your verification code is: <strong><?php echo $_SESSION['mfa_code']; ?></strong>
                  </div>
              <?php endif; ?>
              
              <div class="telegram-info">
                  <i class="fab fa-telegram"></i>
                  <div>
                      We've sent a verification code to your Telegram account. Please check your messages.
                  </div>
              </div>
              
              <form method="post" action="">
                  <div class="mb-3">
                      <input type="text" class="form-control" id="code" name="code" placeholder="------" maxlength="6" autocomplete="off" required>
                  </div>
                  
                  <div class="timer" id="timer">
                      Code expires in <?php echo sprintf('%02d:%02d', $minutes, $seconds); ?>
                  </div>
                  
                  <button type="submit" class="btn btn-primary mt-3">Verify</button>
              </form>
              
              <div class="mt-3">
                  <a href="mfa-verify.php?resend=1" class="resend-link">Didn't receive a code? Resend</a>
              </div>
          </div>
          <div class="card-footer">
              <a href="login.php" class="text-decoration-none" style="color: #2aa198;">
                  <i class="fas fa-arrow-left"></i> Back to login
              </a>
          </div>
      </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      document.addEventListener('DOMContentLoaded', function() {
          // Auto-focus the code input
          document.getElementById('code').focus();
          
          // Countdown timer
          let remainingTime = <?php echo $remainingTime; ?>;
          const timerElement = document.getElementById('timer');
          
          const countdownInterval = setInterval(function() {
              remainingTime--;
              if (remainingTime <= 0) {
                  clearInterval(countdownInterval);
                  timerElement.innerHTML = 'Code expired. <a href="mfa-verify.php?resend=1" class="resend-link">Resend code</a>';
              } else {
                  const minutes = Math.floor(remainingTime / 60);
                  const seconds = remainingTime % 60;
                  timerElement.textContent = `Code expires in ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
              }
          }, 1000);
      });
  </script>
</body>
</html>

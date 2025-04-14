<?php
session_start();
require_once '../config/database.php';
require_once '../services/notification_service.php';

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'request';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'request') {
        $email = $_POST['email'] ?? '';
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            // Check if email exists
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // Don't reveal that the email doesn't exist for security reasons
                $success = 'If your email is registered, you will receive a password reset link shortly.';
            } else {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $query = "UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':expiry', $expiry);
                $stmt->bindParam(':user_id', $user['user_id']);
                
                if ($stmt->execute()) {
                    // Send reset link to user
                    $resetLink = "http://{$_SERVER['HTTP_HOST']}/auth/forgot-password.php?step=reset&token={$token}";
                    
                    $message = "Hello {$user['full_name']},\n\n";
                    $message .= "You have requested to reset your password for the ATM Fraud Detection System.\n\n";
                    $message .= "Please click the link below to reset your password:\n";
                    $message .= "{$resetLink}\n\n";
                    $message .= "This link will expire in 1 hour.\n\n";
                    $message .= "If you did not request this password reset, please ignore this message and secure your account.";
                    
                    $notificationService = new NotificationService();
                    $notificationService->sendNotification($user, $message, 'Password Reset Request');
                    
                    $success = 'If your email is registered, you will receive a password reset link shortly.';
                } else {
                    $error = 'An error occurred. Please try again later.';
                }
            }
        }
    } elseif ($step === 'reset') {
        $token = $_GET['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($token)) {
            $error = 'Invalid reset token.';
        } elseif (empty($password) || empty($confirmPassword)) {
            $error = 'Please enter both password fields.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Check if token is valid
            $query = "SELECT * FROM users WHERE reset_token = :token AND reset_token_expiry > NOW()";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = 'Invalid or expired reset token.';
            } else {
                // Hash new password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password and clear token
                $query = "UPDATE users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':user_id', $user['user_id']);
                
                if ($stmt->execute()) {
                    // Log password change
                    $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address, device_info) 
                              VALUES (:user_id, 'PASSWORD_CHANGE', 'SUCCESS', :ip_address, :device_info)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user['user_id']);
                    $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                    $stmt->bindParam(':device_info', $_SERVER['HTTP_USER_AGENT']);
                    $stmt->execute();
                    
                    $success = 'Your password has been reset successfully. You can now login with your new password.';
                    
                    // Send confirmation email
                    $message = "Hello {$user['full_name']},\n\n";
                    $message .= "Your password for the ATM Fraud Detection System has been reset successfully.\n\n";
                    $message .= "If you did not make this change, please contact support immediately.";
                    
                    $notificationService = new NotificationService();
                    $notificationService->sendNotification($user, $message, 'Password Reset Confirmation');
                    
                    // Redirect to login page after a delay
                    header('Refresh: 5; URL=login.php');
                } else {
                    $error = 'An error occurred. Please try again later.';
                }
            }
        }
    }
}

// Validate token for reset step
if ($step === 'reset') {
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        $error = 'Invalid reset token.';
    } else {
        // Check if token is valid
        $query = "SELECT * FROM users WHERE reset_token = :token AND reset_token_expiry > NOW()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = 'Invalid or expired reset token.';
            $step = 'invalid';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ATM Fraud Detection</title>
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
        .reset-container {
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
        }
        .form-control:focus {
            background-color: #00232c;
            border-color: #2aa198;
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(42, 161, 152, 0.25);
        }
        .form-floating label {
            color: #93a1a1;
        }
        .form-floating>.form-control:focus~label,
        .form-floating>.form-control:not(:placeholder-shown)~label {
            color: #2aa198;
        }
        .alert-danger {
            background-color: rgba(220, 50, 47, 0.2);
            border-color: #dc322f;
            color: #fff;
        }
        .alert-success {
            background-color: rgba(42, 161, 152, 0.2);
            border-color: #2aa198;
            color: #fff;
        }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #93a1a1;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .password-feedback {
            font-size: 0.8rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Reset Password</h3>
                <p class="mb-0">ATM Fraud Detection System</p>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($step === 'request' && empty($success)): ?>
                    <form method="post" action="">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                            <label for="email">Email address</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Reset Link</button>
                    </form>
                <?php elseif ($step === 'reset' && empty($success)): ?>
                    <form method="post" action="?step=reset&token=<?php echo htmlspecialchars($_GET['token']); ?>">
                        <div class="form-floating mb-3 position-relative">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password">New Password</label>
                            <span class="password-toggle" id="password-toggle">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        
                        <div class="password-strength" id="password-strength"></div>
                        <div class="password-feedback" id="password-feedback"></div>
                        
                        <div class="form-floating mb-3 position-relative">
                            <input type="password" class="form-control" id="confirm-password" name="confirm_password" placeholder="Confirm Password" required>
                            <label for="confirm-password">Confirm Password</label>
                            <span class="password-toggle" id="confirm-password-toggle">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </form>
                <?php elseif ($step === 'invalid'): ?>
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color: #dc322f;"></i>
                        <p>The password reset link is invalid or has expired.</p>
                        <p>Please request a new password reset link.</p>
                        <a href="forgot-password.php" class="btn btn-primary mt-3">Request New Link</a>
                    </div>
                <?php endif; ?>
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
            // Password toggle
            const passwordToggles = document.querySelectorAll('.password-toggle');
            
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
                    // Toggle icon
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            });
            
            // Password strength meter
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('password-strength');
            const feedback = document.getElementById('password-feedback');
            
            if (passwordInput && strengthBar && feedback) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    let message = '';
                    
                    if (password.length >= 8) {
                        strength += 25;
                    }
                    
                    if (password.match(/[A-Z]/)) {
                        strength += 25;
                    }
                    
                    if (password.match(/[0-9]/)) {
                        strength += 25;
                    }
                    
                    if (password.match(/[^A-Za-z0-9]/)) {
                        strength += 25;
                    }
                    
                    // Update strength bar
                    strengthBar.style.width = strength + '%';
                    
                    // Set color based on strength
                    if (strength <= 25) {
                        strengthBar.style.backgroundColor = '#dc322f';
                        message = 'Weak password';
                    } else if (strength <= 50) {
                        strengthBar.style.backgroundColor = '#cb4b16';
                        message = 'Fair password';
                    } else if (strength <= 75) {
                        strengthBar.style.backgroundColor = '#b58900';
                        message = 'Good password';
                    } else {
                        strengthBar.style.backgroundColor = '#859900';
                        message = 'Strong password';
                    }
                    
                    feedback.textContent = message;
                    feedback.style.color = strengthBar.style.backgroundColor;
                });
            }
        });
    </script>
</body>
</html>

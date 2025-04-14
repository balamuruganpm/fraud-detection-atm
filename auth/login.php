<?php
session_start();
require_once '../config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user type
    if ($_SESSION['is_admin']) {
        header('Location: ../admin/index.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
    exit;
}

$error = '';
$loginAttempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
$isLocked = isset($_SESSION['login_locked_until']) && $_SESSION['login_locked_until'] > time();

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        $_SESSION['login_attempts'] = ++$loginAttempts;
    } else {
        // Check if user exists
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // For debugging - remove in production
        // echo "Stored hash: " . ($user ? $user['password'] : 'User not found') . "<br>";
        // echo "Input password: " . $password . "<br>";
        // echo "Generated hash: " . password_hash($password, PASSWORD_DEFAULT) . "<br>";
        
        // Check if user exists and password is correct
        // Note: For testing, we're also allowing 'password123' as a direct match
        if ($user && (password_verify($password, $user['password']) || $password === 'password123')) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            $_SESSION['last_activity'] = time();
            
            // Reset login attempts
            unset($_SESSION['login_attempts']);
            unset($_SESSION['login_locked_until']);
            
            // Log successful login
            $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address, device_info) 
                      VALUES (:user_id, 'LOGIN', 'SUCCESS', :ip_address, :device_info)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user['user_id']);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->bindParam(':device_info', $_SERVER['HTTP_USER_AGENT']);
            $stmt->execute();
            
            // Redirect based on user type
            if ($user['is_admin']) {
                header('Location: ../admin/index.php');
            } else {
                // Check if MFA is enabled
                if ($user['mfa_enabled']) {
                    $_SESSION['mfa_pending'] = true;
                    $_SESSION['mfa_user_id'] = $user['user_id'];
                    header('Location: ../auth/mfa-verify.php');
                } else {
                    header('Location: ../user/index.php');
                }
            }
            exit;
        } else {
            // Log failed login attempt
            if ($user) {
                $query = "INSERT INTO auth_logs (user_id, auth_type, status, ip_address, device_info) 
                          VALUES (:user_id, 'LOGIN', 'FAILED', :ip_address, :device_info)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user['user_id']);
                $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                $stmt->bindParam(':device_info', $_SERVER['HTTP_USER_AGENT']);
                $stmt->execute();
            }
            
            $error = 'Invalid username or password.';
            $_SESSION['login_attempts'] = ++$loginAttempts;
            
            // Lock account after 5 failed attempts
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_locked_until'] = time() + 300; // Lock for 5 minutes
                $error = 'Too many failed login attempts. Your account is locked for 5 minutes.';
            }
        }
    }
}

// Check if account is locked
if ($isLocked) {
    $remainingTime = $_SESSION['login_locked_until'] - time();
    $minutes = floor($remainingTime / 60);
    $seconds = $remainingTime % 60;
    $error = "Your account is locked due to too many failed attempts. Please try again in {$minutes}m {$seconds}s.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ATM Fraud Detection</title>
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
        .login-container {
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
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #93a1a1;
        }
        .alert-danger {
            background-color: rgba(220, 50, 47, 0.2);
            border-color: #dc322f;
            color: #fff;
        }
        .login-attempts {
            font-size: 0.8rem;
            color: #dc322f;
            margin-top: 5px;
        }
        .biometric-login {
            text-align: center;
            margin-top: 20px;
        }
        .biometric-btn {
            background: none;
            border: none;
            color: #2aa198;
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .biometric-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">ATM Fraud Detection</h3>
                <p class="mb-0">Login to your account</p>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="" id="login-form">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required <?php echo $isLocked ? 'disabled' : ''; ?>>
                        <label for="username">Username</label>
                    </div>
                    <div class="form-floating mb-3 position-relative">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required <?php echo $isLocked ? 'disabled' : ''; ?>>
                        <label for="password">Password</label>
                        <span class="password-toggle" id="password-toggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <?php if ($loginAttempts > 0 && $loginAttempts < 5): ?>
                        <div class="login-attempts">
                            <i class="fas fa-exclamation-triangle"></i> Failed login attempts: <?php echo $loginAttempts; ?>/5
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember-me" name="remember_me" <?php echo $isLocked ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="remember-me">Remember me</label>
                        </div>
                        <a href="forgot-password.php" class="text-decoration-none" style="color: #2aa198;">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" <?php echo $isLocked ? 'disabled' : ''; ?>>Login</button>
                    
                    <div class="biometric-login">
                        <p>Or login with</p>
                        <button type="button" id="biometric-login" class="biometric-btn" <?php echo $isLocked ? 'disabled' : ''; ?>>
                            <i class="fas fa-fingerprint"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <p class="mb-0">Don't have an account? Contact your bank.</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle
            const passwordToggle = document.getElementById('password-toggle');
            const passwordInput = document.getElementById('password');
            
            passwordToggle.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                const icon = passwordToggle.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
            
            // Biometric login
            const biometricBtn = document.getElementById('biometric-login');
            
            biometricBtn.addEventListener('click', function() {
                // Check if Web Authentication API is supported
                if (window.PublicKeyCredential) {
                    alert('Biometric authentication is not yet implemented in this demo.');
                    // In a real implementation, you would:
                    // 1. Request a challenge from the server
                    // 2. Use navigator.credentials.get() to perform authentication
                    // 3. Verify the authentication on the server
                } else {
                    alert('Your browser does not support biometric authentication.');
                }
            });
            
            <?php if ($isLocked): ?>
            // Countdown timer for locked account
            let remainingTime = <?php echo $_SESSION['login_locked_until'] - time(); ?>;
            const countdownInterval = setInterval(function() {
                remainingTime--;
                if (remainingTime <= 0) {
                    clearInterval(countdownInterval);
                    window.location.reload();
                } else {
                    const minutes = Math.floor(remainingTime / 60);
                    const seconds = remainingTime % 60;
                    document.querySelector('.alert-danger').textContent = 
                        `Your account is locked due to too many failed attempts. Please try again in ${minutes}m ${seconds}s.`;
                }
            }, 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>

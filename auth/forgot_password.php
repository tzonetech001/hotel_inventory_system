<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';
$email = '';
$phone = '';

// Step 1: Verify Email
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address!";
    } else {
        $sql = "SELECT id, fullname, username, phone, phone_verified FROM users WHERE email = ? AND status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_fullname'] = $user['fullname'];
            $_SESSION['reset_phone'] = $user['phone'];
            
            header("Location: forgot_password.php?step=2");
            exit();
        } else {
            $error = "Email address not found in our system!";
        }
    }
}

// Step 2: Verify Phone
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_phone'])) {
    $phone = trim($_POST['phone']);
    $expected_phone = $_SESSION['reset_phone'] ?? '';
    
    // Remove any formatting from phone number for comparison
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    $expected_clean = preg_replace('/[^0-9]/', '', $expected_phone);
    
    if (empty($phone)) {
        $error = "Please enter your phone number!";
    } elseif ($phone_clean !== $expected_clean) {
        $error = "Phone number does not match our records!";
    } else {
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ssi", $reset_token, $reset_expires, $_SESSION['reset_user_id']);
        $stmt->execute();
        
        $_SESSION['reset_token'] = $reset_token;
        
        header("Location: forgot_password.php?step=3&token=" . $reset_token);
        exit();
    }
}

// Step 3: Reset Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill all fields!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Verify token
        $sql = "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($stmt->execute()) {
                // Log the password reset
                logActivity($user['id'], 'Password Reset', 'Password reset via forgot password');
                
                // Clear reset session
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_fullname']);
                unset($_SESSION['reset_phone']);
                unset($_SESSION['reset_token']);
                
                $success = "Password reset successfully! You can now login with your new password.";
                
                // Redirect to login after 3 seconds
                header("refresh:3;url=login.php");
            } else {
                $error = "Error resetting password. Please try again!";
            }
        } else {
            $error = "Invalid or expired reset link! Please restart the process.";
        }
    }
}

// Check if token is valid for step 3
if ($step == 3) {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        header("Location: forgot_password.php");
        exit();
    }
    
    // Verify token
    $sql = "SELECT id, fullname FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        header("Location: forgot_password.php");
        exit();
    }
    
    $_SESSION['reset_fullname'] = $user['fullname'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reset-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h1 {
            color: #1E3A8A;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .reset-header p {
            color: #6B7280;
            font-size: 14px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #E5E7EB;
            z-index: 1;
        }
        
        .step {
            text-align: center;
            z-index: 2;
            background: white;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: #E5E7EB;
            color: #6B7280;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
        }
        
        .step.active .step-number {
            background: #1E3A8A;
            color: white;
        }
        
        .step.completed .step-number {
            background: #10B981;
            color: white;
        }
        
        .step-label {
            font-size: 12px;
            color: #6B7280;
        }
        
        .step.active .step-label {
            color: #1E3A8A;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #FF6B6B;
            box-shadow: 0 0 0 3px rgba(255,107,107,0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 40px;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9CA3AF;
        }
        
        .toggle-password:hover {
            color: #1E3A8A;
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #FF6B6B;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background: #e55a5a;
            transform: translateY(-2px);
        }
        
        .btn-back {
            width: 100%;
            padding: 12px;
            background: #F3F4F6;
            color: #374151;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-back:hover {
            background: #E5E7EB;
        }
        
        .error-message {
            background: #FEE2E2;
            color: #DC2626;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success-message {
            background: #D1FAE5;
            color: #065F46;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .info-box {
            background: #DBEAFE;
            border-left: 4px solid #1E3A8A;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1E40AF;
        }
        
        .info-box i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-key" style="font-size: 48px; color: #1E3A8A;"></i>
            <h1>Reset Password</h1>
            <p>Follow the steps to reset your password</p>
        </div>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-label">Email</div>
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">Phone</div>
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-label">New Password</div>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <a href="login.php" class="btn-submit" style="text-align: center; text-decoration: none; display: block;">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        <?php endif; ?>
        
        <!-- Step 1: Email Form -->
        <?php if($step == 1 && !$success): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" placeholder="Enter your registered email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <button type="submit" name="verify_email" class="btn-submit">
                <i class="fas fa-arrow-right"></i> Verify Email
            </button>
            
            <a href="login.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </form>
        <?php endif; ?>
        
        <!-- Step 2: Phone Verification -->
        <?php if($step == 2 && !$success): ?>
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            Please enter your registered phone number to verify your identity.
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" name="phone" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($phone); ?>" required>
                <small style="color: #6B7280; font-size: 12px;">Enter the phone number registered with your account</small>
            </div>
            
            <button type="submit" name="verify_phone" class="btn-submit">
                <i class="fas fa-check-circle"></i> Verify Phone
            </button>
            
            <a href="forgot_password.php?step=1" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </form>
        <?php endif; ?>
        
        <!-- Step 3: Reset Password -->
        <?php if($step == 3 && !$success): ?>
        <div class="info-box">
            <i class="fas fa-user-check"></i>
            Hello <strong><?php echo htmlspecialchars($_SESSION['reset_fullname'] ?? ''); ?></strong>, please enter your new password.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> New Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Enter new password" required>
                    <i class="fas fa-eye toggle-password" id="togglePassword1"></i>
                </div>
                <small style="color: #6B7280; font-size: 12px;">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                    <i class="fas fa-eye toggle-password" id="togglePassword2"></i>
                </div>
            </div>
            
            <button type="submit" name="reset_password" class="btn-submit">
                <i class="fas fa-save"></i> Reset Password
            </button>
            
            <a href="login.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Toggle password visibility for step 3
        const togglePassword1 = document.getElementById('togglePassword1');
        const togglePassword2 = document.getElementById('togglePassword2');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (togglePassword1) {
            togglePassword1.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
        
        if (togglePassword2) {
            togglePassword2.addEventListener('click', function() {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
    </script>
</body>
</html>
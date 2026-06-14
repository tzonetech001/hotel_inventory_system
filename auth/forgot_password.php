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
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } else {
        $user_found = false;
        
        // 1. Check department_users table
        $sql = "SELECT id, fullname, phone, 'department' as user_type FROM department_users WHERE email = ? AND status = 'active'";
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
            $_SESSION['reset_user_type'] = 'department';
            $user_found = true;
        }
        
        // 2. Check suppliers table
        if (!$user_found) {
            $sql = "SELECT id, company_name, contact_person, phone, 'supplier' as user_type FROM suppliers WHERE email = ? AND status = 'active'";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_fullname'] = $user['contact_person'] ?? $user['company_name'];
                $_SESSION['reset_phone'] = $user['phone'];
                $_SESSION['reset_user_type'] = 'supplier';
                $user_found = true;
            }
        }
        
        // 3. Check users table (staff)
        if (!$user_found) {
            $sql = "SELECT id, fullname, phone, 'staff' as user_type FROM users WHERE email = ? AND status = 'active'";
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
                $_SESSION['reset_user_type'] = 'staff';
                $user_found = true;
            }
        }
        
        if ($user_found) {
            header("Location: forgot_password.php?step=2");
            exit();
        } else {
            $error = "No account found with that email address!";
        }
    }
}

// Step 2: Verify Phone and Reset Password Directly
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $phone = trim($_POST['phone']);
    $expected_phone = $_SESSION['reset_phone'] ?? '';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Remove any formatting from phone number for comparison
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    $expected_clean = preg_replace('/[^0-9]/', '', $expected_phone);
    
    if (empty($phone)) {
        $error = "Please enter your phone number!";
    } elseif ($phone_clean !== $expected_clean) {
        $error = "Phone number does not match our records!";
    } elseif (empty($password) || empty($confirm_password)) {
        $error = "Please enter new password!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $user_type = $_SESSION['reset_user_type'] ?? 'staff';
        $user_id = $_SESSION['reset_user_id'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($user_type == 'staff') {
            $sql = "UPDATE users SET password = ? WHERE id = ?";
        } elseif ($user_type == 'supplier') {
            $sql = "UPDATE suppliers SET password = ? WHERE id = ?";
        } else {
            $sql = "UPDATE department_users SET password = ? WHERE id = ?";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Log the password reset
            if ($user_type == 'staff') {
                logActivity($user_id, 'Password Reset', 'Password reset via forgot password');
            } elseif ($user_type == 'supplier') {
                logActivity(0, 'Supplier Password Reset', "Password reset for supplier ID: {$user_id}", 'supplier');
            } else {
                logActivity(0, 'Department Password Reset', "Password reset for department user ID: {$user_id}", 'department');
            }
            
            // Clear session
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_fullname']);
            unset($_SESSION['reset_phone']);
            unset($_SESSION['reset_user_type']);
            
            $_SESSION['toast_message'] = "Password reset successfully! Please login with your new password.";
            $_SESSION['toast_type'] = "success";
            
            header("Location: login.php");
            exit();
        } else {
            $error = "Error resetting password. Please try again!";
        }
    }
}

// Check if we have user data for step 2
if ($step == 2 && empty($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
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
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 480px;
            padding: 40px;
            transition: all 0.3s;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .reset-header .logo-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px -5px rgba(30,58,138,0.3);
        }
        
        .reset-header .logo-icon i {
            font-size: 28px;
            color: white;
        }
        
        .reset-header h1 {
            color: #1E3A8A;
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .reset-header p {
            color: #6B7280;
            font-size: 14px;
        }
        
        /* Step Indicator */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 25%;
            right: 25%;
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
            transition: all 0.3s;
        }
        
        .step.active .step-number {
            background: #1E3A8A;
            color: white;
            box-shadow: 0 4px 10px rgba(30,58,138,0.3);
        }
        
        .step.completed .step-number {
            background: #10B981;
            color: white;
        }
        
        .step-label {
            font-size: 12px;
            color: #6B7280;
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: #1E3A8A;
            font-weight: 600;
        }
        
        .step.completed .step-label {
            color: #10B981;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #1E3A8A;
        }
        
        .required {
            color: #EF4444;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: #FF6B6B;
            box-shadow: 0 0 0 3px rgba(255,107,107,0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            width: 100%;
            padding: 14px 48px 14px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .password-wrapper input:focus {
            outline: none;
            border-color: #FF6B6B;
            box-shadow: 0 0 0 3px rgba(255,107,107,0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9CA3AF;
            transition: color 0.3s;
            font-size: 18px;
        }
        
        .toggle-password:hover {
            color: #1E3A8A;
        }
        
        /* Password Strength */
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 4px;
            background: #E5E7EB;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        
        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }
        
        .strength-text {
            font-size: 11px;
            color: #6B7280;
        }
        
        .strength-text.weak { color: #EF4444; }
        .strength-text.medium { color: #F59E0B; }
        .strength-text.strong { color: #10B981; }
        
        /* Match Status */
        .match-status {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .match-status.match {
            color: #10B981;
        }
        
        .match-status.not-match {
            color: #EF4444;
        }
        
        /* Buttons */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #FF6B6B;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }
        
        .btn-submit:hover {
            background: #e55a5a;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255,107,107,0.3);
        }
        
        .btn-back {
            width: 100%;
            padding: 14px;
            background: #F3F4F6;
            color: #374151;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 12px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-back:hover {
            background: #E5E7EB;
        }
        
        /* Messages */
        .error-message {
            background: #FEF2F2;
            color: #DC2626;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            border-left: 4px solid #DC2626;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box {
            background: #EFF6FF;
            border-left: 4px solid #1E3A8A;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #1E40AF;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box i {
            font-size: 18px;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .reset-container {
                padding: 28px 20px;
            }
            
            .step-label {
                font-size: 10px;
            }
            
            .step-number {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="logo-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1>Reset Password</h1>
            <p>Reset your password in two simple steps</p>
        </div>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-label">Email</div>
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">Reset</div>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Step 1: Identity Form (Email only) -->
        <?php if($step == 1): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="email" name="email" placeholder="Enter your registered email" 
                           value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                </div>
                <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 6px;">
                    <i class="fas fa-info-circle"></i> Enter the email address you used to register
                </small>
            </div>
            
            <button type="submit" name="verify_email" class="btn-submit">
                <i class="fas fa-arrow-right"></i> Continue
            </button>
            
            <a href="login.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </form>
        <?php endif; ?>
        
        <!-- Step 2: Verify Phone and Reset Password -->
        <?php if($step == 2): ?>
        <div class="info-box">
            <i class="fas fa-user-check"></i>
            <span>Hello <strong><?php echo htmlspecialchars($_SESSION['reset_fullname'] ?? ''); ?></strong>, please verify your phone and set new password.</span>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-phone-alt"></i> Phone Number <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="tel" name="phone" placeholder="Enter your registered phone number" 
                           value="<?php echo htmlspecialchars($phone); ?>" required autofocus>
                </div>
                <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 6px;">
                    <i class="fas fa-info-circle"></i> Enter the phone number linked to your account
                </small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> New Password <span class="required">*</span></label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Enter new password" required>
                    <i class="fas fa-eye toggle-password" id="togglePassword1"></i>
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-bar-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Enter a password</div>
                </div>
                <small style="color: #6B7280; font-size: 12px;">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirm Password <span class="required">*</span></label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                    <i class="fas fa-eye toggle-password" id="togglePassword2"></i>
                </div>
                <div class="match-status" id="matchStatus"></div>
            </div>
            
            <button type="submit" name="reset_password" class="btn-submit" id="resetBtn">
                <i class="fas fa-save"></i> Reset Password
            </button>
            
            <a href="forgot_password.php?step=1" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Toggle password visibility
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
        
        // Password strength checker
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        
        function checkPasswordStrength(pwd) {
            let strength = 0;
            
            if (pwd.length >= 6) strength++;
            if (pwd.length >= 10) strength++;
            if (pwd.match(/[a-z]/) && pwd.match(/[A-Z]/)) strength++;
            if (pwd.match(/[0-9]/)) strength++;
            if (pwd.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (pwd.length === 0) {
                strengthFill.style.width = '0%';
                strengthFill.style.background = '#E5E7EB';
                strengthText.textContent = 'Enter a password';
                strengthText.className = 'strength-text';
                return;
            }
            
            if (strength <= 2) {
                strengthFill.style.width = '33%';
                strengthFill.style.background = '#EF4444';
                strengthText.textContent = 'Weak password';
                strengthText.className = 'strength-text weak';
            } else if (strength <= 4) {
                strengthFill.style.width = '66%';
                strengthFill.style.background = '#F59E0B';
                strengthText.textContent = 'Medium password';
                strengthText.className = 'strength-text medium';
            } else {
                strengthFill.style.width = '100%';
                strengthFill.style.background = '#10B981';
                strengthText.textContent = 'Strong password!';
                strengthText.className = 'strength-text strong';
            }
        }
        
        if (password) {
            password.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });
        }
        
        // Password match checker
        const matchStatus = document.getElementById('matchStatus');
        
        function checkPasswordMatch() {
            if (!password || !confirmPassword) return;
            
            const pwd = password.value;
            const confirm = confirmPassword.value;
            
            if (confirm.length === 0) {
                matchStatus.innerHTML = '';
                return;
            }
            
            if (pwd === confirm) {
                matchStatus.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match!';
                matchStatus.className = 'match-status match';
            } else {
                matchStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
                matchStatus.className = 'match-status not-match';
            }
        }
        
        if (confirmPassword) {
            confirmPassword.addEventListener('input', checkPasswordMatch);
        }
        
        // Form submit loading state
        const resetForm = document.querySelector('form[method="POST"]');
        const resetBtn = document.getElementById('resetBtn');
        
        if (resetForm && resetBtn) {
            resetForm.addEventListener('submit', function(e) {
                if (password && confirmPassword) {
                    if (password.value !== confirmPassword.value) {
                        e.preventDefault();
                        matchStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
                        matchStatus.className = 'match-status not-match';
                        return;
                    }
                    
                    if (password.value.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters!');
                        return;
                    }
                }
                
                resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
                resetBtn.disabled = true;
            });
        }
        
        // Auto focus on first input
        const firstInput = document.querySelector('input[type="text"], input[type="email"], input[type="tel"]');
        if (firstInput) {
            firstInput.focus();
        }
    </script>
</body>
</html>
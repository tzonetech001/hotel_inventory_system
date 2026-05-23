<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Admin']);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$reset_id = intval($_GET['id'] ?? 0);

if ($reset_id <= 0) {
    header("Location: view_users.php");
    exit();
}

// Get user details
$sql = "SELECT fullname, username, email FROM users WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $reset_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: view_users.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password)) {
        $error = "Please enter a password!";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $reset_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Reset Password', "Reset password for user: {$user['username']}");
            
            // Set success message in session for toast notification
            $_SESSION['toast_message'] = "Password reset successfully for <strong>" . htmlspecialchars($user['fullname']) . "</strong>!";
            $_SESSION['toast_type'] = "success";
            
            // Redirect back to view_users.php
            header("Location: view_users.php");
            exit();
        } else {
            $error = "Error resetting password! Please try again.";
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-key"></i> Reset Password</h1>
        <p>Reset password for <strong><?php echo htmlspecialchars($user['fullname']); ?></strong> (<?php echo htmlspecialchars($user['username']); ?>)</p>
    </div>
    
    <?php if($error): ?>
        <script>showToast('<?php echo addslashes($error); ?>', 'error');</script>
    <?php endif; ?>
    
    <div class="card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-lock"></i> New Password</h3>
            <p class="card-subtitle">Set a strong password for this user</p>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="resetPasswordForm">
                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> User Information</label>
                    <div class="user-info-box">
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['fullname']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password *</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" required autocomplete="new-password">
                        <i class="fas fa-eye toggle-password" data-target="password"></i>
                    </div>
                    <small>Minimum 6 characters</small>
                    
                    <!-- Password Strength Indicator -->
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar"></div>
                        <div class="strength-text">Enter a password</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password *</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
                        <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                    </div>
                    <div class="match-status" id="matchStatus"></div>
                </div>
                
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="warning-content">
                        <strong>Important Notice:</strong>
                        <ul>
                            <li>This will change the user's password immediately</li>
                            <li>The user will need to use the new password to login</li>
                            <li>Make sure to share the new password securely with the user</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                    <a href="view_users.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Animations */
    .animate-card {
        animation: fadeInUp 0.5s ease;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Card Header */
    .card-header {
        border-bottom: 1px solid #E5E7EB;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .card-header h3 {
        margin-bottom: 5px;
        color: #1E3A8A;
    }
    
    .card-subtitle {
        font-size: 13px;
        color: #6B7280;
        margin: 0;
    }
    
    /* User Info Box */
    .user-info-box {
        background: #F9FAFB;
        border-radius: 10px;
        padding: 15px;
        border: 1px solid #E5E7EB;
    }
    
    .info-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        width: 100px;
        font-weight: 600;
        color: #374151;
    }
    
    .info-value {
        flex: 1;
        color: #1E3A8A;
        font-weight: 500;
    }
    
    /* Password Wrapper */
    .password-wrapper {
        position: relative;
    }
    
    .password-wrapper input {
        width: 100%;
        padding: 12px 40px 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 16px;
        transition: all 0.3s;
    }
    
    .password-wrapper input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
        z-index: 10;
    }
    
    .toggle-password:hover {
        color: #1E3A8A;
    }
    
    /* Password Strength Indicator */
    .password-strength {
        margin-top: 8px;
    }
    
    .strength-bar {
        height: 4px;
        background: #E5E7EB;
        border-radius: 4px;
        overflow: hidden;
        transition: all 0.3s;
    }
    
    .strength-bar::before {
        content: '';
        display: block;
        height: 100%;
        width: 0%;
        transition: width 0.3s, background 0.3s;
    }
    
    .strength-bar.weak::before {
        width: 33%;
        background: #EF4444;
    }
    
    .strength-bar.medium::before {
        width: 66%;
        background: #F59E0B;
    }
    
    .strength-bar.strong::before {
        width: 100%;
        background: #10B981;
    }
    
    .strength-text {
        font-size: 11px;
        margin-top: 5px;
        color: #6B7280;
    }
    
    .strength-text.weak {
        color: #EF4444;
    }
    
    .strength-text.medium {
        color: #F59E0B;
    }
    
    .strength-text.strong {
        color: #10B981;
    }
    
    /* Match Status */
    .match-status {
        font-size: 12px;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .match-status.match {
        color: #10B981;
    }
    
    .match-status.not-match {
        color: #EF4444;
    }
    
    /* Warning Box */
    .warning-box {
        background: #FEF3C7;
        border-left: 4px solid #F59E0B;
        padding: 15px;
        border-radius: 10px;
        margin: 20px 0;
        display: flex;
        gap: 12px;
    }
    
    .warning-box i {
        font-size: 20px;
        color: #F59E0B;
    }
    
    .warning-content {
        flex: 1;
    }
    
    .warning-content strong {
        display: block;
        margin-bottom: 8px;
        color: #92400E;
    }
    
    .warning-content ul {
        margin: 0;
        padding-left: 20px;
        color: #78350F;
        font-size: 13px;
    }
    
    .warning-content li {
        margin: 5px 0;
    }
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
    
    .btn-primary {
        background: #FF6B6B;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,107,107,0.3);
    }
    
    .btn-primary:active {
        transform: translateY(0);
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .info-row {
            flex-direction: column;
            padding: 10px 0;
        }
        
        .info-label {
            width: auto;
            margin-bottom: 5px;
        }
        
        .warning-box {
            flex-direction: column;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary, .btn-secondary {
            justify-content: center;
        }
    }
</style>

<script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });
    
    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    
    function checkPasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        if (password.length === 0) {
            strengthBar.className = 'strength-bar';
            strengthText.className = 'strength-text';
            strengthText.textContent = 'Enter a password';
            return;
        }
        
        if (strength <= 2) {
            strengthBar.className = 'strength-bar weak';
            strengthText.className = 'strength-text weak';
            strengthText.textContent = 'Weak password';
        } else if (strength <= 4) {
            strengthBar.className = 'strength-bar medium';
            strengthText.className = 'strength-text medium';
            strengthText.textContent = 'Medium password';
        } else {
            strengthBar.className = 'strength-bar strong';
            strengthText.className = 'strength-text strong';
            strengthText.textContent = 'Strong password!';
        }
    }
    
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        checkPasswordMatch();
    });
    
    // Password match checker
    const confirmInput = document.getElementById('confirm_password');
    const matchStatus = document.getElementById('matchStatus');
    
    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        
        if (confirm.length === 0) {
            matchStatus.innerHTML = '';
            return;
        }
        
        if (password === confirm) {
            matchStatus.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match!';
            matchStatus.className = 'match-status match';
        } else {
            matchStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
            matchStatus.className = 'match-status not-match';
        }
    }
    
    confirmInput.addEventListener('input', checkPasswordMatch);
    
    // Form submit loading state
    const form = document.getElementById('resetPasswordForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(e) {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        
        if (password !== confirm) {
            e.preventDefault();
            showToast('Passwords do not match!', 'error');
            return;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            showToast('Password must be at least 6 characters!', 'error');
            return;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting Password...';
        submitBtn.disabled = true;
    });
    
    // Auto focus on password field
    passwordInput.focus();
</script>

<?php include '../templates/footer.php'; ?>
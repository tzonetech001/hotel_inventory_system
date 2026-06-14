<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if (isset($_SESSION['user_id']) || isset($_SESSION['department_user_id'])) {
    header("Location: " . APP_URL . "/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login_input) || empty($password)) {
        $error = "Please enter your username/email and password!";
    } else {
        $logged_in = false;
        
        // ============================================
        // 1. Try DEPARTMENT USER login (by email only)
        // ============================================
        if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
            $sql = "SELECT du.*, d.department_name, d.department_code 
                    FROM department_users du
                    JOIN departments d ON du.department_id = d.id
                    WHERE du.email = ? AND du.status = 'active'";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("s", $login_input);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['department_user_id'] = $user['id'];
                    $_SESSION['department_user_name'] = $user['fullname'];
                    $_SESSION['department_id'] = $user['department_id'];
                    $_SESSION['department_name'] = $user['department_name'];
                    $_SESSION['department_code'] = $user['department_code'];
                    $_SESSION['department_email'] = $user['email'];
                    $_SESSION['user_type'] = 'department';
                    $_SESSION['role'] = $user['department_name'] . ' Department';
                    
                    logActivity(0, 'Department Login', "Department user {$user['fullname']} logged in", 'department');
                    
                    header("Location: " . APP_URL . "/department/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username/email or password!";
                    $logged_in = true;
                }
            }
        }
        
        // ============================================
        // 2. Try SUPPLIER login (by email only)
        // ============================================
        if (!$logged_in && filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
            $sql = "SELECT * FROM suppliers WHERE email = ? AND status = 'active'";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("s", $login_input);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($supplier = $result->fetch_assoc()) {
                if (password_verify($password, $supplier['password'])) {
                    $_SESSION['user_id'] = $supplier['id'];
                    $_SESSION['username'] = $supplier['company_name'];
                    $_SESSION['fullname'] = $supplier['contact_person'] ?? $supplier['company_name'];
                    $_SESSION['role'] = 'Supplier';
                    $_SESSION['email'] = $supplier['email'];
                    $_SESSION['phone'] = $supplier['phone'];
                    $_SESSION['user_type'] = 'supplier';
                    $_SESSION['supplier_id'] = $supplier['id'];
                    
                    logActivity(0, 'Supplier Login', "Supplier {$supplier['company_name']} logged in", 'supplier');
                    
                    header("Location: " . APP_URL . "/supplier/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username/email or password!";
                    $logged_in = true;
                }
            }
        }
        
        // ============================================
        // 3. Try STAFF login (Admin, Manager, Storekeeper, Procurement)
        //    Can use EITHER username OR email
        // ============================================
        if (!$logged_in) {
            // Check if input is email
            if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
                // Login by email
                $sql = "SELECT u.*, r.role_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.id 
                        WHERE u.email = ? AND u.status = 'active'";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("s", $login_input);
            } else {
                // Login by username
                $sql = "SELECT u.*, r.role_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.id 
                        WHERE u.username = ? AND u.status = 'active'";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("s", $login_input);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role'] = $user['role_name'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['user_type'] = 'staff';
                    
                    logActivity($user['id'], 'Login', 'Staff logged in successfully');
                    
                    header("Location: " . APP_URL . "/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username/email or password!";
                }
            } else {
                $error = "Invalid username/email or password!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo APP_NAME; ?> - Login</title>
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
        
        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .login-header .logo-icon {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .login-header .logo-icon i {
            font-size: 32px;
            color: white;
        }
        
        .login-header h1 {
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
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
        
        .btn-login {
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
        
        .btn-login:hover {
            background: #e55a5a;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255,107,107,0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: #1E3A8A;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .forgot-password a:hover {
            color: #FF6B6B;
            text-decoration: underline;
        }
        
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
        
        .error-message i {
            font-size: 18px;
        }
        
        .info-note {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
        }
        
        .info-note p {
            font-size: 12px;
            color: #9CA3AF;
            margin-bottom: 8px;
        }
        
        .info-note i {
            color: #1E3A8A;
            margin-right: 5px;
        }
        
        .role-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            justify-content: center;
        }
        
        .role-badge {
            font-size: 10px;
            padding: 3px 8px;
            background: #F3F4F6;
            border-radius: 20px;
            color: #6B7280;
        }
        
        .role-badge i {
            margin-right: 3px;
            font-size: 9px;
        }
        
        /* Loading state */
        .btn-login.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        @media (max-width: 480px) {
            .login-body {
                padding: 30px 20px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-icon">
                <i class="fas fa-hotel"></i>
            </div>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Hotel Inventory Management System</p>
        </div>
        
        <div class="login-body">
            <?php if($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Username or Email</label>
                    <div class="input-wrapper">
                        <input type="text" name="login_input" id="login_input" 
                               placeholder="Enter your username or email" 
                               value="<?php echo htmlspecialchars($_POST['login_input'] ?? ''); ?>"
                               autocomplete="off" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="forgot-password">
                <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
            </div>
         
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
        
        // Form submit loading state
        const form = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const loginInput = document.getElementById('login_input');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                if (loginInput.value.trim() === '' || password.value === '') {
                    e.preventDefault();
                    return;
                }
                
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
            });
        }
        
        // Auto focus on login input
        if (loginInput) {
            loginInput.focus();
        }
        
        // Allow Enter key to submit
        if (loginInput) {
            loginInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (form) form.submit();
                }
            });
        }
        
        if (password) {
            password.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (form) form.submit();
                }
            });
        }
    </script>
</body>
</html>
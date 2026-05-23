<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Admin']);

$user_id = $_SESSION['user_id'];
$error = '';

// Get roles
$roles_sql = "SELECT id, role_name FROM roles";
$roles_result = $db->query($roles_sql);
$roles = $roles_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role_id = intval($_POST['role_id']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($fullname) || empty($username) || empty($email) || empty($role_id)) {
        $error = "Please fill all required fields!";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if username exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username or email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (fullname, username, password, email, phone, role_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssssi", $fullname, $username, $hashed_password, $email, $phone, $role_id);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'Add User', "Added new user: $username ($fullname)");
                
                // Set success message in session for toast
                $_SESSION['toast_message'] = "User <strong>" . htmlspecialchars($fullname) . "</strong> added successfully!";
                $_SESSION['toast_type'] = "success";
                
                // Redirect to view users
                header("Location: view_users.php");
                exit();
            } else {
                $error = "Error adding user: " . $db->error;
            }
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Add New User</h1>
        <p>Create new system user with role-based access</p>
    </div>
    
    <?php if($error): ?>
        <script>showToast('<?php echo addslashes($error); ?>', 'error');</script>
    <?php endif; ?>
    
    <!-- Two Column Layout -->
    <div class="two-column-layout">
        <!-- Left Column: Form -->
        <div class="form-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> User Information</h3>
                    <p class="card-subtitle">Fill in the details below to create a new user</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="addUserForm">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                            <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" placeholder="Enter full name" required autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Username <span class="required">*</span></label>
                            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Enter username" required autocomplete="off">
                            <small>Username must be unique and will be used for login</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email <span class="required">*</span></label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="user@example.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone</label>
                                <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="Enter phone number">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="password" placeholder="Enter password" required>
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
                                <label><i class="fas fa-lock"></i> Confirm Password <span class="required">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>
                                    <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                                </div>
                                <div class="match-status" id="matchStatus"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-badge"></i> User Role <span class="required">*</span></label>
                            <select name="role_id" id="role_id" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo (($_POST['role_id'] ?? '') == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo $role['role_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Role determines what the user can access in the system</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Create User
                            </button>
                            <button type="reset" class="btn-secondary" id="resetBtn">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <a href="view_users.php" class="btn-outline">
                                <i class="fas fa-arrow-left"></i> Back to Users
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Info & Guide -->
        <div class="info-column">
            <div class="card info-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Role Guide</h3>
                </div>
                <div class="card-body">
                    <div class="role-guide">
                        <div class="role-item">
                            <div class="role-icon admin">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="role-info">
                                <h4>Admin</h4>
                                <p>Full system access, can manage users, view all reports, and configure system settings.</p>
                            </div>
                        </div>
                        
                        <div class="role-item">
                            <div class="role-icon manager">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="role-info">
                                <h4>Hotel Manager</h4>
                                <p>Can approve purchase orders, view reports, and manage suppliers.</p>
                            </div>
                        </div>
                        
                        <div class="role-item">
                            <div class="role-icon storekeeper">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="role-info">
                                <h4>Storekeeper</h4>
                                <p>Manages inventory, records stock in/out, and adds new items.</p>
                            </div>
                        </div>
                        
                        <div class="role-item">
                            <div class="role-icon procurement">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="role-info">
                                <h4>Procurement Officer</h4>
                                <p>Creates purchase orders, tracks deliveries, and manages suppliers.</p>
                            </div>
                        </div>
                        
                       
                </div>
            </div>
           
        </div>
    </div>
</div>

<style>
    /* Two Column Layout */
    .two-column-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 25px;
    }
    
    /* Animations */
    .animate-card {
        animation: fadeInUp 0.4s ease;
    }
    
    .animate-card-delayed {
        animation: fadeInUp 0.4s ease 0.1s both;
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
    
    /* Card Styles */
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: box-shadow 0.3s;
    }
    
    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .card-header {
        padding: 20px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .card-subtitle {
        margin: 5px 0 0;
        font-size: 13px;
        color: #6B7280;
    }
    
    .card-body {
        padding: 24px;
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }
    
    .required {
        color: #EF4444;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #6B7280;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    /* Password Wrapper */
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
        transition: color 0.3s;
    }
    
    .toggle-password:hover {
        color: #1E3A8A;
    }
    
    /* Password Strength */
    .password-strength {
        margin-top: 8px;
    }
    
    .strength-bar {
        height: 4px;
        background: #E5E7EB;
        border-radius: 4px;
        overflow: hidden;
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
    
    .strength-text.weak { color: #EF4444; }
    .strength-text.medium { color: #F59E0B; }
    .strength-text.strong { color: #10B981; }
    
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
    
    /* Form Actions Buttons */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
        flex-wrap: wrap;
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .btn-outline {
        background: transparent;
        border: 1px solid #1E3A8A;
        color: #1E3A8A;
        padding: 12px 24px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-outline:hover {
        background: #1E3A8A;
        color: white;
    }
    
    /* Role Guide */
    .role-guide {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    .role-item {
        display: flex;
        gap: 15px;
        padding: 12px;
        background: #F9FAFB;
        border-radius: 12px;
        transition: transform 0.2s;
    }
    
    .role-item:hover {
        transform: translateX(5px);
    }
    
    .role-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
    }
    
    .role-icon.admin { background: #1E3A8A; }
    .role-icon.manager { background: #7C3AED; }
    .role-icon.storekeeper { background: #059669; }
    .role-icon.procurement { background: #D97706; }
    .role-icon.supplier { background: #6B7280; }
    
    .role-info h4 {
        margin: 0 0 5px;
        font-size: 14px;
        color: #1F2937;
    }
    
    .role-info p {
        margin: 0;
        font-size: 12px;
        color: #6B7280;
        line-height: 1.4;
    }
    
    /* Tips List */
    .tips-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .tips-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
        color: #374151;
    }
    
    .tips-list li:last-child {
        border-bottom: none;
    }
    
    .tips-list li i {
        color: #10B981;
        font-size: 14px;
    }
    
    /* Responsive */
    @media (max-width: 900px) {
        .two-column-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary, .btn-secondary, .btn-outline {
            justify-content: center;
            width: 100%;
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
    const form = document.getElementById('addUserForm');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    
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
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating User...';
        submitBtn.disabled = true;
    });
    
    // Reset button
    resetBtn.addEventListener('click', function() {
        setTimeout(() => {
            passwordInput.value = '';
            confirmInput.value = '';
            checkPasswordStrength('');
            checkPasswordMatch();
        }, 100);
    });
    
    // Auto focus
    document.getElementById('fullname').focus();
</script>

<?php include '../templates/footer.php'; ?>
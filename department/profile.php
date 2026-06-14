<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if department user is logged in
if (!isset($_SESSION['department_user_id'])) {
    header("Location: login.php");
    exit();
}

$department_user_id = $_SESSION['department_user_id'];
$department_id = $_SESSION['department_id'];
$department_name = $_SESSION['department_name'];

// Get user data
$sql = "SELECT du.*, d.department_name, d.department_code 
        FROM department_users du
        JOIN departments d ON du.department_id = d.id
        WHERE du.id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $department_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Update Profile
    if (isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname']);
        $phone = trim($_POST['phone']);
        $position = trim($_POST['position']);
        
        if (empty($fullname) || empty($phone)) {
            $error = "Please fill in all required fields!";
        } else {
            $update_sql = "UPDATE department_users SET fullname = ?, phone = ?, position = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("sssi", $fullname, $phone, $position, $department_user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['department_user_name'] = $fullname;
                $success = "Profile updated successfully!";
                // Refresh user data
                $user['fullname'] = $fullname;
                $user['phone'] = $phone;
                $user['position'] = $position;
            } else {
                $error = "Error updating profile!";
            }
        }
    }
    
    // Change Password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all password fields!";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE department_users SET password = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $department_user_id);
                
                if ($update_stmt->execute()) {
                    $success = "Password changed successfully!";
                } else {
                    $error = "Error changing password!";
                }
            } else {
                $error = "Current password is incorrect!";
            }
        }
    }
}

// Get active tab from URL
$active_tab = isset($_GET['tab']) && $_GET['tab'] == 'security' ? 'security' : 'profile';

include 'header.php';
?>

<div class="profile-page">
    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> My Profile</h1>
        <p>Manage your personal information and account settings</p>
    </div>
    
    <?php if($success): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Profile Tabs -->
    <div class="profile-tabs">
        <a href="profile.php" class="profile-tab <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> Profile Information
        </a>
        <a href="profile.php?tab=security" class="profile-tab <?php echo $active_tab == 'security' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i> Security & Password
        </a>
    </div>
    
    <?php if($active_tab == 'profile'): ?>
        <!-- Profile Information Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Personal Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="profile-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small class="disabled-note">Email cannot be changed</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Employee ID</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['employee_id'] ?? 'Not set'); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['department_name']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Position/Title</label>
                            <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" placeholder="e.g., Department Head, Supervisor">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Sex</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['sex'] ?? 'Not specified'); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Last Login</label>
                            <input type="text" value="<?php echo $user['last_login'] ? date('d/m/Y H:i:s', strtotime($user['last_login'])) : 'Never'; ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Stats Card -->
        <div class="card stats-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Account Statistics</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid-mini">
                    <div class="stat-mini">
                        <div class="stat-mini-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-mini-info">
                            <span class="stat-mini-label">Member Since</span>
                            <span class="stat-mini-value"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-mini-info">
                            <span class="stat-mini-label">Account Status</span>
                            <span class="stat-mini-value status-badge <?php echo $user['status']; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Change Password Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="password-form">
                    <div class="form-group">
                        <label>Current Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="current_password" id="current_password" required>
                            <button type="button" class="toggle-password" data-target="current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="new_password" id="new_password" required>
                            <button type="button" class="toggle-password" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" required>
                            <button type="button" class="toggle-password" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="password-hints">
                        <p><i class="fas fa-shield-alt"></i> Password requirements:</p>
                        <ul>
                            <li>Minimum 6 characters</li>
                            <li>Use a mix of letters and numbers</li>
                            <li>Avoid common passwords</li>
                        </ul>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn-primary">
                            <i class="fas fa-lock"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
  
    <?php endif; ?>
</div>

<style>
    .profile-page {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .page-header {
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        color: #1E3A8A;
        margin-bottom: 5px;
        font-size: 28px;
    }
    
    .page-header p {
        color: #6B7280;
    }
    
    /* Alert Messages */
    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .alert.success {
        background: #D1FAE5;
        color: #065F46;
        border-left: 4px solid #10B981;
    }
    
    .alert.error {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 4px solid #EF4444;
    }
    
    /* Profile Tabs */
    .profile-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .profile-tab {
        padding: 12px 24px;
        background: white;
        border-radius: 12px;
        text-decoration: none;
        color: #6B7280;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .profile-tab:hover {
        background: #F3F4F6;
        color: #1E3A8A;
    }
    
    .profile-tab.active {
        background: #1E3A8A;
        color: white;
    }
    
    /* Cards */
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 25px;
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
    
    .card-body {
        padding: 24px;
    }
    
    /* Form Styles */
    .profile-form, .password-form {
        max-width: 100%;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 0;
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
    
    .form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .form-group input:disabled {
        background: #F9FAFB;
        color: #6B7280;
        cursor: not-allowed;
    }
    
    .disabled-note {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #9CA3AF;
    }
    
    /* Password Input with Toggle */
    .password-input-wrapper {
        position: relative;
    }
    
    .password-input-wrapper input {
        padding-right: 45px;
    }
    
    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #9CA3AF;
        font-size: 16px;
        padding: 5px;
    }
    
    .toggle-password:hover {
        color: #1E3A8A;
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #6B7280;
    }
    
    .password-hints {
        background: #FEF3C7;
        padding: 15px;
        border-radius: 10px;
        margin: 20px 0;
    }
    
    .password-hints p {
        margin-bottom: 8px;
        font-weight: 500;
        color: #92400E;
    }
    
    .password-hints ul {
        margin-left: 20px;
        color: #92400E;
        font-size: 13px;
    }
    
    .password-hints li {
        margin-bottom: 4px;
    }
    
    .tips-list {
        list-style: none;
        padding: 0;
    }
    
    .tips-list li {
        padding: 10px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid #F3F4F6;
    }
    
    .tips-list li:last-child {
        border-bottom: none;
    }
    
    .tips-list li i {
        color: #10B981;
    }
    
    .form-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
    
    .btn-primary {
        background: #FF6B6B;
        color: white;
        border: none;
        padding: 12px 28px;
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
    }
    
    /* Stats Grid Mini */
    .stats-grid-mini {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .stat-mini {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #F9FAFB;
        border-radius: 12px;
    }
    
    .stat-mini-icon {
        width: 45px;
        height: 45px;
        background: #EFF6FF;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1E3A8A;
        font-size: 20px;
    }
    
    .stat-mini-label {
        font-size: 11px;
        color: #6B7280;
        display: block;
    }
    
    .stat-mini-value {
        font-size: 16px;
        font-weight: 600;
        color: #1F2937;
        display: block;
    }
    
    .status-badge.active {
        background: #D1FAE5;
        color: #065F46;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        display: inline-block;
    }
    
    .status-badge.inactive {
        background: #FEE2E2;
        color: #991B1B;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        display: inline-block;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .profile-page {
            padding: 0;
        }
        
        .page-header h1 {
            font-size: 22px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .card-header {
            padding: 15px 18px;
        }
        
        .card-body {
            padding: 18px;
        }
        
        .profile-tabs {
            gap: 8px;
        }
        
        .profile-tab {
            padding: 10px 16px;
            font-size: 13px;
        }
        
        .stats-grid-mini {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .profile-tabs {
            flex-direction: column;
        }
        
        .profile-tab {
            justify-content: center;
        }
    }
</style>

<script>
    // Password visibility toggle
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        });
    }, 5000);
  // Mobile Sidebar Functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');
        
        function openSidebar() {
            mobileSidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            mobileSidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', openSidebar);
        }
        
        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', closeSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // Profile Dropdown
        const profileDropdownBtn = document.getElementById('profileDropdownBtn');
        const profileDropdownContent = document.getElementById('profileDropdownContent');
        
        if (profileDropdownBtn) {
            profileDropdownBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdownContent.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (profileDropdownBtn && profileDropdownContent) {
                    if (!profileDropdownBtn.contains(e.target) && !profileDropdownContent.contains(e.target)) {
                        profileDropdownContent.classList.remove('show');
                    }
                }
            });
        }
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });
        
        // Add active class to current nav item based on URL
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.department-nav a, .sidebar-menu a').forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage || (currentPage === '' && href === 'dashboard.php')) {
                link.classList.add('active');
            }
        });
    </script>

<?php include '../templates/footer.php'; ?>
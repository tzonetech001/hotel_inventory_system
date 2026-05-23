<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Check if user is logged in
checkAuth(); // All roles can access

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'profile';

// Get user details
$sql = "SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: " . APP_URL . "/dashboard.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    if (empty($fullname) || empty($email)) {
        $error = "Full name and email are required!";
    } else {
        // Check if email already exists for another user
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Email already exists for another user!";
        } else {
            $update_sql = "UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                
                logActivity($user_id, 'Update Profile', "Updated profile information");
                $_SESSION['toast_message'] = "Profile updated successfully!";
                $_SESSION['toast_type'] = "success";
                header("Location: profile.php?tab=profile");
                exit();
            } else {
                $error = "Error updating profile!";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill all password fields!";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Verify current password
        $pass_sql = "SELECT password FROM users WHERE id = ?";
        $pass_stmt = $db->prepare($pass_sql);
        $pass_stmt->bind_param("i", $user_id);
        $pass_stmt->execute();
        $pass_result = $pass_stmt->get_result();
        $user_data = $pass_result->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                logActivity($user_id, 'Change Password', "Changed password successfully");
                $_SESSION['toast_message'] = "Password changed successfully!";
                $_SESSION['toast_type'] = "success";
                header("Location: profile.php?tab=security");
                exit();
            } else {
                $error = "Error changing password!";
            }
        } else {
            $error = "Current password is incorrect!";
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $fileext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($fileext, $allowed)) {
            if ($_FILES['profile_picture']['size'] <= 5242880) { // 5MB
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $fileext;
                $upload_path = '../uploads/profile/' . $new_filename;
                
                // Create directory if not exists
                if (!is_dir('../uploads/profile')) {
                    mkdir('../uploads/profile', 0755, true);
                }
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old picture if exists
                    if ($user['profile_picture'] && file_exists('../uploads/profile/' . $user['profile_picture'])) {
                        unlink('../uploads/profile/' . $user['profile_picture']);
                    }
                    
                    $update_sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->bind_param("si", $new_filename, $user_id);
                    $update_stmt->execute();
                    
                    logActivity($user_id, 'Upload Picture', "Updated profile picture");
                    $_SESSION['toast_message'] = "Profile picture updated successfully!";
                    $_SESSION['toast_type'] = "success";
                    header("Location: profile.php?tab=profile");
                    exit();
                } else {
                    $error = "Error uploading file!";
                }
            } else {
                $error = "File too large! Maximum 5MB.";
            }
        } else {
            $error = "Invalid file type! Allowed: JPG, PNG, GIF";
        }
    } else {
        $error = "Please select a file to upload!";
    }
}

// Handle picture removal
if (isset($_GET['remove_picture'])) {
    if ($user['profile_picture'] && file_exists('../uploads/profile/' . $user['profile_picture'])) {
        unlink('../uploads/profile/' . $user['profile_picture']);
    }
    
    $update_sql = "UPDATE users SET profile_picture = NULL WHERE id = ?";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    
    logActivity($user_id, 'Remove Picture', "Removed profile picture");
    $_SESSION['toast_message'] = "Profile picture removed!";
    $_SESSION['toast_type'] = "success";
    header("Location: profile.php?tab=profile");
    exit();
}

// Get user activity logs
$activity_sql = "SELECT * FROM system_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$activity_stmt = $db->prepare($activity_sql);
$activity_stmt->bind_param("i", $user_id);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();
$activities = $activity_result->fetch_all(MYSQLI_ASSOC);

include 'header.php';
include 'sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> My Profile</h1>
        <p>Manage your account settings and preferences</p>
    </div>
    
    <?php if($error): ?>
        <script>showToast('<?php echo addslashes($error); ?>', 'error');</script>
    <?php endif; ?>
    
    <!-- Profile Tabs -->
    <div class="profile-tabs">
        <a href="?tab=profile" class="profile-tab <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> Profile Info
        </a>
        <a href="?tab=security" class="profile-tab <?php echo $active_tab == 'security' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i> Security
        </a>
        <a href="?tab=activity" class="profile-tab <?php echo $active_tab == 'activity' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Activity Log
        </a>
    </div>
    
    <!-- Profile Tab Content -->
    <?php if($active_tab == 'profile'): ?>
    <div class="two-column-layout">
        <!-- Left Column: Profile Picture -->
        <div class="profile-picture-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-camera"></i> Profile Picture</h3>
                </div>
                <div class="card-body text-center">
                    <div class="profile-avatar">
                        <?php if($user['profile_picture'] && file_exists('../uploads/profile/' . $user['profile_picture'])): ?>
                            <img src="<?php echo APP_URL; ?>/uploads/profile/<?php echo $user['profile_picture']; ?>" alt="Profile Picture" class="avatar-img">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user-circle"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-name">
                        <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                        <span class="role-badge"><?php echo $user['role_name']; ?></span>
                    </div>
                    
                    <div class="profile-actions">
                        <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
                            <label for="profile_picture" class="btn-secondary">
                                <i class="fas fa-upload"></i> Upload Photo
                            </label>
                            <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;" onchange="this.form.submit()">
                            <input type="hidden" name="upload_picture" value="1">
                        </form>
                        
                        <?php if($user['profile_picture']): ?>
                            <a href="?remove_picture=1" class="btn-outline" onclick="return confirm('Remove profile picture?')">
                                <i class="fas fa-trash"></i> Remove
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="upload-info">
                        <small><i class="fas fa-info-circle"></i> Allowed: JPG, PNG, GIF (Max 5MB)</small>
                    </div>
                </div>
            </div>
            
            <div class="card info-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-shield-alt"></i> Account Info</h3>
                </div>
                <div class="card-body">
                    <div class="account-details">
                        <div class="detail-item">
                            <span class="detail-label">User ID:</span>
                            <span class="detail-value">#<?php echo $user['id']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Username:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Role:</span>
                            <span class="detail-value"><?php echo $user['role_name']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Member Since:</span>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value status-badge <?php echo $user['status']; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Edit Profile Form -->
        <div class="profile-form-column">
            <div class="card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Edit Profile Information</h3>
                    <p class="card-subtitle">Update your personal information</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="profileForm">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled class="disabled-input">
                            <small>Username cannot be changed</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn-primary" id="updateProfileBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Security Tab Content -->
    <?php if($active_tab == 'security'): ?>
    <div class="two-column-layout">
        <!-- Left Column: Change Password -->
        <div class="password-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-key"></i> Change Password</h3>
                    <p class="card-subtitle">Update your password to keep your account secure</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="passwordForm">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Current Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="current_password" id="current_password" required>
                                <i class="fas fa-eye toggle-password" data-target="current_password"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="new_password" required>
                                <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                            </div>
                            <small>Minimum 6 characters</small>
                            
                            <!-- Password Strength Indicator -->
                            <div class="password-strength" id="passwordStrength">
                                <div class="strength-bar"></div>
                                <div class="strength-text">Enter a password</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm New Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" required>
                                <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                            </div>
                            <div class="match-status" id="matchStatus"></div>
                        </div>
                        
                        <div class="password-tips">
                            <h4><i class="fas fa-shield-alt"></i> Password Tips:</h4>
                            <ul>
                                <li>Use at least 6 characters</li>
                                <li>Mix uppercase and lowercase letters</li>
                                <li>Include numbers and special characters</li>
                                <li>Avoid using common words or personal info</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn-primary" id="changePasswordBtn">
                                <i class="fas fa-sync-alt"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Security Tips -->
        <div class="security-tips-column">
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Security Tips</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle"></i> Never share your password with anyone</li>
                        <li><i class="fas fa-check-circle"></i> Use a unique password for this system</li>
                        <li><i class="fas fa-check-circle"></i> Change your password regularly</li>
                        <li><i class="fas fa-check-circle"></i> Always log out when using shared computers</li>
                        <li><i class="fas fa-check-circle"></i> Contact admin if you suspect unauthorized access</li>
                    </ul>
                </div>
            </div>
            
            <div class="card session-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Session Info</h3>
                </div>
                <div class="card-body">
                    <div class="session-info">
                        <div class="session-item">
                            <i class="fas fa-calendar"></i>
                            <span>Last Login: <?php echo date('d M Y H:i', strtotime($user['created_at'] ?? 'now')); ?></span>
                        </div>
                        <div class="session-item">
                            <i class="fas fa-ip"></i>
                            <span>IP Address: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></span>
                        </div>
                        <div class="session-item">
                            <i class="fas fa-browser"></i>
                            <span>Browser: <?php echo $_SERVER['HTTP_USER_AGENT'] ? substr($_SERVER['HTTP_USER_AGENT'], 0, 50) : 'Unknown'; ?>...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Activity Log Tab Content -->
    <?php if($active_tab == 'activity'): ?>
    <div class="card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Your Activity Log</h3>
            <p class="card-subtitle">Recent actions and activities performed by you</p>
        </div>
        <div class="card-body">
            <?php if(count($activities) > 0): ?>
                <div class="activity-list">
                    <?php foreach($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                    $icon = 'fa-info-circle';
                                    if(strpos($activity['action'], 'Login') !== false) $icon = 'fa-sign-in-alt';
                                    elseif(strpos($activity['action'], 'Logout') !== false) $icon = 'fa-sign-out-alt';
                                    elseif(strpos($activity['action'], 'Add') !== false) $icon = 'fa-plus-circle';
                                    elseif(strpos($activity['action'], 'Delete') !== false) $icon = 'fa-trash';
                                    elseif(strpos($activity['action'], 'Update') !== false || strpos($activity['action'], 'Edit') !== false) $icon = 'fa-edit';
                                    elseif(strpos($activity['action'], 'Stock') !== false) $icon = 'fa-boxes';
                                    elseif(strpos($activity['action'], 'Password') !== false) $icon = 'fa-key';
                                    else $icon = 'fa-bell';
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                                <div class="activity-meta">
                                    <?php echo date('d M Y H:i:s', strtotime($activity['created_at'])); ?>
                                    <?php if($activity['ip_address']): ?>
                                        • IP: <?php echo $activity['ip_address']; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if($activity['details']): ?>
                                    <div class="activity-detail"><?php echo htmlspecialchars($activity['details']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No activity logs found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Two Column Layout */
    .two-column-layout {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 25px;
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
        border-radius: 10px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        transition: all 0.3s;
        border: 1px solid #E5E7EB;
    }
    
    .profile-tab i {
        margin-right: 8px;
    }
    
    .profile-tab:hover {
        background: #F3F4F6;
        border-color: #1E3A8A;
    }
    
    .profile-tab.active {
        background: #1E3A8A;
        color: white;
        border-color: #1E3A8A;
    }
    
    /* Profile Picture */
    .profile-picture-column, .profile-form-column {
        animation: fadeInUp 0.4s ease;
    }
    
    .profile-avatar {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .avatar-img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #1E3A8A;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .avatar-placeholder {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1E3A8A, #2563EB);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    
    .avatar-placeholder i {
        font-size: 80px;
        color: white;
    }
    
    .profile-name {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .profile-name h3 {
        margin: 0 0 8px;
        color: #1F2937;
    }
    
    .profile-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .upload-info {
        text-align: center;
        font-size: 11px;
        color: #6B7280;
    }
    
    /* Account Details */
    .account-details {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .detail-item:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        font-size: 13px;
        color: #6B7280;
    }
    
    .detail-value {
        font-size: 13px;
        font-weight: 600;
        color: #1F2937;
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-badge.active {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-badge.inactive {
        background: #FEE2E2;
        color: #991B1B;
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
    
    .disabled-input {
        background: #F9FAFB;
        cursor: not-allowed;
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
    
    /* Password Tips */
    .password-tips {
        background: #F0F9FF;
        border-radius: 10px;
        padding: 15px;
        margin: 20px 0;
    }
    
    .password-tips h4 {
        margin: 0 0 10px;
        color: #1E3A8A;
        font-size: 14px;
    }
    
    .password-tips ul {
        margin: 0;
        padding-left: 20px;
    }
    
    .password-tips li {
        margin: 5px 0;
        font-size: 13px;
        color: #374151;
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
        padding: 12px 0;
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
        width: 20px;
    }
    
    /* Session Info */
    .session-info {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .session-item {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        color: #374151;
    }
    
    .session-item i {
        width: 20px;
        color: #1E3A8A;
    }
    
    /* Activity List */
    .activity-list {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .activity-item {
        display: flex;
        gap: 15px;
        padding: 15px;
        border-bottom: 1px solid #E5E7EB;
        transition: background 0.2s;
    }
    
    .activity-item:hover {
        background: #F9FAFB;
    }
    
    .activity-icon {
        width: 40px;
        height: 40px;
        background: #F3F4F6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1E3A8A;
    }
    
    .activity-details {
        flex: 1;
    }
    
    .activity-action {
        font-weight: 600;
        color: #1F2937;
        margin-bottom: 4px;
    }
    
    .activity-meta {
        font-size: 11px;
        color: #9CA3AF;
        margin-bottom: 4px;
    }
    
    .activity-detail {
        font-size: 12px;
        color: #6B7280;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: #D1D5DB;
        margin-bottom: 15px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    /* Buttons */
    .btn-primary {
        background: #FF6B6B;
        color: white;
        padding: 12px 28px;
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
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .btn-outline {
        background: transparent;
        border: 1px solid #1E3A8A;
        color: #1E3A8A;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
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
    
    .form-actions {
        margin-top: 25px;
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
    
    /* Responsive */
    @media (max-width: 900px) {
        .two-column-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .profile-picture-column {
            order: 2;
        }
        
        .profile-form-column {
            order: 1;
        }
        
        .profile-tabs {
            justify-content: center;
        }
        
        .profile-tab {
            flex: 1;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .profile-tabs {
            flex-direction: column;
        }
        
        .detail-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        
        .activity-item {
            flex-direction: column;
        }
        
        .activity-icon {
            align-self: flex-start;
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
    
    // Password strength checker (for security tab)
    const newPasswordInput = document.getElementById('new_password');
    if (newPasswordInput) {
        const strengthBar = document.querySelector('#passwordStrength .strength-bar');
        const strengthText = document.querySelector('#passwordStrength .strength-text');
        
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
        
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
    }
    
    // Password match checker
    const confirmInput = document.getElementById('confirm_password');
    const matchStatus = document.getElementById('matchStatus');
    
    function checkPasswordMatch() {
        if (!newPasswordInput || !confirmInput) return;
        
        const password = newPasswordInput.value;
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
    
    if (confirmInput) {
        confirmInput.addEventListener('input', checkPasswordMatch);
    }
    
    // Form submit loading states
    const profileForm = document.getElementById('profileForm');
    const passwordForm = document.getElementById('passwordForm');
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    
    if (profileForm) {
        profileForm.addEventListener('submit', function() {
            if (updateProfileBtn) {
                updateProfileBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                updateProfileBtn.disabled = true;
            }
        });
    }
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password')?.value || '';
            const confirmPass = document.getElementById('confirm_password')?.value || '';
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                showToast('Passwords do not match!', 'error');
                return;
            }
            
            if (newPass.length < 6) {
                e.preventDefault();
                showToast('Password must be at least 6 characters!', 'error');
                return;
            }
            
            if (changePasswordBtn) {
                changePasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
                changePasswordBtn.disabled = true;
            }
        });
    }
    
    // Profile picture upload preview
    const profilePictureInput = document.getElementById('profile_picture');
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarImg = document.querySelector('.avatar-img');
                    if (avatarImg) {
                        avatarImg.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
</script>

<?php include 'footer.php'; ?>
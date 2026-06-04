<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Allow all authenticated users
checkAuth();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current user data
$sql = "SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: login.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update Profile Information
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        $errors = array();
        
        if (empty($fullname)) {
            $errors[] = "Full name is required";
        }
        if (empty($email)) {
            $errors[] = "Email is required";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (empty($errors)) {
            // Check if email exists for other users
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
                    logActivity($user_id, 'Update Profile', "Updated profile information");
                    $_SESSION['toast_message'] = "Profile updated successfully!";
                    $_SESSION['toast_type'] = "success";
                    header("Location: profile.php");
                    exit();
                } else {
                    $error = "Error updating profile: " . $db->error;
                }
            }
        } else {
            $error = implode(", ", $errors);
        }
    }
    
    // Change Password
    if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = array();
        
        if (empty($current_password)) {
            $errors[] = "Current password is required";
        }
        if (empty($new_password)) {
            $errors[] = "New password is required";
        }
        if (empty($confirm_password)) {
            $errors[] = "Please confirm your new password";
        }
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        if ($new_password != $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        if (empty($errors)) {
            if (!password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect!";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    logActivity($user_id, 'Change Password', "Changed account password");
                    $_SESSION['toast_message'] = "Password changed successfully! Please login again.";
                    $_SESSION['toast_type'] = "success";
                    
                    // Optional: Log out user after password change for security
                    // session_destroy();
                    // header("Location: login.php");
                    // exit();
                    
                    header("Location: profile.php");
                    exit();
                } else {
                    $error = "Error changing password: " . $db->error;
                }
            }
        } else {
            $error = implode(", ", $errors);
        }
    }
    
    // Upload Profile Picture
    if (isset($_POST['action']) && $_POST['action'] == 'upload_picture' && isset($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];
        
        if ($file['error'] == UPLOAD_ERR_NO_FILE) {
            $error = "Please select an image to upload.";
        } elseif ($file['error'] != UPLOAD_ERR_OK) {
            $error = "Error uploading file.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                $error = "Only JPG, PNG, GIF, and WEBP images are allowed.";
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = "File size must be less than 2MB.";
            } else {
                // Get the correct document root path
                $doc_root = $_SERVER['DOCUMENT_ROOT'];
                $upload_dir = $doc_root . '/hotel_inventory/uploads/profile_pictures/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = "user_{$user_id}_" . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Delete old profile picture
                if (!empty($user['profile_picture'])) {
                    $old_file = $upload_dir . $user['profile_picture'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $update_sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->bind_param("si", $filename, $user_id);
                    
                    if ($update_stmt->execute()) {
                        logActivity($user_id, 'Upload Profile Picture', "Updated profile picture");
                        $_SESSION['toast_message'] = "Profile picture updated successfully!";
                        $_SESSION['toast_type'] = "success";
                        header("Location: profile.php");
                        exit();
                    } else {
                        $error = "Error updating database: " . $db->error;
                    }
                } else {
                    $error = "Error saving uploaded file.";
                }
            }
        }
    }
    
    // Remove Profile Picture
    if (isset($_POST['action']) && $_POST['action'] == 'remove_picture') {
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        $upload_dir = $doc_root . '/hotel_inventory/uploads/profile_pictures/';
        
        if (!empty($user['profile_picture'])) {
            $old_file = $upload_dir . $user['profile_picture'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
        $update_sql = "UPDATE users SET profile_picture = NULL WHERE id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            logActivity($user_id, 'Remove Profile Picture', "Removed profile picture");
            $_SESSION['toast_message'] = "Profile picture removed successfully!";
            $_SESSION['toast_type'] = "success";
            header("Location: profile.php");
            exit();
        } else {
            $error = "Error removing profile picture: " . $db->error;
        }
    }
}

// Refresh user data after updates
$sql = "SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

include '../templates/header.php';
include '../templates/sidebar.php';

$profile_picture_path = !empty($user['profile_picture']) ? '/hotel_inventory/uploads/profile_pictures/' . $user['profile_picture'] : '';
$full_image_path = !empty($user['profile_picture']) ? $_SERVER['DOCUMENT_ROOT'] . '/hotel_inventory/uploads/profile_pictures/' . $user['profile_picture'] : '';
$has_profile_image = !empty($user['profile_picture']) && file_exists($full_image_path);
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> My Profile</h1>
        <p>View and manage your personal information</p>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <!-- Two Column Layout -->
    <div class="two-column-layout">
        <!-- Left Column: Profile Picture & Info -->
        <div class="profile-column">
            <!-- Profile Picture Card -->
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-camera"></i> Profile Picture</h3>
                </div>
                <div class="card-body text-center">
                    <div class="profile-picture-container">
                        <?php if ($has_profile_image): ?>
                            <img src="<?php echo $profile_picture_path; ?>?t=<?php echo time(); ?>" alt="Profile Picture" class="profile-picture" id="profileImage">
                        <?php else: ?>
                            <div class="profile-picture-placeholder">
                                <i class="fas fa-user-circle"></i>
                            </div>
                        <?php endif; ?>
                        <div class="profile-picture-overlay" onclick="document.getElementById('pictureInput').click()">
                            <i class="fas fa-camera"></i>
                            <span>Change</span>
                        </div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="pictureForm">
                        <input type="hidden" name="action" value="upload_picture">
                        <input type="file" name="profile_picture" id="pictureInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                    </form>
                    
                    <?php if ($has_profile_image): ?>
                        <form method="POST" action="" id="removePictureForm">
                            <input type="hidden" name="action" value="remove_picture">
                            <button type="submit" class="btn-outline-small" onclick="return confirm('Are you sure you want to remove your profile picture?')">
                                <i class="fas fa-trash"></i> Remove Picture
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="profile-info-basic">
                        <h2><?php echo htmlspecialchars($user['fullname']); ?></h2>
                        <span class="role-badge role-<?php echo strtolower(str_replace(' ', '-', $user['role_name'])); ?>">
                            <?php echo htmlspecialchars($user['role_name']); ?>
                        </span>
                        <p class="username-info">
                            <i class="fas fa-at"></i> @<?php echo htmlspecialchars($user['username']); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Account Info Card -->
            <div class="card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label">User ID:</span>
                            <span class="info-value">#<?php echo $user['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Account Created:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Updated:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Account Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                    <i class="fas <?php echo $user['status'] == 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Edit Forms -->
        <div class="forms-column">
            <!-- Edit Profile Form -->
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Edit Profile Information</h3>
                    <p class="card-subtitle">Update your personal details</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <small>Your email address is used for notifications</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                            <small>Format: 0712 345 678</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="saveProfileBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Change Password Form -->
            <div class="card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lock"></i> Change Password</h3>
                    <p class="card-subtitle">Update your password regularly for security</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Current Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="current_password" id="current_password" required>
                                <i class="fas fa-eye toggle-password" data-target="current_password"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> New Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="new_password" required>
                                <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                            </div>
                            <small>Password must be at least 6 characters</small>
                            
                            <div class="password-strength" id="passwordStrength" style="display: none;">
                                <div class="strength-bar"></div>
                                <div class="strength-text"></div>
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
                        
                        <div class="password-requirements">
                            <h4><i class="fas fa-shield-alt"></i> Password Requirements:</h4>
                            <ul>
                                <li id="req-length">✓ At least 6 characters</li>
                                <li id="req-number">✓ At least one number</li>
                                <li id="req-lowercase">✓ At least one lowercase letter</li>
                                <li id="req-uppercase">✓ At least one uppercase letter</li>
                                <li id="req-special">✓ At least one special character</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="changePasswordBtn">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Two Column Layout */
    .two-column-layout {
        display: grid;
        grid-template-columns: 360px 1fr;
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
    
    /* Alert Styles */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: fadeInUp 0.3s ease;
    }
    
    .alert-error {
        background: #FEF2F2;
        border: 1px solid #FEE2E2;
        color: #DC2626;
    }
    
    .alert-success {
        background: #ECFDF5;
        border: 1px solid #D1FAE5;
        color: #065F46;
    }
    
    /* Card Styles */
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: box-shadow 0.3s;
        margin-bottom: 25px;
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
    
    .text-center {
        text-align: center;
    }
    
    /* Profile Picture */
    .profile-picture-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 20px;
        border-radius: 50%;
        overflow: hidden;
        cursor: pointer;
    }
    
    .profile-picture {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-picture-placeholder {
        width: 100%;
        height: 100%;
        background: #F3F4F6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 80px;
        color: #9CA3AF;
    }
    
    .profile-picture-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0,0,0,0.6);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 8px;
        font-size: 12px;
        transform: translateY(100%);
        transition: transform 0.3s;
        cursor: pointer;
    }
    
    .profile-picture-container:hover .profile-picture-overlay {
        transform: translateY(0);
    }
    
    .profile-info-basic {
        text-align: center;
        margin-top: 15px;
    }
    
    .profile-info-basic h2 {
        margin: 0 0 8px;
        font-size: 20px;
        color: #1F2937;
    }
    
    .username-info {
        margin-top: 8px;
        font-size: 13px;
        color: #6B7280;
    }
    
    /* Role Badge */
    .role-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .role-admin { background: #1E3A8A; color: white; }
    .role-hotel-manager { background: #7C3AED; color: white; }
    .role-storekeeper { background: #059669; color: white; }
    .role-procurement-officer { background: #D97706; color: white; }
    
    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-active {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-inactive {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    /* Info List */
    .info-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #F3F4F6;
    }
    
    .info-item:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-size: 13px;
        color: #6B7280;
    }
    
    .info-value {
        font-size: 13px;
        font-weight: 500;
        color: #1F2937;
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
    
    .form-group small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #6B7280;
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
    
    /* Password Requirements */
    .password-requirements {
        background: #F9FAFB;
        border-radius: 10px;
        padding: 15px;
        margin: 15px 0;
    }
    
    .password-requirements h4 {
        margin: 0 0 10px;
        font-size: 13px;
        color: #374151;
    }
    
    .password-requirements ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .password-requirements li {
        font-size: 11px;
        color: #9CA3AF;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .password-requirements li.valid {
        color: #10B981;
    }
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
    
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
    
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-outline-small {
        background: transparent;
        border: 1px solid #DC2626;
        color: #DC2626;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
    }
    
    .btn-outline-small:hover {
        background: #FEE2E2;
    }
    
    /* Responsive */
    @media (max-width: 900px) {
        .two-column-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary {
            justify-content: center;
            width: 100%;
        }
        
        .info-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        
        .password-requirements ul {
            flex-direction: column;
            gap: 8px;
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
    
    // Profile picture upload
    const pictureInput = document.getElementById('pictureInput');
    if (pictureInput) {
        pictureInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('pictureForm').submit();
            }
        });
    }
    
    // Password strength checker
    const newPasswordInput = document.getElementById('new_password');
    const strengthContainer = document.getElementById('passwordStrength');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            updatePasswordRequirements(this.value);
            checkPasswordMatch();
        });
    }
    
    function checkPasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        if (password.length === 0) {
            strengthContainer.style.display = 'none';
            return;
        }
        
        strengthContainer.style.display = 'block';
        
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
    
    function updatePasswordRequirements(password) {
        const reqLength = document.getElementById('req-length');
        const reqNumber = document.getElementById('req-number');
        const reqLowercase = document.getElementById('req-lowercase');
        const reqUppercase = document.getElementById('req-uppercase');
        const reqSpecial = document.getElementById('req-special');
        
        if (reqLength) reqLength.classList.toggle('valid', password.length >= 6);
        if (reqNumber) reqNumber.classList.toggle('valid', /[0-9]/.test(password));
        if (reqLowercase) reqLowercase.classList.toggle('valid', /[a-z]/.test(password));
        if (reqUppercase) reqUppercase.classList.toggle('valid', /[A-Z]/.test(password));
        if (reqSpecial) reqSpecial.classList.toggle('valid', /[^a-zA-Z0-9]/.test(password));
    }
    
    // Password match checker
    const confirmInput = document.getElementById('confirm_password');
    const matchStatus = document.getElementById('matchStatus');
    
    if (confirmInput) {
        confirmInput.addEventListener('input', checkPasswordMatch);
    }
    
    function checkPasswordMatch() {
        const password = newPasswordInput ? newPasswordInput.value : '';
        const confirm = confirmInput ? confirmInput.value : '';
        
        if (confirm.length === 0) {
            if (matchStatus) matchStatus.innerHTML = '';
            return;
        }
        
        if (matchStatus) {
            if (password === confirm) {
                matchStatus.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match!';
                matchStatus.className = 'match-status match';
            } else {
                matchStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
                matchStatus.className = 'match-status not-match';
            }
        }
    }
    
    // Form submit loading states
    const profileForm = document.getElementById('profileForm');
    const passwordForm = document.getElementById('passwordForm');
    
    if (profileForm) {
        profileForm.addEventListener('submit', function() {
            const btn = document.getElementById('saveProfileBtn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                btn.disabled = true;
            }
        });
    }
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = newPasswordInput ? newPasswordInput.value : '';
            const confirm = confirmInput ? confirmInput.value : '';
            
            if (newPassword !== confirm) {
                e.preventDefault();
                showToast('Passwords do not match!', 'error');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                showToast('Password must be at least 6 characters!', 'error');
                return;
            }
            
            const btn = document.getElementById('changePasswordBtn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
                btn.disabled = true;
            }
        });
    }
</script>

<?php include '../templates/footer.php'; ?>
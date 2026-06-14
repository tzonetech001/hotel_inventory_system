<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Admin can access
checkAuth(['Admin','Hotel Manager']);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$user_id_param = intval($_GET['id'] ?? 0);

if ($user_id_param <= 0) {
    header("Location: view_department_users.php");
    exit();
}

// Get user details
$sql = "SELECT du.*, d.department_name 
        FROM department_users du 
        JOIN departments d ON du.department_id = d.id 
        WHERE du.id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $user_id_param);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: view_department_users.php");
    exit();
}

// Get departments for dropdown
$departments_sql = "SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name";
$departments_result = $db->query($departments_sql);
$departments = $departments_result->fetch_all(MYSQLI_ASSOC);

// Get recent activity for this user
$activity_sql = "SELECT * FROM system_logs 
                 WHERE details LIKE ? 
                 ORDER BY created_at DESC LIMIT 10";
$search_pattern = "%{$user['fullname']}%";
$activity_stmt = $db->prepare($activity_sql);
$activity_stmt->bind_param("s", $search_pattern);
$activity_stmt->execute();
$activities = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $sex = $_POST['sex'] ?? '';
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department_id = intval($_POST['department_id']);
    $employee_id = !empty(trim($_POST['employee_id'])) ? trim($_POST['employee_id']) : null;
    $position = !empty(trim($_POST['position'])) ? trim($_POST['position']) : null;
    $status = $_POST['status'];
    $reset_password = isset($_POST['reset_password']) && $_POST['reset_password'] == '1';
    
    // Validation
    if (empty($fullname)) {
        $error = "Full name is required!";
    } elseif (empty($email)) {
        $error = "Email address is required!";
    } elseif (empty($phone)) {
        $error = "Phone number is required!";
    } elseif (empty($department_id)) {
        $error = "Please select a department!";
    } else {
        // Check if email already exists for other users
        $check_sql = "SELECT id FROM department_users WHERE email = ? AND id != ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id_param);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Email already registered to another user!";
        } else {
            // Check if phone already exists for other users
            $check_sql = "SELECT id FROM department_users WHERE phone = ? AND id != ?";
            $check_stmt = $db->prepare($check_sql);
            $check_stmt->bind_param("si", $phone, $user_id_param);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "Phone number already registered to another user!";
            } else {
                // Check if employee ID already exists (only if not null)
                if (!empty($employee_id)) {
                    $check_sql = "SELECT id FROM department_users WHERE employee_id = ? AND id != ?";
                    $check_stmt = $db->prepare($check_sql);
                    $check_stmt->bind_param("si", $employee_id, $user_id_param);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) {
                        $error = "Employee ID already exists!";
                    }
                }
                
                if (empty($error)) {
                    // Update user information
                    $sql = "UPDATE department_users SET 
                            fullname = ?, sex = ?, email = ?, phone = ?, 
                            department_id = ?, employee_id = ?, position = ?, status = ?
                            WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("ssssisssi", 
                        $fullname, $sex, $email, $phone, 
                        $department_id, $employee_id, $position, $status, $user_id_param
                    );
                    
                    if ($stmt->execute()) {
                        // Reset password if requested
                        $password_message = "";
                        if ($reset_password) {
                            $default_password = '123456';
                            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                            $pass_sql = "UPDATE department_users SET password = ? WHERE id = ?";
                            $pass_stmt = $db->prepare($pass_sql);
                            $pass_stmt->bind_param("si", $hashed_password, $user_id_param);
                            if ($pass_stmt->execute()) {
                                $password_message = " Password has been reset to <strong>$default_password</strong>.";
                                logActivity($user_id, 'Reset Department User Password', "Reset password for user: {$user['fullname']}");
                            }
                        }
                        
                        logActivity($user_id, 'Edit Department User', "Updated user: $fullname");
                        
                        $_SESSION['toast_message'] = "User <strong>" . htmlspecialchars($fullname) . "</strong> updated successfully!" . $password_message;
                        $_SESSION['toast_type'] = "success";
                        
                        header("Location: view_department_users.php");
                        exit();
                    } else {
                        $error = "Error updating user: " . $db->error;
                    }
                }
            }
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-edit"></i> Edit Department User</h1>
            <p>Update user information and manage account settings</p>
        </div>
        <div class="header-actions">
            <a href="view_department_users.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>
    
    <div class="two-column-layout">
        <!-- Left Column: Edit Form -->
        <div class="form-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-circle"></i> User Information</h3>
                    <p class="card-subtitle">Edit the details of <?php echo htmlspecialchars($user['fullname']); ?></p>
                </div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="editUserForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-venus-mars"></i> Sex</label>
                                <select name="sex">
                                    <option value="">Select</option>
                                    <option value="Male" <?php echo $user['sex'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $user['sex'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $user['sex'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number <span class="required">*</span></label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Department <span class="required">*</span></label>
                                <select name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo $user['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i> Employee ID</label>
                                <input type="text" name="employee_id" value="<?php echo htmlspecialchars($user['employee_id'] ?? ''); ?>" placeholder="Optional - Staff ID">
                                <small>Leave empty if not applicable</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-briefcase"></i> Position/Title</label>
                                <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" placeholder="e.g., Department Head, Supervisor">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-toggle-on"></i> Status</label>
                                <select name="status">
                                    <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <small>Inactive users cannot login</small>
                            </div>
                        </div>
                        
                        <div class="form-row password-reset-row">
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> Password Options</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="reset_password" id="reset_password" value="1">
                                    <label for="reset_password">
                                        <i class="fas fa-sync-alt"></i> Reset password to default (123456)
                                    </label>
                                </div>
                                <small>User can change password after first login</small>
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <div class="info-content">
                                <strong>Note:</strong> 
                                <ul>
                                    <li>Department users can login using their email and password</li>
                                    <li>They can view and confirm stock out requests for their department</li>
                                    <li>They can scan QR codes to confirm receipt of items</li>
                                    <li>Setting status to "Inactive" will prevent login</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Update User
                            </button>
                            <button type="reset" class="btn-secondary" id="resetBtn">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <a href="view_department_users.php" class="btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: User Info & Activity -->
        <div class="info-column">
            <!-- User Stats Card -->
            <div class="card stats-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> User Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="stat-item">
                        <span class="stat-label">User ID:</span>
                        <span class="stat-value">#<?php echo $user['id']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Registered On:</span>
                        <span class="stat-value"><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Last Updated:</span>
                        <span class="stat-value"><?php echo date('d M Y, H:i', strtotime($user['updated_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Department:</span>
                        <span class="stat-value dept-badge">
                            <i class="fas <?php echo getDepartmentIcon($user['department_name']); ?>"></i>
                            <?php echo htmlspecialchars($user['department_name']); ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Status:</span>
                        <span class="stat-value">
                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                <i class="fas <?php echo $user['status'] == 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Card -->
            <div class="card activity-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                <div class="card-body">
                    <?php if(count($activities) > 0): ?>
                        <div class="activity-list">
                            <?php foreach($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                                        <div class="activity-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('d M Y, H:i', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-small">
                            <i class="fas fa-inbox"></i>
                            <p>No recent activity found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions Card -->
            <div class="card actions-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <button onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')" class="action-link">
                            <i class="fas fa-power-off"></i>
                            <?php echo $user['status'] == 'active' ? 'Deactivate User' : 'Activate User'; ?>
                        </button>
                        <button onclick="resetUserPassword(<?php echo $user['id']; ?>)" class="action-link">
                            <i class="fas fa-key"></i>
                            Reset Password to Default
                        </button>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['fullname']); ?>')" class="action-link delete">
                            <i class="fas fa-trash"></i>
                            Delete User Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .two-column-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 25px;
    }
    
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
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 20px;
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
    
    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-header h1 {
        font-size: 24px;
        color: #1E3A8A;
        margin: 0;
    }
    
    .page-header p {
        margin: 5px 0 0;
        color: #6B7280;
    }
    
    .header-actions .btn-outline {
        background: transparent;
        border: 1px solid #1E3A8A;
        color: #1E3A8A;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    
    .header-actions .btn-outline:hover {
        background: #1E3A8A;
        color: white;
    }
    
    /* Form Styles */
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
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        background: #FEF3C7;
        border-radius: 10px;
        border: 1px solid #FDE68A;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .checkbox-group label {
        margin: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        color: #92400E;
    }
    
    .checkbox-group label i {
        color: #F59E0B;
    }
    
    .alert {
        padding: 12px 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert.error {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 4px solid #EF4444;
    }
    
    .info-box {
        background: #DBEAFE;
        border-left: 4px solid #1E3A8A;
        padding: 15px;
        border-radius: 10px;
        margin: 20px 0;
        display: flex;
        gap: 12px;
    }
    
    .info-box i {
        font-size: 20px;
        color: #1E3A8A;
    }
    
    .info-content {
        flex: 1;
        font-size: 13px;
        color: #1E40AF;
    }
    
    .info-content ul {
        margin: 8px 0 0 20px;
        padding: 0;
    }
    
    .info-content li {
        margin: 3px 0;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
    
    .btn-primary, .btn-secondary, .btn-outline {
        padding: 12px 24px;
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
    
    .btn-primary {
        background: #FF6B6B;
        color: white;
        border: none;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
        border: none;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .btn-outline {
        background: transparent;
        border: 1px solid #1E3A8A;
        color: #1E3A8A;
    }
    
    .btn-outline:hover {
        background: #1E3A8A;
        color: white;
    }
    
    /* Stats Card */
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .stat-item:last-child {
        border-bottom: none;
    }
    
    .stat-label {
        font-size: 13px;
        color: #6B7280;
    }
    
    .stat-value {
        font-weight: 600;
        color: #374151;
    }
    
    .dept-badge {
        background: #E0E7FF;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
    
    /* Activity List */
    .activity-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .activity-item {
        display: flex;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 32px;
        height: 32px;
        background: #DBEAFE;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1E3A8A;
    }
    
    .activity-details {
        flex: 1;
    }
    
    .activity-action {
        font-size: 13px;
        font-weight: 500;
        color: #374151;
    }
    
    .activity-time {
        font-size: 11px;
        color: #9CA3AF;
        margin-top: 4px;
    }
    
    .activity-time i {
        margin-right: 3px;
    }
    
    /* Quick Actions */
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .action-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        background: #F3F4F6;
        border: none;
        border-radius: 10px;
        width: 100%;
        text-align: left;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
        color: #374151;
    }
    
    .action-link:hover {
        background: #E5E7EB;
        transform: translateX(5px);
    }
    
    .action-link.delete {
        color: #EF4444;
    }
    
    .action-link.delete:hover {
        background: #FEE2E2;
    }
    
    .action-link i {
        width: 20px;
    }
    
    .empty-small {
        text-align: center;
        padding: 30px 20px;
    }
    
    .empty-small i {
        font-size: 36px;
        color: #D1D5DB;
        margin-bottom: 10px;
    }
    
    .empty-small p {
        color: #6B7280;
        font-size: 13px;
    }
    
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
        
        .page-header {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<script>
    // Form submission
    const form = document.getElementById('editUserForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(e) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;
    });
    
    // Reset button
    document.getElementById('resetBtn').addEventListener('click', function() {
        setTimeout(() => {
            // Refresh the page to original values
            location.reload();
        }, 100);
    });
    
    // Quick Actions
    function toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const action = newStatus === 'active' ? 'activate' : 'deactivate';
        
        if (confirm(`Are you sure you want to ${action} this user?`)) {
            window.location.href = `view_department_users.php?toggle=${userId}`;
        }
    }
    
    function resetUserPassword(userId) {
        if (confirm('Reset password to default (123456)? The user will need to change it after first login.')) {
            window.location.href = `edit_department_user.php?id=${userId}&reset_pass=1`;
        }
    }
    
    function deleteUser(userId, userName) {
        if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone!`)) {
            window.location.href = `view_department_users.php?delete=${userId}`;
        }
    }
    
    // Handle password reset from URL parameter
    <?php if(isset($_GET['reset_pass'])): ?>
    window.addEventListener('load', function() {
        const resetCheckbox = document.getElementById('reset_password');
        if (resetCheckbox) {
            resetCheckbox.checked = true;
            resetCheckbox.dispatchEvent(new Event('change'));
        }
    });
    <?php endif; ?>
</script>

<?php
// Helper function for department icons
function getDepartmentIcon($department) {
    switch($department) {
        case 'Kitchen': return 'fa-utensils';
        case 'Housekeeping': return 'fa-broom';
        case 'Laundry': return 'fa-tshirt';
        case 'Front Office': return 'fa-hotel';
        case 'Maintenance': return 'fa-wrench';
        case 'Restaurant': return 'fa-utensil-spoon';
        case 'Bar': return 'fa-cocktail';
        case 'Store': return 'fa-warehouse';
        default: return 'fa-building';
    }
}
?>

<?php include '../templates/footer.php'; ?>
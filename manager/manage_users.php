<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Hotel Manager can access
checkAuth(['Hotel Manager']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'storekeepers';

// Define allowed roles for manager to manage
$allowed_roles = ['Storekeeper', 'Procurement Officer', 'Supplier'];
$role_ids = [
    'Storekeeper' => 3,
    'Procurement Officer' => 4,
    'Supplier' => 5
];

// ============================================
// HANDLE ADD USER
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role_name = $_POST['role_name'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $role_id = $role_ids[$role_name] ?? 0;
    
    if (empty($fullname) || empty($username) || empty($email) || $role_id == 0) {
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
                logActivity($user_id, 'Add User', "Added new $role_name: $username ($fullname)");
                $_SESSION['toast_message'] = "$role_name added successfully!";
                $_SESSION['toast_type'] = "success";
                header("Location: manage_users.php?tab=" . strtolower(str_replace(' ', '_', $role_name)) . "s");
                exit();
            } else {
                $error = "Error adding user: " . $db->error;
            }
        }
    }
}

// ============================================
// HANDLE EDIT USER
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $edit_id = intval($_POST['user_id']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role_name = $_POST['role_name'];
    $status = $_POST['status'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $role_id = $role_ids[$role_name] ?? 0;
    
    if (empty($fullname) || empty($email) || $role_id == 0) {
        $error = "Please fill all required fields!";
    } elseif (!empty($password) && $password != $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?, role_id = ?, status = ?, password = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssissi", $fullname, $email, $phone, $role_id, $status, $hashed_password, $edit_id);
        } else {
            $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?, role_id = ?, status = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssisi", $fullname, $email, $phone, $role_id, $status, $edit_id);
        }
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Edit User', "Updated user ID: $edit_id");
            $_SESSION['toast_message'] = "User updated successfully!";
            $_SESSION['toast_type'] = "success";
            header("Location: manage_users.php?tab=" . strtolower(str_replace(' ', '_', $role_name)) . "s");
            exit();
        } else {
            $error = "Error updating user!";
        }
    }
}

// ============================================
// HANDLE DELETE USER
// ============================================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $tab = $_GET['tab'] ?? 'storekeepers';
    
    // Get user details before deleting
    $user_sql = "SELECT username, role_id FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_sql);
    $user_stmt->bind_param("i", $delete_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $deleted_user = $user_result->fetch_assoc();
    
    if ($deleted_user) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Delete User', "Deleted user: {$deleted_user['username']}");
            $_SESSION['toast_message'] = "User deleted successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error deleting user!";
            $_SESSION['toast_type'] = "error";
        }
    }
    header("Location: manage_users.php?tab=$tab");
    exit();
}

// ============================================
// HANDLE TOGGLE STATUS
// ============================================
if (isset($_GET['toggle'])) {
    $toggle_id = intval($_GET['toggle']);
    $tab = $_GET['tab'] ?? 'storekeepers';
    
    $sql = "SELECT status FROM users WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $toggle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_status = $result->fetch_assoc();
    
    if ($user_status) {
        $new_status = ($user_status['status'] == 'active') ? 'inactive' : 'active';
        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $new_status, $toggle_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "User status updated!";
            $_SESSION['toast_type'] = "success";
        }
    }
    header("Location: manage_users.php?tab=$tab");
    exit();
}

// ============================================
// HANDLE RESET PASSWORD
// ============================================
if (isset($_GET['reset_password'])) {
    $reset_id = intval($_GET['reset_password']);
    $tab = $_GET['tab'] ?? 'storekeepers';
    
    // Generate random password
    $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $reset_id);
    
    if ($stmt->execute()) {
        // Get user details
        $user_sql = "SELECT username, email FROM users WHERE id = ?";
        $user_stmt = $db->prepare($user_sql);
        $user_stmt->bind_param("i", $reset_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        
        logActivity($user_id, 'Reset Password', "Reset password for user: {$user_data['username']}");
        $_SESSION['toast_message'] = "Password reset successfully! New password: <strong>$new_password</strong>";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error resetting password!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: manage_users.php?tab=$tab");
    exit();
}

// ============================================
// GET USERS BY ROLE
// ============================================
function getUsersByRole($db, $role_id) {
    $sql = "SELECT u.*, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.role_id = ? 
            ORDER BY u.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

$storekeepers = getUsersByRole($db, 3);
$procurement_officers = getUsersByRole($db, 4);
$suppliers_list = getUsersByRole($db, 5);

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $sql = "SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-users-gear"></i> User Management</h1>
        <p>Manage storekeepers, procurement officers, and suppliers</p>
    </div>
    
    <?php if($error): ?>
        <script>showToast('<?php echo addslashes($error); ?>', 'error');</script>
    <?php endif; ?>
    
    <!-- User Management Tabs -->
    <div class="user-tabs">
        <a href="?tab=storekeepers" class="user-tab <?php echo $active_tab == 'storekeepers' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i> Storekeepers
            <span class="badge"><?php echo count($storekeepers); ?></span>
        </a>
        <a href="?tab=procurement_officers" class="user-tab <?php echo $active_tab == 'procurement_officers' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Procurement Officers
            <span class="badge"><?php echo count($procurement_officers); ?></span>
        </a>
        <a href="?tab=suppliers" class="user-tab <?php echo $active_tab == 'suppliers' ? 'active' : ''; ?>">
            <i class="fas fa-truck"></i> Suppliers
            <span class="badge"><?php echo count($suppliers_list); ?></span>
        </a>
    </div>
    
    <!-- Add User Card -->
    <div class="card add-user-card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> 
                <?php 
                    if ($active_tab == 'storekeepers') echo 'Add New Storekeeper';
                    elseif ($active_tab == 'procurement_officers') echo 'Add New Procurement Officer';
                    else echo 'Add New Supplier';
                ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="add-user-form" id="addUserForm">
                <input type="hidden" name="add_user" value="1">
                <input type="hidden" name="role_name" value="<?php 
                    if ($active_tab == 'storekeepers') echo 'Storekeeper';
                    elseif ($active_tab == 'procurement_officers') echo 'Procurement Officer';
                    else echo 'Supplier';
                ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" name="fullname" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Username *</label>
                        <input type="text" name="username" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" name="phone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password *</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="add_password" required>
                            <i class="fas fa-eye toggle-password" data-target="add_password"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm Password *</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="add_confirm_password" required>
                            <i class="fas fa-eye toggle-password" data-target="add_confirm_password"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users List -->
    <div class="card users-list-card animate-card-delayed">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> 
                <?php 
                    if ($active_tab == 'storekeepers') echo 'Storekeepers List';
                    elseif ($active_tab == 'procurement_officers') echo 'Procurement Officers List';
                    else echo 'Suppliers List';
                ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            $users_list = [];
                            if ($active_tab == 'storekeepers') $users_list = $storekeepers;
                            elseif ($active_tab == 'procurement_officers') $users_list = $procurement_officers;
                            else $users_list = $suppliers_list;
                        ?>
                        
                        <?php if(count($users_list) > 0): ?>
                            <?php foreach($users_list as $user): ?>
                                <tr class="user-row">
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="user-name-cell">
                                            <div class="user-avatar-sm">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <i class="fas <?php echo $user['status'] == 'active' ? 'fa-circle' : 'fa-circle'; ?>"></i>
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="btn-icon edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?reset_password=<?php echo $user['id']; ?>&tab=<?php echo $active_tab; ?>" class="btn-icon key" title="Reset Password" onclick="return confirm('Reset password for <?php echo addslashes($user['fullname']); ?>? New password will be shown.')">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            <a href="?toggle=<?php echo $user['id']; ?>&tab=<?php echo $active_tab; ?>" class="btn-icon toggle" title="Toggle Status">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            <a href="?delete=<?php echo $user['id']; ?>&tab=<?php echo $active_tab; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete <?php echo addslashes($user['fullname']); ?>? This cannot be undone!')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <p>No users found in this category</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit User</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="role_name" id="edit_role_name">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="fullname" id="edit_fullname" required>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="edit_username" disabled class="disabled-input">
                    <small>Username cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="edit_phone">
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>New Password <span class="optional">(optional)</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="edit_password">
                        <i class="fas fa-eye toggle-password" data-target="edit_password"></i>
                    </div>
                    <small>Leave blank to keep current password</small>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="edit_confirm_password">
                        <i class="fas fa-eye toggle-password" data-target="edit_confirm_password"></i>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* User Tabs */
    .user-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .user-tab {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: white;
        border-radius: 12px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        transition: all 0.3s;
        border: 1px solid #E5E7EB;
    }
    
    .user-tab i {
        font-size: 16px;
    }
    
    .user-tab .badge {
        background: #F3F4F6;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 12px;
        color: #6B7280;
    }
    
    .user-tab:hover, .user-tab.active {
        background: #1E3A8A;
        color: white;
        border-color: #1E3A8A;
    }
    
    .user-tab:hover .badge, .user-tab.active .badge {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    /* Add User Card */
    .add-user-card {
        margin-bottom: 25px;
    }
    
    .add-user-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    /* User Name Cell */
    .user-name-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .user-avatar-sm {
        width: 32px;
        height: 32px;
        background: #F3F4F6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1E3A8A;
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
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
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        background: #F3F4F6;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s;
        color: #6B7280;
        border: none;
        cursor: pointer;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
    }
    
    .btn-icon.edit:hover { background: #DBEAFE; color: #1E3A8A; }
    .btn-icon.key:hover { background: #FEF3C7; color: #D97706; }
    .btn-icon.toggle:hover { background: #FEE2E2; color: #EF4444; }
    .btn-icon.delete:hover { background: #FEE2E2; color: #DC2626; }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 500px;
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .modal-header .close {
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
    }
    
    .modal-header .close:hover {
        color: #EF4444;
    }
    
    .modal-body {
        padding: 24px;
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
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #9CA3AF;
    }
    
    .toggle-password:hover {
        color: #1E3A8A;
    }
    
    .disabled-input {
        background: #F9FAFB;
        cursor: not-allowed;
    }
    
    .optional {
        font-weight: normal;
        font-size: 11px;
        color: #6B7280;
    }
    
    .text-center {
        text-align: center;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: #D1D5DB;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #6B7280;
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
    @media (max-width: 768px) {
        .user-tabs {
            flex-direction: column;
        }
        
        .user-tab {
            justify-content: center;
        }
        
        .add-user-form .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .modal-content {
            width: 95%;
            margin: 20px;
        }
    }
</style>

<script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            }
        });
    });
    
    // Edit User Function
    function editUser(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_fullname').value = user.fullname;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_status').value = user.status;
        document.getElementById('edit_role_name').value = user.role_name;
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_confirm_password').value = '';
        
        document.getElementById('editUserModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }
    
    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('editUserModal');
        if (event.target === modal) {
            closeEditModal();
        }
    }
    
    // Form submit loading states
    document.getElementById('addUserForm')?.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;
    });
    
    document.getElementById('editUserForm')?.addEventListener('submit', function(e) {
        const password = document.getElementById('edit_password').value;
        const confirm = document.getElementById('edit_confirm_password').value;
        
        if (password !== confirm) {
            e.preventDefault();
            showToast('Passwords do not match!', 'error');
            return;
        }
        
        if (password.length > 0 && password.length < 6) {
            e.preventDefault();
            showToast('Password must be at least 6 characters!', 'error');
            return;
        }
        
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;
    });
</script>

<?php include '../templates/footer.php'; ?>
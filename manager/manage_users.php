<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Hotel Manager can access
checkAuth(['Hotel Manager']);

$user_id = $_SESSION['user_id'];
$error = '';
$active_tab = $_GET['tab'] ?? 'storekeepers';
$search = $_GET['search'] ?? '';

// Define allowed roles for manager to manage
$role_ids = [
    'Storekeeper' => 3,
    'Procurement Officer' => 4,
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
        $_SESSION['toast_message'] = "Please fill all required fields!";
        $_SESSION['toast_type'] = "error";
    } elseif ($password != $confirm_password) {
        $_SESSION['toast_message'] = "Passwords do not match!";
        $_SESSION['toast_type'] = "error";
    } elseif (strlen($password) < 6) {
        $_SESSION['toast_message'] = "Password must be at least 6 characters!";
        $_SESSION['toast_type'] = "error";
    } else {
        // Check if username exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['toast_message'] = "Username or email already exists!";
            $_SESSION['toast_type'] = "error";
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
                $_SESSION['toast_message'] = "Error adding user: " . $db->error;
                $_SESSION['toast_type'] = "error";
            }
        }
    }
    header("Location: manage_users.php?tab=$active_tab");
    exit();
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
        $_SESSION['toast_message'] = "Please fill all required fields!";
        $_SESSION['toast_type'] = "error";
    } elseif (!empty($password) && $password != $confirm_password) {
        $_SESSION['toast_message'] = "Passwords do not match!";
        $_SESSION['toast_type'] = "error";
    } elseif (!empty($password) && strlen($password) < 6) {
        $_SESSION['toast_message'] = "Password must be at least 6 characters!";
        $_SESSION['toast_type'] = "error";
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
            $_SESSION['toast_message'] = "Error updating user!";
            $_SESSION['toast_type'] = "error";
        }
    }
    header("Location: manage_users.php?tab=$active_tab");
    exit();
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
        } else {
            $_SESSION['toast_message'] = "Error updating status!";
            $_SESSION['toast_type'] = "error";
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
        $user_sql = "SELECT username, fullname FROM users WHERE id = ?";
        $user_stmt = $db->prepare($user_sql);
        $user_stmt->bind_param("i", $reset_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        
        logActivity($user_id, 'Reset Password', "Reset password for user: {$user_data['username']}");
        $_SESSION['toast_message'] = "Password reset for <strong>" . htmlspecialchars($user_data['fullname']) . "</strong>! New password: <code>$new_password</code>";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error resetting password!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: manage_users.php?tab=$tab");
    exit();
}

// ============================================
// GET USERS BY ROLE WITH SEARCH
// ============================================
function getUsersByRole($db, $role_id, $search = '') {
    $sql = "SELECT u.*, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.role_id = ?";
    
    if (!empty($search)) {
        $search = $db->real_escape_string($search);
        $sql .= " AND (u.fullname LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

$storekeepers = getUsersByRole($db, 3, $search);
$procurement_officers = getUsersByRole($db, 4, $search);

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
        <p>Manage storekeepers and procurement officers</p>
    </div>
    
    <!-- User Management Tabs -->
    <div class="user-tabs">
        <a href="?tab=storekeepers&search=<?php echo urlencode($search); ?>" class="user-tab <?php echo $active_tab == 'storekeepers' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i> Storekeepers
            <span class="badge"><?php echo count($storekeepers); ?></span>
        </a>
        <a href="?tab=procurement_officers&search=<?php echo urlencode($search); ?>" class="user-tab <?php echo $active_tab == 'procurement_officers' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Procurement Officers
            <span class="badge"><?php echo count($procurement_officers); ?></span>
        </a>
    </div>
    
    <!-- Search Bar -->
    <div class="search-bar">
        <form method="GET" action="" class="search-form">
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name, username or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if(!empty($search)): ?>
                <a href="?tab=<?php echo $active_tab; ?>" class="btn-clear">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
            <button type="submit" class="btn-search">
                <i class="fas fa-filter"></i> Search
            </button>
        </form>
    </div>
    
    <!-- Add User Card -->
    <div class="card add-user-card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> 
                <?php 
                    if ($active_tab == 'storekeepers') echo 'Add New Storekeeper';
                    else echo 'Add New Procurement Officer';
                ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="add-user-form" id="addUserForm">
                <input type="hidden" name="add_user" value="1">
                <input type="hidden" name="role_name" value="<?php 
                    if ($active_tab == 'storekeepers') echo 'Storekeeper';
                    else echo 'Procurement Officer';
                ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                        <input type="text" name="fullname" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Username <span class="required">*</span></label>
                        <input type="text" name="username" placeholder="Enter username" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="user@example.com" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" name="phone" placeholder="Enter phone number">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="add_password" required>
                            <i class="fas fa-eye toggle-password" data-target="add_password"></i>
                        </div>
                        <small>Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="add_confirm_password" required>
                            <i class="fas fa-eye toggle-password" data-target="add_confirm_password"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="addSubmitBtn">
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
                    else echo 'Procurement Officers List';
                ?>
            </h3>
            <div class="card-header-info">
                Showing <?php 
                    if ($active_tab == 'storekeepers') echo count($storekeepers);
                    else echo count($procurement_officers);
                ?> user(s)
            </div>
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
                            else $users_list = $procurement_officers;
                        ?>
                        
                        <?php if(count($users_list) > 0): ?>
                            <?php foreach($users_list as $index => $user): ?>
                                <tr class="user-row" style="animation-delay: <?php echo $index * 0.03; ?>s">
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
                                            <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="btn-icon edit" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?reset_password=<?php echo $user['id']; ?>&tab=<?php echo $active_tab; ?>" class="btn-icon key" title="Reset Password" onclick="return confirm('Reset password for <?php echo addslashes($user['fullname']); ?>?')">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            <a href="?toggle=<?php echo $user['id']; ?>&tab=<?php echo $active_tab; ?>" class="btn-icon toggle" title="Toggle Status" onclick="return confirm('Change status for <?php echo addslashes($user['fullname']); ?>?')">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            <a href="?delete=<?php echo $user['id']; ?>&tab=<?php echo $active_tab; ?>" class="btn-icon delete" title="Delete User" onclick="return confirm('Delete <?php echo addslashes($user['fullname']); ?>? This cannot be undone!')">
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
                                        <p>No users found<?php echo !empty($search) ? ' matching "' . htmlspecialchars($search) . '"' : ' in this category'; ?></p>
                                        <?php if(!empty($search)): ?>
                                            <a href="?tab=<?php echo $active_tab; ?>" class="btn-secondary" style="margin-top: 10px;">Clear Search</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Note about Suppliers -->
    <div class="info-note">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Note:</strong> Suppliers are managed separately in the 
            <a href="suppliers.php">Suppliers Management</a> page.
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
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="fullname" id="edit_fullname" required>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="edit_username" disabled class="disabled-input">
                    <small>Username cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="edit_phone" placeholder="Enter phone number">
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
                    <button type="submit" class="btn-primary" id="editSubmitBtn">
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
        padding: 12px 28px;
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
    
    /* Search Bar */
    .search-bar {
        background: white;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .search-form {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .search-box {
        position: relative;
        flex: 1;
        min-width: 250px;
    }
    
    .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
    }
    
    .search-box input {
        width: 100%;
        padding: 10px 12px 10px 38px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .search-box input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .btn-search {
        background: #1E3A8A;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-search:hover {
        background: #2563EB;
    }
    
    .btn-clear {
        background: #F3F4F6;
        color: #374151;
        padding: 10px 16px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
    /* Card Styles */
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
        margin-bottom: 25px;
    }
    
    .card-header {
        padding: 18px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 16px;
        color: #1E3A8A;
    }
    
    .card-header-info {
        font-size: 13px;
        color: #6B7280;
    }
    
    .card-body {
        padding: 24px;
    }
    
    /* Add User Form */
    .add-user-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
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
    
    .optional {
        font-weight: normal;
        font-size: 11px;
        color: #6B7280;
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
        transition: color 0.3s;
    }
    
    .toggle-password:hover {
        color: #1E3A8A;
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
        transform: translateY(-1px);
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
    
    /* Table Styles */
    .table-responsive {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table thead {
        background: #F9FAFB;
    }
    
    .data-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 14px;
        vertical-align: middle;
    }
    
    .user-row {
        transition: background 0.2s;
        animation: fadeInRow 0.3s ease backwards;
    }
    
    @keyframes fadeInRow {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .user-row:hover {
        background: #F9FAFB;
    }
    
    /* User Name Cell */
    .user-name-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .user-avatar-sm {
        width: 35px;
        height: 35px;
        background: #F3F4F6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1E3A8A;
        font-size: 18px;
    }
    
    /* Status Badges */
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
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 6px;
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
    
    /* Empty State */
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
    
    /* Info Note */
    .info-note {
        background: #F0F9FF;
        border-left: 4px solid #1E3A8A;
        padding: 12px 20px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        margin-top: 20px;
    }
    
    .info-note i {
        font-size: 18px;
        color: #1E3A8A;
    }
    
    .info-note a {
        color: #1E3A8A;
        font-weight: 600;
        text-decoration: none;
    }
    
    .info-note a:hover {
        text-decoration: underline;
    }
    
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
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
        animation: modalSlideIn 0.3s ease;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #F9FAFB;
        border-radius: 20px 20px 0 0;
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
    
    .disabled-input {
        background: #F9FAFB;
        cursor: not-allowed;
    }
    
    .text-center {
        text-align: center;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .user-tabs {
            flex-direction: column;
        }
        
        .user-tab {
            justify-content: center;
        }
        
        .search-form {
            flex-direction: column;
        }
        
        .search-box {
            width: 100%;
        }
        
        .btn-search, .btn-clear {
            width: 100%;
            text-align: center;
            justify-content: center;
        }
        
        .add-user-form .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary, .btn-secondary {
            justify-content: center;
            width: 100%;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .data-table th, 
        .data-table td {
            padding: 10px 8px;
            font-size: 12px;
        }
        
        .modal-content {
            width: 95%;
            margin: 20px;
        }
        
        .info-note {
            flex-direction: column;
            text-align: center;
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
    const addForm = document.getElementById('addUserForm');
    const addSubmitBtn = document.getElementById('addSubmitBtn');
    
    if (addForm) {
        addForm.addEventListener('submit', function() {
            addSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            addSubmitBtn.disabled = true;
        });
    }
    
    const editForm = document.getElementById('editUserForm');
    const editSubmitBtn = document.getElementById('editSubmitBtn');
    
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
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
            
            editSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            editSubmitBtn.disabled = true;
        });
    }
    
    // Animate rows
    document.querySelectorAll('.user-row').forEach((row, index) => {
        row.style.animationDelay = `${index * 0.03}s`;
    });
</script>

<?php include '../templates/footer.php'; ?>
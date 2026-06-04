<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Admin']);

$user_id = $_SESSION['user_id'];

// Handle user status toggle
if (isset($_GET['toggle'])) {
    $toggle_id = intval($_GET['toggle']);
    $sql = "SELECT status FROM users WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $toggle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $new_status = ($user['status'] == 'active') ? 'inactive' : 'active';
    $sql = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $new_status, $toggle_id);
    
    if ($stmt->execute()) {
        logActivity($user_id, 'Toggle User', "Changed user ID $toggle_id status to $new_status");
        $_SESSION['toast_message'] = "User status updated successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error updating user status!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: view_users.php");
    exit();
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    if ($delete_id != $user_id) {
        $user_sql = "SELECT username FROM users WHERE id = ?";
        $user_stmt = $db->prepare($user_sql);
        $user_stmt->bind_param("i", $delete_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $deleted_user = $user_result->fetch_assoc();
        
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Delete User', "Deleted user: {$deleted_user['username']} (ID: $delete_id)");
            $_SESSION['toast_message'] = "User deleted successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error deleting user!";
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $_SESSION['toast_message'] = "You cannot delete your own account!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: view_users.php");
    exit();
}

// Search functionality
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE 1=1";

if (!empty($search)) {
    $search = $db->real_escape_string($search);
    $sql .= " AND (u.fullname LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
}

if (!empty($role_filter)) {
    $role_filter = $db->real_escape_string($role_filter);
    $sql .= " AND r.role_name = '$role_filter'";
}

$sql .= " ORDER BY u.created_at DESC";
$result = $db->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);

$roles_sql = "SELECT DISTINCT role_name FROM roles";
$roles_result = $db->query($roles_sql);
$roles_list = $roles_result->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Manage Users</h1>
        <p>View, edit, and manage system users</p>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar animate-slideDown">
        <form method="GET" action="" class="search-form">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name, username or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-box">
                <select name="role" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <?php foreach($roles_list as $role): ?>
                        <option value="<?php echo $role['role_name']; ?>" <?php echo $role_filter == $role['role_name'] ? 'selected' : ''; ?>>
                            <?php echo $role['role_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if(!empty($search) || !empty($role_filter)): ?>
                <a href="view_users.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
            <button type="submit" class="btn-search">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
        
        <a href="add_user.php" class="btn-clear">
            <i class="fas fa-plus"></i> Add User
        </a>
    </div>
    
    <!-- Users Table -->
    <div class="card animate-fadeIn">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> System Users (<?php echo count($users); ?>)</h3>
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
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $user): ?>
                                <tr class="user-row" data-id="<?php echo $user['id']; ?>">
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="user-name-cell">
                                            <div class="user-avatar-sm">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                                                <div class="user-meta-sm"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower(str_replace(' ', '-', $user['role_name'])); ?>">
                                            <?php echo $user['role_name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <i class="fas <?php echo $user['status'] == 'active' ? 'fa-circle' : 'fa-circle'; ?>"></i>
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-icon edit" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="reset_password.php?id=<?php echo $user['id']; ?>" class="btn-icon key" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            <a href="?toggle=<?php echo $user['id']; ?>" class="btn-icon toggle" title="Toggle Status">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?php echo $user['id']; ?>" class="btn-icon delete" title="Delete User" onclick="return confirmDelete('<?php echo addslashes($user['fullname']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <p>No users found</p>
                                        <a href="add_user.php" class="btn-primary" style="margin-top: 10px;">Add First User</a>
                                    </div>
                                </td
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .search-filter-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .search-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        flex: 1;
    }
    
    .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
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
        padding: 10px 12px 10px 35px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .search-box input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 2px rgba(30,58,138,0.1);
    }
    
    .filter-box select {
        padding: 10px 12px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        background: white;
        font-size: 14px;
        cursor: pointer;
    }
    
    .btn-search {
        padding: 10px 20px;
        background: #1E3A8A;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-search:hover {
        background: #2563EB;
    }
    
    .btn-clear {
        padding: 10px 16px;
        background: #F3F4F6;
        color: #374151;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
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
    
    .user-meta-sm {
        font-size: 11px;
        color: #9CA3AF;
        margin-top: 2px;
    }
    
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
    .role-supplier { background: #6B7280; color: white; }
    
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
    
    .status-active i {
        font-size: 8px;
        color: #10B981;
    }
    
    .status-inactive {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .status-inactive i {
        font-size: 8px;
        color: #EF4444;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
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
    }
    
    .btn-icon:hover { transform: translateY(-2px); }
    .btn-icon.edit:hover { background: #DBEAFE; color: #1E3A8A; }
    .btn-icon.key:hover { background: #FEF3C7; color: #D97706; }
    .btn-icon.toggle:hover { background: #FEE2E2; color: #EF4444; }
    .btn-icon.delete:hover { background: #FEE2E2; color: #DC2626; }
    
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
    
    .animate-slideDown {
        animation: slideDown 0.4s ease;
    }
    
    .animate-fadeIn {
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .user-row {
        transition: background 0.2s;
        animation: fadeInUp 0.3s ease forwards;
        opacity: 0;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .user-row:hover {
        background: #F9FAFB;
    }
    
    .text-center {
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .search-filter-bar {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-form {
            flex-direction: column;
        }
        
        .search-box, .filter-box select, .btn-search, .btn-clear {
            width: 100%;
        }
        
        .action-buttons {
            flex-wrap: wrap;
        }
    }
</style>

<script>
function confirmDelete(username) {
    return confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone!`);
}

// Animate rows
document.querySelectorAll('.user-row').forEach((row, index) => {
    row.style.animationDelay = `${index * 0.03}s`;
});
</script>

<?php include '../templates/footer.php'; ?>
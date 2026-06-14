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

// Handle user status toggle
if (isset($_GET['toggle'])) {
    $toggle_id = intval($_GET['toggle']);
    $sql = "SELECT status, fullname FROM department_users WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $toggle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        $new_status = ($user['status'] == 'active') ? 'inactive' : 'active';
        $sql = "UPDATE department_users SET status = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $new_status, $toggle_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Toggle Department User', "Changed user {$user['fullname']} status to $new_status");
            $_SESSION['toast_message'] = "User status updated successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error updating user status!";
            $_SESSION['toast_type'] = "error";
        }
    }
    header("Location: view_department_users.php");
    exit();
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $user_sql = "SELECT fullname, email FROM department_users WHERE id = ?";
    $user_stmt = $db->prepare($user_sql);
    $user_stmt->bind_param("i", $delete_id);
    $user_stmt->execute();
    $deleted_user = $user_stmt->get_result()->fetch_assoc();
    
    if ($deleted_user) {
        $sql = "DELETE FROM department_users WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Delete Department User', "Deleted user: {$deleted_user['fullname']} ({$deleted_user['email']})");
            $_SESSION['toast_message'] = "User deleted successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error deleting user!";
            $_SESSION['toast_type'] = "error";
        }
    }
    header("Location: view_department_users.php");
    exit();
}

// Handle password reset
if (isset($_GET['reset_password'])) {
    $reset_id = intval($_GET['reset_password']);
    $new_password = generateRandomPassword();
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE department_users SET password = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $reset_id);
    
    if ($stmt->execute()) {
        // Get user email
        $user_sql = "SELECT fullname, email FROM department_users WHERE id = ?";
        $user_stmt = $db->prepare($user_sql);
        $user_stmt->bind_param("i", $reset_id);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        
        logActivity($user_id, 'Reset Department User Password', "Reset password for user: {$user['fullname']}");
        
        $_SESSION['toast_message'] = "Password reset successfully! New password: <strong>$new_password</strong>";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error resetting password!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: view_department_users.php");
    exit();
}

// Search and filter
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT du.*, d.department_name 
        FROM department_users du 
        JOIN departments d ON du.department_id = d.id 
        WHERE 1=1";

if (!empty($search)) {
    $search = $db->real_escape_string($search);
    $sql .= " AND (du.fullname LIKE '%$search%' OR du.email LIKE '%$search%' OR du.employee_id LIKE '%$search%' OR du.phone LIKE '%$search%')";
}

if (!empty($department_filter)) {
    $department_filter = $db->real_escape_string($department_filter);
    $sql .= " AND d.department_name = '$department_filter'";
}

if (!empty($status_filter)) {
    $status_filter = $db->real_escape_string($status_filter);
    $sql .= " AND du.status = '$status_filter'";
}

$sql .= " ORDER BY du.created_at DESC";
$result = $db->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get departments for filter
$depts_sql = "SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name";
$depts_result = $db->query($depts_sql);
$departments = $depts_result->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> Department Users</h1>
            <p>Manage department staff who confirm stock requests via QR code</p>
        </div>
        <div class="header-actions">
            <a href="register.php" class="btn-primary">
                <i class="fas fa-user-plus"></i> Register New User
            </a>
        </div>
    </div>
    
    <!-- Stats Summary -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #1E3A8A20;">
                <i class="fas fa-users" style="color: #1E3A8A;"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Users</span>
                <span class="stat-value"><?php echo count($users); ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #10B98120;">
                <i class="fas fa-check-circle" style="color: #10B981;"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Active Users</span>
                <span class="stat-value">
                    <?php 
                        $active_count = 0;
                        foreach($users as $u) {
                            if ($u['status'] == 'active') $active_count++;
                        }
                        echo $active_count;
                    ?>
                </span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #8B5CF620;">
                <i class="fas fa-building" style="color: #8B5CF6;"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Departments</span>
                <span class="stat-value"><?php echo count($departments); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="filter-container">
        <form method="GET" action="" class="filter-form" id="filterForm">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" placeholder="Search by name, email, employee ID or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>" class="search-input">
            </div>
            <div class="filter-group">
                <select name="department" class="filter-select">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department_name']); ?>" 
                                <?php echo ($department_filter == $dept['department_name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if(!empty($search) || !empty($department_filter) || !empty($status_filter)): ?>
                <a href="view_department_users.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="data-card">
        <div class="card-header">
            <div class="header-title">
                <i class="fas fa-users"></i>
                <h3>Department Staff List</h3>
            </div>
            <div class="header-info">
                Showing <strong><?php echo count($users); ?></strong> user(s)
            </div>
        </div>
        <div class="table-wrapper">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Staff Details</th>
                        <th>Contact</th>
                        <th>Department</th>
                        <th>Employee ID</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($users) > 0): ?>
                        <?php foreach($users as $index => $user): ?>
                            <tr class="user-row" style="animation-delay: <?php echo $index * 0.02; ?>s">
                                <td><?php echo $user['id']; ?></td>
                                <td class="staff-cell">
                                    <div class="staff-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="staff-info">
                                        <div class="staff-name">
                                            <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                                            <?php if($user['sex']): ?>
                                                <span class="staff-sex">(<?php echo $user['sex']; ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="staff-meta">
                                            <i class="fas fa-calendar-alt"></i> 
                                            <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="contact-cell">
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                                </td>
                                <td>
                                    <span class="dept-badge">
                                        <i class="fas <?php echo getDepartmentIcon($user['department_name']); ?>"></i>
                                        <?php echo htmlspecialchars($user['department_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if(!empty($user['employee_id'])): ?>
                                        <code><?php echo htmlspecialchars($user['employee_id']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($user['position'])): ?>
                                        <span class="position-tag"><?php echo htmlspecialchars($user['position']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <i class="fas <?php echo $user['status'] == 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <a href="edit_department_user.php?id=<?php echo $user['id']; ?>" class="action-btn edit-btn" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?reset_password=<?php echo $user['id']; ?>" class="action-btn reset-btn" title="Reset Password" 
                                       onclick="return confirmReset('<?php echo addslashes($user['fullname']); ?>')">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <a href="?toggle=<?php echo $user['id']; ?>" class="action-btn toggle-btn" title="Toggle Status">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <a href="?delete=<?php echo $user['id']; ?>" class="action-btn delete-btn" title="Delete User" 
                                       onclick="return confirmDelete('<?php echo addslashes($user['fullname']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="empty-row">
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    <h4>No Department Users Found</h4>
                                    <p>No users match your search criteria</p>
                                    <a href="register.php" class="btn-create-empty">
                                        <i class="fas fa-user-plus"></i> Register First User
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .main-content {
        padding: 20px;
        background: #F3F4F6;
        min-height: 100vh;
    }
    
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
    
    .header-actions .btn-primary {
        background: #FF6B6B;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        color: white;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    
    .header-actions .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
    }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-label {
        font-size: 12px;
        color: #6B7280;
        display: block;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    /* Filter Container */
    .filter-container {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .filter-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-wrapper {
        flex: 2;
        min-width: 250px;
        position: relative;
    }
    
    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
    }
    
    .search-input {
        width: 100%;
        padding: 12px 12px 12px 42px;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .filter-group select {
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        font-size: 14px;
        background: white;
        cursor: pointer;
        min-width: 150px;
    }
    
    .btn-filter, .btn-clear {
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-filter {
        background: #1E3A8A;
        color: white;
    }
    
    .btn-filter:hover {
        background: #2563EB;
    }
    
    .btn-clear {
        background: #F3F4F6;
        color: #374151;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
    /* Data Card */
    .data-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .card-header {
        padding: 20px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .header-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .header-title i {
        font-size: 20px;
        color: #1E3A8A;
    }
    
    .header-title h3 {
        margin: 0;
        font-size: 18px;
        color: #1E3A8A;
    }
    
    .header-info {
        font-size: 13px;
        color: #6B7280;
    }
    
    /* Table Styles */
    .table-wrapper {
        overflow-x: auto;
    }
    
    .users-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .users-table thead {
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .users-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .users-table td {
        padding: 16px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
        vertical-align: middle;
    }
    
    .user-row {
        transition: background 0.2s;
        animation: fadeInUp 0.3s ease backwards;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .user-row:hover {
        background: #F9FAFB;
    }
    
    /* Staff Cell */
    .staff-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .staff-avatar {
        width: 40px;
        height: 40px;
        background: #F3F4F6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #1E3A8A;
    }
    
    .staff-info {
        flex: 1;
    }
    
    .staff-name {
        font-size: 14px;
        margin-bottom: 4px;
    }
    
    .staff-sex {
        font-size: 11px;
        color: #6B7280;
        margin-left: 5px;
    }
    
    .staff-meta {
        font-size: 11px;
        color: #9CA3AF;
    }
    
    .staff-meta i {
        margin-right: 3px;
    }
    
    /* Contact Cell */
    .contact-cell div {
        margin-bottom: 4px;
        font-size: 12px;
    }
    
    .contact-cell div:last-child {
        margin-bottom: 0;
    }
    
    .contact-cell i {
        width: 20px;
        color: #9CA3AF;
    }
    
    /* Department Badge */
    .dept-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        background: #E0E7FF;
        color: #1E3A8A;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .dept-badge i {
        font-size: 11px;
    }
    
    /* Position Tag */
    .position-tag {
        display: inline-block;
        padding: 4px 10px;
        background: #F3F4F6;
        border-radius: 15px;
        font-size: 11px;
        color: #374151;
    }
    
    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
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
    .actions-cell {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .action-btn {
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 14px;
    }
    
    .edit-btn {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    .edit-btn:hover {
        background: #BFDBFE;
        transform: translateY(-2px);
    }
    
    .reset-btn {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .reset-btn:hover {
        background: #FDE68A;
        transform: translateY(-2px);
    }
    
    .toggle-btn {
        background: #F3F4F6;
        color: #6B7280;
    }
    
    .toggle-btn:hover {
        background: #E5E7EB;
        transform: translateY(-2px);
    }
    
    .delete-btn {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .delete-btn:hover {
        background: #FECACA;
        transform: translateY(-2px);
    }
    
    /* Empty State */
    .empty-row td {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state {
        text-align: center;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #D1D5DB;
        margin-bottom: 20px;
    }
    
    .empty-state h4 {
        font-size: 18px;
        color: #374151;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #6B7280;
        margin-bottom: 20px;
    }
    
    .btn-create-empty {
        background: #FF6B6B;
        color: white;
        padding: 12px 24px;
        border-radius: 10px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    
    .btn-create-empty:hover {
        background: #e55a5a;
        transform: translateY(-2px);
    }
    
    .text-muted {
        color: #9CA3AF;
        font-style: italic;
    }
    
    code {
        background: #F3F4F6;
        padding: 3px 8px;
        border-radius: 5px;
        font-size: 11px;
        font-family: monospace;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .users-table {
            font-size: 12px;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px 10px;
        }
    }
    
    @media (max-width: 768px) {
        .filter-form {
            flex-direction: column;
        }
        
        .search-wrapper,
        .filter-group select,
        .btn-filter,
        .btn-clear {
            width: 100%;
        }
        
        .page-header {
            flex-direction: column;
            text-align: center;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .card-header {
            flex-direction: column;
            text-align: center;
        }
        
        .staff-cell {
            flex-direction: column;
            text-align: center;
        }
        
        .contact-cell {
            text-align: center;
        }
        
        .actions-cell {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .users-table th,
        .users-table td {
            padding: 8px 6px;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
        }
    }
</style>

<script>
function confirmDelete(username) {
    return confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone!`);
}

function confirmReset(username) {
    return confirm(`Reset password for "${username}"? A new random password will be generated and shown.`);
}

// Animate rows
document.querySelectorAll('.user-row').forEach((row, index) => {
    row.style.animationDelay = `${index * 0.02}s`;
});
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

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
?>

<?php include '../templates/footer.php'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Admin']);

$user_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// Total users
$result = $db->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total items
$result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE status = 'active'");
$stats['total_items'] = $result->fetch_assoc()['count'];

// Low stock items
$result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE current_stock <= minimum_stock");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Total suppliers
$result = $db->query("SELECT COUNT(*) as count FROM suppliers WHERE status = 'active'");
$stats['total_suppliers'] = $result->fetch_assoc()['count'];

// Pending POs
$result = $db->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'pending'");
$stats['pending_po'] = $result->fetch_assoc()['count'];

// Recent users
$recent_users = $db->query("SELECT id, fullname, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Recent activities
$recent_activities = $db->query("SELECT l.*, u.fullname FROM system_logs l JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?>! System overview and management</p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #1E3A8A20;">
                <i class="fas fa-users" style="color: #1E3A8A;"></i>
            </div>
            <div class="stat-info">
                <h3>Total Users</h3>
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #10B98120;">
                <i class="fas fa-boxes" style="color: #10B981;"></i>
            </div>
            <div class="stat-info">
                <h3>Inventory Items</h3>
                <div class="stat-number"><?php echo $stats['total_items']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #FF6B6B20;">
                <i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i>
            </div>
            <div class="stat-info">
                <h3>Low Stock Alerts</h3>
                <div class="stat-number" style="color: #FF6B6B;"><?php echo $stats['low_stock']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #F59E0B20;">
                <i class="fas fa-truck" style="color: #F59E0B;"></i>
            </div>
            <div class="stat-info">
                <h3>Active Suppliers</h3>
                <div class="stat-number"><?php echo $stats['total_suppliers']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #8B5CF620;">
                <i class="fas fa-shopping-cart" style="color: #8B5CF6;"></i>
            </div>
            <div class="stat-info">
                <h3>Pending Orders</h3>
                <div class="stat-number"><?php echo $stats['pending_po']; ?></div>
            </div>
        </div>
    </div>
    
    <div class="two-columns">
        <!-- Recent Users -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Recent Users</h3>
                <a href="view_users.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <div class="user-list">
                    <?php foreach($recent_users as $user): ?>
                        <div class="user-item">
                            <div class="user-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['username']); ?> | <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                            </div>
                            <div class="user-actions">
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-icon">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activities</h3>
                <button onclick="refreshLogs()" class="btn-icon"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="card-body">
                <div class="activity-list">
                    <?php foreach($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                    $icon = 'fa-info-circle';
                                    if(strpos($activity['action'], 'Login') !== false) $icon = 'fa-sign-in-alt';
                                    elseif(strpos($activity['action'], 'Add') !== false) $icon = 'fa-plus-circle';
                                    elseif(strpos($activity['action'], 'Delete') !== false) $icon = 'fa-trash';
                                    elseif(strpos($activity['action'], 'Update') !== false) $icon = 'fa-edit';
                                    elseif(strpos($activity['action'], 'Stock') !== false) $icon = 'fa-boxes';
                                    else $icon = 'fa-bell';
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                                <div class="activity-meta">
                                    <?php echo htmlspecialchars($activity['fullname']); ?> • 
                                    <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                </div>
                                <?php if($activity['details']): ?>
                                    <div class="activity-detail"><?php echo htmlspecialchars($activity['details']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="action-buttons">
            <a href="add_user.php" class="action-btn">
                <i class="fas fa-user-plus"></i> Add New User
            </a>
            <a href="../manager/suppliers.php" class="action-btn">
                <i class="fas fa-truck"></i> Manage Suppliers
            </a>
            <a href="../manager/reports.php" class="action-btn">
                <i class="fas fa-chart-line"></i> View Reports
            </a>
            <a href="../storekeeper/view_items.php" class="action-btn">
                <i class="fas fa-boxes"></i> View Inventory
            </a>
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
    
    .stat-info h3 {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 5px;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .two-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }
    
    .user-list, .activity-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .user-item, .activity-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px;
        border-bottom: 1px solid #E5E7EB;
        transition: background 0.3s;
    }
    
    .user-item:hover, .activity-item:hover {
        background: #F9FAFB;
    }
    
    .user-avatar {
        font-size: 40px;
        color: #1E3A8A;
    }
    
    .user-info {
        flex: 1;
    }
    
    .user-name {
        font-weight: 600;
        color: #1F2937;
    }
    
    .user-email {
        font-size: 12px;
        color: #6B7280;
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
        font-weight: 500;
        font-size: 14px;
        color: #1F2937;
    }
    
    .activity-meta {
        font-size: 11px;
        color: #9CA3AF;
        margin-top: 2px;
    }
    
    .activity-detail {
        font-size: 12px;
        color: #6B7280;
        margin-top: 4px;
    }
    
    .btn-link {
        color: #1E3A8A;
        text-decoration: none;
        font-size: 13px;
    }
    
    .btn-link:hover {
        color: #FF6B6B;
    }
    
    .quick-actions {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-top: 10px;
    }
    
    .quick-actions h3 {
        margin-bottom: 15px;
        color: #1E3A8A;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .action-btn {
        background: #1E3A8A;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .action-btn:hover {
        background: #2563EB;
        transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
        .two-columns {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
function refreshLogs() {
    location.reload();
}
</script>

<?php include '../templates/footer.php'; ?>
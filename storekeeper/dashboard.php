<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// Total items
$result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE status = 'active'");
$stats['total_items'] = $result->fetch_assoc()['count'];

// Low stock items
$result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE current_stock <= minimum_stock");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Total stock value
$result = $db->query("SELECT SUM(current_stock * unit_price) as value FROM inventory_items");
$stats['total_value'] = $result->fetch_assoc()['value'] ?? 0;

// Recent stock movements
$recent_movements = $db->query("SELECT sm.*, i.item_name 
                                 FROM stock_movements sm
                                 JOIN inventory_items i ON sm.item_id = i.id
                                 ORDER BY sm.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Low stock items list
$low_stock_items = getLowStockItems();

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-warehouse"></i> Storekeeper Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?>! Manage hotel inventory</p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #1E3A8A20;">
                <i class="fas fa-boxes" style="color: #1E3A8A;"></i>
            </div>
            <div class="stat-info">
                <h3>Total Items</h3>
                <div class="stat-number"><?php echo $stats['total_items']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #FF6B6B20;">
                <i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i>
            </div>
            <div class="stat-info">
                <h3>Low Stock Items</h3>
                <div class="stat-number" style="color: #FF6B6B;"><?php echo $stats['low_stock']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #10B98120;">
                <i class="fas fa-chart-line" style="color: #10B981;"></i>
            </div>
            <div class="stat-info">
                <h3>Inventory Value</h3>
                <div class="stat-number">TZS <?php echo number_format($stats['total_value'], 0); ?></div>
            </div>
        </div>
    </div>
    
    <div class="two-columns">
        <!-- Low Stock Alerts -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i> Low Stock Alerts</h3>
                <a href="view_items.php" class="btn-link">View All Items <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <?php if(count($low_stock_items) > 0): ?>
                    <div class="alert-list">
                        <?php foreach($low_stock_items as $item): ?>
                            <div class="alert-item">
                                <div class="alert-info">
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <div class="stock-details">
                                        Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?> | 
                                        Minimum: <?php echo $item['minimum_stock']; ?> <?php echo $item['unit']; ?>
                                    </div>
                                </div>
                                <div class="alert-actions">
                                    <a href="stock_in.php" class="btn-small primary">Receive Stock</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-check-circle" style="color: #10B981; font-size: 40px;"></i>
                        <p>All items are at healthy stock levels! Great job!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-grid">
                    <a href="add_item.php" class="quick-card">
                        <i class="fas fa-plus-circle" style="color: #1E3A8A;"></i>
                        <span>Add New Item</span>
                    </a>
                    <a href="stock_in.php" class="quick-card">
                        <i class="fas fa-arrow-down" style="color: #10B981;"></i>
                        <span>Stock In</span>
                    </a>
                    <a href="stock_out.php" class="quick-card">
                        <i class="fas fa-arrow-up" style="color: #FF6B6B;"></i>
                        <span>Stock Out</span>
                    </a>
                    <a href="view_items.php" class="quick-card">
                        <i class="fas fa-list" style="color: #F59E0B;"></i>
                        <span>View All Items</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Stock Movements -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Stock Movements</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_movements as $movement): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($movement['item_name']); ?></td>
                                <td>
                                    <?php if($movement['movement_type'] == 'IN'): ?>
                                        <span class="badge-in">RECEIVED</span>
                                    <?php else: ?>
                                        <span class="badge-out">ISSUED</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($movement['movement_type'] == 'IN'): ?>
                                        <span class="text-success">+<?php echo number_format($movement['quantity']); ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">-<?php echo number_format($movement['quantity']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($movement['reference_no'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
    
    .alert-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .alert-item:last-child {
        border-bottom: none;
    }
    
    .alert-info strong {
        color: #1F2937;
    }
    
    .stock-details {
        font-size: 12px;
        color: #6B7280;
        margin-top: 4px;
    }
    
    .btn-small {
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
        display: inline-block;
    }
    
    .btn-small.primary {
        background: #FF6B6B;
        color: white;
    }
    
    .btn-small.primary:hover {
        background: #e55a5a;
    }
    
    .empty-message {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-message p {
        margin-top: 10px;
        color: #6B7280;
    }
    
    .quick-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .quick-card {
        background: #F9FAFB;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s;
        border: 1px solid #E5E7EB;
    }
    
    .quick-card i {
        font-size: 32px;
        display: block;
        margin-bottom: 10px;
    }
    
    .quick-card span {
        font-size: 14px;
        color: #374151;
    }
    
    .quick-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #1E3A8A;
    }
    
    .badge-in, .badge-out {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .badge-in {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .badge-out {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .text-success {
        color: #10B981;
        font-weight: 600;
    }
    
    .text-danger {
        color: #EF4444;
        font-weight: 600;
    }
    
    .btn-link {
        color: #1E3A8A;
        text-decoration: none;
        font-size: 13px;
    }
    
    .btn-link:hover {
        color: #FF6B6B;
    }
    
    @media (max-width: 768px) {
        .two-columns {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include '../templates/footer.php'; ?>
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
       
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname']); ?>! Here's your inventory overview</p>
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
                <span class="stat-trend">in inventory</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #FF6B6B20;">
                <i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i>
            </div>
            <div class="stat-info">
                <h3>Low Stock Items</h3>
                <div class="stat-number" style="color: #FF6B6B;"><?php echo $stats['low_stock']; ?></div>
                <span class="stat-trend">need reorder</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #10B98120;">
                <i class="fas fa-chart-line" style="color: #10B981;"></i>
            </div>
            <div class="stat-info">
                <h3>Inventory Value</h3>
                <div class="stat-number">TZS <?php echo number_format($stats['total_value'], 0); ?></div>
                <span class="stat-trend">total value</span>
            </div>
        </div>
    </div>
    
    <div class="two-columns">
        <!-- Low Stock Alerts -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i> Low Stock Alerts</h3>
                <a href="view_items.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <?php if(count($low_stock_items) > 0): ?>
                    <div class="alert-list">
                        <?php foreach($low_stock_items as $item): ?>
                            <div class="alert-item">
                                <div class="alert-info">
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <div class="stock-details">
                                        <span class="current-stock">Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?></span>
                                        <span class="min-stock">Min: <?php echo $item['minimum_stock']; ?> <?php echo $item['unit']; ?></span>
                                    </div>
                                </div>
                                <div class="alert-actions">
                                    <a href="stock_in.php?item=<?php echo $item['id']; ?>" class="btn-small primary">
                                        <i class="fas fa-arrow-down"></i> Receive
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-check-circle" style="color: #10B981; font-size: 48px;"></i>
                        <p>All items are at healthy stock levels!</p>
                        <span class="empty-subtitle">Great job maintaining inventory!</span>
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
                        <small>Receive goods</small>
                    </a>
                    <a href="stock_out.php" class="quick-card">
                        <i class="fas fa-arrow-up" style="color: #FF6B6B;"></i>
                        <span>Stock Out</span>
                        <small>Issue items</small>
                    </a>
                    <a href="view_items.php" class="quick-card">
                        <i class="fas fa-list" style="color: #F59E0B;"></i>
                        <span>View All Items</span>
                        <small>Browse inventory</small>
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
            <?php if(count($recent_movements) > 0): ?>
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
                                    <td><strong><?php echo htmlspecialchars($movement['item_name']); ?></strong></td>
                                    <td>
                                        <?php if($movement['movement_type'] == 'IN'): ?>
                                            <span class="badge-in"><i class="fas fa-arrow-down"></i> RECEIVED</span>
                                        <?php else: ?>
                                            <span class="badge-out"><i class="fas fa-arrow-up"></i> ISSUED</span>
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
            <?php else: ?>
                <div class="empty-message-small">
                    <i class="fas fa-exchange-alt"></i>
                    <p>No stock movements recorded yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stat-info h3 {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .stat-trend {
        font-size: 11px;
        color: #9CA3AF;
    }
    
    /* Two Columns */
    .two-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }
    
    /* Alert Items */
    .alert-list {
        max-height: 350px;
        overflow-y: auto;
    }
    
    .alert-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px;
        border-bottom: 1px solid #E5E7EB;
        transition: background 0.2s;
    }
    
    .alert-item:hover {
        background: #F9FAFB;
    }
    
    .alert-item:last-child {
        border-bottom: none;
    }
    
    .alert-info strong {
        color: #1F2937;
        font-size: 15px;
    }
    
    .stock-details {
        display: flex;
        gap: 15px;
        margin-top: 5px;
    }
    
    .current-stock, .min-stock {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 12px;
    }
    
    .current-stock {
        background: #F3F4F6;
        color: #374151;
    }
    
    .min-stock {
        background: #FEF3C7;
        color: #92400E;
    }
    
    /* Quick Grid */
    .quick-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 15px;
    }
    
    .quick-card {
        background: #F9FAFB;
        padding: 20px 15px;
        border-radius: 12px;
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
        font-weight: 600;
        color: #374151;
        display: block;
    }
    
    .quick-card small {
        font-size: 11px;
        color: #9CA3AF;
        display: block;
        margin-top: 4px;
    }
    
    .quick-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #1E3A8A;
        background: white;
    }
    
    /* Badges */
    .badge-in, .badge-out {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
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
        font-weight: 700;
    }
    
    .text-danger {
        color: #EF4444;
        font-weight: 700;
    }
    
    /* Empty States */
    .empty-message {
        text-align: center;
        padding: 50px 20px;
    }
    
    .empty-message p {
        margin-top: 15px;
        color: #374151;
        font-weight: 500;
    }
    
    .empty-subtitle {
        font-size: 12px;
        color: #9CA3AF;
    }
    
    .empty-message-small {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-message-small i {
        font-size: 40px;
        color: #D1D5DB;
        margin-bottom: 10px;
    }
    
    .empty-message-small p {
        color: #6B7280;
    }
    
    /* Button Link */
    .btn-link {
        color: #1E3A8A;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: color 0.2s;
    }
    
    .btn-link:hover {
        color: #FF6B6B;
    }
    
    .btn-small.primary {
        background: #FF6B6B;
        color: white;
        padding: 6px 14px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
    }
    
    .btn-small.primary:hover {
        background: #e55a5a;
        transform: translateY(-1px);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .two-columns {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .quick-grid {
            grid-template-columns: 1fr;
        }
        
        .alert-item {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        
        .stock-details {
            justify-content: center;
        }
    }
</style>

<?php include '../templates/footer.php'; ?>
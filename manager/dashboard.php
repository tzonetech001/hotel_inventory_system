<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager']);

$user_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// Total items
$result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE status = 'active'");
$stats['total_items'] = $result->fetch_assoc()['count'];

// Low stock items
$result = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE current_stock <= minimum_stock");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Pending POs
$result = $db->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'pending'");
$stats['pending_po'] = $result->fetch_assoc()['count'];

// Total suppliers
$result = $db->query("SELECT COUNT(*) as count FROM suppliers WHERE status = 'active'");
$stats['total_suppliers'] = $result->fetch_assoc()['count'];

// Recent stock movements
$recent_movements = $db->query("SELECT sm.*, i.item_name, u.fullname 
                                 FROM stock_movements sm
                                 JOIN inventory_items i ON sm.item_id = i.id
                                 JOIN users u ON sm.performed_by = u.id
                                 ORDER BY sm.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Low stock items list
$low_stock_items = getLowStockItems();

// Pending POs list
$pending_pos = $db->query("SELECT po.*, s.company_name FROM purchase_orders po 
                            JOIN suppliers s ON po.supplier_id = s.id 
                            WHERE po.status = 'pending' 
                            ORDER BY po.created_at ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Manager Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?>! Hotel inventory overview</p>
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
                <h3>Low Stock Alerts</h3>
                <div class="stat-number" style="color: #FF6B6B;"><?php echo $stats['low_stock']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #F59E0B20;">
                <i class="fas fa-clock" style="color: #F59E0B;"></i>
            </div>
            <div class="stat-info">
                <h3>Pending Approvals</h3>
                <div class="stat-number"><?php echo $stats['pending_po']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #10B98120;">
                <i class="fas fa-truck" style="color: #10B981;"></i>
            </div>
            <div class="stat-info">
                <h3>Active Suppliers</h3>
                <div class="stat-number"><?php echo $stats['total_suppliers']; ?></div>
            </div>
        </div>
    </div>
    
    <div class="two-columns">
        <!-- Low Stock Alerts -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i> Low Stock Alerts</h3>
                <a href="reports.php?type=stock" class="btn-link">View Report <i class="fas fa-arrow-right"></i></a>
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
                                <div class="alert-status critical">
                                    CRITICAL
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-check-circle" style="color: #10B981; font-size: 40px;"></i>
                        <p>All items are at healthy stock levels!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pending Approvals -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-invoice"></i> Pending Purchase Orders</h3>
                <a href="approve_po.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <?php if(count($pending_pos) > 0): ?>
                    <div class="po-list">
                        <?php foreach($pending_pos as $po): ?>
                            <div class="po-item">
                                <div class="po-info">
                                    <div class="po-number"><?php echo $po['po_number']; ?></div>
                                    <div class="po-supplier"><?php echo htmlspecialchars($po['company_name']); ?></div>
                                    <div class="po-amount">TZS <?php echo number_format($po['total_amount'], 2); ?></div>
                                </div>
                                <div class="po-action">
                                    <a href="approve_po.php" class="btn-small">
                                        Review <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-check" style="color: #10B981; font-size: 40px;"></i>
                        <p>No pending approvals!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Stock Movements</h3>
            <a href="reports.php?type=movements" class="btn-link">View Full Report <i class="fas fa-arrow-right"></i></a>
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
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_movements as $movement): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($movement['item_name']); ?></td>
                                <td>
                                    <?php if($movement['movement_type'] == 'IN'): ?>
                                        <span class="badge-in">IN</span>
                                    <?php else: ?>
                                        <span class="badge-out">OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($movement['movement_type'] == 'IN'): ?>
                                        <span class="text-success">+<?php echo $movement['quantity']; ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">-<?php echo $movement['quantity']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($movement['fullname']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="action-buttons">
            <a href="reports.php" class="action-btn">
                <i class="fas fa-chart-line"></i> Generate Report
            </a>
            <a href="approve_po.php" class="action-btn">
                <i class="fas fa-check-double"></i> Review Orders
            </a>
            <a href="suppliers.php" class="action-btn">
                <i class="fas fa-truck"></i> Manage Suppliers
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
    
    .alert-item, .po-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .alert-item:last-child, .po-item:last-child {
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
    
    .alert-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .alert-status.critical {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .po-number {
        font-weight: 600;
        color: #1E3A8A;
    }
    
    .po-supplier {
        font-size: 12px;
        color: #6B7280;
    }
    
    .po-amount {
        font-size: 13px;
        font-weight: 500;
        margin-top: 4px;
    }
    
    .btn-small {
        background: #1E3A8A;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
    }
    
    .btn-small:hover {
        background: #2563EB;
    }
    
    .empty-message {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-message p {
        margin-top: 10px;
        color: #6B7280;
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

<?php include '../templates/footer.php'; ?>
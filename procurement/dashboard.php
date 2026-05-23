<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer']);

$user_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// Total POs
$result = $db->query("SELECT COUNT(*) as count FROM purchase_orders");
$stats['total_pos'] = $result->fetch_assoc()['count'];

// Pending POs
$result = $db->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'pending'");
$stats['pending_pos'] = $result->fetch_assoc()['count'];

// Approved POs
$result = $db->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'approved'");
$stats['approved_pos'] = $result->fetch_assoc()['count'];

// Delivered POs
$result = $db->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'delivered'");
$stats['delivered_pos'] = $result->fetch_assoc()['count'];

// Recent POs
$recent_pos = $db->query("SELECT po.*, s.company_name 
                           FROM purchase_orders po
                           JOIN suppliers s ON po.supplier_id = s.id
                           ORDER BY po.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// Low stock items that need reordering
$low_stock_items = getLowStockItems();

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Procurement Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?>! Manage purchase orders</p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-info">
                <h3>Total Orders</h3>
                <div class="stat-number"><?php echo $stats['total_pos']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pending Approval</h3>
                <div class="stat-number"><?php echo $stats['pending_pos']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon approved">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Approved Orders</h3>
                <div class="stat-number"><?php echo $stats['approved_pos']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon delivered">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-info">
                <h3>Delivered</h3>
                <div class="stat-number"><?php echo $stats['delivered_pos']; ?></div>
            </div>
        </div>
    </div>
    
    <div class="two-columns">
        <!-- Low Stock Items (Need Reorder) -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i> Items Need Reorder</h3>
                <a href="create_po.php" class="btn-link">Create PO <i class="fas fa-arrow-right"></i></a>
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
                                        Min: <?php echo $item['minimum_stock']; ?> <?php echo $item['unit']; ?>
                                    </div>
                                </div>
                                <div class="alert-actions">
                                    <a href="create_po.php?item_id=<?php echo $item['id']; ?>" class="btn-small primary">
                                        <i class="fas fa-plus"></i> Create PO
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-check-circle" style="color: #10B981; font-size: 40px;"></i>
                        <p>All items are above minimum stock levels!</p>
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
                    <a href="create_po.php" class="quick-card">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create New PO</span>
                    </a>
                    <a href="view_po.php" class="quick-card">
                        <i class="fas fa-list"></i>
                        <span>View All Orders</span>
                    </a>
                    <a href="track_delivery.php" class="quick-card">
                        <i class="fas fa-truck"></i>
                        <span>Track Deliveries</span>
                    </a>
                    <a href="../manager/suppliers.php" class="quick-card">
                        <i class="fas fa-building"></i>
                        <span>Manage Suppliers</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Purchase Orders -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Purchase Orders</h3>
            <a href="view_po.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Order Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_pos as $po): ?>
                            <tr>
                                <td><strong><?php echo $po['po_number']; ?></strong></td>
                                <td><?php echo htmlspecialchars($po['company_name']); ?></td
                                <td><?php echo date('d M Y', strtotime($po['order_date'])); ?></td>
                                <td>TZS <?php echo number_format($po['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $po['status']; ?>">
                                        <?php echo ucfirst($po['status']); ?>
                                    </span>
                                 </td
                                <td>
                                    <a href="view_po.php" class="btn-icon">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                 </td
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Floating Action Button -->
    <a href="create_po.php" class="fab">
        <i class="fas fa-plus"></i>
    </a>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
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
    
    .stat-icon.total {
        background: #1E3A8A20;
        color: #1E3A8A;
    }
    
    .stat-icon.pending {
        background: #FEF3C7;
        color: #F59E0B;
    }
    
    .stat-icon.approved {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .stat-icon.delivered {
        background: #DBEAFE;
        color: #1E40AF;
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
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .card-header {
        padding: 18px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 16px;
        color: #1E3A8A;
    }
    
    .btn-link {
        color: #1E3A8A;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
    }
    
    .btn-link:hover {
        color: #FF6B6B;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .alert-list {
        max-height: 300px;
        overflow-y: auto;
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
        display: inline-flex;
        align-items: center;
        gap: 5px;
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
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
        font-size: 28px;
        display: block;
        margin-bottom: 10px;
    }
    
    .quick-card span {
        font-size: 13px;
        color: #374151;
    }
    
    .quick-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #1E3A8A;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-pending {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .status-approved {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-delivered {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    .fab {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 56px;
        height: 56px;
        background: #FF6B6B;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 24px;
        box-shadow: 0 4px 12px rgba(255,107,107,0.4);
        transition: all 0.3s;
        z-index: 100;
    }
    
    .fab:hover {
        transform: scale(1.1);
        background: #e55a5a;
    }
    
    @media (max-width: 768px) {
        .two-columns {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-card {
            padding: 15px;
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            font-size: 20px;
        }
        
        .stat-number {
            font-size: 22px;
        }
        
        .quick-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .fab {
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
    }
</style>

<?php include '../templates/footer.php'; ?>
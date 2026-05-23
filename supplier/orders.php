<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Supplier can access
checkAuth(['Supplier']);

$supplier_id = $_SESSION['supplier_id'] ?? $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT po.*, u.fullname as created_by_name
        FROM purchase_orders po
        JOIN users u ON po.created_by = u.id
        WHERE po.supplier_id = ?";

if ($status_filter != 'all') {
    $sql .= " AND po.status = '" . $db->real_escape_string($status_filter) . "'";
}

if (!empty($search)) {
    $search = $db->real_escape_string($search);
    $sql .= " AND po.po_number LIKE '%$search%'";
}

$sql .= " ORDER BY po.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// Get order statistics
$order_stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'delivered' => 0,
    'rejected' => 0,
    'total_amount' => 0
];

foreach ($orders as $order) {
    $order_stats['total']++;
    $order_stats['total_amount'] += $order['total_amount'];
    
    if ($order['status'] == 'pending') $order_stats['pending']++;
    elseif ($order['status'] == 'approved') $order_stats['approved']++;
    elseif ($order['status'] == 'delivered') $order_stats['delivered']++;
    elseif ($order['status'] == 'rejected') $order_stats['rejected']++;
}

// Handle mark as delivered
if (isset($_GET['mark_delivered'])) {
    $po_id = intval($_GET['mark_delivered']);
    
    // Check if order belongs to this supplier and is approved
    $check_sql = "SELECT id FROM purchase_orders WHERE id = ? AND supplier_id = ? AND status = 'approved'";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("ii", $po_id, $supplier_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $update_sql = "UPDATE purchase_orders SET status = 'delivered' WHERE id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->bind_param("i", $po_id);
        
        if ($update_stmt->execute()) {
            logActivity(0, 'Supplier Mark Delivered', "Marked PO #$po_id as delivered", 'supplier');
            $_SESSION['toast_message'] = "Order marked as delivered successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error updating order status!";
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $_SESSION['toast_message'] = "Invalid order or order cannot be marked as delivered!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: orders.php?status=" . urlencode($status_filter) . "&search=" . urlencode($search));
    exit();
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> My Orders</h1>
        <p>View and manage all purchase orders from the hotel</p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-info">
                <h3>Total Orders</h3>
                <div class="stat-number"><?php echo $order_stats['total']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pending</h3>
                <div class="stat-number"><?php echo $order_stats['pending']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon approved">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Approved</h3>
                <div class="stat-number"><?php echo $order_stats['approved']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon delivered">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-info">
                <h3>Delivered</h3>
                <div class="stat-number"><?php echo $order_stats['delivered']; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon amount">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h3>Total Value</h3>
                <div class="stat-number">TZS <?php echo number_format($order_stats['total_amount'], 0); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <form method="GET" action="" class="search-form" id="filterForm">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by PO number..." 
                       value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
            </div>
            <div class="filter-tabs">
                <a href="?status=all&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All
                    <span class="count"><?php echo $order_stats['total']; ?></span>
                </a>
                <a href="?status=pending&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                    <span class="count pending"><?php echo $order_stats['pending']; ?></span>
                </a>
                <a href="?status=approved&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Approved
                    <span class="count approved"><?php echo $order_stats['approved']; ?></span>
                </a>
                <a href="?status=delivered&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i> Delivered
                    <span class="count delivered"><?php echo $order_stats['delivered']; ?></span>
                </a>
                <a href="?status=rejected&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Rejected
                    <span class="count rejected"><?php echo $order_stats['rejected']; ?></span>
                </a>
            </div>
            <?php if(!empty($search)): ?>
                <a href="?status=<?php echo $status_filter; ?>" class="btn-clear">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
            <button type="submit" class="btn-search">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>
    
    <!-- Orders Table -->
    <div class="card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-list"></i> Purchase Orders</h3>
            <div class="card-header-info">
                Showing <?php echo count($orders); ?> order(s)
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Order Date</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Expected Delivery</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($orders) > 0): ?>
                            <?php foreach($orders as $index => $order): ?>
                                <tr class="order-row" style="animation-delay: <?php echo $index * 0.03; ?>s">
                                    <td>
                                        <div class="po-cell">
                                            <strong class="po-number"><?php echo $order['po_number']; ?></strong>
                                            <div class="po-meta">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($order['created_by_name']); ?>
                                            </div>
                                        </div>
                            </td>
                                    <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <button onclick="viewOrderItems(<?php echo $order['id']; ?>)" class="btn-link">
                                            <i class="fas fa-eye"></i> View Items
                                        </button>
                                     </td>
                                    <td>
                                        <div class="amount-cell">
                                            TZS <?php echo number_format($order['total_amount'], 2); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($order['expected_delivery']): ?>
                                            <?php echo date('d M Y', strtotime($order['expected_delivery'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <i class="fas <?php 
                                                echo $order['status'] == 'pending' ? 'fa-clock' : 
                                                    ($order['status'] == 'approved' ? 'fa-check-circle' : 
                                                    ($order['status'] == 'delivered' ? 'fa-truck' : 'fa-times-circle')); 
                                            ?>"></i>
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($order['status'] == 'approved'): ?>
                                            <!-- <button onclick="markAsDelivered(<?php echo $order['id']; ?>)" class="btn-deliver">
                                                <i class="fas fa-check"></i> Mark Delivered
                                            </button> -->
                                        <?php elseif($order['status'] == 'delivered'): ?>
                                            <span class="delivered-badge">
                                                <i class="fas fa-check-circle"></i> Completed
                                            </span>
                                        <?php elseif($order['status'] == 'rejected'): ?>
                                            <span class="rejected-badge">
                                                <i class="fas fa-times-circle"></i> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="pending-badge">
                                                <i class="fas fa-clock"></i> Awaiting Approval
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h3>No Orders Found</h3>
                                        <p>No purchase orders found<?php echo !empty($search) ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?></p>
                                        <?php if(!empty($search) || $status_filter != 'all'): ?>
                                            <a href="orders.php" class="btn-secondary" style="margin-top: 15px;">
                                                <i class="fas fa-undo"></i> Clear Filters
                                            </a>
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
</div>

<!-- View Items Modal -->
<div id="itemsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-list"></i> Order Items</h3>
            <span class="close" onclick="closeItemsModal()">&times;</span>
        </div>
        <div class="modal-body" id="itemsModalBody">
            <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<style>
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
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
    
    .stat-icon.amount {
        background: #FCE7F3;
        color: #EC4899;
    }
    
    .stat-info h3 {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 5px;
    }
    
    .stat-number {
        font-size: 24px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    /* Search and Filter Bar */
    .search-filter-bar {
        background: white;
        padding: 20px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .search-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
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
        padding: 12px 12px 12px 38px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .search-box input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .filter-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .filter-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: #F3F4F6;
        border-radius: 30px;
        text-decoration: none;
        color: #374151;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .filter-tab i {
        font-size: 13px;
    }
    
    .filter-tab .count {
        background: #E5E7EB;
        padding: 2px 6px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .filter-tab .count.pending {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .filter-tab .count.approved {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .filter-tab .count.delivered {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    .filter-tab .count.rejected {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .filter-tab:hover, .filter-tab.active {
        background: #1E3A8A;
        color: white;
    }
    
    .filter-tab:hover .count, .filter-tab.active .count {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .btn-search {
        padding: 10px 20px;
        background: #1E3A8A;
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-search:hover {
        background: #2563EB;
    }
    
    .btn-clear {
        padding: 10px 20px;
        background: #F3F4F6;
        color: #374151;
        border-radius: 10px;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
    /* Card */
    .animate-card {
        animation: fadeInUp 0.4s ease;
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
        padding: 0;
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
        padding: 15px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-table td {
        padding: 16px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 14px;
        vertical-align: middle;
    }
    
    .order-row {
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
    
    .order-row:hover {
        background: #F9FAFB;
    }
    
    /* Cell Styles */
    .po-cell .po-number {
        font-size: 14px;
        color: #1E3A8A;
    }
    
    .po-meta {
        font-size: 11px;
        color: #9CA3AF;
        margin-top: 4px;
    }
    
    .amount-cell {
        font-weight: 600;
        color: #1E3A8A;
    }
    
    .text-muted {
        color: #9CA3AF;
        font-size: 12px;
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 12px;
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
    
    .status-rejected {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    /* Action Buttons */
    .btn-link {
        background: none;
        border: none;
        color: #1E3A8A;
        cursor: pointer;
        text-decoration: underline;
        font-size: 13px;
    }
    
    .btn-link:hover {
        color: #FF6B6B;
    }
    
    .btn-deliver {
        background: #10B981;
        color: white;
        padding: 6px 14px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-deliver:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .delivered-badge {
        background: #DBEAFE;
        color: #1E40AF;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .rejected-badge {
        background: #FEE2E2;
        color: #991B1B;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .pending-badge {
        background: #FEF3C7;
        color: #92400E;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
        max-width: 750px;
        max-height: 80vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
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
        position: sticky;
        top: 0;
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
    
    .loading-spinner {
        text-align: center;
        padding: 40px;
        color: #6B7280;
    }
    
    /* Order Items Styles */
    .order-info {
        background: #F0F9FF;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
    
    .order-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .info-item {
        margin-bottom: 10px;
    }
    
    .info-label {
        font-size: 12px;
        color: #6B7280;
        display: block;
        margin-bottom: 4px;
    }
    
    .info-value {
        font-weight: 600;
        color: #1F2937;
    }
    
    .info-value.amount {
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .items-table th,
    .items-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .items-table th {
        background: #F3F4F6;
        font-weight: 600;
        font-size: 13px;
    }
    
    .total-row {
        background: #F9FAFB;
        font-weight: bold;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: #D1D5DB;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 18px;
        color: #374151;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    .text-center {
        text-align: center;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-card {
            padding: 15px;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
        
        .stat-number {
            font-size: 20px;
        }
        
        .search-form {
            flex-direction: column;
        }
        
        .search-box {
            width: 100%;
        }
        
        .filter-tabs {
            justify-content: center;
        }
        
        .btn-search, .btn-clear {
            width: 100%;
            text-align: center;
        }
        
        .data-table th,
        .data-table td {
            padding: 10px 8px;
            font-size: 12px;
        }
        
        .btn-deliver {
            padding: 4px 10px;
            font-size: 11px;
        }
        
        .order-info-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }
    }
</style>

<script>
// View Order Items
function viewOrderItems(poId) {
    const modalBody = document.getElementById('itemsModalBody');
    modalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading order details...</div>';
    document.getElementById('itemsModal').style.display = 'flex';
    
    fetch(`../procurement/get_po_details.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>${data.error}</p></div>`;
                return;
            }
            
            let itemsHtml = '';
            data.items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td><strong>${item.item_name}</strong></td>
                        <td>${item.quantity} units</td>
                        <td>TZS ${parseFloat(item.unit_price).toLocaleString()}</td>
                        <td class="amount">TZS ${parseFloat(item.total_price).toLocaleString()}</td>
                    </tr>
                `;
            });
            
            modalBody.innerHTML = `
                <div class="order-info">
                    <div class="order-info-grid">
                        <div>
                            <div class="info-item">
                                <span class="info-label">PO Number</span>
                                <span class="info-value">${data.po.po_number}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Order Date</span>
                                <span class="info-value">${data.po.order_date}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Expected Delivery</span>
                                <span class="info-value">${data.po.expected_delivery || 'Not specified'}</span>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="status-badge status-${data.po.status}">
                                        ${data.po.status.toUpperCase()}
                                    </span>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Created By</span>
                                <span class="info-value">${data.po.created_by_name}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Approved By</span>
                                <span class="info-value">${data.po.approved_by_name || 'Pending'}</span>
                            </div>
                        </div>
                    </div>
                    ${data.po.notes ? `<div class="info-item" style="margin-top: 10px;">
                        <span class="info-label">Notes</span>
                        <span class="info-value">${data.po.notes}</span>
                    </div>` : ''}
                </div>
                
                <h4 style="margin: 20px 0 15px;"><i class="fas fa-list"></i> Items to Supply</h4>
                <div class="table-responsive">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                            <tr class="total-row">
                                <td colspan="3" style="text-align: right;"><strong>Grand Total:</strong></td>
                                <td><strong>TZS ${parseFloat(data.po.total_amount).toLocaleString()}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
        })
        .catch(error => {
            modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading order details</p></div>`;
        });
}

// Mark as Delivered
function markAsDelivered(poId) {
    if (confirm('Are you sure you want to mark this order as DELIVERED?\n\nThis will notify the hotel that the items have been delivered.')) {
        window.location.href = `?mark_delivered=${poId}&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>`;
    }
}

// Close Modal
function closeItemsModal() {
    document.getElementById('itemsModal').style.display = 'none';
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('itemsModal');
    if (event.target === modal) {
        closeItemsModal();
    }
}

// Auto-search on input (debounced)
let searchTimeout;
const searchInput = document.querySelector('.search-box input');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    });
}

// Animate rows
document.querySelectorAll('.order-row').forEach((row, index) => {
    row.style.animationDelay = `${index * 0.03}s`;
});

// PHP Session Toast Messages
<?php if(isset($_SESSION['toast_message'])): ?>
    showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type'] ?? 'success'; ?>');
    <?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); ?>
<?php endif; ?>
</script>

<?php include '../templates/footer.php'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer', 'Hotel Manager']);

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$user_role = $_SESSION['role'];

// Build query with filters
$sql = "SELECT po.*, s.company_name as supplier_name, 
        u.fullname as created_by_name,
        a.fullname as approved_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN users u ON po.created_by = u.id
        LEFT JOIN users a ON po.approved_by = a.id
        WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND po.status = '" . $db->real_escape_string($status_filter) . "'";
}

if (!empty($search)) {
    $search = $db->real_escape_string($search);
    $sql .= " AND (po.po_number LIKE '%$search%' OR s.company_name LIKE '%$search%')";
}

$sql .= " ORDER BY po.created_at DESC";

$result = $db->query($sql);
$orders = $result->fetch_all(MYSQLI_ASSOC);

// Get counts for each status
$status_counts = [];
$count_sql = "SELECT status, COUNT(*) as count FROM purchase_orders GROUP BY status";
$count_result = $db->query($count_sql);
while ($row = $count_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

// Calculate totals
$total_amount = 0;
foreach ($orders as $order) {
    $total_amount += $order['total_amount'];
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Purchase Orders</h1>
        <p>View and track all purchase orders</p>
    </div>
    
    <!-- Stats Summary Row -->
    <div class="stats-row">
        <div class="stat-mini-card">
            <div class="stat-mini-icon total">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-mini-info">
                <span class="stat-mini-label">Total Orders</span>
                <span class="stat-mini-value"><?php echo count($orders); ?></span>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-mini-info">
                <span class="stat-mini-label">Total Value</span>
                <span class="stat-mini-value">TZS <?php echo number_format($total_amount, 0); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <form method="GET" action="" class="search-form" id="filterForm">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by PO number or supplier..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-tabs-wrapper">
                <div class="filter-tabs">
                    <a href="?status=all&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All
                        <span class="count"><?php echo array_sum($status_counts); ?></span>
                    </a>
                    <a href="?status=pending&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Pending
                        <span class="count pending"><?php echo $status_counts['pending'] ?? 0; ?></span>
                    </a>
                    <a href="?status=approved&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Approved
                        <span class="count approved"><?php echo $status_counts['approved'] ?? 0; ?></span>
                    </a>
                    <a href="?status=rejected&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle"></i> Rejected
                        <span class="count rejected"><?php echo $status_counts['rejected'] ?? 0; ?></span>
                    </a>
                    <a href="?status=delivered&search=<?php echo urlencode($search); ?>" class="filter-tab <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
                        <i class="fas fa-truck"></i> Delivered
                        <span class="count delivered"><?php echo $status_counts['delivered'] ?? 0; ?></span>
                    </a>
                </div>
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
        
        <div class="action-buttons-header">
            <a href="create_po.php" class="btn-primary">
                <i class="fas fa-plus"></i> New PO
            </a>
            <button onclick="window.print()" class="btn-secondary" title="Print Report">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice"></i> Purchase Orders List</h3>
            <div class="card-header-info">
                Showing <?php echo count($orders); ?> order(s)
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table" id="ordersTable">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Order Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($orders) > 0): ?>
                            <?php foreach($orders as $index => $order): ?>
                                <tr class="order-row" data-status="<?php echo $order['status']; ?>" style="animation-delay: <?php echo $index * 0.03; ?>s">
                                    <td>
                                        <div class="po-cell">
                                            <strong class="po-number"><?php echo $order['po_number']; ?></strong>
                                            <div class="po-meta">
                                                <i class="fas fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($order['order_date'])); ?>
                                            </div>
                                        </div>
                            </td>
                                    <td>
                                        <div class="supplier-cell">
                                            <i class="fas fa-building"></i>
                                            <?php echo htmlspecialchars($order['supplier_name']); ?>
                                        </div>
                            </td>
                                    <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <div class="amount-cell">
                                            TZS <?php echo number_format($order['total_amount'], 2); ?>
                                        </div>
                            </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <i class="fas <?php 
                                                echo $order['status'] == 'pending' ? 'fa-clock' : 
                                                    ($order['status'] == 'approved' ? 'fa-check-circle' : 
                                                    ($order['status'] == 'rejected' ? 'fa-times-circle' : 
                                                    ($order['status'] == 'delivered' ? 'fa-truck' : 'fa-check'))); 
                                            ?>"></i>
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="creator-cell">
                                            <?php echo htmlspecialchars($order['created_by_name']); ?>
                                            <div class="creator-date"><?php echo date('d M', strtotime($order['created_at'])); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="viewPO(<?php echo $order['id']; ?>)" class="btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if($order['status'] == 'pending' && $user_role == 'Hotel Manager'): ?>
                                                <button onclick="approvePO(<?php echo $order['id']; ?>)" class="btn-icon approve" title="Approve">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button onclick="rejectPO(<?php echo $order['id']; ?>)" class="btn-icon reject" title="Reject">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if($order['status'] == 'approved' && $user_role == 'Procurement Officer'): ?>
                                                <a href="track_delivery.php" class="btn-icon track" title="Track Delivery">
                                                    <i class="fas fa-truck"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Purchase Orders Found</h4>
                                        <p>No orders match your search criteria</p>
                                        <a href="create_po.php" class="btn-primary" style="margin-top: 15px;">
                                            <i class="fas fa-plus"></i> Create First PO
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
</div>

<!-- PO Details Modal -->
<div id="poModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice"></i> Purchase Order Details</h3>
            <div class="modal-actions">
                <button onclick="printPO()" class="btn-icon" title="Print">
                    <i class="fas fa-print"></i>
                </button>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="poModalBody">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
    </div>
</div>

<style>
    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-mini-card {
        background: white;
        border-radius: 14px;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .stat-mini-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    
    .stat-mini-icon.total {
        background: #1E3A8A20;
        color: #1E3A8A;
    }
    
    .stat-mini-icon.pending {
        background: #FEF3C7;
        color: #F59E0B;
    }
    
    .stat-mini-info {
        flex: 1;
    }
    
    .stat-mini-label {
        font-size: 12px;
        color: #6B7280;
        display: block;
    }
    
    .stat-mini-value {
        font-size: 18px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    /* Search and Filter Bar */
    .search-filter-bar {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .search-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 15px;
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
    
    .filter-tabs-wrapper {
        flex: 2;
        overflow-x: auto;
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
        white-space: nowrap;
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
    
    .filter-tab:hover, .filter-tab.active {
        background: #1E3A8A;
        color: white;
    }
    
    .filter-tab:hover .count, .filter-tab.active .count {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .btn-search, .btn-clear {
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
    }
    
    .btn-search {
        background: #1E3A8A;
        color: white;
    }
    
    .btn-search:hover {
        background: #2563EB;
    }
    
    .btn-clear {
        background: #F3F4F6;
        color: #374151;
        text-decoration: none;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
    .action-buttons-header {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .btn-primary {
        background: #FF6B6B;
        color: white;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
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
        padding: 10px 16px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-secondary:hover {
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
    
    .supplier-cell i {
        color: #9CA3AF;
        margin-right: 6px;
        width: 16px;
    }
    
    .amount-cell {
        font-weight: 600;
        color: #1E3A8A;
    }
    
    .creator-cell {
        font-size: 13px;
    }
    
    .creator-date {
        font-size: 10px;
        color: #9CA3AF;
        margin-top: 2px;
    }
    
    /* Status Badge */
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
    
    .status-rejected {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .status-delivered {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .btn-icon {
        width: 34px;
        height: 34px;
        background: #F3F4F6;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        color: #6B7280;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
    }
    
    .btn-icon.approve:hover {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .btn-icon.reject:hover {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .btn-icon.track:hover {
        background: #DBEAFE;
        color: #1E40AF;
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
        max-width: 850px;
        max-height: 85vh;
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
        z-index: 10;
        border-radius: 20px 20px 0 0;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .modal-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-actions .btn-icon {
        background: white;
        border: 1px solid #E5E7EB;
    }
    
    .modal-header .close {
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
        line-height: 1;
        margin-left: 5px;
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
    
    /* PO Details Styles */
    .po-details {
        line-height: 1.6;
    }
    
    .po-header {
        background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
    
    .po-header-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .po-header-item {
        margin-bottom: 10px;
    }
    
    .po-header-label {
        font-size: 12px;
        color: #6B7280;
        display: block;
        margin-bottom: 4px;
    }
    
    .po-header-value {
        font-weight: 600;
        color: #1F2937;
    }
    
    .po-header-value.amount {
        color: #1E3A8A;
        font-size: 18px;
    }
    
    /* Status Timeline */
    .status-timeline {
        background: #F9FAFB;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .timeline-title {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .timeline-steps {
        display: flex;
        justify-content: space-between;
        position: relative;
    }
    
    .timeline-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 10%;
        right: 10%;
        height: 2px;
        background: #E5E7EB;
        z-index: 1;
    }
    
    .timeline-step {
        text-align: center;
        z-index: 2;
        background: #F9FAFB;
        flex: 1;
        position: relative;
    }
    
    .timeline-icon {
        width: 40px;
        height: 40px;
        background: #E5E7EB;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px;
        color: #9CA3AF;
    }
    
    .timeline-step.completed .timeline-icon {
        background: #10B981;
        color: white;
    }
    
    .timeline-step.active .timeline-icon {
        background: #1E3A8A;
        color: white;
    }
    
    .timeline-step.rejected .timeline-icon {
        background: #EF4444;
        color: white;
    }
    
    .timeline-label {
        font-size: 11px;
        font-weight: 600;
        color: #6B7280;
    }
    
    .timeline-step.completed .timeline-label,
    .timeline-step.active .timeline-label {
        color: #1E3A8A;
    }
    
    .timeline-step.rejected .timeline-label {
        color: #EF4444;
    }
    
    .timeline-date {
        font-size: 10px;
        color: #9CA3AF;
        margin-top: 4px;
    }
    
    /* PO Items Table */
    .po-items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .po-items-table th,
    .po-items-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .po-items-table th {
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
    
    .empty-state h4 {
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
    
    /* Print Styles */
    @media print {
        .sidebar, .top-header, .search-filter-bar, .stats-row, .action-buttons-header, .modal-actions, .fab, .btn-icon, .btn-primary, .btn-secondary {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
        
        .modal {
            display: block !important;
            position: relative;
            background: white;
        }
        
        .modal-content {
            box-shadow: none;
            max-height: none;
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .search-form {
            flex-direction: column;
        }
        
        .search-box {
            width: 100%;
        }
        
        .filter-tabs-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        
        .action-buttons-header {
            justify-content: stretch;
        }
        
        .action-buttons-header .btn-primary,
        .action-buttons-header .btn-secondary {
            flex: 1;
            text-align: center;
            justify-content: center;
        }
        
        .data-table th, 
        .data-table td {
            padding: 10px 8px;
            font-size: 12px;
        }
        
        .po-header-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        
        .timeline-steps {
            flex-direction: column;
            gap: 15px;
        }
        
        .timeline-steps::before {
            display: none;
        }
        
        .timeline-step {
            display: flex;
            align-items: center;
            gap: 15px;
            text-align: left;
        }
        
        .timeline-icon {
            margin: 0;
        }
        
        .action-buttons {
            flex-wrap: wrap;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-row {
            grid-template-columns: 1fr;
        }
        
        .filter-tabs {
            flex-wrap: nowrap;
        }
        
        .po-items-table {
            font-size: 11px;
        }
        
        .po-items-table th,
        .po-items-table td {
            padding: 6px;
        }
    }
</style>

<script>
// View PO Details
function viewPO(id) {
    const modalBody = document.getElementById('poModalBody');
    modalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    document.getElementById('poModal').style.display = 'flex';
    
    fetch(`get_po_details.php?id=${id}`)
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
                        <td><strong>${item.item_name}</strong> ${item.unit ? '(' + item.unit + ')' : ''}</td
                        <td>${item.quantity} units</td
                        <td>TZS ${parseFloat(item.unit_price).toLocaleString()}</td
                        <td class="amount">TZS ${parseFloat(item.total_price).toLocaleString()}</td
                    </tr>
                `;
            });
            
            // Determine timeline status
            let timelineClass = '';
            let rejectClass = '';
            if (data.po.status === 'rejected') {
                timelineClass = 'rejected';
                rejectClass = 'rejected';
            }
            
            modalBody.innerHTML = `
                <div class="po-details">
                    <div class="po-header">
                        <div class="po-header-grid">
                            <div>
                                <div class="po-header-item">
                                    <span class="po-header-label">PO Number</span>
                                    <span class="po-header-value">${data.po.po_number}</span>
                                </div>
                                <div class="po-header-item">
                                    <span class="po-header-label">Order Date</span>
                                    <span class="po-header-value">${data.po.order_date}</span>
                                </div>
                                <div class="po-header-item">
                                    <span class="po-header-label">Expected Delivery</span>
                                    <span class="po-header-value">${data.po.expected_delivery || 'Not specified'}</span>
                                </div>
                            </div>
                            <div>
                                <div class="po-header-item">
                                    <span class="po-header-label">Supplier</span>
                                    <span class="po-header-value">${data.po.supplier_name}</span>
                                </div>
                                <div class="po-header-item">
                                    <span class="po-header-label">Contact</span>
                                    <span class="po-header-value">${data.po.contact_person || 'N/A'} | ${data.po.supplier_phone || 'N/A'}</span>
                                </div>
                                <div class="po-header-item">
                                    <span class="po-header-label">Total Amount</span>
                                    <span class="po-header-value amount">TZS ${parseFloat(data.po.total_amount).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>
                        ${data.po.notes ? `<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(30,58,138,0.1);">
                            <span class="po-header-label">Notes</span>
                            <p style="margin: 5px 0 0; font-size: 13px;">${data.po.notes}</p>
                        </div>` : ''}
                    </div>
                    
                    <!-- Status Timeline -->
                    <div class="status-timeline">
                        <div class="timeline-title">
                            <i class="fas fa-chart-line"></i> Order Status Timeline
                        </div>
                        <div class="timeline-steps">
                            <div class="timeline-step ${data.po.status === 'pending' || data.po.status === 'approved' || data.po.status === 'delivered' ? 'completed' : ''}">
                                <div class="timeline-icon"><i class="fas fa-file-invoice"></i></div>
                                <div class="timeline-label">Created</div>
                                <div class="timeline-date">${new Date(data.po.created_at).toLocaleDateString()}</div>
                            </div>
                            <div class="timeline-step ${data.po.status === 'approved' || data.po.status === 'delivered' ? 'completed' : ''} ${data.po.status === 'rejected' ? 'rejected' : ''}">
                                <div class="timeline-icon"><i class="fas fa-check-circle"></i></div>
                                <div class="timeline-label">${data.po.status === 'rejected' ? 'Rejected' : 'Approved'}</div>
                                <div class="timeline-date">${data.po.approved_by_name ? 'By: ' + data.po.approved_by_name : 'Pending'}</div>
                            </div>
                            <div class="timeline-step ${data.po.status === 'delivered' ? 'completed' : ''}">
                                <div class="timeline-icon"><i class="fas fa-truck"></i></div>
                                <div class="timeline-label">Delivered</div>
                                <div class="timeline-date">${data.po.status === 'delivered' ? 'Completed' : 'Pending'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 style="margin: 20px 0 15px;"><i class="fas fa-list"></i> Order Items</h4>
                    <div class="table-responsive">
                        <table class="po-items-table">
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
                </div>
            `;
        })
        .catch(error => {
            modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading details</p></div>`;
        });
}

// Approve PO
function approvePO(id) {
    if (confirm('Are you sure you want to APPROVE this purchase order?')) {
        window.location.href = `../manager/approve_po.php?id=${id}&action=approve`;
    }
}

// Reject PO
function rejectPO(id) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason !== null && reason.trim() !== '') {
        window.location.href = `../manager/approve_po.php?id=${id}&action=reject&reason=${encodeURIComponent(reason)}`;
    } else if (reason !== null) {
        alert('Please provide a reason for rejection.');
    }
}

// Close modal
function closeModal() {
    document.getElementById('poModal').style.display = 'none';
}

// Print PO
function printPO() {
    window.print();
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('poModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Auto-submit search on input (debounced)
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
</script>

<?php include '../templates/footer.php'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer', 'Hotel Manager']);

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Build query with filters
$sql = "SELECT po.*, s.company_name as supplier_name, 
        s.contact_person, s.email as supplier_email, s.phone as supplier_phone,
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
    $sql .= " AND (po.po_number LIKE '%$search%' OR s.company_name LIKE '%$search%' OR u.fullname LIKE '%$search%')";
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

// Calculate totals by status
$total_pending = 0;
$total_approved = 0;
$total_rejected = 0;
$total_delivered = 0;
$total_all = 0;

foreach ($orders as $order) {
    $total_all += $order['total_amount'];
    if ($order['status'] == 'pending') $total_pending += $order['total_amount'];
    if ($order['status'] == 'approved') $total_approved += $order['total_amount'];
    if ($order['status'] == 'rejected') $total_rejected += $order['total_amount'];
    if ($order['status'] == 'delivered') $total_delivered += $order['total_amount'];
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Purchase Orders</h1>
        <p>View and track all purchase orders history</p>
    </div>
    
    <!-- Stats Cards Row -->
    <div class="stats-grid">
        <div class="stat-card total-card">
            <div class="stat-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Total Orders</span>
                <span class="stat-value"><?php echo count($orders); ?></span>
                <span class="stat-sub">All time</span>
            </div>
        </div>
        <div class="stat-card pending-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Pending Value</span>
                <span class="stat-value">TZS <?php echo number_format($total_pending, 0); ?></span>
                <span class="stat-sub"><?php echo $status_counts['pending'] ?? 0; ?> orders</span>
            </div>
        </div>
        <div class="stat-card approved-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Approved Value</span>
                <span class="stat-value">TZS <?php echo number_format($total_approved, 0); ?></span>
                <span class="stat-sub"><?php echo $status_counts['approved'] ?? 0; ?> orders</span>
            </div>
        </div>
        <div class="stat-card delivered-card">
            <div class="stat-icon">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Delivered Value</span>
                <span class="stat-value">TZS <?php echo number_format($total_delivered, 0); ?></span>
                <span class="stat-sub"><?php echo $status_counts['delivered'] ?? 0; ?> orders</span>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="filter-container">
        <form method="GET" action="" class="filter-form" id="filterForm">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" placeholder="Search by PO number, supplier or requester..." 
                       value="<?php echo htmlspecialchars($search); ?>" class="search-input">
            </div>
            <div class="filter-tabs">
                <a href="?status=all&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All
                    <span class="badge"><?php echo array_sum($status_counts); ?></span>
                </a>
                <a href="?status=pending&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab pending-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-hourglass-half"></i> Pending
                    <span class="badge"><?php echo $status_counts['pending'] ?? 0; ?></span>
                </a>
                <a href="?status=approved&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab approved-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Approved
                    <span class="badge"><?php echo $status_counts['approved'] ?? 0; ?></span>
                </a>
                <a href="?status=rejected&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab rejected-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Rejected
                    <span class="badge"><?php echo $status_counts['rejected'] ?? 0; ?></span>
                </a>
                <a href="?status=delivered&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab delivered-tab <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i> Delivered
                    <span class="badge"><?php echo $status_counts['delivered'] ?? 0; ?></span>
                </a>
            </div>
            <div class="filter-actions">
                <?php if(!empty($search)): ?>
                    <a href="?status=<?php echo $status_filter; ?>" class="btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="create_po.php" class="btn-create">
                    <i class="fas fa-plus"></i> New PO
                </a>
                <button type="button" onclick="window.print()" class="btn-print" title="Print Report">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Orders Table -->
    <div class="data-card">
        <div class="card-header">
            <div class="header-title">
                <i class="fas fa-file-invoice"></i>
                <h3>Purchase Orders List</h3>
            </div>
            <div class="header-info">
                Showing <strong><?php echo count($orders); ?></strong> order(s)
                <?php if($status_filter != 'all'): ?>
                    <span class="filter-badge">Filter: <?php echo ucfirst($status_filter); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="table-wrapper">
            <table class="orders-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>PO Details</th>
                        <th>Supplier</th>
                        <th>Order Info</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Approval Info</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($orders) > 0): ?>
                        <?php foreach($orders as $index => $order): ?>
                            <tr class="order-row" data-status="<?php echo $order['status']; ?>" style="animation-delay: <?php echo $index * 0.02; ?>s">
                                <td class="po-cell">
                                    <div class="po-number">
                                        <i class="fas fa-hashtag"></i>
                                        <strong><?php echo htmlspecialchars($order['po_number']); ?></strong>
                                    </div>
                                    <div class="po-meta">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <?php echo date('d M Y', strtotime($order['order_date'])); ?>
                                    </div>
                                    <div class="po-meta">
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($order['created_by_name']); ?>
                                    </div>
                                 </td>
                                <td class="supplier-cell">
                                    <div class="supplier-name">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($order['supplier_name']); ?>
                                    </div>
                                    <?php if(!empty($order['contact_person'])): ?>
                                        <div class="supplier-contact">
                                            <i class="fas fa-user-tie"></i> 
                                            <?php echo htmlspecialchars($order['contact_person']); ?>
                                        </div>
                                    <?php endif; ?>
                                 </td>
                                <td class="info-cell">
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Order: <?php echo date('d/m/Y', strtotime($order['order_date'])); ?></span>
                                    </div>
                                    <?php if(!empty($order['expected_delivery'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-truck"></i>
                                            <span>Expected: <?php echo date('d/m/Y', strtotime($order['expected_delivery'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="info-item">
                                            <i class="fas fa-truck"></i>
                                            <span>Expected: Not specified</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if(!empty($order['approved_at'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-check-double"></i>
                                            <span>Processed: <?php echo date('d/m/Y', strtotime($order['approved_at'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                 </td>
                                <td class="amount-cell">
                                    <div class="amount-value">
                                        TZS <?php echo number_format($order['total_amount'], 0); ?>
                                    </div>
                                    <?php if($order['status'] == 'pending'): ?>
                                        <div class="amount-pending-badge">Awaiting approval</div>
                                    <?php endif; ?>
                                 </td>
                                <td class="status-cell">
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <i class="fas <?php 
                                            echo $order['status'] == 'pending' ? 'fa-hourglass-half' : 
                                                ($order['status'] == 'approved' ? 'fa-check-circle' : 
                                                ($order['status'] == 'rejected' ? 'fa-times-circle' : 
                                                ($order['status'] == 'delivered' ? 'fa-truck' : 'fa-check'))); 
                                        ?>"></i>
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    <?php if($order['status'] == 'rejected' && !empty($order['rejection_reason'])): ?>
                                        <div class="rejection-tooltip" title="<?php echo htmlspecialchars($order['rejection_reason']); ?>">
                                            <i class="fas fa-comment-dots"></i> Has reason
                                        </div>
                                    <?php endif; ?>
                                 </td>
                                <td class="approval-cell">
                                    <?php if(!empty($order['approved_by_name'])): ?>
                                        <div class="approved-by">
                                            <i class="fas fa-user-check"></i>
                                            <?php echo htmlspecialchars($order['approved_by_name']); ?>
                                        </div>
                                        <div class="approved-date">
                                            <i class="fas fa-clock"></i>
                                            <?php echo !empty($order['approved_at']) ? date('d M Y H:i', strtotime($order['approved_at'])) : 'N/A'; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="not-approved">Not processed yet</span>
                                    <?php endif; ?>
                                 </td>
                                <td class="actions-cell">
                                    <button onclick="viewPODetails(<?php echo $order['id']; ?>)" class="action-btn view-btn" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if($order['status'] == 'pending' && $user_role == 'Hotel Manager'): ?>
                                        <button onclick="approvePO(<?php echo $order['id']; ?>)" class="action-btn approve-btn" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="rejectPO(<?php echo $order['id']; ?>)" class="action-btn reject-btn" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if($order['status'] == 'approved' && $user_role == 'Procurement Officer'): ?>
                                        <button onclick="markAsDelivered(<?php echo $order['id']; ?>)" class="action-btn deliver-btn" title="Mark as Delivered">
                                            <i class="fas fa-truck"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if($order['status'] == 'rejected' && !empty($order['rejection_reason'])): ?>
                                        <button onclick="showRejectionReason('<?php echo htmlspecialchars(addslashes($order['rejection_reason'])); ?>')" 
                                                class="action-btn reason-btn" title="View Rejection Reason">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                    <?php endif; ?>
                                 </td>
                             </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-row">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>No Purchase Orders Found</h4>
                                    <p>No orders match your search criteria</p>
                                    <a href="create_po.php" class="btn-create-empty">
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

<!-- PO Details Modal -->
<div id="poModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-file-invoice"></i>
                <h3>Purchase Order Details</h3>
            </div>
            <div class="modal-actions">
                <button onclick="printPO()" class="modal-btn print" title="Print">
                    <i class="fas fa-print"></i>
                </button>
                <button class="modal-btn close" onclick="closeModal()">&times;</button>
            </div>
        </div>
        <div class="modal-body" id="poModalBody">
            <div class="loading-container">
                <div class="spinner"></div>
                <p>Loading order details...</p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Main Layout */
    .main-content {
        padding: 20px;
        background: #F3F4F6;
        min-height: 100vh;
    }
    
    .page-header {
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 24px;
        color: #1E3A8A;
        margin: 0 0 5px 0;
    }
    
    .page-header p {
        color: #6B7280;
        margin: 0;
    }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
    
    .total-card .stat-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .pending-card .stat-icon {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .approved-card .stat-icon {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    
    .delivered-card .stat-icon {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }
    
    .stat-details {
        flex: 1;
    }
    
    .stat-label {
        font-size: 12px;
        color: #6B7280;
        display: block;
    }
    
    .stat-value {
        font-size: 22px;
        font-weight: 700;
        color: #1F2937;
        display: block;
        margin: 5px 0;
    }
    
    .stat-sub {
        font-size: 11px;
        color: #9CA3AF;
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
        flex-wrap: wrap;
        gap: 15px;
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
    
    .filter-tabs {
        flex: 3;
        display: flex;
        gap: 10px;
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
    
    .filter-tab .badge {
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
    
    .filter-tab:hover .badge, .filter-tab.active .badge {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .filter-tab.pending-tab.active {
        background: #F59E0B;
    }
    
    .filter-tab.approved-tab.active {
        background: #10B981;
    }
    
    .filter-tab.rejected-tab.active {
        background: #EF4444;
    }
    
    .filter-tab.delivered-tab.active {
        background: #3B82F6;
    }
    
    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .btn-clear, .btn-filter, .btn-create, .btn-print {
        padding: 10px 18px;
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
        transform: translateY(-1px);
    }
    
    .btn-clear {
        background: #F3F4F6;
        color: #374151;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
    .btn-create {
        background: #FF6B6B;
        color: white;
    }
    
    .btn-create:hover {
        background: #e55a5a;
        transform: translateY(-1px);
    }
    
    .btn-print {
        background: #F3F4F6;
        color: #374151;
    }
    
    .btn-print:hover {
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
        font-size: 16px;
        color: #1E3A8A;
    }
    
    .header-info {
        font-size: 13px;
        color: #6B7280;
    }
    
    .filter-badge {
        background: #1E3A8A;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        margin-left: 10px;
        font-size: 11px;
    }
    
    /* Table Styles */
    .table-wrapper {
        overflow-x: auto;
    }
    
    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .orders-table thead {
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .orders-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .orders-table td {
        padding: 16px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
        vertical-align: top;
    }
    
    .order-row {
        transition: background 0.2s;
        animation: fadeIn 0.3s ease backwards;
    }
    
    @keyframes fadeIn {
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
    
    /* Cell Specific Styles */
    .po-number {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
        font-size: 14px;
    }
    
    .po-number strong {
        color: #1E3A8A;
    }
    
    .po-meta {
        font-size: 11px;
        color: #9CA3AF;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .supplier-name {
        font-weight: 500;
        color: #374151;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .supplier-contact {
        font-size: 11px;
        color: #6B7280;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .info-cell {
        font-size: 12px;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 5px;
        color: #6B7280;
    }
    
    .info-item i {
        width: 16px;
        font-size: 11px;
    }
    
    .amount-value {
        font-size: 16px;
        font-weight: 700;
        color: #1E3A8A;
        margin-bottom: 5px;
    }
    
    .amount-pending-badge {
        font-size: 10px;
        color: #F59E0B;
        background: #FEF3C7;
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
    }
    
    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 5px;
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
    
    .rejection-tooltip {
        font-size: 10px;
        color: #EF4444;
        cursor: help;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 5px;
    }
    
    /* Approval Cell */
    .approved-by {
        font-size: 12px;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 3px;
    }
    
    .approved-date {
        font-size: 10px;
        color: #9CA3AF;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .not-approved {
        font-size: 11px;
        color: #9CA3AF;
        font-style: italic;
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
        font-size: 14px;
    }
    
    .view-btn {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    .view-btn:hover {
        background: #BFDBFE;
        transform: translateY(-2px);
    }
    
    .approve-btn {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .approve-btn:hover {
        background: #A7F3D0;
        transform: translateY(-2px);
    }
    
    .reject-btn {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .reject-btn:hover {
        background: #FECACA;
        transform: translateY(-2px);
    }
    
    .deliver-btn {
        background: #FEF3C7;
        color: #F59E0B;
    }
    
    .deliver-btn:hover {
        background: #FDE68A;
        transform: translateY(-2px);
    }
    
    .reason-btn {
        background: #F3F4F6;
        color: #6B7280;
    }
    
    .reason-btn:hover {
        background: #E5E7EB;
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
    
    /* Modal Styles */
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
        max-width: 900px;
        max-height: 85vh;
        overflow: hidden;
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
    }
    
    .modal-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-title i {
        font-size: 20px;
        color: #1E3A8A;
    }
    
    .modal-title h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
    }
    
    .modal-btn {
        background: #F3F4F6;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .modal-btn.print {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        color: #374151;
    }
    
    .modal-btn.print:hover {
        background: #E5E7EB;
    }
    
    .modal-btn.close {
        font-size: 28px;
        color: #9CA3AF;
        background: none;
        line-height: 1;
    }
    
    .modal-btn.close:hover {
        color: #EF4444;
    }
    
    .modal-body {
        padding: 24px;
        overflow-y: auto;
        max-height: calc(85vh - 70px);
    }
    
    .loading-container {
        text-align: center;
        padding: 60px 20px;
    }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 3px solid #E5E7EB;
        border-top-color: #1E3A8A;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 15px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Print Styles */
    @media print {
        .sidebar, .top-header, .filter-container, .stats-grid, 
        .actions-cell, .btn-create, .btn-print, .modal-actions,
        .action-btn, .btn-filter, .btn-clear {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .data-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
        
        .orders-table {
            font-size: 10px;
        }
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-form {
            flex-direction: column;
        }
        
        .search-wrapper {
            width: 100%;
        }
        
        .filter-tabs {
            width: 100%;
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: 5px;
        }
        
        .filter-actions {
            width: 100%;
            justify-content: stretch;
        }
        
        .filter-actions .btn-clear,
        .filter-actions .btn-filter,
        .filter-actions .btn-create,
        .filter-actions .btn-print {
            flex: 1;
            justify-content: center;
        }
        
        .card-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 10px 8px;
        }
        
        .actions-cell {
            flex-direction: column;
        }
        
        .action-btn {
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
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
        
        .stat-value {
            font-size: 18px;
        }
    }
</style>

<script>
// View PO Details
function viewPODetails(id) {
    const modalBody = document.getElementById('poModalBody');
    modalBody.innerHTML = '<div class="loading-container"><div class="spinner"></div><p>Loading order details...</p></div>';
    document.getElementById('poModal').style.display = 'flex';
    
    fetch(`get_po_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>${data.error}</p></div>`;
                return;
            }
            
            let itemsHtml = '';
            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    itemsHtml += `
                        <tr>
                            <td><strong>${escapeHtml(item.item_name)}</strong> ${item.unit ? '(' + item.unit + ')' : ''}</td>
                            <td>${item.quantity} units</td
                            <td>TZS ${parseFloat(item.unit_price).toLocaleString()}</td
                            <td class="amount">TZS ${parseFloat(item.total_price).toLocaleString()}</td
                        </tr>
                    `;
                });
            } else {
                itemsHtml = '<tr><td colspan="4" class="text-center">No items found</td></tr>';
            }
            
            const rejectionReasonHtml = data.po.rejection_reason ? `
                <div class="rejection-box">
                    <div class="rejection-title">
                        <i class="fas fa-exclamation-triangle"></i> Rejection Reason
                    </div>
                    <div class="rejection-content">
                        ${escapeHtml(data.po.rejection_reason)}
                    </div>
                </div>
            ` : '';
            
            modalBody.innerHTML = `
                <div class="po-details">
                    <div class="po-header">
                        <div class="po-header-grid">
                            <div class="po-header-section">
                                <div class="po-header-item">
                                    <span class="po-header-label">PO Number</span>
                                    <span class="po-header-value">${escapeHtml(data.po.po_number)}</span>
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
                            <div class="po-header-section">
                                <div class="po-header-item">
                                    <span class="po-header-label">Supplier</span>
                                    <span class="po-header-value">${escapeHtml(data.po.supplier_name)}</span>
                                </div>
                                <div class="po-header-item">
                                    <span class="po-header-label">Contact Person</span>
                                    <span class="po-header-value">${escapeHtml(data.po.contact_person || 'N/A')}</span>
                                </div>
                                <div class="po-header-item">
                                    <span class="po-header-label">Total Amount</span>
                                    <span class="po-header-value amount">TZS ${parseFloat(data.po.total_amount).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>
                        ${data.po.notes ? `
                            <div class="po-notes">
                                <span class="po-header-label">Notes</span>
                                <p>${escapeHtml(data.po.notes)}</p>
                            </div>
                        ` : ''}
                        ${rejectionReasonHtml}
                    </div>
                    
                    ${data.po.approved_by_name ? `
                        <div class="approval-info">
                            <div class="approval-title">
                                <i class="fas fa-check-double"></i> Approval Information
                            </div>
                            <div class="approval-details">
                                <div><strong>Approved/Rejected By:</strong> ${escapeHtml(data.po.approved_by_name)}</div>
                                <div><strong>Processed On:</strong> ${data.po.approved_at ? new Date(data.po.approved_at).toLocaleString() : 'N/A'}</div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <h4 class="items-title"><i class="fas fa-list"></i> Order Items</h4>
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
            console.error('Error:', error);
            modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading order details. Please try again.</p></div>`;
        });
}

// Approve PO
function approvePO(id) {
    if (confirm('Are you sure you want to APPROVE this purchase order?\n\nThis will allow procurement to proceed with the order.')) {
        window.location.href = `../manager/approve_po.php?id=${id}&action=approve`;
    }
}

// Reject PO
function rejectPO(id) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason !== null && reason.trim() !== '') {
        if (confirm(`Are you sure you want to REJECT this purchase order?\n\nReason: ${reason}`)) {
            window.location.href = `../manager/approve_po.php?id=${id}&action=reject&reason=${encodeURIComponent(reason)}`;
        }
    } else if (reason !== null) {
        alert('Please provide a reason for rejection.');
    }
}

// Mark as Delivered
function markAsDelivered(id) {
    if (confirm('Are you sure you want to mark this purchase order as DELIVERED?\n\nThis will update inventory stock levels.')) {
        window.location.href = `mark_delivered.php?id=${id}`;
    }
}

// Show Rejection Reason
function showRejectionReason(reason) {
    alert('Rejection Reason:\n\n' + reason);
}

// Print PO
function printPO() {
    window.print();
}

// Close Modal
function closeModal() {
    document.getElementById('poModal').style.display = 'none';
}

// Helper Functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
const searchInput = document.querySelector('.search-input');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    });
}
</script>

<style>
    /* Additional styles for modal content */
    .po-header {
        background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
    
    .po-header-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .po-header-section {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .po-header-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        border-bottom: 1px solid rgba(30,58,138,0.1);
    }
    
    .po-header-label {
        font-size: 12px;
        color: #6B7280;
    }
    
    .po-header-value {
        font-weight: 600;
        color: #1F2937;
    }
    
    .po-header-value.amount {
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .po-notes {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(30,58,138,0.1);
    }
    
    .po-notes p {
        margin: 5px 0 0;
        font-size: 13px;
        color: #6B7280;
    }
    
    .rejection-box {
        margin-top: 15px;
        padding: 15px;
        background: #FEE2E2;
        border-radius: 8px;
        border-left: 4px solid #EF4444;
    }
    
    .rejection-title {
        font-weight: 600;
        color: #991B1B;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .rejection-content {
        color: #7F1D1D;
        font-size: 13px;
    }
    
    .approval-info {
        background: #F9FAFB;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .approval-title {
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .approval-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        font-size: 13px;
    }
    
    .items-title {
        margin: 20px 0 15px;
        color: #1E3A8A;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .po-items-table {
        width: 100%;
        border-collapse: collapse;
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
        font-size: 12px;
    }
    
    .po-items-table td.amount {
        font-weight: 600;
        color: #1E3A8A;
    }
    
    .total-row {
        background: #F9FAFB;
        font-weight: bold;
    }
    
    .total-row td {
        padding: 12px;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .text-center {
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .po-header-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .po-header-item {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .approval-details {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        
        .po-items-table th,
        .po-items-table td {
            padding: 8px;
            font-size: 12px;
        }
    }
</style>

<?php include '../templates/footer.php'; ?>
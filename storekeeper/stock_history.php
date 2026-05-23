<?php
/**
 * Stock History Page
 * Displays all inventory movements with advanced filtering
 * Accessible by: Admin, Hotel Manager, Storekeeper, Procurement Officer
 */

// ============================================
// INITIALIZATION & AUTHENTICATION
// ============================================

require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Check if user has access (Supplier cannot access)
checkAuth(['Admin', 'Hotel Manager', 'Storekeeper', 'Procurement Officer']);

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// ============================================
// PAGINATION SETUP
// ============================================

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // Records per page
$offset = ($page - 1) * $limit;

// ============================================
// FILTER PARAMETERS
// ============================================

$movement_type = $_GET['type'] ?? '';
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$date_preset = $_GET['date_preset'] ?? '';

// Apply date presets
if (!empty($date_preset)) {
    switch ($date_preset) {
        case 'today':
            $date_from = date('Y-m-d');
            $date_to = date('Y-m-d');
            break;
        case 'yesterday':
            $date_from = date('Y-m-d', strtotime('-1 day'));
            $date_to = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'this_week':
            $date_from = date('Y-m-d', strtotime('monday this week'));
            $date_to = date('Y-m-d');
            break;
        case 'this_month':
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-d');
            break;
        case 'last_month':
            $date_from = date('Y-m-01', strtotime('-1 month'));
            $date_to = date('Y-m-t', strtotime('-1 month'));
            break;
        case 'this_year':
            $date_from = date('Y-01-01');
            $date_to = date('Y-m-d');
            break;
    }
}

// ============================================
// BUILD WHERE CONDITIONS
// ============================================

$where_conditions = ["1=1"];

// Type filter (IN/OUT)
if (!empty($movement_type)) {
    $type_escaped = $db->real_escape_string($movement_type);
    $where_conditions[] = "sm.movement_type = '$type_escaped'";
}

// Item filter
if ($item_id > 0) {
    $where_conditions[] = "sm.item_id = $item_id";
}

// Date range filters
if (!empty($date_from)) {
    $where_conditions[] = "DATE(sm.created_at) >= '$date_from'";
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(sm.created_at) <= '$date_to'";
}

// Search filter (item name, user, reference)
if (!empty($search)) {
    $search_escaped = $db->real_escape_string($search);
    $where_conditions[] = "(i.item_name LIKE '%$search_escaped%' 
                           OR u.fullname LIKE '%$search_escaped%' 
                           OR sm.reference_no LIKE '%$search_escaped%')";
}

$where_clause = implode(' AND ', $where_conditions);

// ============================================
// GET TOTAL COUNT FOR PAGINATION
// ============================================

$count_sql = "SELECT COUNT(*) as total 
              FROM stock_movements sm
              JOIN inventory_items i ON sm.item_id = i.id
              JOIN users u ON sm.performed_by = u.id
              WHERE $where_clause";
$count_result = $db->query($count_sql);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

// ============================================
// GET STOCK MOVEMENTS DATA
// ============================================

$sql = "SELECT sm.*, 
               i.item_name, 
               i.unit, 
               u.fullname as performed_by_name,
               CASE 
                   WHEN sm.movement_type = 'IN' THEN 'Stock In'
                   ELSE 'Stock Out'
               END as movement_type_name
        FROM stock_movements sm
        JOIN inventory_items i ON sm.item_id = i.id
        JOIN users u ON sm.performed_by = u.id
        WHERE $where_clause
        ORDER BY sm.created_at DESC
        LIMIT $offset, $limit";

$result = $db->query($sql);
$movements = $result->fetch_all(MYSQLI_ASSOC);

// ============================================
// GET FILTER DROPDOWN DATA
// ============================================

// Get items for filter dropdown
$items_sql = "SELECT id, item_name FROM inventory_items WHERE status = 'active' ORDER BY item_name";
$items_result = $db->query($items_sql);
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// ============================================
// CALCULATE SUMMARY STATISTICS
// ============================================

$summary_sql = "SELECT 
                    SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE 0 END) as total_in,
                    SUM(CASE WHEN movement_type = 'OUT' THEN quantity ELSE 0 END) as total_out,
                    COUNT(*) as total_transactions,
                    COUNT(DISTINCT item_id) as unique_items
                FROM stock_movements sm
                WHERE $where_clause";
$summary_result = $db->query($summary_sql);
$summary = $summary_result->fetch_assoc();

// Calculate percentages for chart
$total_movements = $summary['total_in'] + $summary['total_out'];
$in_percentage = $total_movements > 0 ? round(($summary['total_in'] / $total_movements) * 100) : 0;
$out_percentage = $total_movements > 0 ? round(($summary['total_out'] / $total_movements) * 100) : 0;

// ============================================
// INCLUDE HEADER & SIDEBAR
// ============================================

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    
    <!-- ============================================ -->
    <!-- PAGE HEADER -->
    <!-- ============================================ -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-history"></i> Stock History</h1>
            <p>View and analyze all inventory movements and transactions</p>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="exportToExcel()" class="btn-primary">
                <i class="fas fa-file-excel"></i> Export
            </button>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- SUMMARY CARDS -->
    <!-- ============================================ -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon" style="background: #1E3A8A20;">
                <i class="fas fa-exchange-alt" style="color: #1E3A8A;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Total Transactions</span>
                <span class="summary-value"><?php echo number_format($summary['total_transactions']); ?></span>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background: #10B98120;">
                <i class="fas fa-arrow-down" style="color: #10B981;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Stock In</span>
                <span class="summary-value" style="color: #10B981;">+<?php echo number_format($summary['total_in']); ?></span>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background: #EF444420;">
                <i class="fas fa-arrow-up" style="color: #EF4444;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Stock Out</span>
                <span class="summary-value" style="color: #EF4444;">-<?php echo number_format($summary['total_out']); ?></span>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background: #F59E0B20;">
                <i class="fas fa-balance-scale" style="color: #F59E0B;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Net Movement</span>
                <span class="summary-value"><?php echo number_format($summary['total_in'] - $summary['total_out']); ?></span>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background: #8B5CF620;">
                <i class="fas fa-cubes" style="color: #8B5CF6;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Unique Items</span>
                <span class="summary-value"><?php echo number_format($summary['unique_items']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- FILTER BAR -->
    <!-- ============================================ -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form" id="filterForm">
            <!-- Quick Date Presets -->
            <div class="filter-group date-presets">
                <label>Quick Filter:</label>
                <select name="date_preset" onchange="this.form.submit()" class="preset-select">
                    <option value="">Select Period</option>
                    <option value="today" <?php echo $date_preset == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $date_preset == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="this_week" <?php echo $date_preset == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="this_month" <?php echo $date_preset == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="last_month" <?php echo $date_preset == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                    <option value="this_year" <?php echo $date_preset == 'this_year' ? 'selected' : ''; ?>>This Year</option>
                </select>
            </div>
            
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by item, user, or reference..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <!-- Movement Type Filter -->
            <div class="filter-group">
                <select name="type" class="filter-select">
                    <option value="">All Types</option>
                    <option value="IN" <?php echo $movement_type == 'IN' ? 'selected' : ''; ?>>📥 Stock In (Received)</option>
                    <option value="OUT" <?php echo $movement_type == 'OUT' ? 'selected' : ''; ?>>📤 Stock Out (Issued)</option>
                </select>
            </div>
            
            <!-- Item Filter -->
            <div class="filter-group">
                <select name="item_id" class="filter-select">
                    <option value="0">All Items</option>
                    <?php foreach($items as $item): ?>
                        <option value="<?php echo $item['id']; ?>" <?php echo $item_id == $item['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['item_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Date Range -->
            <div class="filter-group date-range">
                <input type="date" name="date_from" placeholder="From" value="<?php echo htmlspecialchars($date_from); ?>" class="date-input">
                <span class="date-sep">→</span>
                <input type="date" name="date_to" placeholder="To" value="<?php echo htmlspecialchars($date_to); ?>" class="date-input">
            </div>
            
            <!-- Action Buttons -->
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Apply
            </button>
            <a href="stock_history.php" class="btn-clear">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>
    
    <!-- ============================================ -->
    <!-- STOCK MOVEMENTS TABLE -->
    <!-- ============================================ -->
    <div class="card">
        <div class="card-header">
            <div class="header-left">
                <h3><i class="fas fa-list"></i> Stock Movement History</h3>
            </div>
            <div class="header-right">
                <div class="legend">
                    <span class="legend-item in"><i class="fas fa-circle"></i> Stock In</span>
                    <span class="legend-item out"><i class="fas fa-circle"></i> Stock Out</span>
                </div>
                <span class="item-count">
                    <i class="fas fa-database"></i> <?php echo count($movements); ?> of <?php echo number_format($total_items); ?> records
                </span>
            </div>
        </div>
        <div class="card-body">
            <?php if(count($movements) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table" id="stockHistoryTable">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th width="140">Date & Time</th>
                                <th>Item</th>
                                <th width="100">Type</th>
                                <th width="100">Quantity</th>
                                <th width="130">Reference</th>
                                <th width="150">Performed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = $offset + 1; foreach($movements as $movement): ?>
                                <tr class="movement-row" data-type="<?php echo $movement['movement_type']; ?>">
                                    <td class="text-center"><?php echo $counter++; ?></td>
                                    <td>
                                        <div class="date-cell">
                                            <div class="date-day"><?php echo date('d M Y', strtotime($movement['created_at'])); ?></div>
                                            <div class="date-time"><?php echo date('H:i:s', strtotime($movement['created_at'])); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="item-cell">
                                            <div class="item-name"><?php echo htmlspecialchars($movement['item_name']); ?></div>
                                            <div class="item-unit"><?php echo htmlspecialchars($movement['unit']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($movement['movement_type'] == 'IN'): ?>
                                            <span class="type-badge in">
                                                <i class="fas fa-arrow-down"></i> STOCK IN
                                            </span>
                                        <?php else: ?>
                                            <span class="type-badge out">
                                                <i class="fas fa-arrow-up"></i> STOCK OUT
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($movement['movement_type'] == 'IN'): ?>
                                            <span class="quantity in">
                                                <i class="fas fa-plus-circle"></i> +<?php echo number_format($movement['quantity']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="quantity out">
                                                <i class="fas fa-minus-circle"></i> -<?php echo number_format($movement['quantity']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($movement['reference_no']): ?>
                                            <span class="reference-badge">
                                                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($movement['reference_no']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="no-reference">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?php 
                                                    $name_parts = explode(' ', $movement['performed_by_name']);
                                                    $initial = strtoupper(substr($name_parts[0], 0, 1));
                                                ?>
                                                <span class="avatar-initial"><?php echo $initial; ?></span>
                                            </div>
                                            <span class="user-name"><?php echo htmlspecialchars($movement['performed_by_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="notes-cell" title="<?php echo htmlspecialchars($movement['notes'] ?? ''); ?>">
                                            <?php if($movement['notes']): ?>
                                                <?php echo nl2br(htmlspecialchars(substr($movement['notes'], 0, 60))); ?>
                                                <?php if(strlen($movement['notes']) > 60): ?>
                                                    <span class="notes-more">...</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="no-notes">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        <i class="fas fa-info-circle"></i>
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_items); ?> of <?php echo number_format($total_items); ?> entries
                    </div>
                    <div class="pagination-links">
                        <?php if($page > 1): ?>
                            <a href="?page=1&type=<?php echo urlencode($movement_type); ?>&item_id=<?php echo $item_id; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="First">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $page-1; ?>&type=<?php echo urlencode($movement_type); ?>&item_id=<?php echo $item_id; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Previous">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($movement_type); ?>&item_id=<?php echo $item_id; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&type=<?php echo urlencode($movement_type); ?>&item_id=<?php echo $item_id; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?>&type=<?php echo urlencode($movement_type); ?>&item_id=<?php echo $item_id; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Last">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h4>No Stock Movements Found</h4>
                    <p>Try adjusting your search or filter criteria to see more results.</p>
                    <a href="stock_history.php" class="btn-primary">
                        <i class="fas fa-sync-alt"></i> Reset Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ============================================ */
/* GLOBAL STYLES
/* ============================================ */

.main-content {
    padding: 24px;
}

/* ============================================ */
/* PAGE HEADER
/* ============================================ */

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
    margin-bottom: 5px;
}

.page-header p {
    color: #6B7280;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

/* ============================================ */
/* SUMMARY CARDS
/* ============================================ */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.summary-icon {
    width: 55px;
    height: 55px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.summary-info {
    flex: 1;
}

.summary-label {
    font-size: 13px;
    color: #6B7280;
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.summary-value {
    font-size: 24px;
    font-weight: 700;
    color: #1E3A8A;
}

/* ============================================ */
/* FILTER BAR
/* ============================================ */

.filter-bar {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    color: #6B7280;
    font-weight: 500;
}

.search-box {
    position: relative;
    flex: 2;
    min-width: 220px;
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
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: #1E3A8A;
    box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
}

.filter-select {
    padding: 10px 12px;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: #1E3A8A;
}

.preset-select {
    padding: 10px 12px;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    background: #F9FAFB;
    font-size: 14px;
    cursor: pointer;
    min-width: 140px;
}

.date-range {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-direction: row;
}

.date-range .date-input {
    padding: 10px 12px;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    font-size: 14px;
    width: 140px;
}

.date-sep {
    color: #9CA3AF;
    font-size: 14px;
}

.btn-filter, .btn-clear {
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    font-size: 14px;
}

.btn-filter {
    background: #1E3A8A;
    color: white;
}

.btn-filter:hover {
    background: #2563EB;
    transform: translateY(-2px);
}

.btn-clear {
    background: #F3F4F6;
    color: #374151;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-clear:hover {
    background: #E5E7EB;
}

/* ============================================ */
/* CARD STYLES
/* ============================================ */

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
    gap: 15px;
}

.card-header h3 {
    margin: 0;
    color: #1E3A8A;
    font-size: 18px;
}

.header-left, .header-right {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.legend {
    display: flex;
    gap: 15px;
}

.legend-item {
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.legend-item.in i {
    color: #10B981;
    font-size: 10px;
}

.legend-item.out i {
    color: #EF4444;
    font-size: 10px;
}

.item-count {
    font-size: 13px;
    color: #6B7280;
    background: #F3F4F6;
    padding: 5px 12px;
    border-radius: 20px;
}

.card-body {
    padding: 0;
}

/* ============================================ */
/* TABLE STYLES
/* ============================================ */

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #F9FAFB;
    border-bottom: 1px solid #E5E7EB;
}

.data-table th {
    padding: 14px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #E5E7EB;
    font-size: 13px;
    vertical-align: middle;
}

.data-table tbody tr:hover {
    background: #F9FAFB;
}

.text-center {
    text-align: center;
}

/* Date Cell */
.date-cell {
    line-height: 1.4;
}

.date-day {
    font-weight: 600;
    color: #1F2937;
}

.date-time {
    font-size: 11px;
    color: #9CA3AF;
}

/* Item Cell */
.item-cell {
    line-height: 1.4;
}

.item-name {
    font-weight: 600;
    color: #1F2937;
}

.item-unit {
    font-size: 11px;
    color: #9CA3AF;
}

/* Type Badge */
.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.type-badge.in {
    background: #D1FAE5;
    color: #065F46;
}

.type-badge.out {
    background: #FEE2E2;
    color: #991B1B;
}

/* Quantity */
.quantity {
    font-size: 14px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.quantity.in {
    color: #10B981;
}

.quantity.out {
    color: #EF4444;
}

/* Reference Badge */
.reference-badge {
    background: #F3F4F6;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    color: #374151;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.no-reference {
    color: #9CA3AF;
}

/* User Cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 28px;
    height: 28px;
    background: #1E3A8A;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-initial {
    font-size: 12px;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
}

.user-name {
    color: #374151;
}

/* Notes Cell */
.notes-cell {
    max-width: 220px;
    font-size: 12px;
    color: #6B7280;
    line-height: 1.4;
}

.no-notes {
    color: #D1D5DB;
}

.notes-more {
    color: #9CA3AF;
}

/* ============================================ */
/* PAGINATION
/* ============================================ */

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-top: 1px solid #E5E7EB;
    flex-wrap: wrap;
    gap: 15px;
    background: white;
}

.pagination-info {
    font-size: 13px;
    color: #6B7280;
    display: flex;
    align-items: center;
    gap: 6px;
}

.pagination-links {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.page-link {
    padding: 8px 14px;
    background: #F3F4F6;
    border-radius: 8px;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.page-link:hover {
    background: #1E3A8A;
    color: white;
}

.page-link.active {
    background: #1E3A8A;
    color: white;
}

/* ============================================ */
/* EMPTY STATE
/* ============================================ */

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: #F3F4F6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.empty-icon i {
    font-size: 40px;
    color: #9CA3AF;
}

.empty-state h4 {
    color: #374151;
    margin-bottom: 10px;
    font-size: 18px;
}

.empty-state p {
    color: #6B7280;
    margin-bottom: 20px;
}

/* ============================================ */
/* BUTTONS
/* ============================================ */

.btn-primary, .btn-secondary {
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.btn-primary {
    background: #FF6B6B;
    color: white;
}

.btn-primary:hover {
    background: #e55a5a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,107,107,0.3);
}

.btn-secondary {
    background: #F3F4F6;
    color: #374151;
}

.btn-secondary:hover {
    background: #E5E7EB;
}

/* ============================================ */
/* RESPONSIVE DESIGN
/* ============================================ */

@media (max-width: 1024px) {
    .summary-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 15px;
    }
    
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .summary-card {
        padding: 15px;
    }
    
    .summary-icon {
        width: 45px;
        height: 45px;
        font-size: 20px;
    }
    
    .summary-value {
        font-size: 18px;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .search-box, .filter-group, .date-range {
        width: 100%;
    }
    
    .date-range {
        flex-direction: column;
        align-items: stretch;
    }
    
    .date-range .date-input {
        width: 100%;
    }
    
    .btn-filter, .btn-clear {
        width: 100%;
        justify-content: center;
    }
    
    .card-header {
        flex-direction: column;
        text-align: center;
    }
    
    .header-left, .header-right {
        justify-content: center;
    }
    
    .pagination {
        flex-direction: column;
        text-align: center;
    }
    
    .data-table th, .data-table td {
        padding: 10px 12px;
    }
    
    .user-name {
        display: none;
    }
    
    .notes-cell {
        max-width: 150px;
    }
}

@media (max-width: 480px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .legend {
        justify-content: center;
    }
}

/* ============================================ */
/* PRINT STYLES
/* ============================================ */

@media print {
    .sidebar, .top-header, .filter-bar, .pagination, .header-actions, .legend {
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
    
    .btn-primary, .btn-secondary {
        display: none;
    }
    
    .data-table {
        font-size: 10px;
    }
}

/* ============================================ */
/* ANIMATIONS
/* ============================================ */

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.movement-row {
    animation: fadeInUp 0.3s ease forwards;
    opacity: 0;
}
</style>

<script>
/**
 * Export table data to CSV/Excel
 */
function exportToExcel() {
    const table = document.getElementById('stockHistoryTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        const rowData = [];
        cols.forEach(col => {
            let text = col.innerText;
            // Clean up the text
            text = text.replace(/[^\w\s\d\/:.-]/g, ' ').trim();
            rowData.push('"' + text.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'stock_history_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
    a.click();
    URL.revokeObjectURL(url);
    showToast('Exported to CSV successfully!', 'success');
}

/**
 * Auto-submit form when filter changes
 */
document.querySelectorAll('.filter-select, .preset-select').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Animate rows on load
document.querySelectorAll('.movement-row').forEach((row, index) => {
    row.style.animationDelay = `${index * 0.03}s`;
});
</script>

<?php include '../templates/footer.php'; ?>
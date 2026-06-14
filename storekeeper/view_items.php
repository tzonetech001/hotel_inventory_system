<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper', 'Hotel Manager', 'Procurement Officer']);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'ASC';
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["i.status != 'deleted'"];
if (!empty($search)) {
    $search_escaped = $db->real_escape_string($search);
    $where_conditions[] = "(i.item_name LIKE '%$search_escaped%' OR i.category LIKE '%$search_escaped%')";
}
if (!empty($category)) {
    $category_escaped = $db->real_escape_string($category);
    $where_conditions[] = "i.category = '$category_escaped'";
}
if (!empty($department_filter)) {
    $department_escaped = $db->real_escape_string($department_filter);
    $where_conditions[] = "i.department = '$department_escaped'";
}
if (!empty($status_filter)) {
    if ($status_filter == 'low_stock') {
        $where_conditions[] = "i.current_stock <= i.minimum_stock";
    } elseif ($status_filter == 'normal') {
        $where_conditions[] = "i.current_stock > i.minimum_stock AND i.current_stock < i.maximum_stock";
    } elseif ($status_filter == 'overstock') {
        $where_conditions[] = "i.current_stock >= i.maximum_stock";
    }
}
$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_by = match($sort) {
    'stock' => 'i.current_stock',
    'price' => 'i.unit_price',
    'category' => 'i.category',
    'department' => 'i.department',
    default => 'i.item_name'
};
$order_clause = "$order_by $order";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM inventory_items i WHERE $where_clause";
$count_result = $db->query($count_sql);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

// Get items
$sql = "SELECT i.*, s.company_name as supplier_name 
        FROM inventory_items i 
        LEFT JOIN suppliers s ON i.supplier_id = s.id 
        WHERE $where_clause
        ORDER BY $order_clause
        LIMIT $offset, $limit";

$result = $db->query($sql);
$items = $result->fetch_all(MYSQLI_ASSOC);

// Get unique categories for filter
$cat_result = $db->query("SELECT DISTINCT category FROM inventory_items WHERE category IS NOT NULL AND category != '' AND status != 'deleted'");
$categories = $cat_result->fetch_all(MYSQLI_ASSOC);

// Get unique departments for filter
$dept_result = $db->query("SELECT DISTINCT department FROM inventory_items WHERE department IS NOT NULL AND department != '' AND status != 'deleted'");
$departments = $dept_result->fetch_all(MYSQLI_ASSOC);

// Get summary statistics
$summary_sql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN current_stock <= minimum_stock THEN 1 ELSE 0 END) as low_stock_count,
                    SUM(current_stock * unit_price) as total_value,
                    SUM(current_stock) as total_units
                FROM inventory_items i 
                WHERE status != 'deleted'";
$summary_result = $db->query($summary_sql);
$summary = $summary_result->fetch_assoc();

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-boxes"></i> Inventory Items</h1>
            <p>View and manage all inventory items with department tracking</p>
        </div>
        <div class="header-actions">
            <a href="add_item.php" class="btn-primary">
                <i class="fas fa-plus"></i> Add New Item
            </a>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon" style="background: #1E3A8A20;">
                <i class="fas fa-boxes" style="color: #1E3A8A;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Total Items</span>
                <span class="summary-value"><?php echo number_format($summary['total_items']); ?></span>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background: #FF6B6B20;">
                <i class="fas fa-exclamation-triangle" style="color: #FF6B6B;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Low Stock</span>
                <span class="summary-value" style="color: #FF6B6B;"><?php echo number_format($summary['low_stock_count']); ?></span>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background: #10B98120;">
                <i class="fas fa-chart-line" style="color: #10B981;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Total Value</span>
                <span class="summary-value">TZS <?php echo number_format($summary['total_value'], 0); ?></span>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background: #F59E0B20;">
                <i class="fas fa-cubes" style="color: #F59E0B;"></i>
            </div>
            <div class="summary-info">
                <span class="summary-label">Total Units</span>
                <span class="summary-value"><?php echo number_format($summary['total_units']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form" id="filterForm">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name or category..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <select name="department" class="filter-select">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo ($department_filter == $dept['department']) ? 'selected' : ''; ?>>
                            <i class="fas <?php echo getDepartmentIcon($dept['department']); ?>"></i> 
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="low_stock" <?php echo $status_filter == 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="normal" <?php echo $status_filter == 'normal' ? 'selected' : ''; ?>>Normal Stock</option>
                    <option value="overstock" <?php echo $status_filter == 'overstock' ? 'selected' : ''; ?>>Over Stock</option>
                </select>
            </div>
            <div class="filter-group">
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                    <option value="stock" <?php echo $sort == 'stock' ? 'selected' : ''; ?>>Sort by Stock</option>
                    <option value="price" <?php echo $sort == 'price' ? 'selected' : ''; ?>>Sort by Price</option>
                    <option value="category" <?php echo $sort == 'category' ? 'selected' : ''; ?>>Sort by Category</option>
                    <option value="department" <?php echo $sort == 'department' ? 'selected' : ''; ?>>Sort by Department</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Apply
            </button>
            <a href="view_items.php" class="btn-clear">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
        
        <div class="export-buttons">
            <button onclick="copyTable()" class="btn-icon" title="Copy Table">
                <i class="fas fa-copy"></i>
            </button>
            <button onclick="exportToExcel()" class="btn-icon" title="Export to Excel">
                <i class="fas fa-file-excel"></i>
            </button>
            <button onclick="window.print()" class="btn-icon" title="Print">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </div>
    
    <!-- Items Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Items List</h3>
            <span class="item-count">Showing <?php echo count($items); ?> of <?php echo number_format($total_items); ?> items</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Department</th>
                            <th>Current Stock</th>
                            <th>Unit</th>
                            <th>Min / Max</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                            <th>Supplier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($items) > 0): ?>
                            <?php $counter = $offset + 1; foreach($items as $item): ?>
                                <tr class="item-row" data-id="<?php echo $item['id']; ?>">
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <div class="item-name-cell">
                                            <div class="item-icon">
                                                <i class="fas fa-box"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                <?php if($item['location']): ?>
                                                    <div class="item-location">
                                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['location']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="category-tag"><?php echo htmlspecialchars($item['category'] ?? '-'); ?></span>
                                    </td>
                                    <td>
                                        <?php if(!empty($item['department'])): ?>
                                            <span class="department-tag">
                                                <i class="fas <?php echo getDepartmentIcon($item['department']); ?>"></i>
                                                <?php echo htmlspecialchars($item['department']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="department-tag none">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="stock-display">
                                            <span class="stock-number <?php echo ($item['current_stock'] <= $item['minimum_stock']) ? 'stock-critical' : ''; ?>">
                                                <?php echo number_format($item['current_stock']); ?>
                                            </span>
                                            <?php if($item['current_stock'] <= $item['minimum_stock']): ?>
                                                <span class="stock-warning-icon" title="Critical Stock Level">
                                                    <i class="fas fa-exclamation-circle"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                                    <td>
                                        <div class="minmax-display">
                                            <span class="min"><?php echo number_format($item['minimum_stock']); ?></span>
                                            <i class="fas fa-arrow-right"></i>
                                            <span class="max"><?php echo number_format($item['maximum_stock']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="price">TZS <?php echo number_format($item['unit_price'], 2); ?></span>
                                    </td>
                                    <td>
                                        <?php if($item['current_stock'] <= $item['minimum_stock']): ?>
                                            <span class="status-badge danger">
                                                <i class="fas fa-exclamation-triangle"></i> Low Stock
                                            </span>
                                        <?php elseif($item['current_stock'] >= $item['maximum_stock']): ?>
                                            <span class="status-badge warning">
                                                <i class="fas fa-chart-line"></i> Over Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge success">
                                                <i class="fas fa-check-circle"></i> Normal
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['supplier_name'] ?? '-'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="viewItem(<?php echo $item['id']; ?>)" class="btn-icon view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn-icon edit" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="stock_in.php?item=<?php echo $item['id']; ?>" class="btn-icon stock-in" title="Stock In">
                                                <i class="fas fa-arrow-down"></i>
                                            </a>
                                            <a href="stock_out_request.php?item=<?php echo $item['id']; ?>" class="btn-icon stock-out" title="Stock Out Request">
                                                <i class="fas fa-qrcode"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-box-open"></i>
                                        <h4>No items found</h4>
                                        <p>Try adjusting your search or filter criteria</p>
                                        <a href="add_item.php" class="btn-primary" style="margin-top: 15px;">
                                            <i class="fas fa-plus"></i> Add New Item
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_items); ?> of <?php echo number_format($total_items); ?> entries
                </div>
                <div class="pagination-links">
                    <?php if($page > 1): ?>
                        <a href="?page=1&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="page-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="page-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="page-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="page-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Item Details Modal -->
<div id="itemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Item Details</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal()" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

<style>
    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-header h1 {
        margin: 0;
        font-size: 24px;
        color: #1E3A8A;
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
    
    /* Summary Grid */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        transition: transform 0.2s;
    }
    
    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .summary-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
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
    }
    
    .summary-value {
        font-size: 24px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    /* Filter Bar */
    .filter-bar {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .filter-form {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        flex: 1;
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
    
    .filter-group select {
        padding: 10px 12px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        background: white;
        font-size: 14px;
        cursor: pointer;
        min-width: 140px;
    }
    
    .btn-filter, .btn-clear {
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
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
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
    .export-buttons {
        display: flex;
        gap: 8px;
    }
    
    .export-buttons .btn-icon {
        width: 38px;
        height: 38px;
        background: #F3F4F6;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
    }
    
    .export-buttons .btn-icon:hover {
        background: #E5E7EB;
        transform: translateY(-2px);
    }
    
    /* Card */
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .card-header {
        padding: 20px 24px;
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
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .item-count {
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
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-table th {
        padding: 15px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
    }
    
    .data-table td {
        padding: 15px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 14px;
        color: #1F2937;
    }
    
    .data-table tr:hover {
        background: #F9FAFB;
    }
    
    /* Item Name Cell */
    .item-name-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .item-icon {
        width: 36px;
        height: 36px;
        background: #F3F4F6;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1E3A8A;
    }
    
    .item-location {
        font-size: 11px;
        color: #9CA3AF;
        margin-top: 3px;
    }
    
    .item-location i {
        font-size: 10px;
    }
    
    /* Category Tag */
    .category-tag {
        background: #F3F4F6;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        color: #374151;
        display: inline-block;
    }
    
    /* Department Tag */
    .department-tag {
        background: #E0E7FF;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        color: #1E3A8A;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .department-tag.none {
        background: #F3F4F6;
        color: #9CA3AF;
    }
    
    .department-tag i {
        font-size: 11px;
    }
    
    /* Stock Display */
    .stock-display {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .stock-number {
        font-weight: 700;
        font-size: 16px;
        color: #1E3A8A;
    }
    
    .stock-number.stock-critical {
        color: #EF4444;
    }
    
    .stock-warning-icon {
        color: #F59E0B;
        cursor: help;
    }
    
    /* Min Max Display */
    .minmax-display {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }
    
    .minmax-display .min {
        color: #EF4444;
    }
    
    .minmax-display .max {
        color: #10B981;
    }
    
    .minmax-display i {
        font-size: 10px;
        color: #9CA3AF;
    }
    
    /* Price */
    .price {
        font-weight: 600;
        color: #1E3A8A;
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-badge.success {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-badge.danger {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .status-badge.warning {
        background: #FEF3C7;
        color: #92400E;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        background: #F3F4F6;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s;
        color: #6B7280;
        border: none;
        cursor: pointer;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
    }
    
    .btn-icon.view:hover {
        background: #DBEAFE;
        color: #1E3A8A;
    }
    
    .btn-icon.edit:hover {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .btn-icon.stock-in:hover {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .btn-icon.stock-out:hover {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    /* Pagination */
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-top: 1px solid #E5E7EB;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .pagination-info {
        font-size: 13px;
        color: #6B7280;
    }
    
    .pagination-links {
        display: flex;
        gap: 8px;
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
    }
    
    .page-link:hover {
        background: #1E3A8A;
        color: white;
    }
    
    .page-link.active {
        background: #1E3A8A;
        color: white;
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
        background-color: white;
        width: 90%;
        max-width: 550px;
        border-radius: 20px;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .close {
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.2s;
    }
    
    .close:hover {
        color: #EF4444;
    }
    
    .modal-body {
        padding: 24px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #E5E7EB;
        display: flex;
        justify-content: flex-end;
    }
    
    .loading-spinner {
        text-align: center;
        padding: 40px;
        color: #6B7280;
    }
    
    .detail-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .detail-label {
        width: 130px;
        font-weight: 600;
        color: #374151;
    }
    
    .detail-value {
        flex: 1;
        color: #1F2937;
    }
    
    .detail-value strong {
        color: #1E3A8A;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #D1D5DB;
        margin-bottom: 20px;
    }
    
    .empty-state h4 {
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
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .summary-card {
            padding: 15px;
        }
        
        .summary-value {
            font-size: 18px;
        }
        
        .filter-bar {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-form {
            flex-direction: column;
        }
        
        .search-box, .filter-group select, .btn-filter, .btn-clear {
            width: 100%;
        }
        
        .export-buttons {
            justify-content: center;
        }
        
        .card-header {
            flex-direction: column;
            text-align: center;
        }
        
        .pagination {
            flex-direction: column;
            text-align: center;
        }
        
        .data-table th, .data-table td {
            padding: 10px;
            font-size: 12px;
        }
        
        .action-buttons {
            flex-wrap: wrap;
        }
        
        .detail-row {
            flex-direction: column;
        }
        
        .detail-label {
            width: auto;
            margin-bottom: 5px;
        }
    }
    
    @media print {
        .sidebar, .top-header, .filter-bar, .export-buttons, .action-buttons, .pagination, .header-actions {
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
    }
</style>

<script>
    // Modal functions
    const modal = document.getElementById('itemModal');
    const modalBody = document.getElementById('modalBody');
    
    function viewItem(id) {
        modal.style.display = 'flex';
        modalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading item details...</div>';
        
        fetch(`get_item.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    modalBody.innerHTML = `<div class="error-message">${data.error}</div>`;
                    return;
                }
                
                const stockStatus = data.current_stock <= data.minimum_stock ? 'danger' : 
                                   (data.current_stock >= data.maximum_stock ? 'warning' : 'success');
                const stockStatusText = data.current_stock <= data.minimum_stock ? 'Low Stock' : 
                                       (data.current_stock >= data.maximum_stock ? 'Over Stock' : 'Normal');
                
                modalBody.innerHTML = `
                    <div class="detail-row">
                        <div class="detail-label">Item Name:</div>
                        <div class="detail-value"><strong>${escapeHtml(data.item_name)}</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Category:</div>
                        <div class="detail-value">${escapeHtml(data.category || '-')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Department:</div>
                        <div class="detail-value">
                            <span class="department-tag">
                                <i class="fas ${getIconForDepartment(data.department)}"></i>
                                ${escapeHtml(data.department || 'Not Assigned')}
                            </span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Current Stock:</div>
                        <div class="detail-value">${formatNumber(data.current_stock)} ${escapeHtml(data.unit || '')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Minimum Stock:</div>
                        <div class="detail-value">${formatNumber(data.minimum_stock)} ${escapeHtml(data.unit || '')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Maximum Stock:</div>
                        <div class="detail-value">${formatNumber(data.maximum_stock)} ${escapeHtml(data.unit || '')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Unit Price:</div>
                        <div class="detail-value">TZS ${formatNumber(data.unit_price, 2)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Total Value:</div>
                        <div class="detail-value">TZS ${formatNumber(data.current_stock * data.unit_price, 2)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Supplier:</div>
                        <div class="detail-value">${escapeHtml(data.supplier_name || '-')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Location:</div>
                        <div class="detail-value">${escapeHtml(data.location || '-')}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Stock Status:</div>
                        <div class="detail-value">
                            <span class="status-badge ${stockStatus}">${stockStatusText}</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Created:</div>
                        <div class="detail-value">${formatDate(data.created_at)}</div>
                    </div>
                    ${data.updated_at ? `
                    <div class="detail-row">
                        <div class="detail-label">Last Updated:</div>
                        <div class="detail-value">${formatDate(data.updated_at)}</div>
                    </div>
                    ` : ''}
                `;
            })
            .catch(error => {
                modalBody.innerHTML = '<div class="error-message">Error loading item details</div>';
            });
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatNumber(num, decimals = 0) {
        return Number(num).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }
    
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    
    function getIconForDepartment(department) {
        const icons = {
            'Kitchen': 'fa-utensils',
            'Housekeeping': 'fa-broom',
            'Laundry': 'fa-tshirt',
            'Front Office': 'fa-hotel',
            'Maintenance': 'fa-wrench',
            'Restaurant': 'fa-utensil-spoon',
            'Bar': 'fa-cocktail',
            'Store': 'fa-warehouse'
        };
        return icons[department] || 'fa-building';
    }
    
    // Export functions
    function copyTable() {
        const table = document.getElementById('itemsTable');
        const range = document.createRange();
        range.selectNode(table);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        document.execCommand('copy');
        window.getSelection().removeAllRanges();
        showToast('Table copied to clipboard!', 'success');
    }
    
    function exportToExcel() {
        const table = document.getElementById('itemsTable');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('th, td');
            const rowData = [];
            cols.forEach(col => {
                let text = col.innerText;
                // Remove action buttons content
                if (col.classList.contains('action-buttons') || col.querySelector('.action-buttons')) {
                    text = '';
                }
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });
        
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'inventory_items.csv';
        a.click();
        URL.revokeObjectURL(url);
        showToast('Exported to CSV successfully!', 'success');
    }
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'error' ? '#EF4444' : '#10B981'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 10000;
            animation: fadeInUp 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
    
    // Animate rows on load
    document.querySelectorAll('.item-row').forEach((row, index) => {
        row.style.animation = `fadeInUp 0.3s ease ${index * 0.03}s forwards`;
        row.style.opacity = '0';
    });
    
    const style = document.createElement('style');
    style.textContent = `
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
    `;
    document.head.appendChild(style);
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
?>

<?php include '../templates/footer.php'; ?>
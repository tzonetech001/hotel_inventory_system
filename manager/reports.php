<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager', 'Admin']);

$report_type = $_GET['type'] ?? 'stock';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$stock_data = [];
$movement_data = [];
$po_data = [];

// Stock Summary Report
if ($report_type == 'stock') {
    $sql = "SELECT i.*, s.company_name as supplier_name
            FROM inventory_items i
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.status = 'active'
            ORDER BY i.item_name";
    $result = $db->query($sql);
    $stock_data = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get total value
    $total_value = 0;
    foreach ($stock_data as $item) {
        $total_value += $item['current_stock'] * $item['unit_price'];
    }
}

// Stock Movements Report
if ($report_type == 'movements') {
    $sql = "SELECT sm.*, i.item_name, i.unit, u.fullname as performed_by_name
            FROM stock_movements sm
            JOIN inventory_items i ON sm.item_id = i.id
            JOIN users u ON sm.performed_by = u.id
            WHERE DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to'
            ORDER BY sm.created_at DESC";
    $result = $db->query($sql);
    $movement_data = $result->fetch_all(MYSQLI_ASSOC);
}

// Purchase Orders Report
if ($report_type == 'purchase') {
    $sql = "SELECT po.*, s.company_name as supplier_name, u.fullname as created_by_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            JOIN users u ON po.created_by = u.id
            WHERE DATE(po.created_at) BETWEEN '$date_from' AND '$date_to'
            ORDER BY po.created_at DESC";
    $result = $db->query($sql);
    $po_data = $result->fetch_all(MYSQLI_ASSOC);
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Reports Dashboard</h1>
        <p>View and analyze inventory and procurement data</p>
    </div>
    
    <!-- Report Navigation -->
    <div class="report-nav">
        <a href="?type=stock" class="report-tab <?php echo $report_type == 'stock' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i> Stock Summary
        </a>
        <a href="?type=movements&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="report-tab <?php echo $report_type == 'movements' ? 'active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i> Stock Movements
        </a>
        <a href="?type=purchase&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="report-tab <?php echo $report_type == 'purchase' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
        </a>
    </div>
    
    <!-- Date Filter (for movements and purchase reports) -->
    <?php if($report_type != 'stock'): ?>
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn-primary">Apply Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stock Summary Report -->
    <?php if($report_type == 'stock'): ?>
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fas fa-chart-pie"></i> Current Stock Summary</h3>
                <button onclick="window.print()" class="report-tab">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Summary Stats -->
            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-label">Total Items</div>
                    <div class="stat-number"><?php echo count($stock_data); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-number" style="color: #FF6B6B;">
                        <?php 
                            $low_count = 0;
                            foreach($stock_data as $item) {
                                if($item['current_stock'] <= $item['minimum_stock']) $low_count++;
                            }
                            echo $low_count;
                        ?>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Inventory Value</div>
                    <div class="stat-number">TZS <?php echo number_format($total_value, 2); ?></div>
                </div>
            </div>
            
            <!-- Stock Table -->
            <div class="table-responsive">
                <table class="data-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Unit</th>
                            <th>Min Stock</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($stock_data as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                                <td>
                                    <span class="<?php echo ($item['current_stock'] <= $item['minimum_stock']) ? 'text-danger' : ''; ?>">
                                        <?php echo number_format($item['current_stock']); ?>
                                    </span>
                                 </td>
                                <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                                <td><?php echo number_format($item['minimum_stock']); ?></td>
                                <td>TZS <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>TZS <?php echo number_format($item['current_stock'] * $item['unit_price'], 2); ?></td>
                                <td>
                                    <?php if($item['current_stock'] <= $item['minimum_stock']): ?>
                                        <span class="badge-danger">Low Stock</span>
                                    <?php elseif($item['current_stock'] >= $item['maximum_stock']): ?>
                                        <span class="badge-warning">Over Stock</span>
                                    <?php else: ?>
                                        <span class="badge-success">Normal</span>
                                    <?php endif; ?>
                                 </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="6" style="text-align: right;"><strong>Grand Total Value:</strong></td>
                            <td colspan="2"><strong>TZS <?php echo number_format($total_value, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stock Movements Report -->
    <?php if($report_type == 'movements'): ?>
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fas fa-history"></i> Stock Movements (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)</h3>
                <button onclick="window.print()" class="btn-secondary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
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
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($movement_data) > 0): ?>
                            <?php foreach($movement_data as $movement): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($movement['item_name']); ?></td>
                                    <td>
                                        <?php if($movement['movement_type'] == 'IN'): ?>
                                            <span class="badge-success">IN</span>
                                        <?php else: ?>
                                            <span class="badge-danger">OUT</span>
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
                                    <td><?php echo htmlspecialchars($movement['performed_by_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No stock movements found for this period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Purchase Orders Report -->
    <?php if($report_type == 'purchase'): ?>
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fas fa-shopping-cart"></i> Purchase Orders (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)</h3>
                <button onclick="window.print()" class="btn-secondary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php
                $total_po_amount = 0;
                $approved_count = 0;
                $pending_count = 0;
                $delivered_count = 0;
                
                foreach($po_data as $po) {
                    $total_po_amount += $po['total_amount'];
                    if($po['status'] == 'approved') $approved_count++;
                    if($po['status'] == 'pending') $pending_count++;
                    if($po['status'] == 'delivered') $delivered_count++;
                }
            ?>
            
            <!-- PO Stats -->
            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-number"><?php echo count($po_data); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Value</div>
                    <div class="stat-number">TZS <?php echo number_format($total_po_amount, 2); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Approved</div>
                    <div class="stat-number" style="color: #10B981;"><?php echo $approved_count; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Pending</div>
                    <div class="stat-number" style="color: #F59E0B;"><?php echo $pending_count; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Delivered</div>
                    <div class="stat-number" style="color: #1E3A8A;"><?php echo $delivered_count; ?></div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Order Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($po_data) > 0): ?>
                            <?php foreach($po_data as $po): ?>
                                <tr>
                                    <td><strong><?php echo $po['po_number']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($po['order_date'])); ?></td>
                                    <td>TZS <?php echo number_format($po['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-<?php echo $po['status']; ?>">
                                            <?php echo ucfirst($po['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($po['created_by_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No purchase orders found for this period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                            <td colspan="3"><strong>TZS <?php echo number_format($total_po_amount, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .report-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .report-tab {
        padding: 10px 20px;
        background: white;
        border-radius: 8px;
        text-decoration: none;
        color: #374151;
        transition: all 0.3s;
        border: 1px solid #E5E7EB;
    }
    
    .report-tab:hover, .report-tab.active {
        background: #1E3A8A;
        color: white;
        border-color: #1E3A8A;
    }
    
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-item {
        background: #F9FAFB;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-label {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 8px;
    }
    
    .stat-number {
        font-size: 24px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .filter-form {
        display: flex;
        align-items: flex-end;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .text-success {
        color: #10B981;
        font-weight: 600;
    }
    
    .text-danger {
        color: #EF4444;
        font-weight: 600;
    }
    
    .badge-success, .badge-danger, .badge-warning {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-success {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .badge-danger {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .badge-warning {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .total-row {
        background: #F9FAFB;
        font-weight: bold;
    }
    
    @media print {
        .sidebar, .top-header, .report-nav, .filter-form, .btn-secondary, .card-header button {
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

<?php include '../templates/footer.php'; ?>
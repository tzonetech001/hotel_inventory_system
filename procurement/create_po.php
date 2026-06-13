<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Allow both Procurement Officer and Hotel Manager to create PO
checkAuth(['Procurement Officer', 'Hotel Manager']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$error = '';
$success = '';

// Get suppliers
$suppliers_sql = "SELECT id, company_name, contact_person, email, phone FROM suppliers WHERE status = 'active' ORDER BY company_name";
$suppliers_result = $db->query($suppliers_sql);
$suppliers = $suppliers_result->fetch_all(MYSQLI_ASSOC);

// Get low stock items (not filtered by supplier yet)
$items_sql = "SELECT id, item_name, unit, current_stock, minimum_stock, unit_price, supplier_id 
              FROM inventory_items 
              WHERE status = 'active' AND current_stock <= minimum_stock 
              ORDER BY current_stock ASC";
$items_result = $db->query($items_sql);
$low_stock_items = $items_result->fetch_all(MYSQLI_ASSOC);

// Get all active items for manual addition (not filtered yet)
$all_items_sql = "SELECT id, item_name, unit, unit_price, supplier_id FROM inventory_items WHERE status = 'active' ORDER BY item_name";
$all_items_result = $db->query($all_items_sql);
$all_items = $all_items_result->fetch_all(MYSQLI_ASSOC);

// Get recent POs for quick reference (for managers)
$recent_pos_sql = "SELECT po_number, supplier_id, created_at FROM purchase_orders WHERE created_by = ? ORDER BY created_at DESC LIMIT 5";
$recent_stmt = $db->prepare($recent_pos_sql);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_pos = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_po'])) {
    $supplier_id = intval($_POST['supplier_id']);
    $expected_delivery = !empty($_POST['expected_delivery']) ? $_POST['expected_delivery'] : null;
    $notes = trim($_POST['notes']);
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $prices = $_POST['prices'] ?? []; // Get custom prices
    
    if ($supplier_id <= 0 || empty($items)) {
        $_SESSION['toast_message'] = "Please select a supplier and at least one item!";
        $_SESSION['toast_type'] = "error";
    } else {
        $db->begin_transaction();
        
        try {
            // Generate PO number
            $year = date('Y');
            $month = date('m');
            $po_sql = "SELECT COUNT(*) as count FROM purchase_orders WHERE YEAR(created_at) = $year AND MONTH(created_at) = $month";
            $po_result = $db->query($po_sql);
            $po_count = $po_result->fetch_assoc()['count'] + 1;
            $po_number = "PO-$year$month-" . str_pad($po_count, 4, '0', STR_PAD_LEFT);
            
            // Calculate total amount
            $total_amount = 0;
            $po_items = [];
            
            foreach ($items as $index => $item_id) {
                $quantity = intval($quantities[$index]);
                $custom_price = floatval($prices[$index] ?? 0);
                
                if ($quantity > 0) {
                    // Get item details
                    $item_sql = "SELECT unit_price, supplier_id, item_name FROM inventory_items WHERE id = $item_id";
                    $item_result = $db->query($item_sql);
                    $item_data = $item_result->fetch_assoc();
                    
                    if (!$item_data) {
                        throw new Exception("Item not found!");
                    }
                    
                    // Check if item belongs to the selected supplier
                    if ($item_data['supplier_id'] != $supplier_id) {
                        throw new Exception("Item '{$item_data['item_name']}' does not belong to the selected supplier!");
                    }
                    
                    // Use custom price if provided and greater than 0, otherwise use default price
                    $price = ($custom_price > 0) ? $custom_price : $item_data['unit_price'];
                    
                    // FIXED: Check if default price is greater than 0 before calculating percentage
                    $default_price = $item_data['unit_price'];
                    if ($custom_price > 0 && $default_price > 0) {
                        $price_diff_percent = abs($custom_price - $default_price) / $default_price;
                        if ($price_diff_percent > 0.2) {
                            // Log price change for monitoring (significant change >20%)
                            $price_change_msg = "Price changed for {$item_data['item_name']} from " . number_format($default_price, 2) . " to " . number_format($custom_price, 2);
                            logActivity($user_id, 'Price Change', $price_change_msg);
                        }
                    } elseif ($custom_price > 0 && $default_price == 0) {
                        // Default price is zero, log as new price set
                        logActivity($user_id, 'Price Set', "Price set for {$item_data['item_name']} to " . number_format($custom_price, 2));
                    }
                    
                    $total = $quantity * $price;
                    $total_amount += $total;
                    
                    $po_items[] = [
                        'item_id' => $item_id,
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'total_price' => $total,
                        'original_price' => $item_data['unit_price']
                    ];
                }
            }
            
            if (empty($po_items)) {
                throw new Exception("No items with valid quantity!");
            }
            
            // Insert purchase order
            $status = 'pending';
            $auto_approve = isset($_POST['auto_approve']) && $_POST['auto_approve'] == '1' && $user_role == 'Hotel Manager';
            if ($auto_approve) {
                $status = 'approved';
            }
            
            $sql = "INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery, total_amount, created_by, notes, status) 
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sisdiss", $po_number, $supplier_id, $expected_delivery, $total_amount, $user_id, $notes, $status);
            $stmt->execute();
            $po_id = $db->insert_id;
            
            // If auto-approved by manager, set approved_by and approved_at
            if ($auto_approve) {
                $approve_sql = "UPDATE purchase_orders SET approved_by = ?, approved_at = NOW() WHERE id = ?";
                $approve_stmt = $db->prepare($approve_sql);
                $approve_stmt->bind_param("ii", $user_id, $po_id);
                $approve_stmt->execute();
            }
            
            // Insert PO items with custom prices
            foreach ($po_items as $item) {
                $sql = "INSERT INTO po_items (po_id, item_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("iiidd", $po_id, $item['item_id'], $item['quantity'], $item['unit_price'], $item['total_price']);
                $stmt->execute();
            }
            
            $db->commit();
            
            // Log price changes summary
            $price_changes = array_filter($po_items, function($item) {
                return $item['unit_price'] != $item['original_price'];
            });
            if (!empty($price_changes)) {
                $change_count = count($price_changes);
                logActivity($user_id, 'Price Changes', "PO $po_number: $change_count item(s) had price adjustments");
            }
            
            logActivity($user_id, 'Create PO', "Created purchase order: $po_number" . ($auto_approve ? " (Auto-approved)" : ""));
            
            if ($auto_approve) {
                $_SESSION['toast_message'] = "Purchase Order <strong>$po_number</strong> created and approved successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Purchase Order <strong>$po_number</strong> created successfully! Waiting for manager approval.";
                $_SESSION['toast_type'] = "success";
            }
            
            header("Location: view_po.php");
            exit();
            
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['toast_message'] = "Error creating purchase order: " . $e->getMessage();
            $_SESSION['toast_type'] = "error";
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Create Purchase Order</h1>
        <p>Create new purchase order for inventory replenishment - You can adjust prices as needed</p>
    </div>
    
    <div class="two-column-layout">
        <!-- Left Column: Form -->
        <div class="form-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Purchase Order Details</h3>
                    <?php if($user_role == 'Hotel Manager'): ?>
                        <span class="role-badge manager-badge">
                            <i class="fas fa-star"></i> Manager Access
                        </span>
                    <?php else: ?>
                        <span class="role-badge procurement-badge">
                            <i class="fas fa-shopping-cart"></i> Procurement Access
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="poForm">
                        <input type="hidden" name="create_po" value="1">
                        
                        <div class="form-group">
                            <label><i class="fas fa-truck"></i> Select Supplier <span class="required">*</span></label>
                            <select name="supplier_id" id="supplier_id" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['company_name']); ?> 
                                        (<?php echo htmlspecialchars($supplier['contact_person'] ?? 'No contact'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Expected Delivery Date</label>
                            <input type="date" name="expected_delivery" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                        </div>
                        
                        <?php if($user_role == 'Hotel Manager'): ?>
                        <div class="form-group manager-options">
                            <label><i class="fas fa-cog"></i> Approval Options</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="auto_approve" id="auto_approve" value="1">
                                <label for="auto_approve">
                                    <i class="fas fa-check-circle"></i> Auto-approve this purchase order
                                </label>
                                <small class="help-text">If checked, this PO will be automatically approved and ready for delivery tracking</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label><i class="fas fa-list"></i> Items to Order <span class="required">*</span></label>
                            <div class="items-container">
                                <div class="items-header">
                                    <span>Item</span>
                                    <span>Current Stock</span>
                                    <span>Default Price</span>
                                    <span>Unit Price (TZS)</span>
                                    <span>Quantity</span>
                                    <span>Total</span>
                                    <span></span>
                                </div>
                                <div id="items-list">
                                    <div class="empty-items" id="emptyItemsMsg">
                                        <p><i class="fas fa-info-circle"></i> Select a supplier first to see items</p>
                                    </div>
                                </div>
                                <button type="button" class="btn-outline" id="addItemBtn" style="display: none;">
                                    <i class="fas fa-plus"></i> Add Another Item
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Additional Notes</label>
                            <textarea name="notes" rows="3" placeholder="Special instructions, delivery requirements, price negotiation notes, etc..."></textarea>
                        </div>
                        
                        <div class="summary-box">
                            <h4><i class="fas fa-chart-simple"></i> Order Summary</h4>
                            <div class="summary-row">
                                <span>Total Items:</span>
                                <span id="totalItems" class="summary-value">0</span>
                            </div>
                            <div class="summary-row">
                                <span>Total Quantity:</span>
                                <span id="totalQuantity" class="summary-value">0</span>
                            </div>
                            <div class="summary-row total-amount-row">
                                <span>Grand Total:</span>
                                <span id="totalAmount" class="summary-value amount">TZS 0</span>
                            </div>
                            <div class="price-note" id="priceNote" style="display: none;">
                                <i class="fas fa-info-circle"></i> 
                                <span>Some items have custom prices adjusted from default values</span>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> 
                                <?php echo ($user_role == 'Hotel Manager') ? 'Create Purchase Order' : 'Submit for Approval'; ?>
                            </button>
                            <button type="reset" class="btn-secondary" id="resetBtn">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Tips and Info -->
        <div class="tips-column">
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Tips</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle"></i> Always select the correct supplier first</li>
                        <li><i class="fas fa-edit"></i> You can adjust unit prices if negotiated with supplier</li>
                        <li><i class="fas fa-chart-line"></i> Price changes are logged for audit purposes</li>
                        <li><i class="fas fa-calendar"></i> Set realistic expected delivery dates</li>
                        <li><i class="fas fa-list"></i> Low stock items are automatically added</li>
                        <li><i class="fas fa-sticky-note"></i> Add notes for price negotiations</li>
                        <?php if($user_role == 'Hotel Manager'): ?>
                            <li><i class="fas fa-star"></i> As Manager, you can auto-approve POs</li>
                        <?php else: ?>
                            <li><i class="fas fa-clock"></i> PO will be sent to manager for approval</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card info-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Quick Stats</h3>
                </div>
                <div class="card-body">
                    <?php
                    $total_suppliers = count($suppliers);
                    $low_stock_count = count($low_stock_items);
                    
                    $pending_count = 0;
                    if ($user_role == 'Hotel Manager') {
                        $pending_sql = "SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'pending'";
                        $pending_result = $db->query($pending_sql);
                        $pending_count = $pending_result->fetch_assoc()['count'];
                    }
                    ?>
                    <div class="stat-row">
                        <span class="stat-label">Active Suppliers:</span>
                        <span class="stat-number"><?php echo $total_suppliers; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Low Stock Items:</span>
                        <span class="stat-number <?php echo $low_stock_count > 0 ? 'warning' : ''; ?>"><?php echo $low_stock_count; ?></span>
                    </div>
                    <?php if($user_role == 'Hotel Manager'): ?>
                    <div class="stat-row">
                        <span class="stat-label">Pending Approvals:</span>
                        <span class="stat-number <?php echo $pending_count > 0 ? 'pending' : ''; ?>"><?php echo $pending_count; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card price-info-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-tag"></i> Price Information</h3>
                </div>
                <div class="card-body">
                    <ul class="price-info-list">
                        <li><i class="fas fa-database"></i> Default prices from inventory</li>
                        <li><i class="fas fa-edit"></i> Click on price field to edit</li>
                        <li><i class="fas fa-percent"></i> Prices are in Tanzanian Shillings (TZS)</li>
                        <li><i class="fas fa-history"></i> Price changes are tracked in logs</li>
                        <li><i class="fas fa-chart-simple"></i> Total updates automatically</li>
                    </ul>
                </div>
            </div>
            
            <?php if(!empty($recent_pos)): ?>
            <div class="card recent-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Your Recent POs</h3>
                </div>
                <div class="card-body">
                    <ul class="recent-list">
                        <?php foreach($recent_pos as $po): ?>
                            <li>
                                <i class="fas fa-file-invoice"></i>
                                <span><?php echo htmlspecialchars($po['po_number']); ?></span>
                                <small><?php echo date('d M Y', strtotime($po['created_at'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Items for dropdown (hidden template) -->
    <select id="itemTemplate" style="display: none;"></select>
</div>

<style>
    .two-column-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 25px;
    }
    
    .animate-card {
        animation: fadeInUp 0.4s ease;
    }
    
    .animate-card-delayed {
        animation: fadeInUp 0.4s ease 0.1s both;
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
        margin-bottom: 20px;
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
    
    .role-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .manager-badge {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    .procurement-badge {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .card-body {
        padding: 24px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }
    
    .required {
        color: #EF4444;
    }
    
    .form-group select,
    .form-group input[type="date"],
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .manager-options {
        background: #F0FDF4;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid #D1FAE5;
    }
    
    .checkbox-group {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-top: 2px;
        cursor: pointer;
    }
    
    .checkbox-group label {
        margin: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .checkbox-group label i {
        color: #10B981;
    }
    
    .help-text {
        width: 100%;
        margin-left: 28px;
        font-size: 11px;
        color: #6B7280;
        display: block;
    }
    
    .items-container {
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 15px;
        background: #F9FAFB;
        overflow-x: auto;
    }
    
    .items-header {
        display: grid;
        grid-template-columns: 1.5fr 0.8fr 1fr 1.2fr 1fr 1.2fr 0.5fr;
        gap: 10px;
        padding: 10px;
        background: white;
        font-weight: 600;
        font-size: 11px;
        border-radius: 8px;
        margin-bottom: 10px;
        color: #6B7280;
        min-width: 700px;
    }
    
    .item-row {
        display: grid;
        grid-template-columns: 1.5fr 0.8fr 1fr 1.2fr 1fr 1.2fr 0.5fr;
        gap: 10px;
        padding: 10px;
        background: white;
        border-radius: 8px;
        margin-bottom: 8px;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        min-width: 700px;
    }
    
    .item-row select,
    .item-row input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        font-size: 13px;
    }
    
    .item-row input.price-input {
        background: #FFFBEB;
        border-color: #FDE68A;
    }
    
    .item-row input.price-input.changed {
        background: #D1FAE5;
        border-color: #10B981;
    }
    
    .current-stock,
    .default-price {
        font-size: 12px;
        color: #6B7280;
        text-align: center;
    }
    
    .item-total {
        font-weight: 600;
        color: #1E3A8A;
        text-align: right;
        font-size: 13px;
    }
    
    .remove-item {
        background: #FEE2E2;
        border: none;
        padding: 8px;
        border-radius: 6px;
        cursor: pointer;
        color: #991B1B;
        transition: all 0.3s;
        width: 100%;
    }
    
    .remove-item:hover {
        background: #FECACA;
    }
    
    .empty-items {
        text-align: center;
        padding: 20px;
        color: #6B7280;
    }
    
    .empty-items i {
        font-size: 24px;
        color: #10B981;
        margin-bottom: 10px;
    }
    
    .btn-outline {
        width: 100%;
        background: transparent;
        border: 1px dashed #1E3A8A;
        color: #1E3A8A;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px;
        font-weight: 500;
    }
    
    .btn-outline:hover {
        background: #DBEAFE;
    }
    
    .summary-box {
        background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
    }
    
    .summary-box h4 {
        margin: 0 0 15px;
        color: #1E3A8A;
        font-size: 16px;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(30,58,138,0.1);
    }
    
    .summary-row:last-child {
        border-bottom: none;
    }
    
    .summary-value {
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .summary-value.amount {
        color: #FF6B6B;
        font-size: 20px;
    }
    
    .total-amount-row {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 2px solid rgba(30,58,138,0.2);
    }
    
    .price-note {
        margin-top: 12px;
        padding: 8px 12px;
        background: #FEF3C7;
        border-radius: 8px;
        font-size: 11px;
        color: #92400E;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }
    
    .btn-primary {
        flex: 1;
        background: #FF6B6B;
        color: white;
        padding: 14px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        flex: 1;
        background: #F3F4F6;
        color: #374151;
        padding: 14px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .tips-list, .price-info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .tips-list li, .price-info-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
        color: #374151;
    }
    
    .tips-list li:last-child, .price-info-list li:last-child {
        border-bottom: none;
    }
    
    .tips-list li i, .price-info-list li i {
        color: #10B981;
        width: 20px;
    }
    
    .price-info-list li i {
        color: #1E3A8A;
    }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .stat-row:last-child {
        border-bottom: none;
    }
    
    .stat-label {
        color: #6B7280;
        font-size: 13px;
    }
    
    .stat-number {
        font-weight: 700;
        color: #1E3A8A;
        font-size: 20px;
    }
    
    .stat-number.warning {
        color: #FF6B6B;
    }
    
    .stat-number.pending {
        color: #F59E0B;
    }
    
    .recent-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .recent-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
    }
    
    .recent-list li:last-child {
        border-bottom: none;
    }
    
    .recent-list li i {
        color: #1E3A8A;
        margin-right: 8px;
    }
    
    .recent-list li small {
        color: #9CA3AF;
        font-size: 10px;
    }
    
    @media (max-width: 900px) {
        .two-column-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .items-header {
            min-width: 700px;
        }
        
        .item-row {
            min-width: 700px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .card-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<script>
// Store all items data
const allItems = <?php echo json_encode($all_items); ?>;
const lowStockItems = <?php echo json_encode($low_stock_items); ?>;
const userRole = '<?php echo $user_role; ?>';
let hasPriceChanges = false;

// When supplier changes, load items for that supplier
document.getElementById('supplier_id').addEventListener('change', function() {
    const supplierId = this.value;
    const itemsList = document.getElementById('items-list');
    const addBtn = document.getElementById('addItemBtn');
    
    if (!supplierId) {
        itemsList.innerHTML = '<div class="empty-items" id="emptyItemsMsg"><p><i class="fas fa-info-circle"></i> Select a supplier first to see items</p></div>';
        addBtn.style.display = 'none';
        updateSummary();
        return;
    }
    
    // Filter items for this supplier
    const supplierItems = allItems.filter(item => item.supplier_id == supplierId);
    const supplierLowStockItems = lowStockItems.filter(item => item.supplier_id == supplierId);
    
    if (supplierItems.length === 0) {
        itemsList.innerHTML = '<div class="empty-items" id="emptyItemsMsg"><p><i class="fas fa-exclamation-triangle"></i> No items found for this supplier</p><p class="small">Please add items to inventory first</p></div>';
        addBtn.style.display = 'none';
        updateSummary();
        return;
    }
    
    // Build options HTML
    let optionsHtml = '';
    supplierItems.forEach(item => {
        optionsHtml += `<option value="${item.id}" data-unit="${item.unit}" data-price="${item.unit_price}" data-supplier="${item.supplier_id}">${escapeHtml(item.item_name)}</option>`;
    });
    
    // Clear items list and add low stock items first
    itemsList.innerHTML = '';
    
    // Add low stock items for this supplier
    if (supplierLowStockItems.length > 0) {
        supplierLowStockItems.forEach(item => {
            const row = createItemRow(item.id, item.item_name, item.unit, item.current_stock, item.minimum_stock, item.unit_price, optionsHtml);
            itemsList.appendChild(row);
        });
    } else {
        // Add one empty row if no low stock items
        const row = createItemRow(null, null, null, null, null, null, optionsHtml);
        itemsList.appendChild(row);
    }
    
    addBtn.style.display = 'block';
    updateSummary();
});

// Function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to format currency
function formatCurrency(amount) {
    return 'TZS ' + parseFloat(amount).toLocaleString();
}

// Function to create item row with editable price
function createItemRow(itemId, itemName, unit, currentStock, minStock, defaultPrice, optionsHtml) {
    const row = document.createElement('div');
    row.className = 'item-row';
    
    let selectHtml = `<select name="items[]" class="item-select">`;
    if (itemId) {
        selectHtml += `<option value="${itemId}" selected data-unit="${unit}" data-price="${defaultPrice}">${escapeHtml(itemName)}</option>`;
        selectHtml += optionsHtml.replace(new RegExp(`value="${itemId}"`, 'g'), `value="${itemId}" selected`);
    } else {
        selectHtml += optionsHtml;
    }
    selectHtml += `</select>`;
    
    const priceValue = defaultPrice || 0;
    
    row.innerHTML = `
        ${selectHtml}
        <span class="current-stock">${currentStock !== null ? currentStock + ' ' + (unit || '') : '-'}</span>
        <span class="default-price">
            ${defaultPrice ? formatCurrency(defaultPrice) : '-'}
        </span>
        <input type="number" name="prices[]" class="price-input" value="${priceValue}" step="100" min="0" placeholder="Unit price">
        <input type="number" name="quantities[]" class="quantity-input" value="${minStock ? Math.max(minStock * 2, 10) : 10}" min="1">
        <span class="item-total">${formatCurrency(0)}</span>
        <button type="button" class="remove-item" onclick="this.closest('.item-row').remove(); updateSummary();">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    const select = row.querySelector('.item-select');
    const priceInput = row.querySelector('.price-input');
    const qtyInput = row.querySelector('.quantity-input');
    const totalSpan = row.querySelector('.item-total');
    
    select.addEventListener('change', function() {
        loadItemDetails(this, row);
        updateItemTotal(row);
    });
    
    priceInput.addEventListener('input', function() {
        const newPrice = parseFloat(this.value) || 0;
        const selectedOption = select.options[select.selectedIndex];
        const defaultPriceVal = selectedOption ? parseFloat(selectedOption.dataset?.price || 0) : 0;
        
        if (newPrice !== defaultPriceVal && newPrice > 0 && defaultPriceVal > 0) {
            this.classList.add('changed');
            hasPriceChanges = true;
            document.getElementById('priceNote').style.display = 'flex';
        } else {
            this.classList.remove('changed');
            // Check if any other prices are changed
            checkAnyPriceChanges();
        }
        
        updateItemTotal(row);
    });
    
    qtyInput.addEventListener('input', function() {
        updateItemTotal(row);
        updateSummary();
    });
    
    // Load details if item is selected
    if (itemId) {
        loadItemDetails(select, row);
    }
    
    updateItemTotal(row);
    
    return row;
}

function checkAnyPriceChanges() {
    const priceInputs = document.querySelectorAll('.price-input');
    let anyChanged = false;
    
    priceInputs.forEach(input => {
        const row = input.closest('.item-row');
        const select = row.querySelector('.item-select');
        const selectedOption = select.options[select.selectedIndex];
        const defaultPrice = selectedOption ? parseFloat(selectedOption.dataset?.price || 0) : 0;
        const currentPrice = parseFloat(input.value) || 0;
        
        if (currentPrice !== defaultPrice && currentPrice > 0 && defaultPrice > 0) {
            anyChanged = true;
        }
    });
    
    hasPriceChanges = anyChanged;
    document.getElementById('priceNote').style.display = hasPriceChanges ? 'flex' : 'none';
}

function updateItemTotal(row) {
    const priceInput = row.querySelector('.price-input');
    const qtyInput = row.querySelector('.quantity-input');
    const totalSpan = row.querySelector('.item-total');
    
    const price = parseFloat(priceInput.value) || 0;
    const qty = parseInt(qtyInput.value) || 0;
    const total = price * qty;
    
    totalSpan.textContent = formatCurrency(total);
    updateSummary();
}

function loadItemDetails(select, row) {
    const itemId = select.value;
    
    if (itemId) {
        const selectedOption = select.options[select.selectedIndex];
        const defaultPrice = parseFloat(selectedOption.dataset?.price || 0);
        const unit = selectedOption.dataset?.unit || '';
        const priceInput = row.querySelector('.price-input');
        
        // Set default price
        priceInput.value = defaultPrice;
        priceInput.classList.remove('changed');
        
        // Fetch current stock and min stock from server
        fetch(`get_item_details.php?id=${itemId}`)
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    row.querySelector('.current-stock').textContent = (data.current_stock || 0) + ' ' + (unit || '');
                    row.querySelector('.default-price').innerHTML = defaultPrice ? formatCurrency(defaultPrice) : '-';
                }
                updateItemTotal(row);
                checkAnyPriceChanges();
            })
            .catch(() => {
                updateItemTotal(row);
            });
    } else {
        row.querySelector('.current-stock').textContent = '-';
        row.querySelector('.default-price').textContent = '-';
        row.querySelector('.price-input').value = 0;
        updateItemTotal(row);
    }
}

function updateSummary() {
    const rows = document.querySelectorAll('.item-row');
    const totalItems = rows.length;
    let totalQuantity = 0;
    let totalAmount = 0;
    
    rows.forEach(row => {
        const qty = parseInt(row.querySelector('.quantity-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        
        totalQuantity += qty;
        totalAmount += qty * price;
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('totalQuantity').textContent = totalQuantity;
    document.getElementById('totalAmount').innerHTML = formatCurrency(totalAmount);
}

// Add new item button
document.getElementById('addItemBtn').addEventListener('click', function() {
    const supplierId = document.getElementById('supplier_id').value;
    if (!supplierId) {
        showToast('Please select a supplier first!', 'error');
        return;
    }
    
    const itemsList = document.getElementById('items-list');
    const supplierItems = allItems.filter(item => item.supplier_id == supplierId);
    
    if (supplierItems.length === 0) {
        showToast('No items available for this supplier!', 'error');
        return;
    }
    
    let optionsHtml = '';
    supplierItems.forEach(item => {
        optionsHtml += `<option value="${item.id}" data-unit="${item.unit}" data-price="${item.unit_price}">${escapeHtml(item.item_name)}</option>`;
    });
    
    const row = createItemRow(null, null, null, null, null, null, optionsHtml);
    itemsList.appendChild(row);
    updateSummary();
});

// Reset form
document.getElementById('resetBtn').addEventListener('click', function() {
    setTimeout(() => {
        const supplierSelect = document.getElementById('supplier_id');
        if (supplierSelect.value) {
            supplierSelect.dispatchEvent(new Event('change'));
        }
        updateSummary();
        hasPriceChanges = false;
        document.getElementById('priceNote').style.display = 'none';
    }, 100);
});

// Form submit validation
const form = document.getElementById('poForm');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', function(e) {
    // Validate that all items have valid prices
    const priceInputs = document.querySelectorAll('.price-input');
    let hasInvalidPrice = false;
    
    priceInputs.forEach(input => {
        const price = parseFloat(input.value);
        if (isNaN(price) || price <= 0) {
            hasInvalidPrice = true;
            input.style.borderColor = '#EF4444';
        } else {
            input.style.borderColor = '#E5E7EB';
        }
    });
    
    if (hasInvalidPrice) {
        e.preventDefault();
        showToast('Please enter valid prices for all items (must be greater than 0)', 'error');
        return;
    }
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating PO...';
    submitBtn.disabled = true;
});

// Initial update
updateSummary();

// Function to show toast
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
</script>

<?php include '../templates/footer.php'; ?>
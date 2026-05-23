<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer']);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get suppliers
$suppliers_sql = "SELECT id, company_name, contact_person, email, phone FROM suppliers WHERE status = 'active' ORDER BY company_name";
$suppliers_result = $db->query($suppliers_sql);
$suppliers = $suppliers_result->fetch_all(MYSQLI_ASSOC);

// Get low stock items
$items_sql = "SELECT id, item_name, unit, current_stock, minimum_stock, unit_price 
              FROM inventory_items 
              WHERE status = 'active' AND current_stock <= minimum_stock 
              ORDER BY current_stock ASC";
$items_result = $db->query($items_sql);
$low_stock_items = $items_result->fetch_all(MYSQLI_ASSOC);

// Get all active items for manual addition
$all_items_sql = "SELECT id, item_name, unit, unit_price FROM inventory_items WHERE status = 'active' ORDER BY item_name";
$all_items_result = $db->query($all_items_sql);
$all_items = $all_items_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_po'])) {
    $supplier_id = intval($_POST['supplier_id']);
    $expected_delivery = $_POST['expected_delivery'];
    $notes = trim($_POST['notes']);
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
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
                if ($quantity > 0) {
                    // Get item price
                    $price_sql = "SELECT unit_price FROM inventory_items WHERE id = $item_id";
                    $price_result = $db->query($price_sql);
                    $price = $price_result->fetch_assoc()['unit_price'] ?? 0;
                    $total = $quantity * $price;
                    $total_amount += $total;
                    
                    $po_items[] = [
                        'item_id' => $item_id,
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'total_price' => $total
                    ];
                }
            }
            
            if (empty($po_items)) {
                throw new Exception("No items with valid quantity!");
            }
            
            // Insert purchase order
            $sql = "INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery, total_amount, created_by, notes) 
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sisdis", $po_number, $supplier_id, $expected_delivery, $total_amount, $user_id, $notes);
            $stmt->execute();
            $po_id = $db->insert_id;
            
            // Insert PO items
            foreach ($po_items as $item) {
                $sql = "INSERT INTO po_items (po_id, item_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("iiidd", $po_id, $item['item_id'], $item['quantity'], $item['unit_price'], $item['total_price']);
                $stmt->execute();
            }
            
            $db->commit();
            
            logActivity($user_id, 'Create PO', "Created purchase order: $po_number");
            $_SESSION['toast_message'] = "Purchase Order <strong>$po_number</strong> created successfully! Waiting for manager approval.";
            $_SESSION['toast_type'] = "success";
            
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
        <p>Create new purchase order for inventory replenishment</p>
    </div>
    
    <div class="two-column-layout">
        <!-- Left Column: Form -->
        <div class="form-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Purchase Order Details</h3>
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
                        
                        <div class="form-group">
                            <label><i class="fas fa-list"></i> Items to Order <span class="required">*</span></label>
                            <div class="items-container">
                                <div class="items-header">
                                    <span>Item</span>
                                    <span>Current Stock</span>
                                    <span>Min Stock</span>
                                    <span>Quantity</span>
                                    <span></span>
                                </div>
                                <div id="items-list">
                                    <?php if(count($low_stock_items) > 0): ?>
                                        <?php foreach($low_stock_items as $item): ?>
                                            <div class="item-row">
                                                <select name="items[]" class="item-select">
                                                    <option value="<?php echo $item['id']; ?>" selected>
                                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                                    </option>
                                                    <?php foreach($all_items as $all_item): ?>
                                                        <?php if($all_item['id'] != $item['id']): ?>
                                                            <option value="<?php echo $all_item['id']; ?>">
                                                                <?php echo htmlspecialchars($all_item['item_name']); ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                                <span class="current-stock"><?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?></span>
                                                <span class="min-stock"><?php echo $item['minimum_stock']; ?> <?php echo $item['unit']; ?></span>
                                                <input type="number" name="quantities[]" class="quantity-input" 
                                                       value="<?php echo max($item['minimum_stock'] * 2, 10); ?>" min="1">
                                                <button type="button" class="remove-item" onclick="this.closest('.item-row').remove(); updateSummary();">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-items">
                                            <p><i class="fas fa-check-circle"></i> No low stock items found</p>
                                            <p class="small">Click "Add Item" to manually add items</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-outline" id="addItemBtn">
                                    <i class="fas fa-plus"></i> Add Another Item
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Additional Notes</label>
                            <textarea name="notes" rows="3" placeholder="Special instructions, delivery requirements, etc..."></textarea>
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
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Submit for Approval
                            </button>
                            <button type="reset" class="btn-secondary" id="resetBtn">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Tips -->
        <div class="tips-column">
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Tips</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle"></i> Always select the correct supplier</li>
                        <li><i class="fas fa-check-circle"></i> Set realistic expected delivery dates</li>
                        <li><i class="fas fa-check-circle"></i> Review low stock items automatically added</li>
                        <li><i class="fas fa-check-circle"></i> Add notes for special requirements</li>
                        <li><i class="fas fa-check-circle"></i> PO will be sent to manager for approval</li>
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
                    ?>
                    <div class="stat-row">
                        <span class="stat-label">Active Suppliers:</span>
                        <span class="stat-number"><?php echo $total_suppliers; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Low Stock Items:</span>
                        <span class="stat-number <?php echo $low_stock_count > 0 ? 'warning' : ''; ?>"><?php echo $low_stock_count; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Items for dropdown (hidden template) -->
    <select id="itemTemplate" style="display: none;">
        <?php foreach($all_items as $item): ?>
            <option value="<?php echo $item['id']; ?>">
                <?php echo htmlspecialchars($item['item_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
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
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
        font-size: 18px;
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
    .form-group input,
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
    
    .items-container {
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 15px;
        background: #F9FAFB;
    }
    
    .items-header {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1.2fr 0.5fr;
        gap: 10px;
        padding: 10px;
        background: white;
        font-weight: 600;
        font-size: 12px;
        border-radius: 8px;
        margin-bottom: 10px;
        color: #6B7280;
    }
    
    .item-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1.2fr 0.5fr;
        gap: 10px;
        padding: 10px;
        background: white;
        border-radius: 8px;
        margin-bottom: 8px;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    .item-row select,
    .item-row input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        font-size: 13px;
    }
    
    .current-stock,
    .min-stock {
        font-size: 13px;
        color: #6B7280;
        text-align: center;
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
    
    .empty-items .small {
        font-size: 12px;
        margin-top: 5px;
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
    
    .tips-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .tips-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 0;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
        color: #374151;
    }
    
    .tips-list li:last-child {
        border-bottom: none;
    }
    
    .tips-list li i {
        color: #10B981;
        width: 20px;
    }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
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
        font-size: 18px;
    }
    
    .stat-number.warning {
        color: #FF6B6B;
    }
    
    @media (max-width: 900px) {
        .two-column-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .items-header {
            display: none;
        }
        
        .item-row {
            grid-template-columns: 1fr;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .item-row select,
        .item-row input,
        .item-row .current-stock,
        .item-row .min-stock,
        .item-row .remove-item {
            width: 100%;
        }
        
        .current-stock,
        .min-stock {
            text-align: left;
            padding: 5px 0;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
</style>

<script>
document.getElementById('addItemBtn').addEventListener('click', function() {
    const itemsList = document.getElementById('items-list');
    const template = document.getElementById('itemTemplate').innerHTML;
    
    // Remove empty-items message if exists
    const emptyDiv = itemsList.querySelector('.empty-items');
    if (emptyDiv) {
        emptyDiv.remove();
    }
    
    const newRow = document.createElement('div');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <select name="items[]" class="item-select">
            ${template}
        </select>
        <span class="current-stock">-</span>
        <span class="min-stock">-</span>
        <input type="number" name="quantities[]" class="quantity-input" value="10" min="1">
        <button type="button" class="remove-item" onclick="this.closest('.item-row').remove(); updateSummary();">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    const select = newRow.querySelector('.item-select');
    select.addEventListener('change', function() {
        loadItemDetails(this);
    });
    
    itemsList.appendChild(newRow);
    updateSummary();
});

function loadItemDetails(select) {
    const itemId = select.value;
    if (itemId) {
        fetch(`get_item_details.php?id=${itemId}`)
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    const row = select.closest('.item-row');
                    row.querySelector('.current-stock').textContent = data.current_stock + ' ' + data.unit;
                    row.querySelector('.min-stock').textContent = data.minimum_stock + ' ' + data.unit;
                }
            });
    }
}

function updateSummary() {
    const rows = document.querySelectorAll('.item-row');
    const totalItems = rows.length;
    let totalQuantity = 0;
    
    rows.forEach(row => {
        const qty = parseInt(row.querySelector('.quantity-input').value) || 0;
        totalQuantity += qty;
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('totalQuantity').textContent = totalQuantity;
}

// Add event listeners to existing rows
document.querySelectorAll('.item-select').forEach(select => {
    select.addEventListener('change', function() {
        loadItemDetails(this);
    });
});

document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('input', updateSummary);
});

// Reset form
document.getElementById('resetBtn').addEventListener('click', function() {
    setTimeout(() => {
        updateSummary();
    }, 100);
});

// Form submit loading state
const form = document.getElementById('poForm');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', function() {
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;
});

updateSummary();
</script>

<?php include '../templates/footer.php'; ?>
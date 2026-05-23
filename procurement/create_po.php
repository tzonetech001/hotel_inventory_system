<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_id = intval($_POST['supplier_id']);
    $expected_delivery = $_POST['expected_delivery'];
    $notes = trim($_POST['notes']);
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    if ($supplier_id <= 0 || empty($items)) {
        $error = "Please select a supplier and at least one item!";
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
            $success = "Purchase Order $po_number created successfully! Waiting for manager approval.";
            
            // Clear form
            $_POST = array();
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error creating purchase order: " . $e->getMessage();
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
    
    <?php if($success): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3>Purchase Order Details</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="poForm">
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Select Supplier *</label>
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
                    <label><i class="fas fa-list"></i> Items to Order</label>
                    <div class="items-container">
                        <div class="items-header">
                            <span>Item</span>
                            <span>Current Stock</span>
                            <span>Min Stock</span>
                            <span>Quantity to Order</span>
                            <span></span>
                        </div>
                        <div id="items-list">
                            <!-- Low stock items will be listed here -->
                            <?php foreach($low_stock_items as $item): ?>
                                <div class="item-row">
                                    <select name="items[]" class="item-select">
                                        <option value="<?php echo $item['id']; ?>" selected>
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                        </option>
                                        <?php foreach($all_items as $all_item): ?>
                                            <?php if($all_item['id'] != $item['id']): ?>
                                                <option value="<?php echo $all_item['id']; ?>">
                                                    <?php echo htmlspecialchars($all_item['name'] ?? $all_item['item_name']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="current-stock"><?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?></span>
                                    <span class="min-stock"><?php echo $item['minimum_stock']; ?> <?php echo $item['unit']; ?></span>
                                    <input type="number" name="quantities[]" class="quantity-input" 
                                           value="<?php echo $item['minimum_stock'] * 2; ?>" min="1">
                                    <button type="button" class="remove-item" onclick="this.closest('.item-row').remove()">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-outline" id="addItemBtn" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Add Another Item
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Additional Notes</label>
                    <textarea name="notes" rows="3" placeholder="Special instructions, delivery requirements, etc..."></textarea>
                </div>
                
                <div class="summary-box">
                    <h4>Order Summary</h4>
                    <div class="summary-row">
                        <span>Total Items:</span>
                        <span id="totalItems">0</span>
                    </div>
                    <div class="summary-row">
                        <span>Total Quantity:</span>
                        <span id="totalQuantity">0</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit for Approval
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
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
    .items-container {
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 15px;
    }
    
    .items-header {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1.5fr 0.5fr;
        gap: 10px;
        padding: 10px;
        background: #F9FAFB;
        font-weight: 600;
        font-size: 13px;
        border-radius: 6px;
        margin-bottom: 10px;
    }
    
    .item-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1.5fr 0.5fr;
        gap: 10px;
        padding: 10px;
        border-bottom: 1px solid #E5E7EB;
        align-items: center;
    }
    
    .item-row select, .item-row input {
        width: 100%;
        padding: 8px;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
    }
    
    .current-stock, .min-stock {
        font-size: 14px;
        color: #6B7280;
    }
    
    .remove-item {
        background: #FEE2E2;
        border: none;
        padding: 6px;
        border-radius: 6px;
        cursor: pointer;
        color: #991B1B;
        transition: all 0.3s;
    }
    
    .remove-item:hover {
        background: #FECACA;
    }
    
    .summary-box {
        background: #F0F9FF;
        padding: 15px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .summary-box h4 {
        margin-bottom: 10px;
        color: #1E3A8A;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
    }
    
    .alert-success, .alert-error {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
</style>

<script>
document.getElementById('addItemBtn').addEventListener('click', function() {
    const itemsList = document.getElementById('items-list');
    const template = document.getElementById('itemTemplate').innerHTML;
    
    const newRow = document.createElement('div');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <select name="items[]" class="item-select">
            ${template}
        </select>
        <span class="current-stock">-</span>
        <span class="min-stock">-</span>
        <input type="number" name="quantities[]" class="quantity-input" value="1" min="1">
        <button type="button" class="remove-item" onclick="this.closest('.item-row').remove()">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    // Add event listener to load item details
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
                const row = select.closest('.item-row');
                row.querySelector('.current-stock').textContent = data.current_stock + ' ' + data.unit;
                row.querySelector('.min-stock').textContent = data.minimum_stock + ' ' + data.unit;
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

updateSummary();
</script>

<?php include '../templates/footer.php'; ?>
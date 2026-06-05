<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];
$error = '';
$success_stock = '';

// Get active items with their supplier info
$items_sql = "SELECT i.id, i.item_name, i.unit, i.current_stock, i.supplier_id, s.company_name as supplier_name 
              FROM inventory_items i
              LEFT JOIN suppliers s ON i.supplier_id = s.id
              WHERE i.status = 'active' 
              ORDER BY i.item_name";
$items_result = $db->query($items_sql);
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// Get approved POs for reference selection (optional)
$approved_pos_sql = "SELECT po.id, po.po_number, s.company_name 
                     FROM purchase_orders po
                     JOIN suppliers s ON po.supplier_id = s.id
                     WHERE po.status IN ('approved', 'delivered', 'confirmed')
                     ORDER BY po.id DESC";
$approved_pos_result = $db->query($approved_pos_sql);
$approved_pos = $approved_pos_result->fetch_all(MYSQLI_ASSOC);

// Pre-select item if passed via GET
$selected_item = isset($_GET['item']) ? intval($_GET['item']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $po_reference = trim($_POST['po_reference'] ?? '');
    $notes = trim($_POST['notes']);
    
    if ($item_id <= 0 || $quantity <= 0) {
        $error = "Please select an item and enter valid quantity!";
    } else {
        // Check if there's an approved PO for this item's supplier (if PO is provided)
        $supplier_check_sql = "SELECT supplier_id FROM inventory_items WHERE id = ?";
        $supplier_stmt = $db->prepare($supplier_check_sql);
        $supplier_stmt->bind_param("i", $item_id);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        $item_data = $supplier_result->fetch_assoc();
        
        if (!$item_data) {
            $error = "Item not found!";
        } else {
            $supplier_id = $item_data['supplier_id'];
            $reference = '';
            $is_valid_po = true;
            
            // If PO reference is provided, validate it
            if (!empty($po_reference)) {
                // Check if PO exists and is approved for this supplier
                $po_check_sql = "SELECT id, status FROM purchase_orders 
                                WHERE po_number = ? AND supplier_id = ? 
                                AND status IN ('approved', 'delivered', 'confirmed')";
                $po_stmt = $db->prepare($po_check_sql);
                $po_stmt->bind_param("si", $po_reference, $supplier_id);
                $po_stmt->execute();
                $po_check_result = $po_stmt->get_result();
                
                if ($po_check_result->num_rows === 0) {
                    // Check if PO exists but not approved
                    $check_sql = "SELECT status FROM purchase_orders 
                                 WHERE po_number = ? AND supplier_id = ?";
                    $check_stmt = $db->prepare($check_sql);
                    $check_stmt->bind_param("si", $po_reference, $supplier_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $po_status = $check_result->fetch_assoc();
                        $error = "PO $po_reference exists but status is '" . ucfirst($po_status['status']) . "'. Only approved POs can be used!";
                    } else {
                        $error = "PO $po_reference not found or does not belong to this item's supplier!";
                    }
                    $is_valid_po = false;
                } else {
                    $reference = "PO Delivery - $po_reference";
                }
            } else {
                // No PO reference, generate generic reference
                $reference = "Direct Stock In - " . date('YmdHis');
            }
            
            if ($is_valid_po) {
                if (updateStock($item_id, $quantity, 'IN', $user_id, $reference)) {
                    logActivity($user_id, 'Stock IN', "Added $quantity units to item ID: $item_id. Reference: $reference");
                    
                    // Get item name for success message
                    $item_sql = "SELECT item_name FROM inventory_items WHERE id = ?";
                    $item_stmt = $db->prepare($item_sql);
                    $item_stmt->bind_param("i", $item_id);
                    $item_stmt->execute();
                    $item_result = $item_stmt->get_result();
                    $item_data_name = $item_result->fetch_assoc();
                    
                    if (!empty($po_reference)) {
                        $_SESSION['toast_message'] = "Successfully received <strong>" . number_format($quantity) . "</strong> units of <strong>" . htmlspecialchars($item_data_name['item_name']) . "</strong> with PO: $po_reference";
                    } else {
                        $_SESSION['toast_message'] = "Successfully received <strong>" . number_format($quantity) . "</strong> units of <strong>" . htmlspecialchars($item_data_name['item_name']) . "</strong>";
                    }
                    $_SESSION['toast_type'] = "success";
                    
                    header("Location: stock_in.php");
                    exit();
                } else {
                    $error = "Error adding stock!";
                }
            }
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-arrow-down"></i> Stock In</h1>
        <p>Receive goods and add to inventory</p>
    </div>
    
    <?php if($error): ?>
        <script>showToast('<?php echo addslashes($error); ?>', 'error');</script>
    <?php endif; ?>
    
    <!-- Two Column Layout -->
    <div class="two-column-layout">
        <!-- Left Column: Form -->
        <div class="form-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Record Stock Receipt</h3>
                    <p class="card-subtitle">Enter details of incoming goods</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="stockInForm">
                        <div class="form-group">
                            <label><i class="fas fa-box"></i> Select Item <span class="required">*</span></label>
                            <select name="item_id" id="item_id" required>
                                <option value="">-- Select Item --</option>
                                <?php foreach($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            data-current="<?php echo $item['current_stock']; ?>"
                                            data-unit="<?php echo $item['unit']; ?>"
                                            data-supplier="<?php echo $item['supplier_id']; ?>"
                                            data-supplier-name="<?php echo htmlspecialchars($item['supplier_name'] ?? 'No Supplier'); ?>"
                                            <?php echo ($selected_item == $item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_name']); ?> 
                                        (Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                        <?php if($item['supplier_name']): ?>
                                            - Supplier: <?php echo htmlspecialchars($item['supplier_name']); ?>
                                        <?php else: ?>
                                            - No Supplier Assigned!
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-file-invoice"></i> Purchase Order Number</label>
                            <input type="text" name="po_reference" id="po_reference" placeholder="Enter PO Number (Optional)" autocomplete="off">
                            <small>Enter PO number if this stock is from a purchase order</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-plus-circle"></i> Quantity <span class="required">*</span></label>
                                <input type="number" name="quantity" id="quantity" min="1" placeholder="Enter quantity" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-calculator"></i> New Stock After</label>
                                <div class="new-stock-display" id="newStockDisplay">
                                    <span id="newStockValue">-</span>
                                    <span id="newStockUnit"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Additional Notes</label>
                            <textarea name="notes" id="notes" rows="3" placeholder="Any additional information about this receipt..."></textarea>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <div class="info-content">
                                <strong>Note:</strong> PO number is optional. If you enter a PO number, it will be validated to ensure it's approved and matches the item's supplier.
                            </div>
                        </div>
                        
                        <div id="poValidationMsg" class="validation-msg" style="display: none;"></div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Record Stock In
                            </button>
                            <button type="reset" class="btn-secondary" id="resetBtn">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Tips & Recent -->
        <div class="info-column">
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Important Rules</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle"></i> PO number is <strong>optional</strong></li>
                        <li><i class="fas fa-check-circle"></i> If PO is entered, it must be approved</li>
                        <li><i class="fas fa-check-circle"></i> Item's supplier must match PO's supplier</li>
                        <li><i class="fas fa-check-circle"></i> Verify quantity before recording</li>
                        <li><i class="fas fa-check-circle"></i> All stock receipts are tracked</li>
                    </ul>
                </div>
            </div>
            
            <div class="card recent-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Stock In</h3>
                </div>
                <div class="card-body">
                    <?php
                    $recent_sql = "SELECT sm.*, i.item_name 
                                   FROM stock_movements sm
                                   JOIN inventory_items i ON sm.item_id = i.id
                                   WHERE sm.movement_type = 'IN'
                                   ORDER BY sm.created_at DESC LIMIT 5";
                    $recent_result = $db->query($recent_sql);
                    if($recent_result->num_rows > 0):
                    ?>
                        <div class="recent-list">
                            <?php while($movement = $recent_result->fetch_assoc()): ?>
                                <div class="recent-item">
                                    <div class="recent-icon">
                                        <i class="fas fa-arrow-down"></i>
                                    </div>
                                    <div class="recent-details">
                                        <div class="recent-item-name"><?php echo htmlspecialchars($movement['item_name']); ?></div>
                                        <div class="recent-meta">
                                            +<?php echo number_format($movement['quantity']); ?> units • 
                                            Ref: <?php echo htmlspecialchars($movement['reference_no'] ?? 'N/A'); ?> •
                                            <?php echo date('d/m H:i', strtotime($movement['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-small">
                            <i class="fas fa-inbox"></i>
                            <p>No recent stock in records</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .two-column-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
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
    
    .card-subtitle {
        margin: 5px 0 0;
        font-size: 13px;
        color: #6B7280;
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
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #6B7280;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .new-stock-display {
        background: #F3F4F6;
        padding: 12px 15px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #1E3A8A;
    }
    
    #newStockValue {
        font-size: 20px;
    }
    
    #newStockUnit {
        font-size: 14px;
        font-weight: normal;
        color: #6B7280;
    }
    
    .info-box {
        background: #DBEAFE;
        border-left: 4px solid #1E3A8A;
        padding: 15px;
        border-radius: 10px;
        margin: 20px 0;
        display: flex;
        gap: 12px;
    }
    
    .info-box i {
        font-size: 20px;
        color: #1E3A8A;
    }
    
    .info-content {
        flex: 1;
        font-size: 13px;
        color: #1E40AF;
        line-height: 1.5;
    }
    
    .validation-msg {
        padding: 12px;
        border-radius: 10px;
        margin: 15px 0;
        font-size: 13px;
    }
    
    .validation-msg.valid {
        background: #D1FAE5;
        color: #065F46;
        border-left: 4px solid #10B981;
    }
    
    .validation-msg.invalid {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 4px solid #EF4444;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
        flex-wrap: wrap;
    }
    
    .btn-primary {
        background: #10B981;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
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
        gap: 12px;
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
        font-size: 16px;
        width: 20px;
    }
    
    .recent-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .recent-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .recent-item:last-child {
        border-bottom: none;
    }
    
    .recent-icon {
        width: 36px;
        height: 36px;
        background: #D1FAE5;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #10B981;
    }
    
    .recent-details {
        flex: 1;
    }
    
    .recent-item-name {
        font-weight: 600;
        color: #1F2937;
        font-size: 14px;
    }
    
    .recent-meta {
        font-size: 11px;
        color: #6B7280;
        margin-top: 2px;
    }
    
    .empty-small {
        text-align: center;
        padding: 30px 20px;
    }
    
    .empty-small i {
        font-size: 36px;
        color: #D1D5DB;
        margin-bottom: 8px;
    }
    
    .empty-small p {
        font-size: 13px;
        color: #6B7280;
    }
    
    @media (max-width: 900px) {
        .two-column-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary, .btn-secondary {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<script>
    const itemSelect = document.getElementById('item_id');
    const quantityInput = document.getElementById('quantity');
    const poReference = document.getElementById('po_reference');
    const newStockValue = document.getElementById('newStockValue');
    const newStockUnit = document.getElementById('newStockUnit');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    const poValidationMsg = document.getElementById('poValidationMsg');
    
    // Store approved POs data from PHP
    const approvedPOs = <?php echo json_encode($approved_pos); ?>;
    
    function calculateNewStock() {
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (selectedOption.value) {
            const currentStock = parseInt(selectedOption.dataset.current) || 0;
            const unit = selectedOption.dataset.unit || '';
            const newStock = currentStock + quantity;
            
            newStockValue.textContent = newStock;
            newStockUnit.textContent = unit;
        } else {
            newStockValue.textContent = '-';
            newStockUnit.textContent = '';
        }
    }
    
    function validatePO() {
        const poNumber = poReference.value.trim();
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        
        // If no PO number, hide validation message
        if (!poNumber) {
            poValidationMsg.style.display = 'none';
            return true;
        }
        
        // If no item selected, show message
        if (!selectedOption.value) {
            poValidationMsg.style.display = 'block';
            poValidationMsg.className = 'validation-msg invalid';
            poValidationMsg.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Please select an item first`;
            return false;
        }
        
        const itemId = selectedOption.value;
        
        // Show loading state
        poValidationMsg.style.display = 'block';
        poValidationMsg.className = 'validation-msg';
        poValidationMsg.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Validating PO...`;
        
        // Validate via AJAX
        fetch(`check_po_supplier.php?po=${encodeURIComponent(poNumber)}&item_id=${itemId}`)
            .then(response => response.json())
            .then(data => {
                if (data.valid) {
                    poValidationMsg.className = 'validation-msg valid';
                    poValidationMsg.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                } else {
                    poValidationMsg.className = 'validation-msg invalid';
                    poValidationMsg.innerHTML = `<i class="fas fa-times-circle"></i> ${data.message}`;
                }
            })
            .catch(() => {
                poValidationMsg.className = 'validation-msg invalid';
                poValidationMsg.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Error validating PO number`;
            });
        
        return true;
    }
    
    // Debounce function for PO validation
    let poValidationTimeout;
    poReference.addEventListener('input', function() {
        clearTimeout(poValidationTimeout);
        poValidationTimeout = setTimeout(validatePO, 500);
    });
    
    itemSelect.addEventListener('change', function() {
        calculateNewStock();
        if (poReference.value.trim()) {
            validatePO();
        }
    });
    
    quantityInput.addEventListener('input', calculateNewStock);
    
    // Form submit validation
    const form = document.getElementById('stockInForm');
    
    form.addEventListener('submit', function(e) {
        const itemId = itemSelect.value;
        const quantity = quantityInput.value;
        const poNumber = poReference.value.trim();
        
        if (!itemId) {
            e.preventDefault();
            showToast('Please select an item!', 'error');
            return;
        }
        
        if (!quantity || quantity <= 0) {
            e.preventDefault();
            showToast('Please enter a valid quantity!', 'error');
            return;
        }
        
        // If PO is provided, validate it before submit
        if (poNumber) {
            e.preventDefault();
            
            fetch(`check_po_supplier.php?po=${encodeURIComponent(poNumber)}&item_id=${itemId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        // Proceed with form submission
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recording...';
                        submitBtn.disabled = true;
                        form.submit();
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(() => {
                    showToast('Error validating PO number', 'error');
                });
        }
        // If no PO, submit directly
    });
    
    resetBtn.addEventListener('click', function() {
        setTimeout(() => {
            calculateNewStock();
            poValidationMsg.style.display = 'none';
            poReference.value = '';
        }, 100);
    });
    
    // Pre-select item from URL
    <?php if($selected_item > 0): ?>
        itemSelect.value = <?php echo $selected_item; ?>;
        calculateNewStock();
    <?php endif; ?>
    
    // Focus on quantity if item is pre-selected
    if (itemSelect.value) {
        quantityInput.focus();
    }
</script>

<?php include '../templates/footer.php'; ?>
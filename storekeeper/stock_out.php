<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];
$error = '';

// Get active items with stock > 0
$items_sql = "SELECT id, item_name, unit, current_stock FROM inventory_items 
              WHERE status = 'active' AND current_stock > 0 
              ORDER BY item_name";
$items_result = $db->query($items_sql);
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// Pre-select item if passed via GET
$selected_item = isset($_GET['item']) ? intval($_GET['item']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $department = trim($_POST['department']);
    $reason = trim($_POST['reason']);
    $reference = trim($_POST['reference']);
    
    if ($item_id <= 0 || $quantity <= 0) {
        $error = "Please select an item and enter valid quantity!";
    } else {
        $current_stock = getCurrentStock($item_id);
        
        if ($quantity > $current_stock) {
            $error = "Insufficient stock! Available: $current_stock";
        } else {
            if (updateStock($item_id, $quantity, 'OUT', $user_id, $reference)) {
                logActivity($user_id, 'Stock OUT', "Removed $quantity units from item ID: $item_id. Department: $department");
                
                // Get item name for success message
                $item_sql = "SELECT item_name FROM inventory_items WHERE id = ?";
                $item_stmt = $db->prepare($item_sql);
                $item_stmt->bind_param("i", $item_id);
                $item_stmt->execute();
                $item_result = $item_stmt->get_result();
                $item_data = $item_result->fetch_assoc();
                
                $_SESSION['toast_message'] = "Successfully issued <strong>" . number_format($quantity) . "</strong> units of <strong>" . htmlspecialchars($item_data['item_name']) . "</strong> to " . htmlspecialchars($department);
                $_SESSION['toast_type'] = "success";
                
                header("Location: stock_out.php");
                exit();
            } else {
                $error = "Error removing stock!";
            }
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-arrow-up"></i> Stock Out</h1>
        <p>Record goods issued from inventory</p>
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
                    <h3><i class="fas fa-clipboard-list"></i> Record Stock Issue</h3>
                    <p class="card-subtitle">Enter details of goods being issued</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="stockOutForm">
                        <div class="form-group">
                            <label><i class="fas fa-box"></i> Select Item <span class="required">*</span></label>
                            <select name="item_id" id="item_id" required>
                                <option value="">-- Select Item --</option>
                                <?php foreach($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            data-current="<?php echo $item['current_stock']; ?>"
                                            data-unit="<?php echo $item['unit']; ?>"
                                            data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                            <?php echo ($selected_item == $item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_name']); ?> 
                                        (Available: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if(count($items) == 0): ?>
                                <div class="warning-box">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No items with stock available. Please add items or receive stock first.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-minus-circle"></i> Quantity <span class="required">*</span></label>
                                <input type="number" name="quantity" id="quantity" min="1" placeholder="Enter quantity" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-calculator"></i> Remaining Stock</label>
                                <div class="remaining-stock-display" id="remainingStockDisplay">
                                    <span id="remainingStockValue">-</span>
                                    <span id="remainingStockUnit"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Department/Usage <span class="required">*</span></label>
                            <select name="department" id="department" required>
                                <option value="">-- Select Department --</option>
                                <option value="Restaurant">🍽️ Restaurant</option>
                                <option value="Bar">🍺 Bar</option>
                                <option value="Housekeeping">🛏️ Housekeeping</option>
                                <option value="Kitchen">🍳 Kitchen</option>
                                <option value="Laundry">🧺 Laundry</option>
                                <option value="Maintenance">🔧 Maintenance</option>
                                <option value="Other">📦 Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Reason for Issue</label>
                            <textarea name="reason" id="reason" rows="2" placeholder="e.g., Daily usage, Guest request, Maintenance..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> Reference Number</label>
                            <input type="text" name="reference" id="reference" placeholder="e.g., Requisition #, Department Request #">
                        </div>
                        
                        <div class="warning-box" id="warningBox" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="warningMessage"></span>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <div class="info-content">
                                Recording stock out will decrease the current inventory level.
                                Always verify the quantity before confirming.
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Record Stock Out
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
            
            
            <div class="card recent-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Stock Out</h3>
                </div>
                <div class="card-body">
                    <?php
                    $recent_sql = "SELECT sm.*, i.item_name 
                                   FROM stock_movements sm
                                   JOIN inventory_items i ON sm.item_id = i.id
                                   WHERE sm.movement_type = 'OUT'
                                   ORDER BY sm.created_at DESC LIMIT 5";
                    $recent_result = $db->query($recent_sql);
                    if($recent_result->num_rows > 0):
                    ?>
                        <div class="recent-list">
                            <?php while($movement = $recent_result->fetch_assoc()): ?>
                                <div class="recent-item">
                                    <div class="recent-icon out">
                                        <i class="fas fa-arrow-up"></i>
                                    </div>
                                    <div class="recent-details">
                                        <div class="recent-item-name"><?php echo htmlspecialchars($movement['item_name']); ?></div>
                                        <div class="recent-meta">
                                            -<?php echo number_format($movement['quantity']); ?> units • 
                                            <?php echo date('d/m H:i', strtotime($movement['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-small">
                            <i class="fas fa-inbox"></i>
                            <p>No recent stock out records</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Two Column Layout */
    .two-column-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 25px;
    }
    
    /* Animations */
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
    
    /* Card Styles */
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
    
    /* Form Styles */
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
        min-height: 60px;
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
    
    /* Remaining Stock Display */
    .remaining-stock-display {
        background: #F3F4F6;
        padding: 12px 15px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #1E3A8A;
    }
    
    #remainingStockValue {
        font-size: 20px;
    }
    
    #remainingStockUnit {
        font-size: 14px;
        font-weight: normal;
        color: #6B7280;
    }
    
    /* Warning Box */
    .warning-box {
        background: #FEF3C7;
        border-left: 4px solid #F59E0B;
        padding: 12px;
        border-radius: 8px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #92400E;
    }
    
    .warning-box i {
        font-size: 18px;
    }
    
    /* Info Box */
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
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
        flex-wrap: wrap;
    }
    
    .btn-primary {
        background: #EF4444;
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
        background: #DC2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239,68,68,0.3);
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
    
    /* Tips List */
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
    
    /* Recent List */
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
        background: #FEE2E2;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #EF4444;
    }
    
    .recent-icon.out {
        background: #FEE2E2;
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
    
    /* Responsive */
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
    const remainingStockValue = document.getElementById('remainingStockValue');
    const remainingStockUnit = document.getElementById('remainingStockUnit');
    const warningBox = document.getElementById('warningBox');
    const warningMessage = document.getElementById('warningMessage');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    
    function calculateRemaining() {
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (selectedOption.value) {
            const currentStock = parseInt(selectedOption.dataset.current) || 0;
            const unit = selectedOption.dataset.unit || '';
            const itemName = selectedOption.dataset.name;
            const remaining = currentStock - quantity;
            
            remainingStockValue.textContent = remaining >= 0 ? remaining : 0;
            remainingStockUnit.textContent = unit;
            
            if (quantity > currentStock) {
                warningBox.style.display = 'flex';
                warningMessage.innerHTML = `⚠️ Insufficient stock! Only ${currentStock} ${unit} of ${itemName} available.`;
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                remainingStockValue.style.color = '#EF4444';
            } else if (quantity > 0 && quantity > currentStock * 0.8) {
                warningBox.style.display = 'flex';
                warningMessage.innerHTML = `⚠️ Warning: This will use ${Math.round((quantity/currentStock)*100)}% of available stock. Remaining: ${remaining} ${unit}`;
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                remainingStockValue.style.color = '#F59E0B';
            } else {
                warningBox.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                remainingStockValue.style.color = '#1E3A8A';
            }
        } else {
            remainingStockValue.textContent = '-';
            remainingStockUnit.textContent = '';
            warningBox.style.display = 'none';
            remainingStockValue.style.color = '#1E3A8A';
        }
    }
    
    itemSelect.addEventListener('change', calculateRemaining);
    quantityInput.addEventListener('input', calculateRemaining);
    
    // Form submit loading state
    const form = document.getElementById('stockOutForm');
    
    form.addEventListener('submit', function(e) {
        const itemId = itemSelect.value;
        const quantity = quantityInput.value;
        const department = document.getElementById('department').value;
        
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
        
        if (!department) {
            e.preventDefault();
            showToast('Please select a department!', 'error');
            return;
        }
        
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const currentStock = parseInt(selectedOption.dataset.current) || 0;
        
        if (quantity > currentStock) {
            e.preventDefault();
            showToast('Insufficient stock!', 'error');
            return;
        }
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recording...';
        submitBtn.disabled = true;
    });
    
    resetBtn.addEventListener('click', function() {
        setTimeout(() => {
            calculateRemaining();
        }, 100);
    });
    
    // Pre-select item from URL
    <?php if($selected_item > 0): ?>
        itemSelect.value = <?php echo $selected_item; ?>;
        calculateRemaining();
        quantityInput.focus();
    <?php endif; ?>
</script>

<?php include '../templates/footer.php'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Storekeeper can access
checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];
$error = '';

// Get departments
$departments_sql = "SELECT id, department_name, department_code FROM departments WHERE status = 'active' ORDER BY department_name";
$departments_result = $db->query($departments_sql);
$departments = $departments_result->fetch_all(MYSQLI_ASSOC);

// Get active items with stock > 0
$items_sql = "SELECT id, item_name, unit, current_stock, default_department_id 
              FROM inventory_items 
              WHERE status = 'active' AND current_stock > 0 
              ORDER BY item_name";
$items_result = $db->query($items_sql);
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// Pre-select item if passed via GET
$selected_item = isset($_GET['item']) ? intval($_GET['item']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_request'])) {
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $department_id = intval($_POST['department_id']);
    $notes = trim($_POST['notes']);
    
    if ($item_id <= 0 || $quantity <= 0 || $department_id <= 0) {
        $_SESSION['toast_message'] = "Please select an item, enter valid quantity, and select a department!";
        $_SESSION['toast_type'] = "error";
    } else {
        $current_stock = getCurrentStock($item_id);
        
        if ($quantity > $current_stock) {
            $_SESSION['toast_message'] = "Insufficient stock! Available: $current_stock";
            $_SESSION['toast_type'] = "error";
        } else {
            // Generate unique request code
            $request_code = 'REQ-' . date('Ymd') . '-' . strtoupper(uniqid());
            
            // Generate QR code data
            $qr_data = json_encode([
                'request_code' => $request_code,
                'item_id' => $item_id,
                'quantity' => $quantity,
                'department_id' => $department_id,
                'created_by' => $user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            // Encode QR data for storage
            $qr_code_encoded = base64_encode($qr_data);
            
            $sql = "INSERT INTO stock_requests (request_code, item_id, quantity, department_id, requested_by, qr_code, request_date, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("siiiiss", $request_code, $item_id, $quantity, $department_id, $user_id, $qr_code_encoded, $notes);
            
            if ($stmt->execute()) {
                $request_id = $db->insert_id;
                
                // Log activity
                logActivity($user_id, 'Stock Request', "Created stock request #$request_code for $quantity units of item ID: $item_id");
                
                $_SESSION['toast_message'] = "Stock request <strong>$request_code</strong> created successfully! Department staff must scan QR code to confirm.";
                $_SESSION['toast_type'] = "success";
                
                // Redirect to show QR code
                header("Location: show_qr.php?id=$request_id");
                exit();
            } else {
                $_SESSION['toast_message'] = "Error creating request: " . $db->error;
                $_SESSION['toast_type'] = "error";
            }
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-qrcode"></i> Stock Out Request</h1>
        <p>Create request for department to confirm via QR code scan</p>
    </div>
    
    <div class="two-column-layout">
        <!-- Left Column: Form -->
        <div class="form-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Create Stock Request</h3>
                    <p class="card-subtitle">Select item, quantity, and department</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="stockRequestForm">
                        <input type="hidden" name="create_request" value="1">
                        
                        <div class="form-group">
                            <label><i class="fas fa-box"></i> Select Item <span class="required">*</span></label>
                            <select name="item_id" id="item_id" required>
                                <option value="">-- Select Item --</option>
                                <?php foreach($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            data-current="<?php echo $item['current_stock']; ?>"
                                            data-unit="<?php echo $item['unit']; ?>"
                                            data-default-dept="<?php echo $item['default_department_id']; ?>"
                                            <?php echo ($selected_item == $item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_name']); ?> 
                                        (Available: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Requesting Department <span class="required">*</span></label>
                            <select name="department_id" id="department_id" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" data-code="<?php echo $dept['department_code']; ?>">
                                        <i class="fas fa-<?php echo strtolower($dept['department_code']); ?>"></i>
                                        <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo $dept['department_code']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>This department will confirm the stock out via QR code</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-minus-circle"></i> Quantity <span class="required">*</span></label>
                                <input type="number" name="quantity" id="quantity" min="1" placeholder="Enter quantity" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-calculator"></i> Remaining After</label>
                                <div class="remaining-stock-display" id="remainingStockDisplay">
                                    <span id="remainingStockValue">-</span>
                                    <span id="remainingStockUnit"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Notes / Reason</label>
                            <textarea name="notes" rows="3" placeholder="Reason for request, specific usage details..."></textarea>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-qrcode"></i>
                            <div class="info-content">
                                <strong>QR Code Approval Process:</strong><br>
                                1. You create request with quantity and department<br>
                                2. System generates unique QR code<br>
                                3. Department staff scans QR code to confirm receipt<br>
                                4. Stock is automatically deducted after confirmation
                            </div>
                        </div>
                        
                        <div class="warning-box" id="warningBox" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="warningMessage"></span>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-qrcode"></i> Create Request & Generate QR
                            </button>
                            <button type="reset" class="btn-secondary" id="resetBtn">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Tips & Pending Requests -->
        <div class="info-column">
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> How It Works</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-qrcode"></i> <strong>1. Create Request</strong> - Select item and department</li>
                        <li><i class="fas fa-print"></i> <strong>2. Print/Save QR</strong> - Give QR to department staff</li>
                        <li><i class="fas fa-camera"></i> <strong>3. Scan to Confirm</strong> - Department scans QR code</li>
                        <li><i class="fas fa-check-circle"></i> <strong>4. Auto Deduct</strong> - Stock automatically updates</li>
                    </ul>
                </div>
            </div>
            
            <div class="card pending-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Pending Confirmations</h3>
                </div>
                <div class="card-body">
                    <?php
                    $pending_sql = "SELECT sr.*, i.item_name, i.unit, d.department_name 
                                   FROM stock_requests sr
                                   JOIN inventory_items i ON sr.item_id = i.id
                                   JOIN departments d ON sr.department_id = d.id
                                   WHERE sr.status = 'pending' AND sr.requested_by = $user_id
                                   ORDER BY sr.created_at DESC LIMIT 5";
                    $pending_result = $db->query($pending_sql);
                    
                    if($pending_result->num_rows > 0):
                    ?>
                        <div class="pending-list">
                            <?php while($request = $pending_result->fetch_assoc()): ?>
                                <div class="pending-item">
                                    <div class="pending-info">
                                        <div class="request-code">
                                            <i class="fas fa-qrcode"></i>
                                            <?php echo htmlspecialchars($request['request_code']); ?>
                                        </div>
                                        <div class="request-details">
                                            <?php echo htmlspecialchars($request['item_name']); ?> - 
                                            <?php echo $request['quantity']; ?> <?php echo $request['unit']; ?>
                                        </div>
                                        <div class="request-department">
                                            <i class="fas fa-building"></i>
                                            <?php echo htmlspecialchars($request['department_name']); ?>
                                        </div>
                                    </div>
                                    <div class="pending-actions">
                                        <a href="show_qr.php?id=<?php echo $request['id']; ?>" class="btn-small">
                                            <i class="fas fa-qrcode"></i> Show QR
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-small">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending requests</p>
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
    
    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
        font-family: inherit;
    }
    
    .form-group select:focus,
    .form-group input:focus,
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
        font-size: 24px;
        color: #1E3A8A;
    }
    
    .info-content {
        flex: 1;
        font-size: 13px;
        color: #1E40AF;
        line-height: 1.5;
    }
    
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
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
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
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
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
        font-size: 18px;
        width: 24px;
    }
    
    .pending-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .pending-item {
        padding: 15px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .pending-item:last-child {
        border-bottom: none;
    }
    
    .request-code {
        font-weight: 600;
        color: #1E3A8A;
        font-size: 12px;
        margin-bottom: 5px;
    }
    
    .request-details {
        font-size: 14px;
        color: #374151;
        margin-bottom: 5px;
    }
    
    .request-department {
        font-size: 11px;
        color: #6B7280;
    }
    
    .btn-small {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #F3F4F6;
        padding: 6px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 12px;
        color: #1E3A8A;
        margin-top: 8px;
        transition: all 0.3s;
    }
    
    .btn-small:hover {
        background: #1E3A8A;
        color: white;
    }
    
    .empty-small {
        text-align: center;
        padding: 30px 20px;
    }
    
    .empty-small i {
        font-size: 36px;
        color: #10B981;
        margin-bottom: 10px;
    }
    
    .empty-small p {
        color: #6B7280;
        font-size: 13px;
    }
    
    @media (max-width: 900px) {
        .two-column-layout {
            grid-template-columns: 1fr;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
</style>

<script>
    const itemSelect = document.getElementById('item_id');
    const departmentSelect = document.getElementById('department_id');
    const quantityInput = document.getElementById('quantity');
    const remainingStockValue = document.getElementById('remainingStockValue');
    const remainingStockUnit = document.getElementById('remainingStockUnit');
    const warningBox = document.getElementById('warningBox');
    const warningMessage = document.getElementById('warningMessage');
    const submitBtn = document.getElementById('submitBtn');
    
    function calculateRemaining() {
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (selectedOption.value) {
            const currentStock = parseInt(selectedOption.dataset.current) || 0;
            const unit = selectedOption.dataset.unit || '';
            const itemName = selectedOption.text.split('(')[0].trim();
            const remaining = currentStock - quantity;
            
            remainingStockValue.textContent = remaining >= 0 ? remaining : 0;
            remainingStockUnit.textContent = unit;
            
            if (quantity > currentStock) {
                warningBox.style.display = 'flex';
                warningMessage.innerHTML = `⚠️ Insufficient stock! Only ${currentStock} ${unit} of ${itemName} available.`;
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                remainingStockValue.style.color = '#EF4444';
            } else if (quantity > 0) {
                warningBox.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                remainingStockValue.style.color = remaining < 0 ? '#EF4444' : '#1E3A8A';
            }
        } else {
            remainingStockValue.textContent = '-';
            remainingStockUnit.textContent = '';
            warningBox.style.display = 'none';
        }
    }
    
    // Auto-select department if item has default department
    itemSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const defaultDept = selectedOption.dataset.defaultDept;
        
        if (defaultDept && defaultDept != '') {
            departmentSelect.value = defaultDept;
        }
        
        calculateRemaining();
    });
    
    quantityInput.addEventListener('input', calculateRemaining);
    
    // Form validation
    const form = document.getElementById('stockRequestForm');
    
    form.addEventListener('submit', function(e) {
        const itemId = itemSelect.value;
        const departmentId = departmentSelect.value;
        const quantity = quantityInput.value;
        
        if (!itemId) {
            e.preventDefault();
            showToast('Please select an item!', 'error');
            return;
        }
        
        if (!departmentId) {
            e.preventDefault();
            showToast('Please select a department!', 'error');
            return;
        }
        
        if (!quantity || quantity <= 0) {
            e.preventDefault();
            showToast('Please enter a valid quantity!', 'error');
            return;
        }
        
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const currentStock = parseInt(selectedOption.dataset.current) || 0;
        
        if (quantity > currentStock) {
            e.preventDefault();
            showToast('Insufficient stock!', 'error');
            return;
        }
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Request...';
        submitBtn.disabled = true;
    });
    
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
    
    // Pre-select from URL
    <?php if($selected_item > 0): ?>
        itemSelect.value = <?php echo $selected_item; ?>;
        calculateRemaining();
        quantityInput.focus();
    <?php endif; ?>
</script>

<?php include '../templates/footer.php'; ?>
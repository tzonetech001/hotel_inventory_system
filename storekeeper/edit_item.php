<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper', 'Admin']);

$user_id = $_SESSION['user_id'];
$error = '';

$item_id = intval($_GET['id'] ?? 0);

if ($item_id <= 0) {
    header("Location: view_items.php");
    exit();
}

// Get item details
$sql = "SELECT * FROM inventory_items WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    header("Location: view_items.php");
    exit();
}

// Get suppliers
$suppliers_sql = "SELECT id, company_name FROM suppliers WHERE status = 'active' ORDER BY company_name";
$suppliers_result = $db->query($suppliers_sql);
$suppliers = $suppliers_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $minimum_stock = intval($_POST['minimum_stock']);
    $maximum_stock = intval($_POST['maximum_stock']);
    $unit_price = floatval($_POST['unit_price']);
    $supplier_id = intval($_POST['supplier_id']);
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    
    if (empty($item_name)) {
        $error = "Item name is required!";
    } else {
        $sql = "UPDATE inventory_items SET 
                item_name = ?, category = ?, unit = ?, minimum_stock = ?, 
                maximum_stock = ?, unit_price = ?, supplier_id = ?, location = ?, status = ?
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssiiidisi", $item_name, $category, $unit, $minimum_stock, 
                          $maximum_stock, $unit_price, $supplier_id, $location, $status, $item_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Edit Item', "Updated item: $item_name (ID: $item_id)");
            
            $_SESSION['toast_message'] = "Item <strong>" . htmlspecialchars($item_name) . "</strong> updated successfully!";
            $_SESSION['toast_type'] = "success";
            
            header("Location: view_items.php");
            exit();
        } else {
            $error = "Error updating item: " . $db->error;
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Item</h1>
        <p>Update item information for <?php echo htmlspecialchars($item['item_name']); ?></p>
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
                    <h3><i class="fas fa-edit"></i> Edit Item Information</h3>
                    <p class="card-subtitle">Update the details for this inventory item</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="editItemForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Item Name <span class="required">*</span></label>
                            <input type="text" name="item_name" id="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-folder"></i> Category</label>
                                <select name="category" id="category">
                                    <option value="">Select Category</option>
                                    <option value="Food" <?php echo $item['category'] == 'Food' ? 'selected' : ''; ?>>Food</option>
                                    <option value="Beverages" <?php echo $item['category'] == 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Cleaning" <?php echo $item['category'] == 'Cleaning' ? 'selected' : ''; ?>>Cleaning Supplies</option>
                                    <option value="Equipment" <?php echo $item['category'] == 'Equipment' ? 'selected' : ''; ?>>Equipment</option>
                                    <option value="Linens" <?php echo $item['category'] == 'Linens' ? 'selected' : ''; ?>>Linens</option>
                                    <option value="Other" <?php echo $item['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-balance-scale"></i> Unit of Measure</label>
                                <select name="unit" id="unit">
                                    <option value="kg" <?php echo $item['unit'] == 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                                    <option value="liters" <?php echo $item['unit'] == 'liters' ? 'selected' : ''; ?>>Liters</option>
                                    <option value="pieces" <?php echo $item['unit'] == 'pieces' ? 'selected' : ''; ?>>Pieces</option>
                                    <option value="boxes" <?php echo $item['unit'] == 'boxes' ? 'selected' : ''; ?>>Boxes</option>
                                    <option value="cartons" <?php echo $item['unit'] == 'cartons' ? 'selected' : ''; ?>>Cartons</option>
                                    <option value="bottles" <?php echo $item['unit'] == 'bottles' ? 'selected' : ''; ?>>Bottles</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-dollar-sign"></i> Unit Price (TZS)</label>
                                <input type="number" step="0.01" name="unit_price" id="unit_price" value="<?php echo $item['unit_price']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Storage Location</label>
                                <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>" placeholder="e.g., Warehouse A, Shelf 1">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-exclamation-triangle"></i> Minimum Stock</label>
                                <input type="number" name="minimum_stock" id="minimum_stock" value="<?php echo $item['minimum_stock']; ?>" min="0">
                                <small>Stock below this level triggers alert</small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-chart-line"></i> Maximum Stock</label>
                                <input type="number" name="maximum_stock" id="maximum_stock" value="<?php echo $item['maximum_stock']; ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-truck"></i> Supplier</label>
                                <select name="supplier_id" id="supplier_id">
                                    <option value="">Select Supplier</option>
                                    <?php foreach($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>" <?php echo $item['supplier_id'] == $supplier['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supplier['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-toggle-on"></i> Status</label>
                                <select name="status" id="status">
                                    <option value="active" <?php echo $item['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $item['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <small>Inactive items won't appear in dropdowns</small>
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <div class="info-content">
                                <strong>Current Stock: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?></strong><br>
                                To update stock levels, please use 
                                <a href="stock_in.php">Stock In</a> or 
                                <a href="stock_out.php">Stock Out</a>.
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Update Item
                            </button>
                            <a href="view_items.php" class="btn-outline">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Quick Links -->
        <div class="info-column">
            <div class="card stock-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-boxes"></i> Stock Management</h3>
                </div>
                <div class="card-body">
                    <div class="quick-stock-actions">
                        <a href="stock_in.php?item=<?php echo $item_id; ?>" class="stock-action in">
                            <i class="fas fa-arrow-down"></i>
                            <div>
                                <strong>Stock In</strong>
                                <small>Receive more stock</small>
                            </div>
                        </a>
                        <a href="stock_out.php?item=<?php echo $item_id; ?>" class="stock-action out">
                            <i class="fas fa-arrow-up"></i>
                            <div>
                                <strong>Stock Out</strong>
                                <small>Issue from inventory</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card info-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Item Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="stat-items">
                        <div class="stat-item">
                            <span class="stat-label">Current Stock:</span>
                            <span class="stat-value <?php echo ($item['current_stock'] <= $item['minimum_stock']) ? 'text-warning' : 'text-success'; ?>">
                                <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>
                            </span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Minimum Stock:</span>
                            <span class="stat-value"><?php echo $item['minimum_stock']; ?> <?php echo $item['unit']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Maximum Stock:</span>
                            <span class="stat-value"><?php echo $item['maximum_stock']; ?> <?php echo $item['unit']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Stock Status:</span>
                            <span class="stat-value">
                                <?php if($item['current_stock'] <= $item['minimum_stock']): ?>
                                    <span class="badge-danger">Critical - Need Reorder</span>
                                <?php elseif($item['current_stock'] >= $item['maximum_stock']): ?>
                                    <span class="badge-warning">Over Stocked</span>
                                <?php else: ?>
                                    <span class="badge-success">Normal</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
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
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
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
    
    .info-content a {
        color: #1E3A8A;
        font-weight: 600;
        text-decoration: none;
    }
    
    .info-content a:hover {
        text-decoration: underline;
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
        background: #FF6B6B;
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
        background: #e55a5a;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,107,107,0.3);
    }
    
    .btn-outline {
        background: transparent;
        border: 1px solid #1E3A8A;
        color: #1E3A8A;
        padding: 12px 24px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-outline:hover {
        background: #1E3A8A;
        color: white;
    }
    
    /* Quick Stock Actions */
    .quick-stock-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .stock-action {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .stock-action.in {
        background: #D1FAE5;
        border-left: 4px solid #10B981;
    }
    
    .stock-action.out {
        background: #FEE2E2;
        border-left: 4px solid #EF4444;
    }
    
    .stock-action i {
        font-size: 28px;
    }
    
    .stock-action.in i {
        color: #10B981;
    }
    
    .stock-action.out i {
        color: #EF4444;
    }
    
    .stock-action strong {
        display: block;
        font-size: 14px;
        color: #1F2937;
    }
    
    .stock-action small {
        font-size: 11px;
        color: #6B7280;
    }
    
    .stock-action:hover {
        transform: translateX(5px);
    }
    
    /* Statistics */
    .stat-items {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .stat-item:last-child {
        border-bottom: none;
    }
    
    .stat-label {
        font-size: 13px;
        color: #6B7280;
    }
    
    .stat-value {
        font-size: 13px;
        font-weight: 600;
        color: #1F2937;
    }
    
    .text-success {
        color: #10B981;
    }
    
    .text-warning {
        color: #F59E0B;
    }
    
    .badge-success, .badge-warning, .badge-danger {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .badge-success {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .badge-warning {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .badge-danger {
        background: #FEE2E2;
        color: #991B1B;
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
        
        .btn-primary, .btn-outline {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<script>
    // Form submit loading state
    const form = document.getElementById('editItemForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(e) {
        const itemName = document.getElementById('item_name').value;
        if (!itemName) {
            e.preventDefault();
            showToast('Item name is required!', 'error');
            return;
        }
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Item...';
        submitBtn.disabled = true;
    });
    
    // Auto focus
    document.getElementById('item_name').focus();
</script>

<?php include '../templates/footer.php'; ?>
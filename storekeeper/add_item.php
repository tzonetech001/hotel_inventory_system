<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Storekeeper can access
checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];
$error = '';

// Get suppliers for dropdown
$suppliers_sql = "SELECT id, company_name FROM suppliers WHERE status = 'active' ORDER BY company_name";
$suppliers_result = $db->query($suppliers_sql);
$suppliers = $suppliers_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $current_stock = intval($_POST['current_stock']);
    $minimum_stock = intval($_POST['minimum_stock']);
    $maximum_stock = intval($_POST['maximum_stock']);
    $unit_price = floatval($_POST['unit_price']);
    $supplier_id = intval($_POST['supplier_id']);
    $location = trim($_POST['location']);
    
    if (empty($item_name)) {
        $error = "Item name is required!";
    } else {
        $sql = "INSERT INTO inventory_items (item_name, category, unit, current_stock, minimum_stock, maximum_stock, unit_price, supplier_id, location) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssiiidis", $item_name, $category, $unit, $current_stock, $minimum_stock, $maximum_stock, $unit_price, $supplier_id, $location);
        
        if ($stmt->execute()) {
            $item_id = $db->insert_id;
            
            // Record initial stock if > 0
            if ($current_stock > 0) {
                updateStock($item_id, $current_stock, 'IN', $user_id, 'Initial stock');
            }
            
            logActivity($user_id, 'Add Item', "Added new item: $item_name");
            
            $_SESSION['toast_message'] = "Item <strong>" . htmlspecialchars($item_name) . "</strong> added successfully!";
            $_SESSION['toast_type'] = "success";
            
            header("Location: view_items.php");
            exit();
        } else {
            $error = "Error adding item: " . $db->error;
        }
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Add New Item</h1>
        <p>Add new product to inventory</p>
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
                    <h3><i class="fas fa-info-circle"></i> Item Information</h3>
                    <p class="card-subtitle">Fill in the details below to add a new item</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="addItemForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Item Name <span class="required">*</span></label>
                            <input type="text" name="item_name" id="item_name" value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>" placeholder="Enter item name" required autocomplete="off">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-folder"></i> Category</label>
                                <select name="category" id="category">
                                    <option value="">Select Category</option>
                                    <option value="Food" <?php echo (($_POST['category'] ?? '') == 'Food') ? 'selected' : ''; ?>>Food</option>
                                    <option value="Beverages" <?php echo (($_POST['category'] ?? '') == 'Beverages') ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Cleaning" <?php echo (($_POST['category'] ?? '') == 'Cleaning') ? 'selected' : ''; ?>>Cleaning Supplies</option>
                                    <option value="Equipment" <?php echo (($_POST['category'] ?? '') == 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                                    <option value="Linens" <?php echo (($_POST['category'] ?? '') == 'Linens') ? 'selected' : ''; ?>>Linens</option>
                                    <option value="Other" <?php echo (($_POST['category'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-balance-scale"></i> Unit of Measure</label>
                                <select name="unit" id="unit">
                                    <option value="kg" <?php echo (($_POST['unit'] ?? '') == 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                                    <option value="liters" <?php echo (($_POST['unit'] ?? '') == 'liters') ? 'selected' : ''; ?>>Liters</option>
                                    <option value="pieces" <?php echo (($_POST['unit'] ?? '') == 'pieces') ? 'selected' : ''; ?>>Pieces</option>
                                    <option value="boxes" <?php echo (($_POST['unit'] ?? '') == 'boxes') ? 'selected' : ''; ?>>Boxes</option>
                                    <option value="cartons" <?php echo (($_POST['unit'] ?? '') == 'cartons') ? 'selected' : ''; ?>>Cartons</option>
                                    <option value="bottles" <?php echo (($_POST['unit'] ?? '') == 'bottles') ? 'selected' : ''; ?>>Bottles</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-dollar-sign"></i> Unit Price (TZS)</label>
                                <input type="number" step="0.01" name="unit_price" id="unit_price" value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>" placeholder="0.00">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Storage Location</label>
                                <input type="text" name="location" id="location" placeholder="e.g., Warehouse A, Shelf 1" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-boxes"></i> Initial Stock</label>
                                <input type="number" name="current_stock" id="current_stock" value="<?php echo htmlspecialchars($_POST['current_stock'] ?? '0'); ?>" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-truck"></i> Supplier</label>
                                <select name="supplier_id" id="supplier_id">
                                    <option value="">Select Supplier</option>
                                    <?php foreach($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>" <?php echo (($_POST['supplier_id'] ?? '') == $supplier['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supplier['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-exclamation-triangle"></i> Minimum Stock (Alert Level)</label>
                                <input type="number" name="minimum_stock" id="minimum_stock" value="<?php echo htmlspecialchars($_POST['minimum_stock'] ?? '10'); ?>" min="0">
                                <small>Stock below this level will trigger alert</small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-chart-line"></i> Maximum Stock</label>
                                <input type="number" name="maximum_stock" id="maximum_stock" value="<?php echo htmlspecialchars($_POST['maximum_stock'] ?? '500'); ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <div class="info-content">
                                <strong>Note:</strong> Items with stock below minimum level will trigger alerts. 
                                You can always update stock later using <strong>Stock In</strong> or <strong>Stock Out</strong>.
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Add Item
                            </button>
                            <button type="reset" class="btn-secondary" id="resetBtn">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <a href="view_items.php" class="btn-outline">
                                <i class="fas fa-arrow-left"></i> Back to Items
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Tips & Guide -->
        <div class="info-column">
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Quick Tips</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle"></i> Use clear and descriptive item names</li>
                        <li><i class="fas fa-check-circle"></i> Set appropriate minimum stock levels for reorder alerts</li>
                        <li><i class="fas fa-check-circle"></i> Assign suppliers to track purchase history</li>
                        <li><i class="fas fa-check-circle"></i> Storage location helps with physical inventory counting</li>
                        <li><i class="fas fa-check-circle"></i> You can update stock later via Stock In/Out</li>
                    </ul>
                </div>
            </div>
            
            <div class="card categories-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-tags"></i> Common Categories</h3>
                </div>
                <div class="card-body">
                    <div class="category-badges">
                        <span class="category-badge"><i class="fas fa-utensils"></i> Food</span>
                        <span class="category-badge"><i class="fas fa-wine-bottle"></i> Beverages</span>
                        <span class="category-badge"><i class="fas fa-soap"></i> Cleaning</span>
                        <span class="category-badge"><i class="fas fa-tools"></i> Equipment</span>
                        <span class="category-badge"><i class="fas fa-bed"></i> Linens</span>
                        <span class="category-badge"><i class="fas fa-box"></i> Other</span>
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
        transition: box-shadow 0.3s;
    }
    
    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
    
    /* Form Actions Buttons */
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
    
    /* Category Badges */
    .category-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .category-badge {
        background: #F3F4F6;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        color: #374151;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    
    .category-badge i {
        color: #1E3A8A;
    }
    
    .category-badge:hover {
        background: #1E3A8A;
        color: white;
        cursor: pointer;
    }
    
    .category-badge:hover i {
        color: white;
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
        
        .btn-primary, .btn-secondary, .btn-outline {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<script>
    // Form submit loading state
    const form = document.getElementById('addItemForm');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    
    form.addEventListener('submit', function(e) {
        const itemName = document.getElementById('item_name').value;
        if (!itemName) {
            e.preventDefault();
            showToast('Item name is required!', 'error');
            return;
        }
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Item...';
        submitBtn.disabled = true;
    });
    
    resetBtn.addEventListener('click', function() {
        setTimeout(() => {
            document.getElementById('item_name').focus();
        }, 100);
    });
    
    // Category badge click to select
    document.querySelectorAll('.category-badge').forEach(badge => {
        badge.addEventListener('click', function() {
            const category = this.textContent.trim();
            const select = document.getElementById('category');
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].text === category) {
                    select.selectedIndex = i;
                    break;
                }
            }
        });
    });
    
    // Auto focus
    document.getElementById('item_name').focus();
</script>

<?php include '../templates/footer.php'; ?>
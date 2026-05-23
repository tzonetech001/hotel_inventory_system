<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Storekeeper can access
checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];
$success = '';
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
            $success = "Item added successfully!";
            
            // Clear form
            $_POST = array();
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
            <h3>Item Information</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form-horizontal">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Item Name *</label>
                        <input type="text" name="item_name" value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Category</label>
                        <select name="category">
                            <option value="">Select Category</option>
                            <option value="Food" <?php echo (($_POST['category'] ?? '') == 'Food') ? 'selected' : ''; ?>>Food</option>
                            <option value="Beverages" <?php echo (($_POST['category'] ?? '') == 'Beverages') ? 'selected' : ''; ?>>Beverages</option>
                            <option value="Cleaning" <?php echo (($_POST['category'] ?? '') == 'Cleaning') ? 'selected' : ''; ?>>Cleaning Supplies</option>
                            <option value="Equipment" <?php echo (($_POST['category'] ?? '') == 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                            <option value="Linens" <?php echo (($_POST['category'] ?? '') == 'Linens') ? 'selected' : ''; ?>>Linens</option>
                            <option value="Other" <?php echo (($_POST['category'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-balance-scale"></i> Unit of Measure</label>
                        <select name="unit">
                            <option value="kg" <?php echo (($_POST['unit'] ?? '') == 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                            <option value="liters" <?php echo (($_POST['unit'] ?? '') == 'liters') ? 'selected' : ''; ?>>Liters</option>
                            <option value="pieces" <?php echo (($_POST['unit'] ?? '') == 'pieces') ? 'selected' : ''; ?>>Pieces</option>
                            <option value="boxes" <?php echo (($_POST['unit'] ?? '') == 'boxes') ? 'selected' : ''; ?>>Boxes</option>
                            <option value="cartons" <?php echo (($_POST['unit'] ?? '') == 'cartons') ? 'selected' : ''; ?>>Cartons</option>
                            <option value="bottles" <?php echo (($_POST['unit'] ?? '') == 'bottles') ? 'selected' : ''; ?>>Bottles</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> Unit Price (TZS)</label>
                        <input type="number" step="0.01" name="unit_price" value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-boxes"></i> Current Stock</label>
                        <input type="number" name="current_stock" value="<?php echo htmlspecialchars($_POST['current_stock'] ?? '0'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-exclamation-triangle"></i> Minimum Stock (Alert Level)</label>
                        <input type="number" name="minimum_stock" value="<?php echo htmlspecialchars($_POST['minimum_stock'] ?? '10'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-chart-line"></i> Maximum Stock</label>
                        <input type="number" name="maximum_stock" value="<?php echo htmlspecialchars($_POST['maximum_stock'] ?? '500'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-truck"></i> Supplier</label>
                        <select name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php foreach($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo (($_POST['supplier_id'] ?? '') == $supplier['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Storage Location</label>
                        <input type="text" name="location" placeholder="e.g., Warehouse A, Shelf 1" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Item
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .form-horizontal {
        max-width: 100%;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: #D1FAE5;
        color: #065F46;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        border-left: 4px solid #10B981;
    }
    
    .alert-error {
        background: #FEE2E2;
        color: #991B1B;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        border-left: 4px solid #EF4444;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
</style>

<?php include '../templates/footer.php'; ?>
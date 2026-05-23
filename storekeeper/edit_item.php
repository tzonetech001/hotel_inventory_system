<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper', 'Admin']);

$user_id = $_SESSION['user_id'];
$success = '';
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
            $success = "Item updated successfully!";
            
            // Refresh item data
            $stmt = $db->prepare("SELECT * FROM inventory_items WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
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
        <p>Update item information</p>
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
            <h3>Edit: <?php echo htmlspecialchars($item['item_name']); ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form-horizontal">
                <div class="form-row">
                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">Select Category</option>
                            <option value="Food" <?php echo $item['category'] == 'Food' ? 'selected' : ''; ?>>Food</option>
                            <option value="Beverages" <?php echo $item['category'] == 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                            <option value="Cleaning" <?php echo $item['category'] == 'Cleaning' ? 'selected' : ''; ?>>Cleaning Supplies</option>
                            <option value="Equipment" <?php echo $item['category'] == 'Equipment' ? 'selected' : ''; ?>>Equipment</option>
                            <option value="Linens" <?php echo $item['category'] == 'Linens' ? 'selected' : ''; ?>>Linens</option>
                            <option value="Other" <?php echo $item['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Unit of Measure</label>
                        <select name="unit">
                            <option value="kg" <?php echo $item['unit'] == 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                            <option value="liters" <?php echo $item['unit'] == 'liters' ? 'selected' : ''; ?>>Liters</option>
                            <option value="pieces" <?php echo $item['unit'] == 'pieces' ? 'selected' : ''; ?>>Pieces</option>
                            <option value="boxes" <?php echo $item['unit'] == 'boxes' ? 'selected' : ''; ?>>Boxes</option>
                            <option value="cartons" <?php echo $item['unit'] == 'cartons' ? 'selected' : ''; ?>>Cartons</option>
                            <option value="bottles" <?php echo $item['unit'] == 'bottles' ? 'selected' : ''; ?>>Bottles</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Unit Price (TZS)</label>
                        <input type="number" step="0.01" name="unit_price" value="<?php echo $item['unit_price']; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Stock (Alert Level)</label>
                        <input type="number" name="minimum_stock" value="<?php echo $item['minimum_stock']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Maximum Stock</label>
                        <input type="number" name="maximum_stock" value="<?php echo $item['maximum_stock']; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php foreach($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo $item['supplier_id'] == $supplier['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Storage Location</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?php echo $item['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $item['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Current stock level is <strong><?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?></strong>. 
                    To update stock, please use <a href="stock_in.php">Stock In</a> or <a href="stock_out.php">Stock Out</a>.
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Item
                    </button>
                    <a href="view_items.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .info-box {
        background: #DBEAFE;
        border-left: 4px solid #1E3A8A;
        padding: 12px;
        border-radius: 8px;
        margin: 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-box a {
        color: #1E3A8A;
        text-decoration: none;
        font-weight: 600;
    }
    
    .info-box a:hover {
        text-decoration: underline;
    }
</style>

<?php include '../templates/footer.php'; ?>
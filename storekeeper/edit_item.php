<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper', 'Admin']);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

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

// Departments list
$departments = ['Kitchen', 'Housekeeping', 'Laundry', 'Front Office', 'Maintenance', 'Restaurant', 'Bar', 'Store'];

// Handle stock adjustment (INCREASE/DECREASE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    $adjustment_type = $_POST['adjustment_type'];
    $adjustment_quantity = intval($_POST['adjustment_quantity']);
    $adjustment_reason = trim($_POST['adjustment_reason']);
    $adjustment_reference = trim($_POST['adjustment_reference']);
    
    if ($adjustment_quantity <= 0) {
        $error = "Please enter a valid quantity!";
    } elseif (empty($adjustment_reason)) {
        $error = "Please provide a reason for stock adjustment!";
    } else {
        if ($adjustment_type == 'increase') {
            // Increase stock
            if (updateStock($item_id, $adjustment_quantity, 'IN', $user_id, $adjustment_reference)) {
                logActivity($user_id, 'Stock Adjustment', "Increased stock of {$item['item_name']} by $adjustment_quantity. Reason: $adjustment_reason");
                $_SESSION['toast_message'] = "Stock increased by <strong>" . number_format($adjustment_quantity) . "</strong> " . $item['unit'];
                $_SESSION['toast_type'] = "success";
                
                // Refresh item data
                $stmt = $db->prepare("SELECT * FROM inventory_items WHERE id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $item = $result->fetch_assoc();
            } else {
                $error = "Error increasing stock!";
            }
        } elseif ($adjustment_type == 'decrease') {
            // Decrease stock
            $current_stock = getCurrentStock($item_id);
            if ($adjustment_quantity > $current_stock) {
                $error = "Cannot decrease by $adjustment_quantity! Only $current_stock " . $item['unit'] . " available.";
            } else {
                if (updateStock($item_id, $adjustment_quantity, 'OUT', $user_id, $adjustment_reference)) {
                    logActivity($user_id, 'Stock Adjustment', "Decreased stock of {$item['item_name']} by $adjustment_quantity. Reason: $adjustment_reason");
                    $_SESSION['toast_message'] = "Stock decreased by <strong>" . number_format($adjustment_quantity) . "</strong> " . $item['unit'];
                    $_SESSION['toast_type'] = "success";
                    
                    // Refresh item data
                    $stmt = $db->prepare("SELECT * FROM inventory_items WHERE id = ?");
                    $stmt->bind_param("i", $item_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $item = $result->fetch_assoc();
                } else {
                    $error = "Error decreasing stock!";
                }
            }
        }
    }
}

// Handle item info update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $minimum_stock = intval($_POST['minimum_stock']);
    $maximum_stock = intval($_POST['maximum_stock']);
    $unit_price = floatval($_POST['unit_price']);
    $supplier_id = intval($_POST['supplier_id']);
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    $department = trim($_POST['department']);
    $old_department = $item['department'] ?? '';
    
    if (empty($item_name)) {
        $error = "Item name is required!";
    } else {
        $sql = "UPDATE inventory_items SET 
                item_name = ?, category = ?, unit = ?, minimum_stock = ?, 
                maximum_stock = ?, unit_price = ?, supplier_id = ?, location = ?, status = ?, department = ?
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssiiidissi", 
            $item_name, $category, $unit, $minimum_stock, 
            $maximum_stock, $unit_price, $supplier_id, $location, $status, $department, $item_id
        );
        
        if ($stmt->execute()) {
            // Log department change if applicable
            if ($old_department != $department) {
                logActivity($user_id, 'Department Change', "Changed department of '$item_name' from '$old_department' to '$department'");
            }
            
            logActivity($user_id, 'Edit Item', "Updated item info: $item_name (ID: $item_id) - Department: $department");
            $_SESSION['toast_message'] = "Item information updated successfully!";
            $_SESSION['toast_type'] = "success";
            
            // Refresh item data
            $stmt = $db->prepare("SELECT * FROM inventory_items WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
            
            header("Location: edit_item.php?id=" . $item_id);
            exit();
        } else {
            $error = "Error updating item: " . $db->error;
        }
    }
}

// Get recent stock movements for this item
$movements_sql = "SELECT sm.*, u.fullname 
                  FROM stock_movements sm
                  JOIN users u ON sm.performed_by = u.id
                  WHERE sm.item_id = ?
                  ORDER BY sm.created_at DESC LIMIT 10";
$movements_stmt = $db->prepare($movements_sql);
$movements_stmt->bind_param("i", $item_id);
$movements_stmt->execute();
$movements_result = $movements_stmt->get_result();
$movements = $movements_result->fetch_all(MYSQLI_ASSOC);

// Calculate stock status percentage
$stock_percentage = 0;
if ($item['maximum_stock'] > 0 && $item['minimum_stock'] > 0) {
    $stock_range = $item['maximum_stock'] - $item['minimum_stock'];
    $current_range = $item['current_stock'] - $item['minimum_stock'];
    if ($stock_range > 0) {
        $stock_percentage = round(($current_range / $stock_range) * 100);
        $stock_percentage = max(0, min(100, $stock_percentage));
    }
}

// Get stock status
$stock_status = '';
$stock_status_class = '';
if ($item['current_stock'] <= $item['minimum_stock']) {
    $stock_status = 'Critical - Need Reorder';
    $stock_status_class = 'critical';
} elseif ($item['current_stock'] >= $item['maximum_stock']) {
    $stock_status = 'Over Stocked';
    $stock_status_class = 'overstock';
} elseif ($item['current_stock'] <= $item['minimum_stock'] * 1.5) {
    $stock_status = 'Warning - Approaching Minimum';
    $stock_status_class = 'warning';
} else {
    $stock_status = 'Normal';
    $stock_status_class = 'normal';
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Item</h1>
        <p>Update item information and manage stock - You can change department assignment</p>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Stock Status Banner -->
    <div class="stock-status-banner stock-<?php echo $stock_status_class; ?>">
        <div class="status-icon">
            <?php if($stock_status_class == 'critical'): ?>
                <i class="fas fa-exclamation-triangle"></i>
            <?php elseif($stock_status_class == 'overstock'): ?>
                <i class="fas fa-chart-line"></i>
            <?php elseif($stock_status_class == 'warning'): ?>
                <i class="fas fa-clock"></i>
            <?php else: ?>
                <i class="fas fa-check-circle"></i>
            <?php endif; ?>
        </div>
        <div class="status-info">
            <div class="status-title">Stock Status: <?php echo $stock_status; ?></div>
            <div class="status-details">
                Current: <strong><?php echo number_format($item['current_stock']); ?> <?php echo $item['unit']; ?></strong> |
                Min: <?php echo number_format($item['minimum_stock']); ?> <?php echo $item['unit']; ?> |
                Max: <?php echo number_format($item['maximum_stock']); ?> <?php echo $item['unit']; ?>
            </div>
        </div>
        <div class="stock-bar">
            <div class="stock-bar-fill" style="width: <?php echo $stock_percentage; ?>%"></div>
        </div>
    </div>
    
    <!-- Tabs Navigation -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="info">
            <i class="fas fa-info-circle"></i> Item Information
        </button>
        <button class="tab-btn" data-tab="stock">
            <i class="fas fa-boxes"></i> Stock Adjustment
        </button>
        <button class="tab-btn" data-tab="history">
            <i class="fas fa-history"></i> Stock History
        </button>
    </div>
    
    <!-- Tab 1: Item Information -->
    <div class="tab-content active" id="tab-info">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-edit"></i> Edit Item Information</h3>
                <p class="card-subtitle">Update the basic information of this item including department</p>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="infoForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Item Name <span class="required">*</span></label>
                            <input type="text" name="item_name" id="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                        </div>
                        
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
                    </div>
                    
                    <div class="form-row">
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
                        
                        <div class="form-group">
                            <label><i class="fas fa-dollar-sign"></i> Unit Price (TZS)</label>
                            <input type="number" step="0.01" name="unit_price" id="unit_price" value="<?php echo $item['unit_price']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group department-group">
                            <label><i class="fas fa-building"></i> Department <span class="required">*</span></label>
                            <select name="department" id="department" required>
                                <option value="">Select Department</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" <?php echo ($item['department'] ?? '') == $dept ? 'selected' : ''; ?>>
                                        <i class="fas <?php echo getDepartmentIcon($dept); ?>"></i> <?php echo $dept; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>This determines which department can request this item</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Storage Location</label>
                            <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>" placeholder="e.g., Warehouse A, Shelf 1">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-exclamation-triangle"></i> Minimum Stock (Alert Level)</label>
                            <input type="number" name="minimum_stock" id="minimum_stock" value="<?php echo $item['minimum_stock']; ?>" min="0">
                            <small>Stock below this level will trigger alert</small>
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
                    
                    <!-- Department Change Warning -->
                    <div class="dept-change-warning" id="deptChangeWarning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="warning-content">
                            <strong>Department Change Alert!</strong>
                            <p>Changing the department will affect future stock out requests. Current department: <strong id="currentDeptDisplay"></strong></p>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <div class="info-content">
                            <strong>Current Stock: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?></strong><br>
                            To update stock levels, use the <strong>Stock Adjustment</strong> tab above.<br>
                            <strong>Current Department:</strong> <?php echo !empty($item['department']) ? $item['department'] : 'Not Assigned'; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_info" class="btn-primary" id="updateInfoBtn">
                            <i class="fas fa-save"></i> Update Information
                        </button>
                        <a href="view_items.php" class="btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Tab 2: Stock Adjustment -->
    <div class="tab-content" id="tab-stock">
        <div class="two-columns">
            <!-- Increase Stock Card -->
            <div class="card increase-card">
                <div class="card-header">
                    <h3><i class="fas fa-arrow-down" style="color: #10B981;"></i> Increase Stock</h3>
                    <p class="card-subtitle">Add more items to inventory</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="increaseForm">
                        <input type="hidden" name="adjust_stock" value="1">
                        <input type="hidden" name="adjustment_type" value="increase">
                        
                        <div class="current-stock-display">
                            <label>Current Stock:</label>
                            <span class="current-stock-value"><?php echo number_format($item['current_stock']); ?> <?php echo $item['unit']; ?></span>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-plus-circle"></i> Quantity to Add <span class="required">*</span></label>
                            <input type="number" name="adjustment_quantity" id="increase_quantity" min="1" placeholder="Enter quantity" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Reason for Addition <span class="required">*</span></label>
                            <select name="adjustment_reason" id="increase_reason" required>
                                <option value="">Select Reason</option>
                                <option value="New Purchase Order">New Purchase Order</option>
                                <option value="Stock Return">Stock Return</option>
                                <option value="Inventory Correction">Inventory Correction</option>
                                <option value="Goods Received">Goods Received</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> Reference Number</label>
                            <input type="text" name="adjustment_reference" placeholder="e.g., PO-001, GRN-001">
                        </div>
                        
                        <div class="preview-box">
                            <div class="preview-label">After Addition:</div>
                            <div class="preview-value" id="increasePreview">
                                <?php echo number_format($item['current_stock']); ?> <?php echo $item['unit']; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-success" id="increaseBtn">
                            <i class="fas fa-arrow-down"></i> Add Stock
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Decrease Stock Card -->
            <div class="card decrease-card">
                <div class="card-header">
                    <h3><i class="fas fa-arrow-up" style="color: #EF4444;"></i> Decrease Stock</h3>
                    <p class="card-subtitle">Remove items from inventory</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="decreaseForm">
                        <input type="hidden" name="adjust_stock" value="1">
                        <input type="hidden" name="adjustment_type" value="decrease">
                        
                        <div class="current-stock-display">
                            <label>Current Stock:</label>
                            <span class="current-stock-value"><?php echo number_format($item['current_stock']); ?> <?php echo $item['unit']; ?></span>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-minus-circle"></i> Quantity to Remove <span class="required">*</span></label>
                            <input type="number" name="adjustment_quantity" id="decrease_quantity" min="1" placeholder="Enter quantity" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Reason for Removal <span class="required">*</span></label>
                            <select name="adjustment_reason" id="decrease_reason" required>
                                <option value="">Select Reason</option>
                                <option value="Kitchen Usage">Kitchen Usage</option>
                                <option value="Restaurant Usage">Restaurant Usage</option>
                                <option value="Damaged Goods">Damaged Goods</option>
                                <option value="Expired Items">Expired Items</option>
                                <option value="Inventory Correction">Inventory Correction</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> Reference Number</label>
                            <input type="text" name="adjustment_reference" placeholder="e.g., Requisition #, Waste Report">
                        </div>
                        
                        <div class="preview-box warning">
                            <div class="preview-label">After Removal:</div>
                            <div class="preview-value" id="decreasePreview">
                                <?php echo number_format($item['current_stock']); ?> <?php echo $item['unit']; ?>
                            </div>
                        </div>
                        
                        <div class="warning-message" id="decreaseWarning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Insufficient stock! Cannot remove more than available.</span>
                        </div>
                        
                        <button type="submit" class="btn-danger" id="decreaseBtn">
                            <i class="fas fa-arrow-up"></i> Remove Stock
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tab 3: Stock History -->
    <div class="tab-content" id="tab-history">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Stock Movement History</h3>
                <p class="card-subtitle">Recent transactions for <?php echo htmlspecialchars($item['item_name']); ?></p>
            </div>
            <div class="card-body">
                <?php if(count($movements) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Reference</th>
                                    <th>Performed By</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($movements as $movement): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?></td>
                                        <td>
                                            <?php if($movement['movement_type'] == 'IN'): ?>
                                                <span class="badge-in"><i class="fas fa-arrow-down"></i> IN</span>
                                            <?php else: ?>
                                                <span class="badge-out"><i class="fas fa-arrow-up"></i> OUT</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($movement['movement_type'] == 'IN'): ?>
                                                <span class="text-success">+<?php echo number_format($movement['quantity']); ?></span>
                                            <?php else: ?>
                                                <span class="text-danger">-<?php echo number_format($movement['quantity']); ?></span>
                                            <?php endif; ?>
                                            <?php echo $item['unit']; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($movement['reference_no'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($movement['notes'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exchange-alt"></i>
                        <h4>No Stock History</h4>
                        <p>No stock movements recorded for this item yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    /* Alert */
    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .alert-error {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 4px solid #EF4444;
    }
    
    /* Stock Status Banner */
    .stock-status-banner {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .stock-status-banner.stock-critical {
        border-left: 5px solid #EF4444;
        background: linear-gradient(135deg, #fff 0%, #FEF2F2 100%);
    }
    
    .stock-status-banner.stock-warning {
        border-left: 5px solid #F59E0B;
        background: linear-gradient(135deg, #fff 0%, #FFFBEB 100%);
    }
    
    .stock-status-banner.stock-overstock {
        border-left: 5px solid #8B5CF6;
        background: linear-gradient(135deg, #fff 0%, #F5F3FF 100%);
    }
    
    .stock-status-banner.stock-normal {
        border-left: 5px solid #10B981;
        background: linear-gradient(135deg, #fff 0%, #ECFDF5 100%);
    }
    
    .status-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stock-critical .status-icon {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .stock-warning .status-icon {
        background: #FEF3C7;
        color: #F59E0B;
    }
    
    .stock-overstock .status-icon {
        background: #EDE9FE;
        color: #8B5CF6;
    }
    
    .stock-normal .status-icon {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .status-info {
        flex: 1;
    }
    
    .status-title {
        font-weight: 700;
        font-size: 16px;
        margin-bottom: 5px;
    }
    
    .status-details {
        font-size: 13px;
        color: #6B7280;
    }
    
    .stock-bar {
        width: 200px;
        height: 8px;
        background: #E5E7EB;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .stock-bar-fill {
        height: 100%;
        background: #1E3A8A;
        border-radius: 10px;
        transition: width 0.3s;
    }
    
    .stock-critical .stock-bar-fill {
        background: #EF4444;
    }
    
    .stock-warning .stock-bar-fill {
        background: #F59E0B;
    }
    
    .stock-overstock .stock-bar-fill {
        background: #8B5CF6;
    }
    
    /* Tabs */
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        flex-wrap: wrap;
        border-bottom: 2px solid #E5E7EB;
        padding-bottom: 0;
    }
    
    .tab-btn {
        padding: 12px 24px;
        background: transparent;
        border: none;
        font-size: 14px;
        font-weight: 600;
        color: #6B7280;
        cursor: pointer;
        transition: all 0.3s;
        border-radius: 10px 10px 0 0;
        position: relative;
    }
    
    .tab-btn i {
        margin-right: 8px;
    }
    
    .tab-btn:hover {
        color: #1E3A8A;
        background: #F3F4F6;
    }
    
    .tab-btn.active {
        color: #1E3A8A;
        background: white;
        border-bottom: 3px solid #FF6B6B;
    }
    
    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }
    
    .tab-content.active {
        display: block;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Card */
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 25px;
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
    
    /* Two Columns for Stock Adjustment */
    .two-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }
    
    /* Increase/Decrease Cards */
    .increase-card, .decrease-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .increase-card .card-header {
        background: #ECFDF5;
        border-bottom-color: #D1FAE5;
    }
    
    .decrease-card .card-header {
        background: #FEF2F2;
        border-bottom-color: #FEE2E2;
    }
    
    .current-stock-display {
        background: #F9FAFB;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .current-stock-display label {
        font-weight: 600;
        color: #374151;
    }
    
    .current-stock-value {
        font-size: 20px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .preview-box {
        background: #F3F4F6;
        padding: 12px 15px;
        border-radius: 10px;
        margin: 20px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .preview-box.warning {
        background: #FEF3C7;
    }
    
    .preview-label {
        font-size: 13px;
        color: #6B7280;
    }
    
    .preview-value {
        font-size: 18px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .warning-message {
        background: #FEE2E2;
        color: #991B1B;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }
    
    .dept-change-warning {
        background: #FEF3C7;
        border-left: 4px solid #F59E0B;
        padding: 12px;
        border-radius: 8px;
        margin: 20px 0;
        display: flex;
        gap: 12px;
        font-size: 13px;
    }
    
    .dept-change-warning i {
        font-size: 18px;
        color: #F59E0B;
    }
    
    .dept-change-warning .warning-content {
        flex: 1;
    }
    
    .dept-change-warning .warning-content p {
        margin: 5px 0 0;
        color: #92400E;
    }
    
    .btn-success {
        background: #10B981;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #EF4444;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-2px);
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
        margin-bottom: 15px;
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
    
    /* Badges */
    .badge-in, .badge-out {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .badge-in {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .badge-out {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .text-success {
        color: #10B981;
        font-weight: 600;
    }
    
    .text-danger {
        color: #EF4444;
        font-weight: 600;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: #D1D5DB;
        margin-bottom: 15px;
    }
    
    .empty-state h4 {
        color: #374151;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    /* Table */
    .table-responsive {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-table th {
        background: #F9FAFB;
        font-weight: 600;
        font-size: 13px;
    }
    
    /* Responsive */
    @media (max-width: 900px) {
        .two-columns {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .stock-status-banner {
            flex-direction: column;
            text-align: center;
        }
        
        .stock-bar {
            width: 100%;
        }
        
        .tabs {
            justify-content: center;
        }
        
        .tab-btn {
            flex: 1;
            text-align: center;
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
    // Get elements
    const departmentSelect = document.getElementById('department');
    const deptChangeWarning = document.getElementById('deptChangeWarning');
    const currentDeptDisplay = document.getElementById('currentDeptDisplay');
    const currentDepartment = '<?php echo addslashes($item['department'] ?? ''); ?>';
    
    // Set current department display
    if (currentDeptDisplay) {
        currentDeptDisplay.textContent = currentDepartment || 'Not Assigned';
    }
    
    // Show warning when department changes
    if (departmentSelect) {
        departmentSelect.addEventListener('change', function() {
            const newDepartment = this.value;
            if (newDepartment !== currentDepartment) {
                deptChangeWarning.style.display = 'flex';
                // Update warning message with new department
                const warningContent = deptChangeWarning.querySelector('.warning-content p');
                if (warningContent) {
                    warningContent.innerHTML = `Changing department from <strong>${currentDepartment || 'Not Assigned'}</strong> to <strong>${newDepartment}</strong> will affect future stock out requests.`;
                }
            } else {
                deptChangeWarning.style.display = 'none';
            }
        });
    }
    
    // Tab switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            document.getElementById(`tab-${tabId}`).classList.add('active');
        });
    });
    
    // Increase stock preview
    const increaseQuantity = document.getElementById('increase_quantity');
    const increasePreview = document.getElementById('increasePreview');
    const currentStock = <?php echo $item['current_stock']; ?>;
    const unit = '<?php echo $item['unit']; ?>';
    
    function updateIncreasePreview() {
        let quantity = parseInt(increaseQuantity.value) || 0;
        let newStock = currentStock + quantity;
        increasePreview.innerHTML = newStock.toLocaleString() + ' ' + unit;
    }
    
    if (increaseQuantity) {
        increaseQuantity.addEventListener('input', updateIncreasePreview);
    }
    
    // Decrease stock preview with validation
    const decreaseQuantity = document.getElementById('decrease_quantity');
    const decreasePreview = document.getElementById('decreasePreview');
    const decreaseWarning = document.getElementById('decreaseWarning');
    const decreaseBtn = document.getElementById('decreaseBtn');
    
    function updateDecreasePreview() {
        let quantity = parseInt(decreaseQuantity.value) || 0;
        let newStock = currentStock - quantity;
        
        if (quantity > currentStock) {
            decreaseWarning.style.display = 'flex';
            decreaseBtn.disabled = true;
            decreaseBtn.style.opacity = '0.5';
            decreasePreview.innerHTML = newStock.toLocaleString() + ' ' + unit;
            decreasePreview.style.color = '#EF4444';
        } else {
            decreaseWarning.style.display = 'none';
            decreaseBtn.disabled = false;
            decreaseBtn.style.opacity = '1';
            decreasePreview.innerHTML = newStock.toLocaleString() + ' ' + unit;
            decreasePreview.style.color = newStock < 0 ? '#EF4444' : '#1E3A8A';
        }
    }
    
    if (decreaseQuantity) {
        decreaseQuantity.addEventListener('input', updateDecreasePreview);
    }
    
    // Form submit loading states
    const infoForm = document.getElementById('infoForm');
    const updateInfoBtn = document.getElementById('updateInfoBtn');
    
    if (infoForm) {
        infoForm.addEventListener('submit', function() {
            updateInfoBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            updateInfoBtn.disabled = true;
        });
    }
    
    const increaseForm = document.getElementById('increaseForm');
    const increaseBtn = document.getElementById('increaseBtn');
    
    if (increaseForm) {
        increaseForm.addEventListener('submit', function(e) {
            const quantity = document.getElementById('increase_quantity').value;
            const reason = document.getElementById('increase_reason').value;
            
            if (!quantity || quantity <= 0) {
                e.preventDefault();
                showToast('Please enter a valid quantity!', 'error');
                return;
            }
            
            if (!reason) {
                e.preventDefault();
                showToast('Please select a reason!', 'error');
                return;
            }
            
            increaseBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Stock...';
            increaseBtn.disabled = true;
        });
    }
    
    const decreaseForm = document.getElementById('decreaseForm');
    const decreaseBtnElem = document.getElementById('decreaseBtn');
    
    if (decreaseForm) {
        decreaseForm.addEventListener('submit', function(e) {
            const quantity = document.getElementById('decrease_quantity').value;
            const reason = document.getElementById('decrease_reason').value;
            
            if (!quantity || quantity <= 0) {
                e.preventDefault();
                showToast('Please enter a valid quantity!', 'error');
                return;
            }
            
            if (!reason) {
                e.preventDefault();
                showToast('Please select a reason!', 'error');
                return;
            }
            
            if (parseInt(quantity) > currentStock) {
                e.preventDefault();
                showToast('Insufficient stock!', 'error');
                return;
            }
            
            decreaseBtnElem.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing Stock...';
            decreaseBtnElem.disabled = true;
        });
    }
    
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
    
    // Auto focus
    document.getElementById('item_name').focus();
</script>

<?php
// Helper function for department icons
function getDepartmentIcon($department) {
    switch($department) {
        case 'Kitchen': return 'fa-utensils';
        case 'Housekeeping': return 'fa-broom';
        case 'Laundry': return 'fa-tshirt';
        case 'Front Office': return 'fa-hotel';
        case 'Maintenance': return 'fa-wrench';
        case 'Restaurant': return 'fa-utensil-spoon';
        case 'Bar': return 'fa-cocktail';
        case 'Store': return 'fa-warehouse';
        default: return 'fa-building';
    }
}
?>

<?php include '../templates/footer.php'; ?>
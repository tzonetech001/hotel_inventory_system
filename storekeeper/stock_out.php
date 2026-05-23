<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get active items with stock > 0
$items_sql = "SELECT id, item_name, unit, current_stock FROM inventory_items 
              WHERE status = 'active' AND current_stock > 0 
              ORDER BY item_name";
$items_result = $db->query($items_sql);
$items = $items_result->fetch_all(MYSQLI_ASSOC);

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
                $success = "Stock removed successfully!";
                
                // Clear form
                $_POST = array();
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
            <h3>Record Stock Issue</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="stockOutForm">
                <div class="form-group">
                    <label><i class="fas fa-box"></i> Select Item *</label>
                    <select name="item_id" id="item_id" required>
                        <option value="">-- Select Item --</option>
                        <?php foreach($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" 
                                    data-current="<?php echo $item['current_stock']; ?>"
                                    data-unit="<?php echo $item['unit']; ?>"
                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>">
                                <?php echo htmlspecialchars($item['item_name']); ?> 
                                (Available: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-minus-circle"></i> Quantity *</label>
                        <input type="number" name="quantity" id="quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calculator"></i> Remaining Stock</label>
                        <input type="text" id="remaining_stock" readonly style="background: #F3F4F6; font-weight: bold;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Department/Usage *</label>
                    <select name="department" required>
                        <option value="">-- Select Department --</option>
                        <option value="Restaurant">Restaurant</option>
                        <option value="Bar">Bar</option>
                        <option value="Housekeeping">Housekeeping</option>
                        <option value="Kitchen">Kitchen</option>
                        <option value="Laundry">Laundry</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Reason for Issue</label>
                    <textarea name="reason" rows="2" placeholder="e.g., Daily usage, Guest request, Maintenance..."></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Reference Number</label>
                    <input type="text" name="reference" placeholder="e.g., Requisition #, Department Request #">
                </div>
                
                <div class="warning-box" id="warningBox" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="warningMessage"></span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Record Stock Out
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Recent Stock Movements (OUT)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Department</th>
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_sql = "SELECT sm.*, i.item_name, u.fullname 
                                       FROM stock_movements sm
                                       JOIN inventory_items i ON sm.item_id = i.id
                                       JOIN users u ON sm.performed_by = u.id
                                       WHERE sm.movement_type = 'OUT'
                                       ORDER BY sm.created_at DESC LIMIT 10";
                        $recent_result = $db->query($recent_sql);
                        if($recent_result->num_rows > 0):
                            while($movement = $recent_result->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($movement['item_name']); ?></td>
                                <td><span class="stock-out-badge">-<?php echo number_format($movement['quantity']); ?></span></td>
                                <td><?php echo htmlspecialchars($movement['notes'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($movement['fullname']); ?></td>
                            </td>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="5" class="text-center">No recent stock out records</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .stock-out-badge {
        display: inline-block;
        padding: 4px 10px;
        background: #FEE2E2;
        color: #991B1B;
        border-radius: 20px;
        font-weight: 600;
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
    }
    
    .warning-box i {
        color: #F59E0B;
        font-size: 20px;
    }
</style>

<script>
document.getElementById('item_id').addEventListener('change', calculateRemaining);
document.getElementById('quantity').addEventListener('input', calculateRemaining);

function calculateRemaining() {
    const select = document.getElementById('item_id');
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const selectedOption = select.options[select.selectedIndex];
    const warningBox = document.getElementById('warningBox');
    const submitBtn = document.getElementById('submitBtn');
    
    if (selectedOption.value) {
        const currentStock = parseInt(selectedOption.dataset.current) || 0;
        const unit = selectedOption.dataset.unit || '';
        const remaining = currentStock - quantity;
        const itemName = selectedOption.dataset.name;
        
        document.getElementById('remaining_stock').value = remaining + ' ' + unit;
        
        if (quantity > currentStock) {
            warningBox.style.display = 'flex';
            document.getElementById('warningMessage').innerHTML = `Insufficient stock! Only ${currentStock} ${unit} of ${itemName} available.`;
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
        } else if (quantity > 0 && quantity > currentStock * 0.8) {
            warningBox.style.display = 'flex';
            document.getElementById('warningMessage').innerHTML = `⚠️ Warning: This will use ${Math.round((quantity/currentStock)*100)}% of available stock. Remaining: ${remaining} ${unit}`;
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        } else {
            warningBox.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        }
    } else {
        document.getElementById('remaining_stock').value = '';
        warningBox.style.display = 'none';
    }
}
</script>

<?php include '../templates/footer.php'; ?>
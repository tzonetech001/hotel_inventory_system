<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get active items
$items_sql = "SELECT id, item_name, unit, current_stock FROM inventory_items WHERE status = 'active' ORDER BY item_name";
$items_result = $db->query($items_sql);
$items = $items_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $reference = trim($_POST['reference']);
    $notes = trim($_POST['notes']);
    
    if ($item_id <= 0 || $quantity <= 0) {
        $error = "Please select an item and enter valid quantity!";
    } else {
        if (updateStock($item_id, $quantity, 'IN', $user_id, $reference)) {
            logActivity($user_id, 'Stock IN', "Added $quantity units to item ID: $item_id. Reference: $reference");
            $success = "Stock added successfully!";
            
            // Get updated stock
            $new_stock = getCurrentStock($item_id);
            
            // Clear form
            $_POST = array();
        } else {
            $error = "Error adding stock!";
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
            <h3>Record Stock Receipt</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="stockInForm">
                <div class="form-group">
                    <label><i class="fas fa-box"></i> Select Item *</label>
                    <select name="item_id" id="item_id" required>
                        <option value="">-- Select Item --</option>
                        <?php foreach($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" 
                                    data-current="<?php echo $item['current_stock']; ?>"
                                    data-unit="<?php echo $item['unit']; ?>">
                                <?php echo htmlspecialchars($item['item_name']); ?> 
                                (Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-plus-circle"></i> Quantity *</label>
                        <input type="number" name="quantity" id="quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calculator"></i> New Stock After</label>
                        <input type="text" id="new_stock" readonly style="background: #F3F4F6; font-weight: bold;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Reference Number</label>
                    <input type="text" name="reference" placeholder="e.g., PO-001, GRN-001, Delivery Note #">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Notes</label>
                    <textarea name="notes" rows="3" placeholder="Additional notes about this receipt..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Record Stock In
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
            <h3>Recent Stock Movements (IN)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Reference</th>
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_sql = "SELECT sm.*, i.item_name, u.fullname 
                                       FROM stock_movements sm
                                       JOIN inventory_items i ON sm.item_id = i.id
                                       JOIN users u ON sm.performed_by = u.id
                                       WHERE sm.movement_type = 'IN'
                                       ORDER BY sm.created_at DESC LIMIT 10";
                        $recent_result = $db->query($recent_sql);
                        if($recent_result->num_rows > 0):
                            while($movement = $recent_result->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($movement['item_name']); ?></td>
                                <td><span class="stock-in-badge">+<?php echo number_format($movement['quantity']); ?></span></td>
                                <td><?php echo htmlspecialchars($movement['reference_no'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($movement['fullname']); ?></td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="5" class="text-center">No recent stock in records</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .stock-in-badge {
        display: inline-block;
        padding: 4px 10px;
        background: #D1FAE5;
        color: #065F46;
        border-radius: 20px;
        font-weight: 600;
    }
    
    input[readonly] {
        background: #F3F4F6;
        cursor: not-allowed;
    }
</style>

<script>
document.getElementById('item_id').addEventListener('change', calculateNewStock);
document.getElementById('quantity').addEventListener('input', calculateNewStock);

function calculateNewStock() {
    const select = document.getElementById('item_id');
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const currentStock = parseInt(selectedOption.dataset.current) || 0;
        const unit = selectedOption.dataset.unit || '';
        const newStock = currentStock + quantity;
        document.getElementById('new_stock').value = newStock + ' ' + unit;
    } else {
        document.getElementById('new_stock').value = '';
    }
}
</script>

<?php include '../templates/footer.php'; ?>
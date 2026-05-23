<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer', 'Storekeeper']);

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle delivery confirmation (for Storekeeper)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delivery'])) {
    $po_id = intval($_POST['po_id']);
    $delivery_date = $_POST['delivery_date'];
    $notes = trim($_POST['notes']);
    
    $db->begin_transaction();
    
    try {
        // Update PO status
        $sql = "UPDATE purchase_orders SET status = 'delivered' WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $po_id);
        $stmt->execute();
        
        // Record delivery
        $sql = "INSERT INTO deliveries (po_id, delivery_date, received_by, notes) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("isis", $po_id, $delivery_date, $user_id, $notes);
        $stmt->execute();
        
        // Get PO items to update stock
        $sql = "SELECT item_id, quantity FROM po_items WHERE po_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $po_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        
        while ($item = $items_result->fetch_assoc()) {
            // Update stock
            updateStock($item['item_id'], $item['quantity'], 'IN', $user_id, "PO Delivery");
        }
        
        $db->commit();
        logActivity($user_id, 'Confirm Delivery', "Confirmed delivery for PO ID: $po_id");
        $success = "Delivery confirmed and stock updated successfully!";
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error confirming delivery: " . $e->getMessage();
    }
}

// Get approved POs waiting for delivery
$sql = "SELECT po.*, s.company_name as supplier_name, s.contact_person, s.phone as supplier_phone,
        u.fullname as created_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN users u ON po.created_by = u.id
        WHERE po.status = 'approved'
        ORDER BY po.expected_delivery ASC, po.created_at ASC";

$result = $db->query($sql);
$pending_deliveries = $result->fetch_all(MYSQLI_ASSOC);

// Get delivered POs
$sql_delivered = "SELECT po.*, s.company_name as supplier_name, d.delivery_date, d.notes as delivery_notes,
                  u.fullname as received_by_name
                  FROM purchase_orders po
                  JOIN suppliers s ON po.supplier_id = s.id
                  JOIN deliveries d ON po.id = d.po_id
                  JOIN users u ON d.received_by = u.id
                  WHERE po.status IN ('delivered', 'confirmed')
                  ORDER BY d.delivery_date DESC LIMIT 20";

$delivered_result = $db->query($sql_delivered);
$delivered_orders = $delivered_result->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Track Deliveries</h1>
        <p>Monitor and confirm incoming deliveries</p>
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
    
    <!-- Pending Deliveries Section -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Pending Deliveries (<?php echo count($pending_deliveries); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if(count($pending_deliveries) > 0): ?>
                <div class="delivery-grid">
                    <?php foreach($pending_deliveries as $delivery): ?>
                        <div class="delivery-card">
                            <div class="delivery-header">
                                <div class="po-number">
                                    <i class="fas fa-file-invoice"></i> <?php echo $delivery['po_number']; ?>
                                </div>
                                <div class="delivery-status pending">
                                    <i class="fas fa-spinner fa-pulse"></i> Awaiting Delivery
                                </div>
                            </div>
                            <div class="delivery-body">
                                <div class="info-group">
                                    <div class="info-label">Supplier:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($delivery['company_name']); ?></div>
                                </div>
                                <div class="info-group">
                                    <div class="info-label">Contact:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($delivery['contact_person'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($delivery['supplier_phone'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="info-group">
                                    <div class="info-label">Expected By:</div>
                                    <div class="info-value <?php echo (strtotime($delivery['expected_delivery']) < time()) ? 'overdue' : ''; ?>">
                                        <?php echo $delivery['expected_delivery'] ? date('d/m/Y', strtotime($delivery['expected_delivery'])) : 'Not specified'; ?>
                                        <?php if(strtotime($delivery['expected_delivery']) < time() && $delivery['expected_delivery']): ?>
                                            <span class="overdue-badge">(Overdue)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="info-group">
                                    <div class="info-label">Total Amount:</div>
                                    <div class="info-value amount">TZS <?php echo number_format($delivery['total_amount'], 2); ?></div>
                                </div>
                                <div class="info-group">
                                    <div class="info-label">Order Date:</div>
                                    <div class="info-value"><?php echo date('d/m/Y', strtotime($delivery['order_date'])); ?></div>
                                </div>
                            </div>
                            <?php if($user_role == 'Storekeeper'): ?>
                                <div class="delivery-actions">
                                    <button onclick="viewOrderItems(<?php echo $delivery['id']; ?>)" class="btn-secondary">
                                        <i class="fas fa-list"></i> View Items
                                    </button>
                                    <button onclick="showConfirmDelivery(<?php echo $delivery['id']; ?>)" class="btn-primary">
                                        <i class="fas fa-check-circle"></i> Confirm Delivery
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="delivery-actions">
                                    <button onclick="viewOrderItems(<?php echo $delivery['id']; ?>)" class="btn-secondary">
                                        <i class="fas fa-list"></i> View Items
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #10B981;"></i>
                    <p>No pending deliveries! All orders have been delivered.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Deliveries Section -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Deliveries</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Delivery Date</th>
                            <th>Total Amount</th>
                            <th>Received By</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($delivered_orders) > 0): ?>
                            <?php foreach($delivered_orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo $order['po_number']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['company_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($order['delivery_date'])); ?></td>
                                    <td>TZS <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($order['received_by_name']); ?></td>
                                    <td>
                                        <span class="status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="viewOrderItems(<?php echo $order['id']; ?>)" class="btn-icon">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No delivery records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Delivery Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> Confirm Delivery</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="confirmForm">
                <input type="hidden" name="po_id" id="confirm_po_id">
                <input type="hidden" name="confirm_delivery" value="1">
                
                <div id="orderSummary" style="background: #F0F9FF; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <!-- Order summary will be loaded here -->
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Delivery Date *</label>
                    <input type="date" name="delivery_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Receiving Notes</label>
                    <textarea name="notes" rows="3" placeholder="Any issues, damages, or comments about this delivery..."></textarea>
                </div>
                
                <div class="warning-box">
                    <i class="fas fa-info-circle"></i>
                    Confirming this delivery will automatically update inventory stock levels.
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> Confirm & Update Stock
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Items Modal -->
<div id="itemsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-list"></i> Order Items</h3>
            <span class="close-items">&times;</span>
        </div>
        <div class="modal-body" id="itemsModalBody">
            <!-- Items will be loaded here -->
        </div>
    </div>
</div>

<style>
    .delivery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 20px;
    }
    
    .delivery-card {
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s;
        background: white;
    }
    
    .delivery-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .delivery-header {
        background: #F9FAFB;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .po-number {
        font-weight: 700;
        font-size: 16px;
        color: #1E3A8A;
    }
    
    .delivery-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .delivery-status.pending {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .delivery-body {
        padding: 15px;
    }
    
    .info-group {
        display: flex;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .info-label {
        width: 100px;
        font-weight: 600;
        color: #6B7280;
    }
    
    .info-value {
        flex: 1;
        color: #374151;
    }
    
    .info-value.overdue {
        color: #DC2626;
    }
    
    .overdue-badge {
        background: #FEE2E2;
        color: #991B1B;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        margin-left: 8px;
    }
    
    .info-value.amount {
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .delivery-actions {
        padding: 15px;
        background: #F9FAFB;
        border-top: 1px solid #E5E7EB;
        display: flex;
        gap: 10px;
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
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
    }
    
    .items-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        padding: 12px;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .item-row:last-child {
        border-bottom: none;
    }
    
    .item-name {
        font-weight: 500;
    }
    
    .item-quantity {
        color: #6B7280;
    }
</style>

<script>
function viewOrderItems(poId) {
    fetch(`get_po_details.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            let itemsHtml = '<div class="items-list">';
            itemsHtml += `<div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #E5E7EB;">
                            <strong>PO Number:</strong> ${data.po.po_number}<br>
                            <strong>Supplier:</strong> ${data.po.supplier_name}
                          </div>`;
            
            data.items.forEach(item => {
                itemsHtml += `
                    <div class="item-row">
                        <div class="item-name">${item.item_name}</div>
                        <div class="item-quantity">${item.quantity} units @ TZS ${parseFloat(item.unit_price).toLocaleString()}</div>
                        <div class="item-total">TZS ${parseFloat(item.total_price).toLocaleString()}</div>
                    </div>
                `;
            });
            
            itemsHtml += `<div class="item-row" style="background: #F3F4F6; font-weight: bold;">
                            <div>Total</div>
                            <div></div>
                            <div>TZS ${parseFloat(data.po.total_amount).toLocaleString()}</div>
                          </div>`;
            itemsHtml += '</div>';
            
            document.getElementById('itemsModalBody').innerHTML = itemsHtml;
            document.getElementById('itemsModal').style.display = 'block';
        });
}

function showConfirmDelivery(poId) {
    fetch(`get_po_details.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            let summaryHtml = `
                <div><strong>PO Number:</strong> ${data.po.po_number}</div>
                <div><strong>Supplier:</strong> ${data.po.supplier_name}</div>
                <div><strong>Total Items:</strong> ${data.items.length}</div>
                <div><strong>Total Amount:</strong> TZS ${parseFloat(data.po.total_amount).toLocaleString()}</div>
            `;
            
            let itemsList = '<div style="margin-top: 10px;"><strong>Items to receive:</strong><ul>';
            data.items.forEach(item => {
                itemsList += `<li>${item.item_name}: ${item.quantity} units</li>`;
            });
            itemsList += '</ul></div>';
            
            document.getElementById('orderSummary').innerHTML = summaryHtml + itemsList;
            document.getElementById('confirm_po_id').value = poId;
            document.getElementById('confirmModal').style.display = 'block';
        });
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

document.querySelector('.close').onclick = closeModal;
document.querySelector('.close-items').onclick = function() {
    document.getElementById('itemsModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('confirmModal')) {
        closeModal();
    }
    if (event.target == document.getElementById('itemsModal')) {
        document.getElementById('itemsModal').style.display = 'none';
    }
}
</script>

<?php include '../templates/footer.php'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle approval/rejection
if (isset($_GET['id']) && isset($_GET['action'])) {
    $po_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'approve') {
        $status = 'approved';
        $message = "Purchase order approved by manager";
    } elseif ($action == 'reject') {
        $status = 'rejected';
        $message = "Purchase order rejected by manager";
    }
    
    if (isset($status)) {
        $sql = "UPDATE purchase_orders SET status = ?, approved_by = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sii", $status, $user_id, $po_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Approve PO', "Purchase order ID: $po_id - $message");
            $success = "Purchase order $message successfully!";
        } else {
            $error = "Error updating purchase order!";
        }
    }
}

// Get pending POs
$sql = "SELECT po.*, s.company_name as supplier_name, u.fullname as created_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN users u ON po.created_by = u.id
        WHERE po.status = 'pending'
        ORDER BY po.created_at ASC";

$result = $db->query($sql);
$pending_orders = $result->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-check-double"></i> Approve Purchase Orders</h1>
        <p>Review and approve purchase orders from procurement</p>
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
            <h3>Pending Approvals (<?php echo count($pending_orders); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if(count($pending_orders) > 0): ?>
                <?php foreach($pending_orders as $order): ?>
                    <div class="approval-card">
                        <div class="approval-header">
                            <div class="po-info">
                                <strong class="po-number"><?php echo $order['po_number']; ?></strong>
                                <span class="po-date">Created: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="po-amount">
                                Total: TZS <?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                        <div class="approval-body">
                            <div class="info-row">
                                <span class="label">Supplier:</span>
                                <span><?php echo htmlspecialchars($order['supplier_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Requested By:</span>
                                <span><?php echo htmlspecialchars($order['created_by_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Expected Delivery:</span>
                                <span><?php echo $order['expected_delivery'] ? date('d/m/Y', strtotime($order['expected_delivery'])) : 'Not specified'; ?></span>
                            </div>
                            <?php if($order['notes']): ?>
                                <div class="info-row">
                                    <span class="label">Notes:</span>
                                    <span><?php echo htmlspecialchars($order['notes']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="approval-actions">
                            <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="btn-secondary">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button onclick="approveOrder(<?php echo $order['id']; ?>)" class="btn-approve">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button onclick="rejectOrder(<?php echo $order['id']; ?>)" class="btn-reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #10B981;"></i>
                    <p>No pending purchase orders to approve!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="orderModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Order Details</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="orderModalBody">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<style>
    .approval-card {
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s;
    }
    
    .approval-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    
    .approval-header {
        background: #F9FAFB;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .po-number {
        font-size: 18px;
        color: #1E3A8A;
    }
    
    .po-date {
        font-size: 12px;
        color: #6B7280;
        margin-left: 15px;
    }
    
    .po-amount {
        font-size: 18px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .approval-body {
        padding: 20px;
        background: white;
    }
    
    .info-row {
        margin-bottom: 10px;
        display: flex;
    }
    
    .info-row .label {
        width: 130px;
        font-weight: 600;
        color: #374151;
    }
    
    .approval-actions {
        padding: 15px 20px;
        background: #F9FAFB;
        border-top: 1px solid #E5E7EB;
        display: flex;
        gap: 15px;
    }
    
    .btn-approve {
        background: #10B981;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-approve:hover {
        background: #059669;
    }
    
    .btn-reject {
        background: #EF4444;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-reject:hover {
        background: #DC2626;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state p {
        margin-top: 15px;
        color: #6B7280;
    }
</style>

<script>
function viewOrderDetails(id) {
    fetch(`../procurement/get_po_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            let itemsHtml = '';
            data.items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td>${item.item_name}</td>
                        <td>${item.quantity}</td>
                        <td>TZS ${parseFloat(item.unit_price).toLocaleString()}</td>
                        <td>TZS ${parseFloat(item.total_price).toLocaleString()}</td>
                    </tr>
                `;
            });
            
            document.getElementById('orderModalBody').innerHTML = `
                <div class="po-details">
                    <div class="po-header">
                        <strong>PO Number:</strong> ${data.po.po_number}<br>
                        <strong>Supplier:</strong> ${data.po.supplier_name}<br>
                        <strong>Total Amount:</strong> TZS ${parseFloat(data.po.total_amount).toLocaleString()}
                    </div>
                    
                    <h4>Items to Purchase</h4>
                    <table class="po-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                    </table>
                </div>
            `;
            document.getElementById('orderModal').style.display = 'block';
        });
}

function approveOrder(id) {
    if (confirm('Are you sure you want to APPROVE this purchase order? This will allow procurement to proceed with the order.')) {
        window.location.href = `approve_po.php?id=${id}&action=approve`;
    }
}

function rejectOrder(id) {
    if (confirm('Are you sure you want to REJECT this purchase order? You may need to provide a reason to procurement.')) {
        window.location.href = `approve_po.php?id=${id}&action=reject`;
    }
}

document.querySelector('.close').onclick = function() {
    document.getElementById('orderModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('orderModal')) {
        document.getElementById('orderModal').style.display = 'none';
    }
}
</script>

<?php include '../templates/footer.php'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager']);

$user_id = $_SESSION['user_id'];
$error = '';

// Handle approval/rejection
if (isset($_GET['id']) && isset($_GET['action'])) {
    $po_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'approve') {
        $status = 'approved';
        $message = "approved";
    } elseif ($action == 'reject') {
        $status = 'rejected';
        $message = "rejected";
    }
    
    if (isset($status)) {
        $sql = "UPDATE purchase_orders SET status = ?, approved_by = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sii", $status, $user_id, $po_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Approve PO', "Purchase order ID: $po_id - $message");
            $_SESSION['toast_message'] = "Purchase order $message successfully!";
            $_SESSION['toast_type'] = "success";
            header("Location: approve_po.php");
            exit();
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
    
    <?php if($error): ?>
        <script>showToast('<?php echo addslashes($error); ?>', 'error');</script>
    <?php endif; ?>
    
    <div class="stats-summary">
        <div class="stat-item">
            <div class="stat-label">Pending Approvals</div>
            <div class="stat-number"><?php echo count($pending_orders); ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Total Value Pending</div>
            <div class="stat-number">
                TZS <?php 
                    $total = 0;
                    foreach($pending_orders as $order) { $total += $order['total_amount']; }
                    echo number_format($total, 2);
                ?>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Pending Approvals (<?php echo count($pending_orders); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if(count($pending_orders) > 0): ?>
                <div class="approval-grid">
                    <?php foreach($pending_orders as $order): ?>
                        <div class="approval-card">
                            <div class="approval-header">
                                <div class="po-number"><?php echo $order['po_number']; ?></div>
                                <div class="po-date"><?php echo date('d M Y', strtotime($order['order_date'])); ?></div>
                            </div>
                            <div class="approval-body">
                                <div class="info-row">
                                    <span class="label">Supplier:</span>
                                    <span class="value"><?php echo htmlspecialchars($order['supplier_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Requested By:</span>
                                    <span class="value"><?php echo htmlspecialchars($order['created_by_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Amount:</span>
                                    <span class="value amount">TZS <?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Expected:</span>
                                    <span class="value"><?php echo $order['expected_delivery'] ? date('d M Y', strtotime($order['expected_delivery'])) : 'Not specified'; ?></span>
                                </div>
                                <?php if($order['notes']): ?>
                                    <div class="info-row notes">
                                        <span class="label">Notes:</span>
                                        <span class="value"><?php echo htmlspecialchars(substr($order['notes'], 0, 100)); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="approval-actions">
                                <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="btn-secondary">
                                    <i class="fas fa-eye"></i> Details
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
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending purchase orders to approve!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice"></i> Order Details</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="orderModalBody">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<style>
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .stat-item {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .stat-label {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 8px;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .approval-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 20px;
    }
    
    .approval-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
        border: 1px solid #E5E7EB;
    }
    
    .approval-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
        font-size: 16px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .po-date {
        font-size: 12px;
        color: #6B7280;
    }
    
    .approval-body {
        padding: 20px;
    }
    
    .info-row {
        display: flex;
        margin-bottom: 12px;
        font-size: 13px;
    }
    
    .info-row .label {
        width: 100px;
        font-weight: 600;
        color: #6B7280;
    }
    
    .info-row .value {
        flex: 1;
        color: #374151;
    }
    
    .info-row .value.amount {
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .info-row.notes .value {
        color: #6B7280;
        font-style: italic;
    }
    
    .approval-actions {
        padding: 15px 20px;
        background: #F9FAFB;
        border-top: 1px solid #E5E7EB;
        display: flex;
        gap: 10px;
    }
    
    .btn-approve {
        background: #10B981;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-approve:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .btn-reject {
        background: #EF4444;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-reject:hover {
        background: #DC2626;
        transform: translateY(-1px);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: #10B981;
        margin-bottom: 15px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
    }
    
    .modal-large {
        max-width: 800px;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #F9FAFB;
        border-radius: 16px 16px 0 0;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .modal-header .close {
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
    }
    
    .modal-header .close:hover {
        color: #EF4444;
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .po-items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .po-items-table th,
    .po-items-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .po-items-table th {
        background: #F3F4F6;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .approval-grid {
            grid-template-columns: 1fr;
        }
        
        .info-row {
            flex-direction: column;
        }
        
        .info-row .label {
            width: auto;
            margin-bottom: 4px;
        }
        
        .approval-actions {
            flex-wrap: wrap;
        }
        
        .btn-approve, .btn-reject, .btn-secondary {
            flex: 1;
            text-align: center;
        }
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
                    <div style="background: #F0F9FF; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <p><strong>PO Number:</strong> ${data.po.po_number}</p>
                        <p><strong>Supplier:</strong> ${data.po.supplier_name}</p>
                        <p><strong>Contact:</strong> ${data.po.contact_person || 'N/A'} | ${data.po.supplier_phone || 'N/A'}</p>
                        <p><strong>Total Amount:</strong> <strong style="color: #1E3A8A;">TZS ${parseFloat(data.po.total_amount).toLocaleString()}</strong></p>
                        <p><strong>Expected Delivery:</strong> ${data.po.expected_delivery || 'Not specified'}</p>
                        ${data.po.notes ? `<p><strong>Notes:</strong> ${data.po.notes}</p>` : ''}
                    </div>
                    
                    <h4 style="margin-bottom: 10px;">Items to Purchase</h4>
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
            document.getElementById('orderModal').style.display = 'flex';
        });
}

function approveOrder(id) {
    if (confirm('Are you sure you want to APPROVE this purchase order?')) {
        window.location.href = `approve_po.php?id=${id}&action=approve`;
    }
}

function rejectOrder(id) {
    if (confirm('Are you sure you want to REJECT this purchase order?')) {
        window.location.href = `approve_po.php?id=${id}&action=reject`;
    }
}

function closeModal() {
    document.getElementById('orderModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php include '../templates/footer.php'; ?>
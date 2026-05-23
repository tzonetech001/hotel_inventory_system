<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer', 'Hotel Manager']);

$status_filter = $_GET['status'] ?? 'all';
$user_role = $_SESSION['role'];

$sql = "SELECT po.*, s.company_name as supplier_name, 
        u.fullname as created_by_name,
        a.fullname as approved_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN users u ON po.created_by = u.id
        LEFT JOIN users a ON po.approved_by = a.id";

if ($status_filter != 'all') {
    $sql .= " WHERE po.status = '" . $db->real_escape_string($status_filter) . "'";
}

$sql .= " ORDER BY po.created_at DESC";

$result = $db->query($sql);
$orders = $result->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Purchase Orders</h1>
        <p>View and track all purchase orders</p>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                <a href="?status=pending" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?status=approved" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="?status=rejected" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
                <a href="?status=delivered" class="filter-tab <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">Delivered</a>
                <a href="?status=confirmed" class="filter-tab <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Order Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($orders) > 0): ?>
                            <?php foreach($orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo $order['po_number']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['supplier_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                    <td>TZS <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['created_by_name']); ?></td>
                                    <td>
                                        <button onclick="viewPO(<?php echo $order['id']; ?>)" class="btn-icon">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if($order['status'] == 'pending' && $user_role == 'Hotel Manager'): ?>
                                            <button onclick="approvePO(<?php echo $order['id']; ?>)" class="btn-icon approve">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                     </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No purchase orders found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="poModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Purchase Order Details</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="poModalBody">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<style>
    .filter-tabs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-tab {
        padding: 8px 16px;
        background: #F3F4F6;
        border-radius: 20px;
        text-decoration: none;
        color: #374151;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .filter-tab:hover, .filter-tab.active {
        background: #1E3A8A;
        color: white;
    }
    
    .status-pending {
        background: #FEF3C7;
        color: #92400E;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-approved {
        background: #D1FAE5;
        color: #065F46;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-rejected {
        background: #FEE2E2;
        color: #991B1B;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-delivered {
        background: #DBEAFE;
        color: #1E40AF;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-confirmed {
        background: #D1FAE5;
        color: #065F46;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .btn-icon.approve {
        color: #10B981;
    }
    
    .modal-large {
        max-width: 800px;
        width: 90%;
    }
    
    .po-details {
        padding: 15px;
    }
    
    .po-header {
        background: #F9FAFB;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
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
    
    .total-row {
        font-weight: bold;
        background: #F9FAFB;
    }
</style>

<script>
function viewPO(id) {
    fetch(`get_po_details.php?id=${id}`)
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
            
            document.getElementById('poModalBody').innerHTML = `
                <div class="po-details">
                    <div class="po-header">
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                <strong>PO Number:</strong> ${data.po.po_number}<br>
                                <strong>Order Date:</strong> ${data.po.order_date}<br>
                                <strong>Expected Delivery:</strong> ${data.po.expected_delivery || 'Not set'}
                            </div>
                            <div>
                                <strong>Status:</strong> 
                                <span class="status-${data.po.status}">${data.po.status.toUpperCase()}</span><br>
                                <strong>Created By:</strong> ${data.po.created_by_name}<br>
                                <strong>Approved By:</strong> ${data.po.approved_by_name || 'Pending'}
                            </div>
                        </div>
                        <div style="margin-top: 10px;">
                            <strong>Supplier:</strong> ${data.po.supplier_name}<br>
                            <strong>Contact:</strong> ${data.po.supplier_contact || 'N/A'}
                        </div>
                        ${data.po.notes ? `<div style="margin-top: 10px;"><strong>Notes:</strong> ${data.po.notes}</div>` : ''}
                    </div>
                    
                    <h4>Order Items</h4>
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
                            <tr class="total-row">
                                <td colspan="3" style="text-align: right;"><strong>Grand Total:</strong></td>
                                <td><strong>TZS ${parseFloat(data.po.total_amount).toLocaleString()}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
            document.getElementById('poModal').style.display = 'block';
        });
}

function approvePO(id) {
    if (confirm('Are you sure you want to approve this purchase order?')) {
        window.location.href = `../manager/approve_po.php?id=${id}&action=approve`;
    }
}

document.querySelector('.close').onclick = function() {
    document.getElementById('poModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('poModal')) {
        document.getElementById('poModal').style.display = 'none';
    }
}
</script>

<?php include '../templates/footer.php'; ?>
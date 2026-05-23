<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Supplier']);

$user_id = $_SESSION['user_id'];

// Get supplier information based on logged in user
// Assuming supplier users have matching email or we link them
$sql = "SELECT * FROM suppliers WHERE email = ? OR contact_person LIKE CONCAT('%', ?, '%')";
$stmt = $db->prepare($sql);
$stmt->bind_param("ss", $_SESSION['email'], $_SESSION['fullname']);
$stmt->execute();
$supplier_result = $stmt->get_result();
$supplier = $supplier_result->fetch_assoc();

if (!$supplier) {
    // If no supplier found, show all orders for demo
    $supplier_id = 0;
} else {
    $supplier_id = $supplier['id'];
}

// Get orders for this supplier
if ($supplier_id > 0) {
    $sql = "SELECT po.*, u.fullname as created_by_name
            FROM purchase_orders po
            JOIN users u ON po.created_by = u.id
            WHERE po.supplier_id = ?
            ORDER BY po.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // For demo, show all pending orders
    $sql = "SELECT po.*, s.company_name as supplier_name, u.fullname as created_by_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            JOIN users u ON po.created_by = u.id
            WHERE po.status IN ('approved', 'pending')
            ORDER BY po.created_at DESC";
    $result = $db->query($sql);
}

$orders = $result->fetch_all(MYSQLI_ASSOC);

// Count orders by status
$pending_count = 0;
$approved_count = 0;
$delivered_count = 0;

foreach ($orders as $order) {
    if ($order['status'] == 'pending') $pending_count++;
    if ($order['status'] == 'approved') $approved_count++;
    if ($order['status'] == 'delivered') $delivered_count++;
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-store"></i> Supplier Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</p>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #FEF3C7;">
                <i class="fas fa-clock" style="color: #F59E0B;"></i>
            </div>
            <div class="stat-info">
                <h3>Pending Orders</h3>
                <div class="stat-number"><?php echo $pending_count; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #D1FAE5;">
                <i class="fas fa-check-circle" style="color: #10B981;"></i>
            </div>
            <div class="stat-info">
                <h3>Approved Orders</h3>
                <div class="stat-number"><?php echo $approved_count; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #DBEAFE;">
                <i class="fas fa-truck" style="color: #1E3A8A;"></i>
            </div>
            <div class="stat-info">
                <h3>Delivered Orders</h3>
                <div class="stat-number"><?php echo $delivered_count; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #FCE7F3;">
                <i class="fas fa-chart-line" style="color: #EC4899;"></i>
            </div>
            <div class="stat-info">
                <h3>Total Orders</h3>
                <div class="stat-number"><?php echo count($orders); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Orders List -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-list"></i> My Orders</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Order Date</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Expected Delivery</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($orders) > 0): ?>
                            <?php foreach($orders as $order): ?>
                                <tr id="order-<?php echo $order['id']; ?>">
                                    <td><strong><?php echo $order['po_number']; ?></strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <button onclick="viewItems(<?php echo $order['id']; ?>)" class="btn-link">
                                            View Items
                                        </button>
                                    </td>
                                    <td>TZS <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo $order['expected_delivery'] ? date('d/m/Y', strtotime($order['expected_delivery'])) : 'Not set'; ?></td>
                                    <td>
                                        <span class="status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($order['status'] == 'approved'): ?>
                                            <button onclick="markAsDelivered(<?php echo $order['id']; ?>)" class="btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                                <i class="fas fa-check"></i> Mark Delivered
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No orders found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Items Modal -->
<div id="itemsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Order Items</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="itemsModalBody">
            <!-- Items will be loaded here -->
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stat-info h3 {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 5px;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .btn-link {
        background: none;
        border: none;
        color: #1E3A8A;
        cursor: pointer;
        text-decoration: underline;
    }
    
    .btn-link:hover {
        color: #FF6B6B;
    }
</style>

<script>
function viewItems(poId) {
    fetch(`../procurement/get_po_details.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            let itemsHtml = '<div class="items-list">';
            itemsHtml += `<div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #E5E7EB;">
                            <strong>PO Number:</strong> ${data.po.po_number}<br>
                            <strong>Order Date:</strong> ${data.po.order_date}<br>
                            <strong>Status:</strong> ${data.po.status}
                          </div>`;
            
            data.items.forEach(item => {
                itemsHtml += `
                    <div class="item-row">
                        <div class="item-name">${item.item_name}</div>
                        <div class="item-quantity">${item.quantity} units</div>
                        <div class="item-price">@ TZS ${parseFloat(item.unit_price).toLocaleString()}</div>
                        <div class="item-total">= TZS ${parseFloat(item.total_price).toLocaleString()}</div>
                    </div>
                `;
            });
            
            itemsHtml += `<div class="item-row" style="background: #F3F4F6; font-weight: bold; margin-top: 10px;">
                            <div>Total</div>
                            <div></div>
                            <div></div>
                            <div>TZS ${parseFloat(data.po.total_amount).toLocaleString()}</div>
                          </div>`;
            itemsHtml += '</div>';
            
            document.getElementById('itemsModalBody').innerHTML = itemsHtml;
            document.getElementById('itemsModal').style.display = 'block';
        });
}

function markAsDelivered(poId) {
    if (confirm('Have you delivered all items for this order? The hotel storekeeper will confirm receipt.')) {
        // Here you would update the status to 'delivered' or send notification
        alert('Thank you! Please wait for storekeeper confirmation.');
    }
}

document.querySelector('.close').onclick = function() {
    document.getElementById('itemsModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('itemsModal')) {
        document.getElementById('itemsModal').style.display = 'none';
    }
}
</script>

<?php include '../templates/footer.php'; ?>
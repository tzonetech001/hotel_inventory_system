<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Procurement Officer', 'Storekeeper']);

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

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
            updateStock($item['item_id'], $item['quantity'], 'IN', $user_id, "PO Delivery");
        }
        
        $db->commit();
        logActivity($user_id, 'Confirm Delivery', "Confirmed delivery for PO ID: $po_id");
        $_SESSION['toast_message'] = "Delivery confirmed and stock updated successfully!";
        $_SESSION['toast_type'] = "success";
        header("Location: track_delivery.php");
        exit();
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['toast_message'] = "Error confirming delivery: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
        header("Location: track_delivery.php");
        exit();
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
    
    <!-- Stats Summary -->
    <div class="stats-summary">
        <div class="stat-item">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Pending Deliveries</div>
                <div class="stat-number"><?php echo count($pending_deliveries); ?></div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon delivered">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Completed Deliveries</div>
                <div class="stat-number"><?php echo count($delivered_orders); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Pending Deliveries Section -->
    <div class="card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Pending Deliveries</h3>
            <span class="badge-count"><?php echo count($pending_deliveries); ?> pending</span>
        </div>
        <div class="card-body">
            <?php if(count($pending_deliveries) > 0): ?>
                <div class="delivery-grid">
                    <?php foreach($pending_deliveries as $delivery): 
                        // Safely get values with null checks
                        $supplier_name = $delivery['supplier_name'] ?? 'N/A';
                        $contact_person = $delivery['contact_person'] ?? 'N/A';
                        $supplier_phone = $delivery['supplier_phone'] ?? 'N/A';
                        $expected_delivery = $delivery['expected_delivery'] ?? null;
                        $is_overdue = ($expected_delivery && strtotime($expected_delivery) < time());
                    ?>
                        <div class="delivery-card <?php echo $is_overdue ? 'overdue' : ''; ?>">
                            <div class="delivery-header">
                                <div class="po-number">
                                    <i class="fas fa-file-invoice"></i> 
                                    <?php echo htmlspecialchars($delivery['po_number'] ?? 'N/A'); ?>
                                </div>
                                <div class="delivery-status pending">
                                    <i class="fas fa-spinner fa-pulse"></i> Awaiting
                                </div>
                            </div>
                            <div class="delivery-body">
                                <div class="info-row">
                                    <span class="info-label">Supplier:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($supplier_name); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Contact:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($contact_person); ?> | <?php echo htmlspecialchars($supplier_phone); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Expected:</span>
                                    <span class="info-value <?php echo $is_overdue ? 'overdue-text' : ''; ?>">
                                        <?php echo $expected_delivery ? date('d M Y', strtotime($expected_delivery)) : 'Not specified'; ?>
                                        <?php if($is_overdue): ?>
                                            <span class="overdue-badge">Overdue</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Amount:</span>
                                    <span class="info-value amount">TZS <?php echo number_format($delivery['total_amount'] ?? 0, 2); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Order Date:</span>
                                    <span class="info-value"><?php echo date('d M Y', strtotime($delivery['order_date'] ?? 'now')); ?></span>
                                </div>
                            </div>
                            <div class="delivery-actions">
                                <button onclick="viewOrderItems(<?php echo $delivery['id']; ?>)" class="btn-secondary">
                                    <i class="fas fa-list"></i> View Items
                                </button>
                                <?php if($user_role == 'Storekeeper'): ?>
                                    <button onclick="showConfirmDelivery(<?php echo $delivery['id']; ?>)" class="btn-primary">
                                        <i class="fas fa-check-circle"></i> Confirm Delivery
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending deliveries! All orders have been processed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Deliveries Section -->
    <?php if(count($delivered_orders) > 0): ?>
    <div class="card animate-card-delayed">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Deliveries</h3>
            <span class="badge-count"><?php echo count($delivered_orders); ?> records</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Delivery Date</th>
                            <th>Amount</th>
                            <th>Received By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($delivered_orders as $order): 
                            $supplier_name = $order['supplier_name'] ?? 'N/A';
                            $delivery_date = $order['delivery_date'] ?? null;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['po_number'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($supplier_name); ?></td>
                                <td><?php echo $delivery_date ? date('d M Y', strtotime($delivery_date)) : 'N/A'; ?></td>
                                <td>TZS <?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                                <td><?php echo htmlspecialchars($order['received_by_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <button onclick="viewOrderItems(<?php echo $order['id']; ?>)" class="btn-icon">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                 </td
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Confirm Delivery Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> Confirm Delivery</h3>
            <span class="close" onclick="closeConfirmModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="confirmForm">
                <input type="hidden" name="po_id" id="confirm_po_id">
                <input type="hidden" name="confirm_delivery" value="1">
                
                <div id="orderSummary" class="order-summary">
                    <!-- Order summary will be loaded here -->
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Delivery Date *</label>
                    <input type="date" name="delivery_date" id="delivery_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Receiving Notes</label>
                    <textarea name="notes" rows="3" placeholder="Any issues, damages, or comments about this delivery..."></textarea>
                </div>
                
                <div class="warning-box">
                    <i class="fas fa-info-circle"></i>
                    <span>Confirming this delivery will automatically update inventory stock levels.</span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="confirmSubmitBtn">
                        <i class="fas fa-check"></i> Confirm & Update Stock
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeConfirmModal()">
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
            <span class="close" onclick="closeItemsModal()">&times;</span>
        </div>
        <div class="modal-body" id="itemsModalBody">
            <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<style>
    /* Stats Summary */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .stat-item {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    
    .stat-item:hover {
        transform: translateY(-2px);
    }
    
    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stat-icon.pending {
        background: #FEF3C7;
        color: #F59E0B;
    }
    
    .stat-icon.delivered {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .stat-info .stat-label {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 5px;
    }
    
    .stat-info .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    /* Card Styles */
    .animate-card {
        animation: fadeInUp 0.4s ease;
    }
    
    .animate-card-delayed {
        animation: fadeInUp 0.4s ease 0.1s both;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 25px;
    }
    
    .card-header {
        padding: 18px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 16px;
        color: #1E3A8A;
    }
    
    .badge-count {
        background: #E5E7EB;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        color: #374151;
    }
    
    .card-body {
        padding: 20px;
    }
    
    /* Delivery Grid */
    .delivery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 20px;
    }
    
    .delivery-card {
        background: white;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
        border: 1px solid #E5E7EB;
    }
    
    .delivery-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .delivery-card.overdue {
        border-left: 4px solid #EF4444;
    }
    
    .delivery-header {
        background: #F9FAFB;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .po-number {
        font-weight: 700;
        font-size: 15px;
        color: #1E3A8A;
    }
    
    .po-number i {
        margin-right: 6px;
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
        padding: 20px;
    }
    
    .info-row {
        display: flex;
        margin-bottom: 12px;
        font-size: 13px;
    }
    
    .info-label {
        width: 90px;
        font-weight: 600;
        color: #6B7280;
    }
    
    .info-value {
        flex: 1;
        color: #374151;
    }
    
    .info-value.overdue-text {
        color: #DC2626;
        font-weight: 500;
    }
    
    .overdue-badge {
        background: #FEE2E2;
        color: #991B1B;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        margin-left: 8px;
    }
    
    .info-value.amount {
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .delivery-actions {
        padding: 15px 20px;
        background: #F9FAFB;
        border-top: 1px solid #E5E7EB;
        display: flex;
        gap: 10px;
    }
    
    /* Buttons */
    .btn-primary {
        background: #FF6B6B;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .btn-icon {
        width: 34px;
        height: 34px;
        background: #F3F4F6;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        color: #6B7280;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-icon:hover {
        background: #DBEAFE;
        color: #1E3A8A;
        transform: translateY(-2px);
    }
    
    /* Empty State */
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
    
    /* Order Summary */
    .order-summary {
        background: #F0F9FF;
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .order-summary div {
        margin-bottom: 8px;
    }
    
    .order-summary strong {
        color: #1E3A8A;
    }
    
    .order-summary ul {
        margin-top: 10px;
        padding-left: 20px;
    }
    
    .order-summary li {
        margin: 5px 0;
    }
    
    /* Warning Box */
    .warning-box {
        background: #FEF3C7;
        border-radius: 10px;
        padding: 12px 15px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #92400E;
    }
    
    .warning-box i {
        font-size: 18px;
    }
    
    /* Form */
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
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }
    
    /* Table */
    .table-responsive {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table thead {
        background: #F9FAFB;
    }
    
    .data-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
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
        border-radius: 20px;
        width: 90%;
        max-width: 550px;
        max-height: 85vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
    }
    
    .modal-large {
        max-width: 700px;
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
        position: sticky;
        top: 0;
        border-radius: 20px 20px 0 0;
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
    
    .loading-spinner {
        text-align: center;
        padding: 40px;
        color: #6B7280;
    }
    
    .items-list {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .item-row:last-child {
        border-bottom: none;
    }
    
    .item-name {
        font-weight: 500;
        flex: 2;
    }
    
    .item-quantity {
        flex: 1;
        text-align: center;
        color: #6B7280;
    }
    
    .item-total {
        flex: 1;
        text-align: right;
        font-weight: 600;
        color: #1E3A8A;
    }
    
    .total-row {
        background: #F3F4F6;
        margin-top: 10px;
        border-radius: 8px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stats-summary {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .delivery-grid {
            grid-template-columns: 1fr;
        }
        
        .info-row {
            flex-direction: column;
        }
        
        .info-label {
            width: auto;
            margin-bottom: 4px;
        }
        
        .delivery-actions {
            flex-direction: column;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .data-table th,
        .data-table td {
            padding: 8px 10px;
            font-size: 12px;
        }
        
        .item-row {
            flex-direction: column;
            text-align: center;
            gap: 8px;
        }
        
        .item-quantity, .item-total {
            text-align: center;
        }
    }
</style>

<script>
// View Order Items
function viewOrderItems(poId) {
    const modalBody = document.getElementById('itemsModalBody');
    modalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    document.getElementById('itemsModal').style.display = 'flex';
    
    fetch(`get_po_details.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>${data.error}</p></div>`;
                return;
            }
            
            let itemsHtml = '<div class="items-list">';
            itemsHtml += `<div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #E5E7EB;">
                            <strong>PO Number:</strong> ${data.po.po_number}<br>
                            <strong>Supplier:</strong> ${data.po.supplier_name}
                          </div>`;
            
            data.items.forEach(item => {
                itemsHtml += `
                    <div class="item-row">
                        <div class="item-name">${item.item_name}</div>
                        <div class="item-quantity">${item.quantity} units</div>
                        <div class="item-total">TZS ${parseFloat(item.total_price).toLocaleString()}</div>
                    </div>
                `;
            });
            
            itemsHtml += `<div class="item-row total-row">
                            <div class="item-name"><strong>Total</strong></div>
                            <div class="item-quantity"></div>
                            <div class="item-total"><strong>TZS ${parseFloat(data.po.total_amount).toLocaleString()}</strong></div>
                          </div>`;
            itemsHtml += '</div>';
            
            modalBody.innerHTML = itemsHtml;
        })
        .catch(error => {
            modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading details</p></div>`;
        });
}

// Show Confirm Delivery Modal
function showConfirmDelivery(poId) {
    const modal = document.getElementById('confirmModal');
    const orderSummary = document.getElementById('orderSummary');
    
    orderSummary.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    modal.style.display = 'flex';
    
    fetch(`get_po_details.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                orderSummary.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>${data.error}</p></div>`;
                return;
            }
            
            let itemsList = '<ul>';
            data.items.forEach(item => {
                itemsList += `<li><strong>${item.item_name}</strong>: ${item.quantity} units @ TZS ${parseFloat(item.unit_price).toLocaleString()}</li>`;
            });
            itemsList += '</ul>';
            
            orderSummary.innerHTML = `
                <div><strong>PO Number:</strong> ${data.po.po_number}</div>
                <div><strong>Supplier:</strong> ${data.po.supplier_name}</div>
                <div><strong>Total Items:</strong> ${data.items.length}</div>
                <div><strong>Total Amount:</strong> <strong style="color: #1E3A8A;">TZS ${parseFloat(data.po.total_amount).toLocaleString()}</strong></div>
                <div style="margin-top: 10px;"><strong>Items to receive:</strong>${itemsList}</div>
            `;
            document.getElementById('confirm_po_id').value = poId;
        })
        .catch(error => {
            orderSummary.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading details</p></div>`;
        });
}

// Close Confirm Modal
function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

// Close Items Modal
function closeItemsModal() {
    document.getElementById('itemsModal').style.display = 'none';
}

// Form submit loading state
const confirmForm = document.getElementById('confirmForm');
if (confirmForm) {
    confirmForm.addEventListener('submit', function() {
        const btn = document.getElementById('confirmSubmitBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;
    });
}

// Close modals on outside click
window.onclick = function(event) {
    const confirmModal = document.getElementById('confirmModal');
    const itemsModal = document.getElementById('itemsModal');
    
    if (event.target === confirmModal) {
        closeConfirmModal();
    }
    if (event.target === itemsModal) {
        closeItemsModal();
    }
}

// Set delivery date to today if empty
const deliveryDateInput = document.getElementById('delivery_date');
if (deliveryDateInput && !deliveryDateInput.value) {
    deliveryDateInput.value = new Date().toISOString().split('T')[0];
}
</script>

<?php include '../templates/footer.php'; ?>
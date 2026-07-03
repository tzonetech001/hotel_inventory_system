<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager']);

// ============================================
// SMS FUNCTIONS - USING WORKING API
// ============================================

// Beem API credentials - FROM WORKING parent_sms_results.php
$sms_api_key = "386bdc07eae64a53";
$sms_secret_key = "NWJmNmZkYTdhODRkYmFhNDY1YjQ4Mzg2NzBiNjEzNzYzMDU0OGE4MWUzOWM5Yjc2OTI5ZDAwNDZiYmQ1ZDY4NA==";

$sms_sender_id = "TZONE";

/**
 * Send SMS using Beem API - NO EMOJI VERSION
 */
function sendSMS($phone, $message) {
    global $sms_api_key, $sms_secret_key, $sms_sender_id;
    
    // Clean phone number
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to Beem format (255XXXXXXXXX)
    if (substr($phone, 0, 1) == '0') {
        $phone = '255' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) == '255') {
        // Already correct
    } elseif (strlen($phone) == 9 && ($phone[0] == '7' || $phone[0] == '6')) {
        $phone = '255' . $phone;
    } elseif (strlen($phone) == 10 && ($phone[0] == '7' || $phone[0] == '6')) {
        $phone = '255' . $phone;
    }
    
    // Validate
    if (strlen($phone) != 12 || substr($phone, 0, 3) != '255') {
        error_log("Invalid phone: $phone");
        return ['success' => false, 'message' => "Invalid phone: $phone"];
    }
    
    // REMOVE ALL EMOJI AND SPECIAL CHARACTERS
    $message = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $message);
    $message = str_replace('✅', '[APPROVED]', $message);
    $message = str_replace('❌', '[REJECTED]', $message);
    $message = str_replace('🔔', '[NOTIFICATION]', $message);
    $message = str_replace('📱', '', $message);
    $message = str_replace('⭐', '', $message);
    $message = str_replace('★', '', $message);
    $message = str_replace('•', '-', $message);
    $message = str_replace('→', '->', $message);
    $message = str_replace('⚠️', '[WARNING]', $message);
    
    // Limit message
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }
    
    // Prepare data
    $postData = [
        'source_addr' => $sms_sender_id,
        'encoding' => 0,
        'message' => $message,
        'recipients' => [
            ['recipient_id' => 1, 'dest_addr' => $phone]
        ]
    ];
    
    error_log("Sending SMS to: $phone");
    
    // Send
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://apisms.beem.africa/v1/send',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($sms_api_key . ':' . $sms_secret_key),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("HTTP Response: $http_code");
    error_log("Response: " . substr($response, 0, 200));
    
    if ($curl_error) {
        return ['success' => false, 'message' => 'CURL Error: ' . $curl_error];
    }
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['successful']) && $result['successful'] === true) {
            error_log("SMS SUCCESS - Phone: $phone");
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } else {
            $error_msg = isset($result['message']) ? $result['message'] : 'Unknown error';
            error_log("Beem Error: $error_msg");
            return ['success' => false, 'message' => "Beem Error: $error_msg"];
        }
    } else {
        $result = json_decode($response, true);
        $error_msg = isset($result['message']) ? $result['message'] : "HTTP Error: $http_code";
        error_log("HTTP Error: $error_msg");
        return ['success' => false, 'message' => $error_msg];
    }
}

/**
 * Get users by role
 */
function getUsersByRole($role) {
    global $db;
    $sql = "SELECT u.id, u.fullname, u.phone FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.role_name = ? AND u.status = 'active' AND u.phone IS NOT NULL AND u.phone != ''";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $role);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get supplier contact
 */
function getSupplierContact($supplier_id) {
    global $db;
    $sql = "SELECT id, company_name, contact_person, phone FROM suppliers WHERE id = ? AND status = 'active'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Send SMS to Supplier - NO EMOJI
 * This sends to the SPECIFIC supplier of the PO
 */
function sendPOStatusToSupplier($po_data, $status, $reason = null) {
    // Get the specific supplier for this PO
    $supplier = getSupplierContact($po_data['supplier_id']);
    
    if (!$supplier) {
        error_log("Supplier not found for PO: {$po_data['po_number']}");
        return ['success' => false, 'message' => 'Supplier not found'];
    }
    
    if (empty($supplier['phone'])) {
        error_log("Supplier has no phone: {$supplier['company_name']}");
        return ['success' => false, 'message' => 'Supplier has no phone'];
    }
    
    error_log("Sending to Supplier: {$supplier['company_name']} ({$supplier['phone']})");
    
    if ($status == 'approved') {
        $message = "[APPROVED] PURCHASE ORDER APPROVED!\n\n";
        $message .= "PO Number: {$po_data['po_number']}\n";
        $message .= "Total Amount: TZS " . number_format($po_data['total_amount'], 0) . "\n\n";
        $message .= "Please proceed with preparing the goods.\n";
        $message .= "Thank you - TZONE Hotel";
    } else {
        $message = "[REJECTED] PURCHASE ORDER REJECTED!\n\n";
        $message .= "PO Number: {$po_data['po_number']}\n";
        $message .= "Reason: " . ($reason ?? "No reason provided") . "\n\n";
        $message .= "Please contact procurement office for details.\n";
        $message .= "- TZONE Hotel";
    }
    
    $result = sendSMS($supplier['phone'], $message);
    error_log("Supplier SMS ({$supplier['company_name']}): " . ($result['success'] ? 'OK' : 'FAILED - ' . $result['message']));
    return $result;
}

/**
 * Send SMS to Storekeeper - NO EMOJI
 */
function sendPOStatusToStorekeeper($po_data, $status, $reason = null) {
    $storekeepers = getUsersByRole('Storekeeper');
    
    if (empty($storekeepers)) {
        error_log("No Storekeepers found");
        return ['success' => false, 'message' => 'No storekeepers'];
    }
    
    if ($status == 'approved') {
        $message = "[APPROVED] PURCHASE ORDER APPROVED!\n\n";
        $message .= "PO: {$po_data['po_number']}\n";
        $message .= "Supplier: {$po_data['supplier_name']}\n";
        $message .= "Amount: TZS " . number_format($po_data['total_amount'], 0) . "\n\n";
        $message .= "Please verify goods upon delivery.\n";
        $message .= "- TZONE Hotel";
    } else {
        $message = "[REJECTED] PURCHASE ORDER REJECTED!\n\n";
        $message .= "PO: {$po_data['po_number']}\n";
        $message .= "Supplier: {$po_data['supplier_name']}\n";
        $message .= "Reason: " . ($reason ?? "No reason provided") . "\n\n";
        $message .= "- TZONE Hotel";
    }
    
    $sent_count = 0;
    foreach ($storekeepers as $storekeeper) {
        if (!empty($storekeeper['phone'])) {
            error_log("Sending to Storekeeper: {$storekeeper['fullname']} ({$storekeeper['phone']})");
            $result = sendSMS($storekeeper['phone'], $message);
            if ($result['success']) {
                $sent_count++;
                error_log("Sent to Storekeeper: {$storekeeper['fullname']}");
            } else {
                error_log("Failed to Storekeeper: {$storekeeper['fullname']} - {$result['message']}");
            }
        }
    }
    
    return ['success' => $sent_count > 0, 'sent' => $sent_count];
}

// ============================================
// TEST SMS - Direct test
// ============================================

if (isset($_GET['test_sms'])) {
    header('Content-Type: application/json');
    $phone = $_GET['phone'] ?? '0712345678';
    $message = "TEST SMS from TZONE Hotel. If you receive this, SMS is working! Time: " . date('Y-m-d H:i:s');
    $result = sendSMS($phone, $message);
    echo json_encode($result);
    exit();
}

// ============================================
// END OF SMS FUNCTIONS
// ============================================

$user_id = $_SESSION['user_id'];
$error = '';

// Handle approval/rejection with reason
if (isset($_GET['id']) && isset($_GET['action'])) {
    $po_id = intval($_GET['id']);
    $action = $_GET['action'];
    $reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';
    
    if ($action == 'approve') {
        $status = 'approved';
        $action_message = "approved";
        $log_message = "Purchase order ID: $po_id - Approved";
    } elseif ($action == 'reject') {
        $status = 'rejected';
        $action_message = "rejected";
        $log_message = "Purchase order ID: $po_id - Rejected" . ($reason ? " - Reason: $reason" : "");
    } else {
        $error = "Invalid action!";
    }
    
    if (isset($status) && !$error) {
        $db->begin_transaction();
        
        try {
            // Update PO status
            $sql = "UPDATE purchase_orders SET status = ?, approved_by = ?, approved_at = NOW()";
            $params = [$status, $user_id];
            $types = "si";
            
            if ($action == 'reject' && $reason) {
                $sql .= ", rejection_reason = ?";
                $params[] = $reason;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $po_id;
            $types .= "i";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Get PO details for SMS - including supplier info
                $po_details_sql = "SELECT po.*, 
                                   s.company_name as supplier_name, 
                                   s.phone as supplier_phone,
                                   u.fullname as created_by_name 
                                   FROM purchase_orders po
                                   JOIN suppliers s ON po.supplier_id = s.id
                                   JOIN users u ON po.created_by = u.id
                                   WHERE po.id = ?";
                $po_details_stmt = $db->prepare($po_details_sql);
                $po_details_stmt->bind_param("i", $po_id);
                $po_details_stmt->execute();
                $po_data = $po_details_stmt->get_result()->fetch_assoc();
                
                if (!$po_data) {
                    throw new Exception("PO data not found!");
                }
                
                // Prepare PO data for SMS
                $sms_po_data = [
                    'id' => $po_data['id'],
                    'po_number' => $po_data['po_number'],
                    'supplier_id' => $po_data['supplier_id'],
                    'supplier_name' => $po_data['supplier_name'],
                    'supplier_phone' => $po_data['supplier_phone'],
                    'total_amount' => $po_data['total_amount'],
                    'created_by_name' => $po_data['created_by_name']
                ];
                
                error_log("=== PO $po_number - Sending SMS ===");
                error_log("Supplier: {$sms_po_data['supplier_name']} - Phone: {$sms_po_data['supplier_phone']}");
                
                // Send SMS based on action
                $supplier_result = ['success' => false];
                $storekeeper_result = ['success' => false];
                
                if ($action == 'approve') {
                    // Send to supplier ONLY
                    $supplier_result = sendPOStatusToSupplier($sms_po_data, 'approved');
                    // Also notify storekeeper
                    $storekeeper_result = sendPOStatusToStorekeeper($sms_po_data, 'approved');
                } elseif ($action == 'reject') {
                    // Send to supplier ONLY with reason
                    $supplier_result = sendPOStatusToSupplier($sms_po_data, 'rejected', $reason);
                    // Also notify storekeeper
                    $storekeeper_result = sendPOStatusToStorekeeper($sms_po_data, 'rejected', $reason);
                }
                
                error_log("=== SMS Summary ===");
                error_log("Supplier: " . ($supplier_result['success'] ? 'SENT' : 'FAILED - ' . ($supplier_result['message'] ?? 'Unknown')));
                error_log("Storekeeper: " . ($storekeeper_result['success'] ? 'SENT' : 'FAILED - ' . ($storekeeper_result['message'] ?? 'Unknown')));
                
                logActivity($user_id, 'Approve/Reject PO', $log_message);
                
                $db->commit();
                
                // Build toast message
                $toast_msg = "Purchase order $action_message successfully!";
                $toast_msg .= "<br>SMS: Supplier " . ($supplier_result['success'] ? 'Sent' : 'Failed');
                if ($storekeeper_result['success']) {
                    $toast_msg .= " | Storekeeper Sent";
                }
                
                $_SESSION['toast_message'] = $toast_msg;
                $_SESSION['toast_type'] = "success";
                header("Location: approve_po.php");
                exit();
            } else {
                throw new Exception("Error updating purchase order: " . $stmt->error);
            }
        } catch (Exception $e) {
            $db->rollback();
            error_log("PO Approval Error: " . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}

// Get pending POs
$sql = "SELECT po.*, s.company_name as supplier_name, 
        s.contact_person, s.phone as supplier_phone, s.email as supplier_email,
        u.fullname as created_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN users u ON po.created_by = u.id
        WHERE po.status = 'pending'
        ORDER BY po.created_at ASC";

$result = $db->query($sql);
$pending_orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get recently approved/rejected POs
$history_sql = "SELECT po.*, s.company_name as supplier_name, 
                u.fullname as created_by_name,
                a.fullname as approved_by_name
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                JOIN users u ON po.created_by = u.id
                LEFT JOIN users a ON po.approved_by = a.id
                WHERE po.status IN ('approved', 'rejected')
                AND po.approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY po.approved_at DESC
                LIMIT 20";

$history_result = $db->query($history_sql);
$recent_history = $history_result ? $history_result->fetch_all(MYSQLI_ASSOC) : [];

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-check-double"></i> Approve Purchase Orders</h1>
        <p>Review and approve purchase orders from procurement department</p>
    </div>
    
    <!-- SMS Status Card -->
    <div class="card" style="border-left: 4px solid #10B981; margin-bottom: 20px;">
        <div class="card-header" style="background: #F0FDF4;">
            <h3 style="color: #065F46;"><i class="fas fa-check-circle"></i> SMS Configuration - WORKING</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <small class="text-muted">API Key:</small>
                    <div><strong><?php echo substr($sms_api_key, 0, 10); ?>...</strong> ✅</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Sender ID:</small>
                    <div><strong><?php echo $sms_sender_id; ?></strong> ✅</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Status:</small>
                    <div><span style="color:#10B981;">✅ Using working API from parent_sms_results.php</span></div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="stats-summary">
        <div class="stat-item pending-stat">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-label">Pending Approvals</div>
                <div class="stat-number"><?php echo count($pending_orders); ?></div>
            </div>
        </div>
        <div class="stat-item value-stat">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Value Pending</div>
                <div class="stat-number">TZS <?php $total = 0; foreach($pending_orders as $order) { $total += $order['total_amount']; } echo number_format($total, 0); ?></div>
            </div>
        </div>
        <div class="stat-item history-stat">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div class="stat-info">
                <div class="stat-label">Processed (7 days)</div>
                <div class="stat-number"><?php echo count($recent_history); ?></div>
            </div>
        </div>
        <div class="stat-item supplier-stat">
            <div class="stat-icon"><i class="fas fa-truck"></i></div>
            <div class="stat-info">
                <div class="stat-label">Active Suppliers</div>
                <div class="stat-number">
                    <?php 
                        $supplier_sql = "SELECT COUNT(*) as count FROM suppliers WHERE status = 'active'";
                        $supplier_result = $db->query($supplier_sql);
                        $supplier_count = $supplier_result->fetch_assoc();
                        echo $supplier_count['count'];
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <i class="fas fa-hourglass-half"></i>
                <h3>Pending Approvals <span class="badge"><?php echo count($pending_orders); ?></span></h3>
            </div>
            <?php if(count($pending_orders) > 0): ?>
                <div class="header-actions">
                    <button onclick="openApproveAllModal()" class="btn-approve-all"><i class="fas fa-check-double"></i> Approve All</button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if(count($pending_orders) > 0): ?>
                <div class="approval-grid">
                    <?php foreach($pending_orders as $index => $order): ?>
                        <div class="approval-card pending-card" style="animation-delay: <?php echo $index * 0.05; ?>s">
                            <div class="approval-header">
                                <div class="po-number"><i class="fas fa-file-invoice"></i> <strong><?php echo htmlspecialchars($order['po_number']); ?></strong></div>
                                <div class="po-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($order['order_date'])); ?></div>
                            </div>
                            <div class="approval-body">
                                <div class="info-row"><span class="label"><i class="fas fa-building"></i> Supplier:</span><span class="value"><?php echo htmlspecialchars($order['supplier_name']); ?></span></div>
                                <div class="info-row"><span class="label"><i class="fas fa-user"></i> Contact Person:</span><span class="value"><?php echo htmlspecialchars($order['contact_person'] ?? 'N/A'); ?></span></div>
                                <div class="info-row"><span class="label"><i class="fas fa-phone"></i> Phone:</span><span class="value"><?php echo htmlspecialchars($order['supplier_phone'] ?? 'N/A'); ?></span></div>
                                <div class="info-row"><span class="label"><i class="fas fa-user-check"></i> Requested By:</span><span class="value"><?php echo htmlspecialchars($order['created_by_name']); ?></span></div>
                                <div class="info-row"><span class="label"><i class="fas fa-money-bill"></i> Total Amount:</span><span class="value amount">TZS <?php echo number_format($order['total_amount'], 0); ?></span></div>
                                <div class="info-row"><span class="label"><i class="fas fa-truck"></i> Expected Delivery:</span><span class="value"><?php echo !empty($order['expected_delivery']) ? date('d M Y', strtotime($order['expected_delivery'])) : 'Not specified'; ?></span></div>
                                <?php if(!empty($order['notes'])): ?>
                                    <div class="info-row notes"><span class="label"><i class="fas fa-sticky-note"></i> Notes:</span><span class="value"><?php echo htmlspecialchars(substr($order['notes'], 0, 150)); ?></span></div>
                                <?php endif; ?>
                            </div>
                            <div class="approval-actions">
                                <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="btn-secondary"><i class="fas fa-eye"></i> View Details</button>
                                <button onclick="approveOrder(<?php echo $order['id']; ?>)" class="btn-approve"><i class="fas fa-check"></i> Approve</button>
                                <button onclick="rejectOrder(<?php echo $order['id']; ?>)" class="btn-reject"><i class="fas fa-times"></i> Reject</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h4>No Pending Approvals</h4>
                    <p>All purchase orders have been processed! Check back later for new requests.</p>
                    <a href="../procurement/create_po.php" class="btn-create"><i class="fas fa-plus"></i> Create New PO</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if(count($recent_history) > 0): ?>
    <div class="card history-card">
        <div class="card-header">
            <div class="header-title"><i class="fas fa-history"></i><h3>Recent Activity (Last 7 Days)</h3></div>
            <a href="../procurement/view_po.php" class="btn-view-all"><i class="fas fa-arrow-right"></i> View All History</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="history-table">
                    <thead><tr><th>PO Number</th><th>Supplier</th><th>Amount</th><th>Status</th><th>Requested By</th><th>Processed By</th><th>Processed Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_history as $history): ?>
                            <tr class="history-row">
                                <td><strong><?php echo htmlspecialchars($history['po_number']); ?></strong><div class="history-date-small"><?php echo date('d/m/Y', strtotime($history['order_date'])); ?></div></td>
                                <td><?php echo htmlspecialchars($history['supplier_name']); ?></td>
                                <td class="amount">TZS <?php echo number_format($history['total_amount'], 0); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $history['status']; ?>">
                                        <i class="fas <?php echo $history['status'] == 'approved' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                    <?php if($history['status'] == 'rejected' && !empty($history['rejection_reason'])): ?>
                                        <div class="rejection-tooltip" title="<?php echo htmlspecialchars($history['rejection_reason']); ?>"><i class="fas fa-comment-dots"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($history['created_by_name']); ?></td>
                                <td><?php echo !empty($history['approved_by_name']) ? '<i class="fas fa-user-check"></i> ' . htmlspecialchars($history['approved_by_name']) : 'N/A'; ?></td>
                                <td><?php echo !empty($history['approved_at']) ? date('d M Y H:i', strtotime($history['approved_at'])) : 'N/A'; ?></td>
                                <td>
                                    <button onclick="viewOrderDetails(<?php echo $history['id']; ?>)" class="btn-icon" title="View Details"><i class="fas fa-eye"></i></button>
                                    <?php if($history['status'] == 'rejected' && !empty($history['rejection_reason'])): ?>
                                        <button onclick="showRejectionReason('<?php echo htmlspecialchars(addslashes($history['rejection_reason'])); ?>')" class="btn-icon reason-btn-small" title="View Rejection Reason"><i class="fas fa-comment"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Approve All Modal -->
<div id="approveAllModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-check-double"></i><h3>Approve All Pending Orders</h3></div>
            <button class="modal-btn close" onclick="closeApproveAllModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="approve-all-content">
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>You are about to approve <strong><?php echo count($pending_orders); ?></strong> pending purchase order(s).</p>
                    <p>Total value: <strong>TZS <?php $total_all = 0; foreach($pending_orders as $order) { $total_all += $order['total_amount']; } echo number_format($total_all, 0); ?></strong></p>
                </div>
                <p class="confirm-text">Are you sure you want to approve all pending orders?</p>
                <div class="modal-actions">
                    <button onclick="closeApproveAllModal()" class="btn-secondary">Cancel</button>
                    <button onclick="approveAllOrders()" class="btn-approve-all">Yes, Approve All</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-file-invoice"></i><h3>Purchase Order Details</h3></div>
            <div class="modal-actions">
                <button onclick="printOrder()" class="modal-btn print" title="Print"><i class="fas fa-print"></i></button>
                <button class="modal-btn close" onclick="closeOrderModal()">&times;</button>
            </div>
        </div>
        <div class="modal-body" id="orderModalBody"><div class="loading-spinner"><div class="spinner"></div><p>Loading order details...</p></div></div>
    </div>
</div>

<style>
    .main-content { padding: 20px; background: #F3F4F6; min-height: 100vh; }
    .page-header { margin-bottom: 25px; }
    .page-header h1 { font-size: 24px; color: #1E3A8A; margin: 0 0 5px 0; }
    .page-header p { color: #6B7280; margin: 0; }
    .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    .alert-error { background: #FEE2E2; color: #991B1B; border-left: 4px solid #EF4444; }
    .stats-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .stat-item { background: white; padding: 20px; border-radius: 16px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.3s; }
    .stat-item:hover { transform: translateY(-2px); }
    .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .pending-stat .stat-icon { background: #FEF3C7; color: #F59E0B; }
    .value-stat .stat-icon { background: #DBEAFE; color: #1E3A8A; }
    .history-stat .stat-icon { background: #D1FAE5; color: #10B981; }
    .supplier-stat .stat-icon { background: #E0E7FF; color: #4F46E5; }
    .stat-info .stat-label { font-size: 12px; color: #6B7280; margin-bottom: 5px; }
    .stat-info .stat-number { font-size: 24px; font-weight: 700; color: #1F2937; }
    .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 25px; }
    .card-header { padding: 20px 24px; background: #F9FAFB; border-bottom: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    .header-title { display: flex; align-items: center; gap: 10px; }
    .header-title i { font-size: 20px; color: #1E3A8A; }
    .header-title h3 { margin: 0; font-size: 18px; color: #1E3A8A; }
    .badge { background: #E5E7EB; padding: 2px 8px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-left: 8px; }
    .btn-approve-all { background: #10B981; color: white; padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; transition: all 0.3s; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
    .btn-approve-all:hover { background: #059669; transform: translateY(-1px); }
    .btn-view-all { background: #1E3A8A; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; }
    .btn-view-all:hover { background: #2563EB; }
    .btn-create { background: #FF6B6B; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
    .btn-create:hover { background: #e55a5a; transform: translateY(-1px); }
    .approval-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; padding: 24px; }
    .approval-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #E5E7EB; transition: all 0.3s; animation: fadeInUp 0.3s ease backwards; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .approval-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .pending-card { border-left: 4px solid #F59E0B; }
    .approval-header { background: #F9FAFB; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E5E7EB; }
    .po-number { font-size: 16px; font-weight: 600; color: #1E3A8A; display: flex; align-items: center; gap: 8px; }
    .po-date { font-size: 12px; color: #6B7280; }
    .approval-body { padding: 20px; }
    .info-row { display: flex; margin-bottom: 12px; font-size: 13px; }
    .info-row .label { width: 120px; font-weight: 600; color: #6B7280; display: flex; align-items: center; gap: 6px; }
    .info-row .value { flex: 1; color: #374151; }
    .info-row .value.amount { font-weight: 700; color: #1E3A8A; font-size: 15px; }
    .approval-actions { padding: 15px 20px; background: #F9FAFB; border-top: 1px solid #E5E7EB; display: flex; gap: 10px; }
    .btn-secondary { flex: 1; background: #F3F4F6; color: #374151; padding: 10px 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-secondary:hover { background: #E5E7EB; }
    .btn-approve { flex: 1; background: #10B981; color: white; padding: 10px 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-approve:hover { background: #059669; transform: translateY(-1px); }
    .btn-reject { flex: 1; background: #EF4444; color: white; padding: 10px 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-reject:hover { background: #DC2626; transform: translateY(-1px); }
    .history-table { width: 100%; border-collapse: collapse; }
    .history-table th, .history-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #E5E7EB; font-size: 13px; }
    .history-table th { background: #F9FAFB; font-weight: 600; }
    .history-row:hover { background: #F9FAFB; }
    .history-table .amount { font-weight: 600; color: #1E3A8A; }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .status-approved { background: #D1FAE5; color: #065F46; }
    .status-rejected { background: #FEE2E2; color: #991B1B; }
    .btn-icon { background: #F3F4F6; border: none; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; color: #6B7280; }
    .btn-icon:hover { background: #DBEAFE; color: #1E3A8A; }
    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state i { font-size: 64px; color: #10B981; margin-bottom: 20px; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
    .modal-content { background: white; border-radius: 20px; animation: modalSlideIn 0.3s ease; }
    @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-50px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .modal-large { width: 90%; max-width: 850px; max-height: 85vh; overflow: hidden; }
    .modal-small { width: 90%; max-width: 450px; }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center; background: #F9FAFB; border-radius: 20px 20px 0 0; }
    .modal-title { display: flex; align-items: center; gap: 10px; }
    .modal-title i { font-size: 20px; color: #1E3A8A; }
    .modal-body { padding: 24px; overflow-y: auto; }
    .modal-large .modal-body { max-height: calc(85vh - 70px); }
    .loading-spinner { text-align: center; padding: 40px; }
    .spinner { width: 40px; height: 40px; border: 3px solid #E5E7EB; border-top-color: #1E3A8A; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .po-header { background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; }
    .po-header-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .po-header-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(30,58,138,0.1); }
    .po-header-label { font-size: 12px; color: #6B7280; }
    .po-header-value { font-weight: 600; color: #1F2937; }
    .po-header-value.amount { color: #1E3A8A; font-size: 18px; }
    .rejection-box { margin-top: 15px; padding: 15px; background: #FEE2E2; border-radius: 8px; border-left: 4px solid #EF4444; }
    .po-items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .po-items-table th, .po-items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #E5E7EB; }
    .po-items-table th { background: #F3F4F6; font-weight: 600; }
    @media (max-width: 768px) { .stats-summary { grid-template-columns: repeat(2, 1fr); } .approval-grid { grid-template-columns: 1fr; padding: 16px; } .info-row { flex-direction: column; } .info-row .label { width: auto; margin-bottom: 4px; } .approval-actions { flex-direction: column; } .btn-approve, .btn-reject, .btn-secondary { width: 100%; } .po-header-grid { grid-template-columns: 1fr; } }
    @media (max-width: 480px) { .stats-summary { grid-template-columns: 1fr; } .card-header { flex-direction: column; align-items: flex-start; } }
    @media print { .sidebar, .top-header, .stats-summary, .approval-actions, .btn-approve-all, .btn-view-all, .modal-actions { display: none !important; } }
</style>

<script>
function viewOrderDetails(id) {
    const modalBody = document.getElementById('orderModalBody');
    modalBody.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Loading order details...</p></div>';
    document.getElementById('orderModal').style.display = 'flex';
    
    fetch(`../procurement/get_po_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                modalBody.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>${data.error}</p></div>`;
                return;
            }
            
            let itemsHtml = '';
            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    itemsHtml += `<tr><td><strong>${escapeHtml(item.item_name)}</strong> ${item.unit ? '(' + item.unit + ')' : ''}</td><td>${item.quantity} units</td><td>TZS ${parseFloat(item.unit_price).toLocaleString()}</td><td>TZS ${parseFloat(item.total_price).toLocaleString()}</td></tr>`;
                });
            } else {
                itemsHtml = '<tr><td colspan="4" class="text-center">No items found</td></tr>';
            }
            
            const rejectionReasonHtml = data.po.rejection_reason ? `<div class="rejection-box"><div class="rejection-title"><i class="fas fa-exclamation-triangle"></i> Rejection Reason</div><div class="rejection-content">${escapeHtml(data.po.rejection_reason)}</div></div>` : '';
            
            modalBody.innerHTML = `
                <div>
                    <div class="po-header">
                        <div class="po-header-grid">
                            <div>
                                <div class="po-header-item"><span class="po-header-label">PO Number</span><span class="po-header-value">${escapeHtml(data.po.po_number)}</span></div>
                                <div class="po-header-item"><span class="po-header-label">Order Date</span><span class="po-header-value">${data.po.order_date}</span></div>
                                <div class="po-header-item"><span class="po-header-label">Expected Delivery</span><span class="po-header-value">${data.po.expected_delivery || 'Not specified'}</span></div>
                            </div>
                            <div>
                                <div class="po-header-item"><span class="po-header-label">Supplier</span><span class="po-header-value">${escapeHtml(data.po.supplier_name)}</span></div>
                                <div class="po-header-item"><span class="po-header-label">Contact Person</span><span class="po-header-value">${escapeHtml(data.po.contact_person || 'N/A')}</span></div>
                                <div class="po-header-item"><span class="po-header-label">Total Amount</span><span class="po-header-value amount">TZS ${parseFloat(data.po.total_amount).toLocaleString()}</span></div>
                            </div>
                        </div>
                        ${data.po.notes ? `<div style="margin-top:15px;padding-top:15px;border-top:1px solid rgba(30,58,138,0.1);"><span class="po-header-label">Notes</span><p style="margin:5px 0 0;">${escapeHtml(data.po.notes)}</p></div>` : ''}
                        ${rejectionReasonHtml}
                    </div>
                    <h4 style="margin:20px 0 15px;">Order Items</h4>
                    <div class="table-responsive">
                        <table class="po-items-table"><thead><tr><th>Item</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>${itemsHtml}<tr style="background:#F9FAFB;font-weight:bold;"><td colspan="3" style="text-align:right;">Grand Total:</td><td>TZS ${parseFloat(data.po.total_amount).toLocaleString()}</td></tr></tbody></table>
                    </div>
                </div>
            `;
        })
        .catch(error => { modalBody.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading order details.</p></div>'; });
}

function approveOrder(id) {
    if (confirm('Are you sure you want to APPROVE this purchase order?\n\nSMS notification will be sent to the supplier and storekeeper.')) {
        window.location.href = `approve_po.php?id=${id}&action=approve`;
    }
}

function rejectOrder(id) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason !== null && reason.trim() !== '') {
        if (confirm(`Are you sure you want to REJECT this purchase order?\n\nSMS notification will be sent to the supplier with the reason.\n\nReason: ${reason}`)) {
            window.location.href = `approve_po.php?id=${id}&action=reject&reason=${encodeURIComponent(reason)}`;
        }
    } else if (reason !== null) {
        alert('Please provide a reason for rejection.');
    }
}

function openApproveAllModal() { document.getElementById('approveAllModal').style.display = 'flex'; }
function closeApproveAllModal() { document.getElementById('approveAllModal').style.display = 'none'; }
function approveAllOrders() {
    if (confirm(`Are you sure you want to approve ALL ${<?php echo count($pending_orders); ?>} pending purchase orders?\n\nSMS notifications will be sent for each approved order.`)) {
        window.location.href = `approve_all_po.php`;
    }
}
function showRejectionReason(reason) { alert('Rejection Reason:\n\n' + reason); }
function printOrder() { window.print(); }
function closeOrderModal() { document.getElementById('orderModal').style.display = 'none'; }
function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
window.onclick = function(event) { if (event.target === document.getElementById('orderModal')) closeOrderModal(); if (event.target === document.getElementById('approveAllModal')) closeApproveAllModal(); }
</script>

<?php include '../templates/footer.php'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

session_start();

// Check if department user is logged in
if (!isset($_SESSION['department_user_id'])) {
    header("Location: login.php");
    exit();
}

$department_user_id = $_SESSION['department_user_id'];
$department_id = $_SESSION['department_id'];
$department_name = $_SESSION['department_name'];

// Get statistics for this department
$stats = [];

// Pending requests for this department
$pending_sql = "SELECT COUNT(*) as count FROM stock_requests WHERE department_id = ? AND status = 'pending'";
$pending_stmt = $db->prepare($pending_sql);
$pending_stmt->bind_param("i", $department_id);
$pending_stmt->execute();
$stats['pending'] = $pending_stmt->get_result()->fetch_assoc()['count'];

// Confirmed requests this month
$confirmed_sql = "SELECT COUNT(*) as count FROM stock_requests WHERE department_id = ? AND status = 'confirmed' AND MONTH(confirmed_at) = MONTH(NOW())";
$confirmed_stmt = $db->prepare($confirmed_sql);
$confirmed_stmt->bind_param("i", $department_id);
$confirmed_stmt->execute();
$stats['confirmed'] = $confirmed_stmt->get_result()->fetch_assoc()['count'];

// Get recent requests
$recent_sql = "SELECT sr.*, i.item_name, i.unit, u.fullname as requester_name
               FROM stock_requests sr
               JOIN inventory_items i ON sr.item_id = i.id
               JOIN users u ON sr.requested_by = u.id
               WHERE sr.department_id = ?
               ORDER BY sr.created_at DESC LIMIT 10";
$recent_stmt = $db->prepare($recent_sql);
$recent_stmt->bind_param("i", $department_id);
$recent_stmt->execute();
$recent_requests = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1><i class="fas fa-chalkboard-user"></i> Department Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['department_user_name']); ?>!</p>
        </div>
        <div class="department-badge">
            <i class="fas fa-building"></i>
            <?php echo htmlspecialchars($department_name); ?>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Pending Confirmations</span>
                <span class="stat-value"><?php echo $stats['pending']; ?></span>
            </div>
        </div>
        <div class="stat-card confirmed">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Confirmed This Month</span>
                <span class="stat-value"><?php echo $stats['confirmed']; ?></span>
            </div>
        </div>
        <div class="stat-card scanner">
            <div class="stat-icon">
                <i class="fas fa-qrcode"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">QR Scanner</span>
                <span class="stat-value" style="font-size: 14px;">Click to scan</span>
            </div>
        </div>
    </div>
    
    <!-- QR Scanner Section -->
    <div class="scanner-section" id="scannerSection" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-qrcode"></i> Scan QR Code</h3>
                <button class="close-scanner" onclick="closeScanner()">&times;</button>
            </div>
            <div class="card-body">
                <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
                <p class="scanner-instruction">Position the QR code in front of the camera</p>
            </div>
        </div>
    </div>
    
    <!-- Recent Requests -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Recent Stock Requests</h3>
            <span class="badge"><?php echo count($recent_requests); ?> requests</span>
        </div>
        <div class="card-body">
            <?php if(count($recent_requests) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Request Code</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Requested By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_requests as $request): ?>
                                <tr class="request-row" data-status="<?php echo $request['status']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['request_code']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['item_name']); ?> (<?php echo $request['unit']; ?>)</td
                                    <td><?php echo $request['quantity']; ?></td
                                    <td><?php echo htmlspecialchars($request['requester_name']); ?></td
                                    <td><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></td
                                    <td>
                                        <?php if($request['status'] == 'pending'): ?>
                                            <span class="status-badge pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php elseif($request['status'] == 'confirmed'): ?>
                                            <span class="status-badge confirmed">
                                                <i class="fas fa-check-circle"></i> Confirmed
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge cancelled">
                                                <i class="fas fa-times-circle"></i> <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                     </td>
                                    <td>
                                        <?php if($request['status'] == 'pending'): ?>
                                            <button onclick="confirmRequest(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['request_code']); ?>')" class="btn-confirm">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                        <?php else: ?>
                                            <span class="no-action">—</span>
                                        <?php endif; ?>
                                     </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>No stock requests have been made for your department yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Instructions Card -->
    <div class="card instructions">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> How to Confirm Stock Receipt</h3>
        </div>
        <div class="card-body">
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <strong>Receive the QR Code</strong>
                        <p>The storekeeper will provide you with a QR code for the requested items</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <strong>Scan the QR Code</strong>
                        <p>Click the "Scan QR Code" button above and position the QR code in front of your camera</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <strong>Confirm Receipt</strong>
                        <p>Verify the items and quantity, then confirm to complete the stock out process</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <strong>Stock Automatically Updated</strong>
                        <p>Once confirmed, inventory stock will be automatically deducted</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .dashboard {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .dashboard-header h1 {
        color: #1E3A8A;
        margin-bottom: 5px;
    }
    
    .dashboard-header p {
        color: #6B7280;
    }
    
    .department-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.3s;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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
    
    .stat-card.pending .stat-icon {
        background: #FEF3C7;
        color: #F59E0B;
    }
    
    .stat-card.confirmed .stat-icon {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .stat-card.scanner .stat-icon {
        background: #DBEAFE;
        color: #1E3A8A;
    }
    
    .stat-info .stat-label {
        font-size: 13px;
        color: #6B7280;
        display: block;
    }
    
    .stat-info .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #1F2937;
        display: block;
    }
    
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
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .badge {
        background: #E5E7EB;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        color: #374151;
    }
    
    .card-body {
        padding: 24px;
    }
    
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
        font-size: 12px;
        color: #374151;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-badge.pending {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .status-badge.confirmed {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-badge.cancelled {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .btn-confirm {
        background: #10B981;
        color: white;
        border: none;
        padding: 6px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .btn-confirm:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .no-action {
        color: #9CA3AF;
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
    
    .scanner-section {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .scanner-section .card {
        max-width: 600px;
        width: 100%;
        margin: 0;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .close-scanner {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
    }
    
    .close-scanner:hover {
        color: #EF4444;
    }
    
    .scanner-instruction {
        text-align: center;
        margin-top: 15px;
        font-size: 13px;
        color: #6B7280;
    }
    
    .steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .step {
        display: flex;
        gap: 15px;
        align-items: flex-start;
    }
    
    .step-number {
        width: 36px;
        height: 36px;
        background: #1E3A8A;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        flex-shrink: 0;
    }
    
    .step-content p {
        margin: 5px 0 0;
        font-size: 12px;
        color: #6B7280;
    }
    
    @media (max-width: 768px) {
        .dashboard {
            padding: 15px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .steps {
            grid-template-columns: 1fr;
        }
        
        .data-table th,
        .data-table td {
            padding: 8px;
            font-size: 12px;
        }
    }
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    let html5QrCode;
    const scannerSection = document.getElementById('scannerSection');
    
    // Open scanner
    document.querySelector('.stat-card.scanner').addEventListener('click', function() {
        openScanner();
    });
    
    function openScanner() {
        scannerSection.style.display = 'flex';
        
        html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure);
    }
    
    function closeScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
        }
        scannerSection.style.display = 'none';
    }
    
    function onScanSuccess(decodedText, decodedResult) {
        // Stop scanning
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
        }
        
        // Process the QR code data
        processQRCode(decodedText);
    }
    
    function onScanFailure(error) {
        // Silent failure, keep scanning
    }
    
    function processQRCode(qrData) {
        try {
            const data = JSON.parse(atob(qrData));
            
            // Send to server for confirmation
            fetch('confirm_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_code: data.request_code,
                    department_user_id: <?php echo $department_user_id; ?>
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✓ Stock confirmed successfully!\n\nItem: ' + result.item_name + '\nQuantity: ' + result.quantity);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('Error processing request: ' + error);
            });
            
        } catch(e) {
            alert('Invalid QR code format. Please scan a valid stock request QR code.');
        }
        
        closeScanner();
    }
    
    function confirmRequest(requestId, requestCode) {
        if (confirm(`Confirm stock receipt for request ${requestCode}?\n\nThis will deduct stock from inventory.`)) {
            fetch('confirm_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    department_user_id: <?php echo $department_user_id; ?>
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✓ Stock confirmed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('Error processing request: ' + error);
            });
        }
    }
    
    // Close scanner on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && scannerSection.style.display === 'flex') {
            closeScanner();
        }
    });
</script>

<?php include 'footer.php'; ?>
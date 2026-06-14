<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

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

// Get recent requests (last 10)
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
        <div class="stat-card scanner" id="openScannerBtn">
            <div class="stat-icon">
                <i class="fas fa-qrcode"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">QR Scanner</span>
                <span class="stat-value" style="font-size: 14px;">Click to scan</span>
            </div>
        </div>
    </div>
    
    <!-- QR Scanner Modal Section -->
    <div class="scanner-modal" id="scannerModal" style="display: none;">
        <div class="scanner-modal-content">
            <div class="scanner-modal-header">
                <h3><i class="fas fa-qrcode"></i> Scan QR Code</h3>
                <button class="close-scanner" onclick="closeScanner()">&times;</button>
            </div>
            <div class="scanner-modal-body">
                <!-- Camera Selection -->
                <div class="camera-selector">
                    <button id="modalToggleCameraBtn" class="btn-camera-switch">
                        <i class="fas fa-sync-alt"></i> Switch Camera
                    </button>
                    <span id="modalCameraStatus" class="camera-status">Back Camera</span>
                </div>
                
                <!-- QR Reader Container -->
                <div id="modalReader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
                
                <div class="scanner-controls">
                    <button id="modalStopScannerBtn" class="btn-secondary">
                        <i class="fas fa-stop"></i> Stop Camera
                    </button>
                </div>
                
                <!-- Flashlight Control (Mobile) -->
                <div class="flashlight-control" style="display: none;" id="modalFlashlightControl">
                    <button id="modalToggleFlashlight" class="btn-flashlight">
                        <i class="fas fa-lightbulb"></i> Toggle Flashlight
                    </button>
                </div>
                
                <p class="scanner-instruction">
                    <i class="fas fa-info-circle"></i>
                    Position the QR code in front of the camera
                </p>
            </div>
        </div>
    </div>
    
    <!-- Recent Requests Table -->
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
                                    <td data-label="Request Code">
                                        <strong><?php echo htmlspecialchars($request['request_code']); ?></strong>
                                    </td>
                                    <td data-label="Item">
                                        <?php echo htmlspecialchars($request['item_name']); ?> 
                                        <small>(<?php echo $request['unit']; ?>)</small>
                                    </td>
                                    <td data-label="Quantity">
                                        <span class="quantity-badge"><?php echo $request['quantity']; ?></span>
                                    </td>
                                    <td data-label="Requested By">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($request['requester_name']); ?>
                                    </td>
                                    <td data-label="Date">
                                        <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($request['created_at'])); ?>
                                        <br>
                                        <small><?php echo date('H:i', strtotime($request['created_at'])); ?></small>
                                    </td>
                                    <td data-label="Status">
                                        <?php if($request['status'] == 'pending'): ?>
                                            <span class="status-badge pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php elseif($request['status'] == 'confirmed'): ?>
                                            <span class="status-badge confirmed">
                                                <i class="fas fa-check-circle"></i> Confirmed
                                            </span>
                                        <?php elseif($request['status'] == 'cancelled'): ?>
                                            <span class="status-badge cancelled">
                                                <i class="fas fa-times-circle"></i> Cancelled
                                            </span>
                                        <?php elseif($request['status'] == 'rejected'): ?>
                                            <span class="status-badge rejected">
                                                <i class="fas fa-ban"></i> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Action">
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
                        <p>Click the "QR Scanner" card above and position the QR code in front of your camera</p>
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
        font-size: 28px;
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
        transition: transform 0.3s, box-shadow 0.3s;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
        font-size: 18px;
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
        -webkit-overflow-scrolling: touch;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }
    
    .data-table th,
    .data-table td {
        padding: 14px 12px;
        text-align: left;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: middle;
    }
    
    .data-table th {
        background: #F9FAFB;
        font-weight: 600;
        font-size: 13px;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table tbody tr:hover {
        background: #F9FAFB;
    }
    
    .quantity-badge {
        display: inline-block;
        background: #EFF6FF;
        color: #1E3A8A;
        padding: 4px 10px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
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
    
    .status-badge.rejected {
        background: #FFE4E6;
        color: #BE123C;
    }
    
    .btn-confirm {
        background: #10B981;
        color: white;
        border: none;
        padding: 7px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .btn-confirm:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .btn-confirm:active {
        transform: translateY(0);
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
    
    /* Scanner Modal Styles */
    .scanner-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.9);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .scanner-modal-content {
        background: white;
        border-radius: 20px;
        max-width: 650px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .scanner-modal-header {
        padding: 20px 24px;
        background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 20px 20px 0 0;
    }
    
    .scanner-modal-header h3 {
        margin: 0;
        font-size: 20px;
    }
    
    .close-scanner {
        background: none;
        border: none;
        font-size: 32px;
        cursor: pointer;
        color: white;
        transition: color 0.3s;
        line-height: 1;
    }
    
    .close-scanner:hover {
        color: #FEE2E2;
    }
    
    .scanner-modal-body {
        padding: 24px;
        text-align: center;
    }
    
    .camera-selector {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 10px;
        background: #F3F4F6;
        border-radius: 12px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .btn-camera-switch {
        background: #1E3A8A;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-camera-switch:hover {
        background: #3B82F6;
        transform: scale(1.02);
    }
    
    .btn-camera-switch:active {
        transform: scale(0.98);
    }
    
    .camera-status {
        background: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        color: #1E3A8A;
        border: 1px solid #E5E7EB;
    }
    
    .scanner-controls {
        margin-top: 20px;
        display: flex;
        gap: 15px;
        justify-content: center;
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .flashlight-control {
        margin-top: 15px;
    }
    
    .btn-flashlight {
        background: #F59E0B;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-flashlight:hover {
        background: #D97706;
    }
    
    .scanner-instruction {
        margin-top: 20px;
        font-size: 13px;
        color: #6B7280;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    /* QR Reader Customization */
    #modalReader {
        border-radius: 12px;
        overflow: hidden;
        background: #000;
    }
    
    #modalReader video {
        width: 100% !important;
        height: auto !important;
        border-radius: 12px;
    }
    
    /* Steps */
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
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .dashboard {
            padding: 15px;
        }
        
        .dashboard-header h1 {
            font-size: 22px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .stat-card {
            padding: 20px;
        }
        
        .card-header {
            padding: 15px 18px;
        }
        
        .card-body {
            padding: 18px;
        }
        
        .data-table {
            min-width: 500px;
        }
        
        .data-table th,
        .data-table td {
            padding: 10px 8px;
            font-size: 12px;
        }
        
        .steps {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .scanner-modal {
            padding: 10px;
        }
        
        .scanner-modal-header {
            padding: 15px 20px;
        }
        
        .scanner-modal-body {
            padding: 20px;
        }
        
        .camera-selector {
            flex-direction: column;
        }
        
        .btn-camera-switch {
            width: 100%;
            justify-content: center;
        }
        
        .scanner-controls {
            flex-direction: column;
        }
        
        .btn-secondary {
            width: 100%;
            justify-content: center;
        }
    }
    
    /* Small phones */
    @media (max-width: 480px) {
        .data-table td::before {
            content: attr(data-label);
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
            color: #374151;
        }
        
        .data-table td {
            display: block;
            text-align: right;
            padding-left: 50%;
            position: relative;
        }
        
        .data-table td::before {
            position: absolute;
            left: 10px;
            width: 45%;
            text-align: left;
        }
        
        .data-table thead {
            display: none;
        }
        
        .data-table tbody tr {
            display: block;
            margin-bottom: 15px;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 10px;
        }
        
        .data-table td {
            border-bottom: 1px solid #F3F4F6;
        }
        
        .data-table td:last-child {
            border-bottom: none;
        }
    }
    
    /* Touch-friendly buttons */
    @media (max-width: 768px) {
        .btn-confirm,
        .btn-camera-switch,
        .btn-secondary,
        .btn-flashlight {
            min-height: 44px;
        }
    }
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    // Scanner variables
    let modalHtml5QrCode;
    let isModalScanning = false;
    let modalAvailableCameras = [];
    let modalCurrentCameraIndex = 0;
    
    // DOM Elements
    const scannerModal = document.getElementById('scannerModal');
    const openScannerBtn = document.getElementById('openScannerBtn');
    const modalToggleCameraBtn = document.getElementById('modalToggleCameraBtn');
    const modalCameraStatus = document.getElementById('modalCameraStatus');
    const modalStopScannerBtn = document.getElementById('modalStopScannerBtn');
    const modalFlashlightControl = document.getElementById('modalFlashlightControl');
    const modalToggleFlashlight = document.getElementById('modalToggleFlashlight');
    
    // Check if running on mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Open scanner modal
    openScannerBtn.addEventListener('click', function() {
        openScanner();
    });
    
    function openScanner() {
        scannerModal.style.display = 'flex';
        startModalScanner();
    }
    
    function closeScanner() {
        if (modalHtml5QrCode && modalHtml5QrCode.isScanning) {
            modalHtml5QrCode.stop();
        }
        isModalScanning = false;
        scannerModal.style.display = 'none';
    }
    
    // Get available cameras
    async function getModalAvailableCameras() {
        try {
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length) {
                modalAvailableCameras = devices;
                return devices;
            }
            return [];
        } catch (err) {
            console.error('Error getting cameras:', err);
            return [];
        }
    }
    
    // Start modal scanner
    async function startModalScanner() {
        // Check camera permissions
        try {
            await navigator.mediaDevices.getUserMedia({ video: true });
        } catch (err) {
            alert('Camera access denied. Please allow camera permissions.');
            closeScanner();
            return;
        }
        
        // Get available cameras
        await getModalAvailableCameras();
        
        // Find back camera
        let backCamera = null;
        if (modalAvailableCameras.length > 0) {
            backCamera = modalAvailableCameras.find(camera => 
                camera.label.toLowerCase().includes('back') || 
                camera.label.toLowerCase().includes('environment') ||
                camera.label.toLowerCase().includes('rear')
            );
            
            if (!backCamera) {
                backCamera = modalAvailableCameras[0];
            }
            modalCurrentCameraIndex = modalAvailableCameras.findIndex(c => c.id === backCamera.id);
        }
        
        // Clear reader element
        const readerElement = document.getElementById('modalReader');
        if (readerElement) {
            readerElement.innerHTML = '';
        }
        
        // Create scanner
        modalHtml5QrCode = new Html5Qrcode("modalReader");
        
        const config = { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };
        
        try {
            const cameraToUse = backCamera ? backCamera.id : { facingMode: "environment" };
            await modalHtml5QrCode.start(cameraToUse, config, onModalScanSuccess, onModalScanFailure);
            isModalScanning = true;
            
            // Show flashlight control for mobile
            if (isMobile) {
                modalFlashlightControl.style.display = 'block';
            }
            
            // Update camera status
            if (backCamera && modalAvailableCameras.length > 0) {
                const isBackCamera = backCamera.label.toLowerCase().includes('back') || 
                                   backCamera.label.toLowerCase().includes('environment') ||
                                   modalCurrentCameraIndex === 0;
                modalCameraStatus.textContent = isBackCamera ? 'Back Camera' : 'Front Camera';
            }
        } catch (err) {
            console.error('Error starting camera:', err);
            alert('Unable to start camera. Please check permissions.');
            closeScanner();
        }
    }
    
    // Toggle camera in modal
    async function toggleModalCamera() {
        if (!isModalScanning) {
            alert('Please start the scanner first');
            return;
        }
        
        if (modalAvailableCameras.length <= 1) {
            alert('Only one camera available on this device');
            return;
        }
        
        modalCurrentCameraIndex = (modalCurrentCameraIndex + 1) % modalAvailableCameras.length;
        const selectedCamera = modalAvailableCameras[modalCurrentCameraIndex];
        
        if (selectedCamera) {
            modalCameraStatus.textContent = 'Switching...';
            
            // Stop current scanner
            if (modalHtml5QrCode && modalHtml5QrCode.isScanning) {
                await modalHtml5QrCode.stop();
            }
            
            // Clear reader
            const readerElement = document.getElementById('modalReader');
            if (readerElement) {
                readerElement.innerHTML = '';
            }
            
            // Restart with new camera
            modalHtml5QrCode = new Html5Qrcode("modalReader");
            const config = { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };
            
            try {
                await modalHtml5QrCode.start(selectedCamera.id, config, onModalScanSuccess, onModalScanFailure);
                isModalScanning = true;
                
                const isBackCamera = selectedCamera.label.toLowerCase().includes('back') || 
                                   selectedCamera.label.toLowerCase().includes('environment') ||
                                   modalCurrentCameraIndex === 0;
                modalCameraStatus.textContent = isBackCamera ? 'Back Camera' : 'Front Camera';
            } catch (err) {
                console.error('Error switching camera:', err);
                alert('Error switching camera');
            }
        }
    }
    
    // Flashlight control
    let modalFlashlightOn = false;
    let modalVideoTrack = null;
    
    async function toggleModalFlashlight() {
        if (!modalHtml5QrCode || !modalHtml5QrCode.isScanning) {
            alert('Scanner must be active to use flashlight');
            return;
        }
        
        try {
            const videoElement = document.querySelector('#modalReader video');
            if (videoElement && videoElement.srcObject) {
                const stream = videoElement.srcObject;
                modalVideoTrack = stream.getVideoTracks()[0];
                
                if (modalVideoTrack && modalVideoTrack.getCapabilities().torch) {
                    modalFlashlightOn = !modalFlashlightOn;
                    await modalVideoTrack.applyConstraints({
                        advanced: [{ torch: modalFlashlightOn }]
                    });
                    
                    modalToggleFlashlight.innerHTML = modalFlashlightOn ? 
                        '<i class="fas fa-lightbulb"></i> Flashlight ON' : 
                        '<i class="fas fa-lightbulb"></i> Toggle Flashlight';
                    modalToggleFlashlight.style.background = modalFlashlightOn ? '#10B981' : '#F59E0B';
                } else {
                    alert('Flashlight not supported on this device/camera');
                }
            }
        } catch (err) {
            console.error('Flashlight error:', err);
            alert('Flashlight feature not available');
        }
    }
    
    function onModalScanSuccess(decodedText, decodedResult) {
        // Stop scanning
        if (modalHtml5QrCode && modalHtml5QrCode.isScanning) {
            modalHtml5QrCode.stop();
            isModalScanning = false;
        }
        
        // Process QR code
        processQRCode(decodedText);
    }
    
    function onModalScanFailure(error) {
        // Silent failure
    }
    
    function processQRCode(qrData) {
        try {
            let requestCode = qrData;
            
            // Try to parse as JSON
            try {
                const data = JSON.parse(atob(qrData));
                requestCode = data.request_code || qrData;
            } catch (e1) {
                try {
                    const data = JSON.parse(qrData);
                    requestCode = data.request_code || qrData;
                } catch (e2) {
                    requestCode = qrData;
                }
            }
            
            // Confirm the request
            confirmRequestByCode(requestCode);
            
        } catch(e) {
            alert('Invalid QR code format. Please scan a valid stock request QR code.');
            closeScanner();
            location.reload();
        }
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
                    alert('✓ Stock confirmed successfully!\n\nItem: ' + result.item_name + '\nQuantity: ' + result.quantity);
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
    
    function confirmRequestByCode(requestCode) {
        if (confirm(`Confirm stock receipt for request ${requestCode}?\n\nThis will deduct stock from inventory.`)) {
            fetch('confirm_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_code: requestCode,
                    department_user_id: <?php echo $department_user_id; ?>
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✓ Stock confirmed successfully!\n\nItem: ' + result.item_name + '\nQuantity: ' + result.quantity);
                    closeScanner();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                    closeScanner();
                    location.reload();
                }
            })
            .catch(error => {
                alert('Error processing request: ' + error);
                closeScanner();
            });
        } else {
            closeScanner();
        }
    }
    
    // Event listeners for modal
    if (modalToggleCameraBtn) {
        modalToggleCameraBtn.addEventListener('click', toggleModalCamera);
    }
    
    if (modalStopScannerBtn) {
        modalStopScannerBtn.addEventListener('click', function() {
            closeScanner();
        });
    }
    
    if (modalToggleFlashlight) {
        modalToggleFlashlight.addEventListener('click', toggleModalFlashlight);
    }
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && scannerModal.style.display === 'flex') {
            closeScanner();
        }
    });
    
    // Close modal when clicking outside
    scannerModal.addEventListener('click', function(e) {
        if (e.target === scannerModal) {
            closeScanner();
        }
    });
    
    // Manual confirm function for table buttons
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
                    alert('✓ Stock confirmed successfully!\n\nItem: ' + result.item_name + '\nQuantity: ' + result.quantity);
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
  // Mobile Sidebar Functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');
        
        function openSidebar() {
            mobileSidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            mobileSidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', openSidebar);
        }
        
        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', closeSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // Profile Dropdown
        const profileDropdownBtn = document.getElementById('profileDropdownBtn');
        const profileDropdownContent = document.getElementById('profileDropdownContent');
        
        if (profileDropdownBtn) {
            profileDropdownBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdownContent.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (profileDropdownBtn && profileDropdownContent) {
                    if (!profileDropdownBtn.contains(e.target) && !profileDropdownContent.contains(e.target)) {
                        profileDropdownContent.classList.remove('show');
                    }
                }
            });
        }
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });
        
        // Add active class to current nav item based on URL
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.department-nav a, .sidebar-menu a').forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage || (currentPage === '' && href === 'dashboard.php')) {
                link.classList.add('active');
            }
        });
    </script>

<?php include '../templates/footer.php'; ?>
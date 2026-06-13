<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper']);

$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($request_id <= 0) {
    header("Location: stock_out_request.php");
    exit();
}

// Get request details
$sql = "SELECT sr.*, i.item_name, i.unit, d.department_name, u.fullname as requester_name
        FROM stock_requests sr
        JOIN inventory_items i ON sr.item_id = i.id
        JOIN departments d ON sr.department_id = d.id
        JOIN users u ON sr.requested_by = u.id
        WHERE sr.id = ? AND sr.requested_by = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    header("Location: stock_out_request.php");
    exit();
}

// Decode QR data
$qr_data = base64_decode($request['qr_code']);
$qr_info = json_decode($qr_data, true);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-qrcode"></i> Stock Request QR Code</h1>
        <p>Print or save this QR code for department confirmation</p>
    </div>
    
    <div class="qr-container" id="qrPrintArea">
        <div class="qr-card">
            <div class="qr-header">
                <div class="hotel-name">
                    <i class="fas fa-hotel"></i>
                    <h2>HOTEL INVENTORY SYSTEM</h2>
                </div>
                <div class="request-badge">
                    STOCK REQUEST
                </div>
            </div>
            
            <div class="qr-body">
                <div class="qr-code-wrapper" id="qrcode">
                    <!-- QR code will be generated here -->
                </div>
                
                <div class="request-info">
                    <div class="info-row">
                        <span class="label">Request Code:</span>
                        <span class="value"><?php echo htmlspecialchars($request['request_code']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Item:</span>
                        <span class="value"><?php echo htmlspecialchars($request['item_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Quantity:</span>
                        <span class="value"><?php echo $request['quantity']; ?> <?php echo $request['unit']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Department:</span>
                        <span class="value"><?php echo htmlspecialchars($request['department_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Requested By:</span>
                        <span class="value"><?php echo htmlspecialchars($request['requester_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Request Date:</span>
                        <span class="value"><?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Status:</span>
                        <span class="value status-<?php echo $request['status']; ?>">
                            <?php echo ucfirst($request['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="qr-footer">
                <p><i class="fas fa-info-circle"></i> Department staff must scan this QR code to confirm receipt of items</p>
                <p class="instruction">Present this QR code to the requesting department for confirmation</p>
            </div>
        </div>
    </div>
    
    <div class="action-buttons">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Print QR Code
        </button>
        <button onclick="downloadQR()" class="btn-download">
            <i class="fas fa-download"></i> Download QR Code
        </button>
        <a href="stock_out_request.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Requests
        </a>
    </div>
    
    <!-- Status Update -->
    <?php if($request['status'] == 'pending'): ?>
    <div class="info-card">
        <div class="info-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="info-content">
            <h4>Pending Confirmation</h4>
            <p>This request is waiting for department staff to scan the QR code. Once scanned, stock will be automatically deducted.</p>
            <a href="cancel_request.php?id=<?php echo $request['id']; ?>" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel this request?')">
                <i class="fas fa-times"></i> Cancel Request
            </a>
        </div>
    </div>
    <?php elseif($request['status'] == 'confirmed'): ?>
    <div class="info-card success">
        <div class="info-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="info-content">
            <h4>Confirmed ✓</h4>
            <p>This request has been confirmed by department staff. Stock has been deducted.</p>
            <p><strong>Confirmed at:</strong> <?php echo date('d/m/Y H:i', strtotime($request['confirmed_at'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .main-content {
        padding: 20px;
        max-width: 900px;
        margin: 0 auto;
    }
    
    .page-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        color: #1E3A8A;
        font-size: 24px;
    }
    
    .page-header p {
        color: #6B7280;
    }
    
    .qr-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .qr-card {
        padding: 30px;
    }
    
    .qr-header {
        text-align: center;
        border-bottom: 2px solid #E5E7EB;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }
    
    .hotel-name {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .hotel-name i {
        font-size: 32px;
        color: #1E3A8A;
    }
    
    .hotel-name h2 {
        margin: 0;
        color: #1E3A8A;
        font-size: 20px;
    }
    
    .request-badge {
        display: inline-block;
        background: #FF6B6B;
        color: white;
        padding: 5px 20px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .qr-body {
        display: flex;
        gap: 40px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .qr-code-wrapper {
        flex-shrink: 0;
        text-align: center;
    }
    
    .qr-code-wrapper canvas,
    .qr-code-wrapper img {
        width: 250px;
        height: 250px;
        border: 2px solid #E5E7EB;
        border-radius: 16px;
        padding: 15px;
        background: white;
    }
    
    .request-info {
        flex: 1;
        min-width: 250px;
    }
    
    .info-row {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid #F3F4F6;
    }
    
    .info-row .label {
        width: 130px;
        font-weight: 600;
        color: #6B7280;
    }
    
    .info-row .value {
        flex: 1;
        color: #1F2937;
        font-weight: 500;
    }
    
    .status-pending {
        color: #F59E0B;
        font-weight: 600;
    }
    
    .status-confirmed {
        color: #10B981;
        font-weight: 600;
    }
    
    .status-cancelled {
        color: #EF4444;
        font-weight: 600;
    }
    
    .qr-footer {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #E5E7EB;
        text-align: center;
        font-size: 12px;
        color: #6B7280;
    }
    
    .qr-footer i {
        color: #1E3A8A;
    }
    
    .instruction {
        margin-top: 10px;
        font-weight: 500;
        color: #1E3A8A;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .btn-print, .btn-download, .btn-back {
        padding: 12px 24px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
    }
    
    .btn-print {
        background: #1E3A8A;
        color: white;
    }
    
    .btn-print:hover {
        background: #2563EB;
        transform: translateY(-2px);
    }
    
    .btn-download {
        background: #10B981;
        color: white;
    }
    
    .btn-download:hover {
        background: #059669;
        transform: translateY(-2px);
    }
    
    .btn-back {
        background: #F3F4F6;
        color: #374151;
    }
    
    .btn-back:hover {
        background: #E5E7EB;
    }
    
    .info-card {
        background: #F0F9FF;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        gap: 20px;
        align-items: flex-start;
        margin-top: 20px;
    }
    
    .info-card.success {
        background: #ECFDF5;
    }
    
    .info-icon i {
        font-size: 32px;
        color: #1E3A8A;
    }
    
    .info-card.success .info-icon i {
        color: #10B981;
    }
    
    .info-content h4 {
        margin: 0 0 10px;
        color: #1E3A8A;
    }
    
    .info-content p {
        margin: 5px 0;
        color: #6B7280;
    }
    
    .btn-cancel {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 16px;
        background: #FEE2E2;
        color: #991B1B;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.3s;
    }
    
    .btn-cancel:hover {
        background: #FECACA;
    }
    
    @media print {
        .sidebar, .top-header, .action-buttons, .info-card, .page-header p {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .qr-container {
            box-shadow: none;
            margin: 0;
        }
        
        .qr-card {
            padding: 0;
        }
        
        .status-pending, .status-confirmed {
            print-color-adjust: exact;
        }
    }
    
    @media (max-width: 600px) {
        .qr-body {
            flex-direction: column;
            align-items: center;
        }
        
        .info-row {
            flex-direction: column;
        }
        
        .info-row .label {
            width: auto;
            margin-bottom: 5px;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Generate QR code
    const qrData = <?php echo json_encode($qr_data); ?>;
    const requestCode = <?php echo json_encode($request['request_code']); ?>;
    
    // Create QR code
    new QRCode(document.getElementById("qrcode"), {
        text: qrData,
        width: 250,
        height: 250,
        colorDark: "#1E3A8A",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
    
    // Download QR code as image
    function downloadQR() {
        const canvas = document.querySelector('#qrcode canvas');
        if (canvas) {
            const link = document.createElement('a');
            link.download = `qr_${requestCode}.png`;
            link.href = canvas.toDataURL();
            link.click();
            showToast('QR code downloaded!', 'success');
        } else {
            showToast('Error generating QR code', 'error');
        }
    }
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'error' ? '#EF4444' : '#10B981'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 10000;
            animation: fadeInUp 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
</script>

<?php include '../templates/footer.php'; ?>
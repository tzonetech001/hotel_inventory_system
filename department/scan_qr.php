<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['department_user_id'])) {
    header("Location: login.php");
    exit();
}

include 'header.php';
?>

<div class="scanner-page">
    <div class="page-header">
        <h1><i class="fas fa-qrcode"></i> QR Code Scanner</h1>
        <p>Position the QR code in front of your camera to confirm stock receipt</p>
    </div>
    
    <div class="card scanner-card">
        <div class="card-body">
            <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
            <div class="scanner-controls">
                <button id="startScanner" class="btn-primary" style="display: none;">
                    <i class="fas fa-camera"></i> Start Camera
                </button>
                <button id="stopScanner" class="btn-secondary">
                    <i class="fas fa-stop"></i> Stop Camera
                </button>
            </div>
            <p class="scanner-note">
                <i class="fas fa-info-circle"></i>
                Only QR codes from this system will be accepted
            </p>
        </div>
    </div>
    
    <!-- Manual confirmation section -->
    <div class="card manual-card">
        <div class="card-header">
            <h3><i class="fas fa-keyboard"></i> Manual Confirmation</h3>
        </div>
        <div class="card-body">
            <p>If you can't scan the QR code, you can manually enter the request code:</p>
            <div class="manual-form">
                <input type="text" id="requestCode" placeholder="Enter Request Code (e.g., REQ-20241225-XXXXX)">
                <button onclick="manualConfirm()" class="btn-primary">
                    <i class="fas fa-check"></i> Confirm Request
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .scanner-page {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .page-header {
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        color: #1E3A8A;
        margin-bottom: 5px;
    }
    
    .page-header p {
        color: #6B7280;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .card-header {
        padding: 20px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .card-body {
        padding: 30px;
        text-align: center;
    }
    
    .scanner-controls {
        margin-top: 20px;
        display: flex;
        gap: 15px;
        justify-content: center;
    }
    
    .btn-primary, .btn-secondary {
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: #FF6B6B;
        color: white;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .scanner-note {
        margin-top: 20px;
        font-size: 12px;
        color: #6B7280;
    }
    
    .manual-form {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    .manual-form input {
        flex: 1;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
    }
    
    .manual-form input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    @media (max-width: 600px) {
        .card-body {
            padding: 20px;
        }
        
        .manual-form {
            flex-direction: column;
        }
        
        .scanner-controls {
            flex-direction: column;
        }
    }
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    let html5QrCode;
    let isScanning = false;
    
    function startScanner() {
        const readerElement = document.getElementById('reader');
        readerElement.innerHTML = '';
        
        html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure);
        isScanning = true;
    }
    
    function stopScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
        }
        isScanning = false;
    }
    
    function onScanSuccess(decodedText, decodedResult) {
        stopScanner();
        
        try {
            const data = JSON.parse(atob(decodedText));
            confirmRequest(data.request_code);
        } catch(e) {
            alert('Invalid QR code format. Please scan a valid stock request QR code.');
            startScanner();
        }
    }
    
    function onScanFailure(error) {
        // Silent failure
    }
    
    function manualConfirm() {
        const requestCode = document.getElementById('requestCode').value.trim();
        if (!requestCode) {
            alert('Please enter a request code');
            return;
        }
        confirmRequest(requestCode);
    }
    
    function confirmRequest(requestCode) {
        if (confirm(`Confirm stock receipt for request ${requestCode}?\n\nThis will deduct stock from inventory.`)) {
            fetch('confirm_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_code: requestCode,
                    department_user_id: <?php echo $_SESSION['department_user_id']; ?>
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✓ Stock confirmed successfully!\n\nItem: ' + result.item_name + '\nQuantity: ' + result.quantity);
                    document.getElementById('requestCode').value = '';
                    startScanner();
                } else {
                    alert('Error: ' + result.message);
                    startScanner();
                }
            })
            .catch(error => {
                alert('Error processing request: ' + error);
                startScanner();
            });
        } else {
            startScanner();
        }
    }
    
    // Start scanner automatically when page loads
    window.addEventListener('load', function() {
        startScanner();
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
        }
    });
    
    document.getElementById('stopScanner').addEventListener('click', function() {
        stopScanner();
    });
</script>

<?php include 'footer.php'; ?>
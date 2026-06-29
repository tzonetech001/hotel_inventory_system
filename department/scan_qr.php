<?php
require_once '../includes/config.php';

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
    
    <!-- Scanner Status Messages -->
    <div id="scannerStatus" class="scanner-status">
        <div id="statusMessage" class="status-info">
            <i class="fas fa-spinner fa-spin"></i> Initializing camera...
        </div>
    </div>
    
    <div class="card scanner-card">
        <div class="card-body">
            <!-- Camera Selection -->
            <div class="camera-selector">
                <button id="toggleCameraBtn" class="btn-camera-switch" style="display: none;">
                    <i class="fas fa-sync-alt"></i> Switch Camera
                </button>
                <span id="cameraStatus" class="camera-status">Loading...</span>
            </div>
            
            <!-- QR Reader Container -->
            <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
            
            <!-- Loading Overlay -->
            <div id="scannerLoading" class="scanner-loading-overlay">
                <div class="loading-spinner">
                    <i class="fas fa-camera fa-3x fa-spin"></i>
                    <p>Starting camera...</p>
                </div>
            </div>
            
            <div class="scanner-controls">
                <button id="startScanner" class="btn-primary" style="display: none;">
                    <i class="fas fa-camera"></i> Start Camera
                </button>
                <button id="stopScanner" class="btn-secondary">
                    <i class="fas fa-stop"></i> Stop Camera
                </button>
                <button id="restartScanner" class="btn-restart">
                    <i class="fas fa-redo"></i> Restart Scanner
                </button>
            </div>
            
            <div class="scanner-note">
                <i class="fas fa-info-circle"></i>
                <span>Only QR codes from this system will be accepted</span>
            </div>
            
            <!-- Flashlight Toggle (for mobile) -->
            <div class="flashlight-control" style="display: none;" id="flashlightControl">
                <button id="toggleFlashlight" class="btn-flashlight">
                    <i class="fas fa-lightbulb"></i> Toggle Flashlight
                </button>
            </div>
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
                <input type="text" id="requestCode" placeholder="Enter Request Code (e.g., REQ-20241225-XXXXX)" autocomplete="off">
                <button onclick="manualConfirm()" class="btn-primary">
                    <i class="fas fa-check"></i> Confirm Request
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Mobile-First Responsive Design */
    .scanner-page {
        max-width: 800px;
        margin: 0 auto;
        padding: 15px;
    }
    
    .page-header {
        margin-bottom: 20px;
    }
    
    .page-header h1 {
        color: #1E3A8A;
        margin-bottom: 5px;
        font-size: 24px;
    }
    
    .page-header p {
        color: #6B7280;
        font-size: 14px;
    }
    
    /* Scanner Status */
    .scanner-status {
        margin-bottom: 15px;
        padding: 12px 16px;
        border-radius: 12px;
        background: #F0F9FF;
        border-left: 4px solid #3B82F6;
    }
    
    .status-info {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #1E3A8A;
        font-size: 14px;
    }
    
    .status-info i {
        font-size: 18px;
    }
    
    .status-error {
        color: #991B1B;
        background: #FEE2E2;
        border-left-color: #EF4444;
    }
    
    .status-success {
        color: #065F46;
        background: #D1FAE5;
        border-left-color: #10B981;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .card-header {
        padding: 15px 20px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .card-body {
        padding: 20px;
        text-align: center;
        position: relative;
    }
    
    /* Loading Overlay */
    .scanner-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.9);
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 16px;
        z-index: 10;
        transition: opacity 0.3s ease;
    }
    
    .scanner-loading-overlay.hidden {
        opacity: 0;
        pointer-events: none;
    }
    
    .loading-spinner {
        text-align: center;
        color: #1E3A8A;
    }
    
    .loading-spinner i {
        margin-bottom: 15px;
    }
    
    .loading-spinner p {
        margin: 0;
        font-size: 16px;
        font-weight: 500;
    }
    
    /* Camera Selector Styles */
    .camera-selector {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
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
        flex-wrap: wrap;
    }
    
    .btn-primary, .btn-secondary, .btn-restart {
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
    
    .btn-primary:active {
        transform: translateY(0);
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .btn-restart {
        background: #8B5CF6;
        color: white;
    }
    
    .btn-restart:hover {
        background: #7C3AED;
    }
    
    .scanner-note {
        margin-top: 20px;
        font-size: 12px;
        color: #6B7280;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .flashlight-control {
        margin-top: 15px;
        text-align: center;
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
    
    .manual-card .card-body p {
        margin-bottom: 15px;
        font-size: 14px;
        color: #6B7280;
    }
    
    .manual-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .manual-form input {
        flex: 1;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        min-width: 200px;
    }
    
    .manual-form input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    /* HTML5 QR Reader Customization */
    #reader {
        border-radius: 12px;
        overflow: hidden;
        background: #000;
        min-height: 300px;
        position: relative;
    }
    
    #reader video {
        width: 100% !important;
        height: auto !important;
        border-radius: 12px;
        min-height: 300px;
        object-fit: cover;
    }
    
    #reader__scan_region {
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #reader__scan_region img {
        display: none;
    }
    
    /* Error message styling inside reader */
    #reader__dashboard_section_csr {
        display: none !important;
    }
    
    /* Mobile Optimizations */
    @media (max-width: 640px) {
        .scanner-page {
            padding: 10px;
        }
        
        .page-header h1 {
            font-size: 20px;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .btn-camera-switch {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-primary, .btn-secondary, .btn-restart {
            padding: 10px 20px;
            font-size: 13px;
        }
        
        .manual-form {
            flex-direction: column;
        }
        
        .manual-form input {
            width: 100%;
        }
        
        .manual-form button {
            width: 100%;
            justify-content: center;
        }
        
        .camera-selector {
            flex-direction: column;
        }
        
        .btn-camera-switch {
            width: 100%;
            justify-content: center;
        }
        
        #reader {
            min-height: 250px;
        }
        
        #reader video {
            min-height: 250px;
        }
    }
    
    /* Touch-friendly button sizes for mobile */
    @media (max-width: 480px) {
        .btn-camera-switch, 
        .btn-primary, 
        .btn-secondary,
        .btn-restart,
        .btn-flashlight {
            min-height: 44px;
            padding: 12px 20px;
        }
    }
    
    /* Landscape mode optimization */
    @media (orientation: landscape) and (max-height: 500px) {
        .scanner-page {
            padding: 5px;
        }
        
        .card-body {
            padding: 10px;
        }
        
        #reader {
            min-height: 200px;
        }
        
        #reader video {
            min-height: 200px;
        }
        
        #reader__scan_region {
            min-height: 200px;
        }
        
        .manual-form {
            flex-direction: row;
        }
    }
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    // Global variables
    let html5QrCode = null;
    let isScanning = false;
    let availableCameras = [];
    let currentCameraIndex = 0;
    let isInitialized = false;
    let cameraRetryCount = 0;
    const MAX_RETRIES = 3;
    
    // DOM Elements
    const readerElement = document.getElementById('reader');
    const toggleCameraBtn = document.getElementById('toggleCameraBtn');
    const cameraStatusSpan = document.getElementById('cameraStatus');
    const startScannerBtn = document.getElementById('startScanner');
    const stopScannerBtn = document.getElementById('stopScanner');
    const restartScannerBtn = document.getElementById('restartScanner');
    const flashlightControl = document.getElementById('flashlightControl');
    const toggleFlashlightBtn = document.getElementById('toggleFlashlight');
    const scannerLoading = document.getElementById('scannerLoading');
    const statusMessage = document.getElementById('statusMessage');
    
    // Helper function to update status
    function updateStatus(message, type = 'info') {
        const statusDiv = document.getElementById('scannerStatus');
        const msgDiv = document.getElementById('statusMessage');
        
        // Remove all status classes
        statusDiv.className = 'scanner-status';
        
        if (type === 'error') {
            statusDiv.classList.add('status-error');
            msgDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        } else if (type === 'success') {
            statusDiv.classList.add('status-success');
            msgDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        } else {
            statusDiv.classList.add('status-info');
            msgDiv.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${message}`;
        }
    }
    
    // Check if running on mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Get available cameras with error handling
    async function getAvailableCameras() {
        try {
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length > 0) {
                availableCameras = devices;
                return devices;
            } else {
                console.warn('No cameras found');
                return [];
            }
        } catch (err) {
            console.error('Error getting cameras:', err);
            return [];
        }
    }
    
    // Start scanner with specific camera
    async function startScannerWithCamera(cameraId) {
        // Stop existing scanner if running
        if (html5QrCode && html5QrCode.isScanning) {
            try {
                await html5QrCode.stop();
            } catch (e) {
                console.warn('Error stopping scanner:', e);
            }
        }
        
        // Clear the reader element
        readerElement.innerHTML = '';
        
        try {
            // Create new scanner
            html5QrCode = new Html5Qrcode("reader", {
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]
            });
            
            const config = { 
                fps: 15, 
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            // Determine which camera to use
            let cameraToUse;
            if (cameraId) {
                cameraToUse = { deviceId: { exact: cameraId } };
            } else {
                // Try to use environment (back) camera by default
                if (isMobile) {
                    cameraToUse = { facingMode: "environment" };
                } else {
                    // For desktop, use first available camera
                    if (availableCameras.length > 0) {
                        cameraToUse = { deviceId: { exact: availableCameras[0].id } };
                    } else {
                        cameraToUse = { facingMode: "environment" };
                    }
                }
            }
            
            // Start scanning
            await html5QrCode.start(
                cameraToUse,
                config,
                onScanSuccess,
                onScanFailure
            );
            
            isScanning = true;
            isInitialized = true;
            
            // Hide loading overlay
            scannerLoading.classList.add('hidden');
            
            // Show camera switch button if multiple cameras
            if (availableCameras.length > 1) {
                toggleCameraBtn.style.display = 'inline-flex';
            }
            
            // Show flashlight control for mobile
            if (isMobile) {
                flashlightControl.style.display = 'block';
            }
            
            // Update camera status
            updateCameraStatus(cameraId);
            updateStatus('Camera is active. Scan your QR code.', 'success');
            
            // Reset retry count on success
            cameraRetryCount = 0;
            
        } catch (err) {
            console.error('Error starting camera:', err);
            
            // Handle specific errors
            let errorMessage = 'Unable to access camera. ';
            
            if (err.message && err.message.includes('Permission')) {
                errorMessage += 'Please allow camera permissions in your browser settings.';
            } else if (err.message && err.message.includes('NotFound')) {
                errorMessage += 'No camera found on this device.';
            } else if (err.message && err.message.includes('NotAllowed')) {
                errorMessage += 'Camera access was denied. Please grant permission and refresh.';
            } else if (err.message && err.message.includes('NotReadable')) {
                errorMessage += 'Camera is busy. Please close other apps using the camera.';
            } else if (err.message && err.message.includes('Overconstrained')) {
                errorMessage += 'Camera not available. Try switching to another camera.';
            } else {
                errorMessage += err.message || 'Unknown error occurred.';
            }
            
            updateStatus(errorMessage, 'error');
            scannerLoading.classList.add('hidden');
            
            // Show start scanner button as fallback
            startScannerBtn.style.display = 'inline-flex';
            
            // Retry with different camera if available
            if (cameraRetryCount < MAX_RETRIES) {
                cameraRetryCount++;
                updateStatus(`Retrying camera (attempt ${cameraRetryCount}/${MAX_RETRIES})...`, 'info');
                
                // Try next camera if available
                if (availableCameras.length > 1 && currentCameraIndex < availableCameras.length - 1) {
                    currentCameraIndex++;
                    setTimeout(() => {
                        startScannerWithCamera(availableCameras[currentCameraIndex].id);
                    }, 1000);
                } else if (availableCameras.length > 0) {
                    // Try first camera
                    currentCameraIndex = 0;
                    setTimeout(() => {
                        startScannerWithCamera(availableCameras[0].id);
                    }, 1000);
                } else {
                    // Try with facing mode
                    setTimeout(() => {
                        startScannerWithCamera(null);
                    }, 1000);
                }
            } else {
                updateStatus('Unable to start camera after multiple attempts. Please use manual confirmation.', 'error');
                startScannerBtn.style.display = 'inline-flex';
            }
            
            isScanning = false;
        }
    }
    
    // Update camera status text
    function updateCameraStatus(cameraId) {
        if (cameraId && availableCameras.length > 0) {
            const camera = availableCameras.find(c => c.id === cameraId);
            if (camera) {
                const label = camera.label.toLowerCase();
                if (label.includes('back') || label.includes('environment') || label.includes('rear')) {
                    cameraStatusSpan.textContent = 'Back Camera';
                } else if (label.includes('front') || label.includes('user') || label.includes('selfie')) {
                    cameraStatusSpan.textContent = 'Front Camera';
                } else {
                    cameraStatusSpan.textContent = 'Camera ' + (availableCameras.indexOf(camera) + 1);
                }
                return;
            }
        }
        cameraStatusSpan.textContent = isScanning ? 'Camera Active' : 'Camera Stopped';
    }
    
    // Toggle between front and back cameras
    async function toggleCamera() {
        if (!isScanning) {
            updateStatus('Please start the scanner first', 'error');
            return;
        }
        
        if (availableCameras.length <= 1) {
            updateStatus('Only one camera available on this device', 'error');
            return;
        }
        
        // Toggle camera index
        currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
        const selectedCamera = availableCameras[currentCameraIndex];
        
        if (selectedCamera) {
            cameraStatusSpan.textContent = 'Switching...';
            updateStatus(`Switching to ${selectedCamera.label || 'Camera ' + (currentCameraIndex + 1)}...`, 'info');
            await startScannerWithCamera(selectedCamera.id);
        }
    }
    
    // Start scanner (with back camera by default)
    async function startScanner() {
        updateStatus('Initializing camera...', 'info');
        scannerLoading.classList.remove('hidden');
        startScannerBtn.style.display = 'none';
        
        try {
            // Check for camera permissions first
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: isMobile ? "environment" : undefined,
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    } 
                });
                // Stop the stream immediately after testing
                stream.getTracks().forEach(track => track.stop());
            } catch (permErr) {
                console.error('Permission error:', permErr);
                if (permErr.name === 'NotAllowedError' || permErr.name === 'PermissionDeniedError') {
                    updateStatus('Camera access denied. Please allow camera permissions in your browser settings and refresh the page.', 'error');
                    scannerLoading.classList.add('hidden');
                    startScannerBtn.style.display = 'inline-flex';
                    return;
                }
            }
            
            // Get available cameras
            await getAvailableCameras();
            
            if (availableCameras.length === 0) {
                // Try to start with facing mode
                updateStatus('No cameras found, trying default...', 'info');
                await startScannerWithCamera(null);
                return;
            }
            
            // Find back camera (environment) or use first available
            let backCamera = availableCameras.find(camera => 
                camera.label.toLowerCase().includes('back') || 
                camera.label.toLowerCase().includes('environment') ||
                camera.label.toLowerCase().includes('rear')
            );
            
            if (!backCamera) {
                backCamera = availableCameras[0];
            }
            
            currentCameraIndex = availableCameras.findIndex(c => c.id === backCamera.id);
            if (currentCameraIndex === -1) currentCameraIndex = 0;
            
            await startScannerWithCamera(backCamera.id);
            
        } catch (err) {
            console.error('Start scanner error:', err);
            updateStatus('Error starting scanner: ' + (err.message || 'Unknown error'), 'error');
            scannerLoading.classList.add('hidden');
            startScannerBtn.style.display = 'inline-flex';
        }
    }
    
    // Stop scanner
    async function stopScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            try {
                await html5QrCode.stop();
                isScanning = false;
                flashlightControl.style.display = 'none';
                cameraStatusSpan.textContent = 'Camera Stopped';
                updateStatus('Camera stopped', 'info');
                startScannerBtn.style.display = 'inline-flex';
                toggleCameraBtn.style.display = 'none';
            } catch (err) {
                console.error('Stop scanner error:', err);
            }
        }
    }
    
    // Restart scanner
    async function restartScanner() {
        await stopScanner();
        setTimeout(() => {
            startScanner();
        }, 500);
    }
    
    // Flashlight control
    let flashlightOn = false;
    
    async function toggleFlashlight() {
        if (!html5QrCode || !html5QrCode.isScanning) {
            updateStatus('Scanner must be active to use flashlight', 'error');
            return;
        }
        
        try {
            const videoElement = document.querySelector('#reader video');
            if (videoElement && videoElement.srcObject) {
                const stream = videoElement.srcObject;
                const track = stream.getVideoTracks()[0];
                
                if (track && track.getCapabilities && track.getCapabilities().torch) {
                    flashlightOn = !flashlightOn;
                    await track.applyConstraints({
                        advanced: [{ torch: flashlightOn }]
                    });
                    
                    toggleFlashlightBtn.innerHTML = flashlightOn ? 
                        '<i class="fas fa-lightbulb"></i> Flashlight ON' : 
                        '<i class="fas fa-lightbulb"></i> Toggle Flashlight';
                    toggleFlashlightBtn.style.background = flashlightOn ? '#10B981' : '#F59E0B';
                } else {
                    updateStatus('Flashlight not supported on this device/camera', 'error');
                }
            } else {
                updateStatus('Unable to access camera stream for flashlight', 'error');
            }
        } catch (err) {
            console.error('Flashlight error:', err);
            updateStatus('Flashlight feature not available', 'error');
        }
    }
    
    function onScanSuccess(decodedText, decodedResult) {
        // Stop scanning temporarily
        stopScanner();
        
        try {
            // Try to parse as base64 encoded JSON
            let data;
            try {
                const decoded = atob(decodedText);
                data = JSON.parse(decoded);
            } catch (e) {
                // If not base64, try to parse directly
                try {
                    data = JSON.parse(decodedText);
                } catch (e2) {
                    // If it's just a request code string
                    data = { request_code: decodedText };
                }
            }
            
            const requestCode = data.request_code || decodedText;
            confirmRequest(requestCode);
        } catch(e) {
            console.error('QR Parse error:', e);
            confirmRequest(decodedText);
        }
    }
    
    function onScanFailure(error) {
        // Silent failure - keep scanning
        // Only log if it's a critical error
        if (error && error.includes('error')) {
            console.warn('Scan error:', error);
        }
    }
    
    function manualConfirm() {
        const requestCode = document.getElementById('requestCode').value.trim();
        if (!requestCode) {
            updateStatus('Please enter a request code', 'error');
            return;
        }
        confirmRequest(requestCode);
    }
    
    function confirmRequest(requestCode) {
        if (confirm(`Confirm stock receipt for request ${requestCode}?\n\nThis will deduct stock from inventory.`)) {
            updateStatus('Processing confirmation...', 'info');
            
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
                    updateStatus(`✓ Stock confirmed successfully! Item: ${result.item_name}, Quantity: ${result.quantity}`, 'success');
                    document.getElementById('requestCode').value = '';
                    
                    // Show success alert
                    alert(`✓ Stock confirmed successfully!\n\nItem: ${result.item_name}\nQuantity: ${result.quantity}`);
                    
                    // Restart scanner after 2 seconds
                    setTimeout(() => {
                        restartScanner();
                    }, 2000);
                } else {
                    updateStatus('Error: ' + result.message, 'error');
                    alert('Error: ' + result.message);
                    // Restart scanner
                    setTimeout(() => {
                        restartScanner();
                    }, 1500);
                }
            })
            .catch(error => {
                updateStatus('Error processing request: ' + error, 'error');
                alert('Error processing request: ' + error);
                setTimeout(() => {
                    restartScanner();
                }, 1500);
            });
        } else {
            // Restart scanner if user cancels
            setTimeout(() => {
                restartScanner();
            }, 500);
        }
    }
    
    // Event Listeners
    toggleCameraBtn.addEventListener('click', toggleCamera);
    stopScannerBtn.addEventListener('click', stopScanner);
    restartScannerBtn.addEventListener('click', restartScanner);
    
    if (toggleFlashlightBtn) {
        toggleFlashlightBtn.addEventListener('click', toggleFlashlight);
    }
    
    // Start scanner automatically when page loads
    window.addEventListener('load', function() {
        // Small delay to ensure DOM is ready
        setTimeout(() => {
            startScanner();
        }, 800);
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (html5QrCode && html5QrCode.isScanning) {
            try {
                html5QrCode.stop();
            } catch (e) {
                // ignore
            }
        }
    });
    
    // Handle visibility change (when user switches tabs)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, stop scanner to save battery
            if (html5QrCode && html5QrCode.isScanning) {
                try {
                    html5QrCode.stop();
                    isScanning = false;
                } catch (e) {
                    // ignore
                }
            }
        } else {
            // Page is visible again, restart scanner if not scanning
            if (!isScanning && isInitialized) {
                setTimeout(() => {
                    startScanner();
                }, 500);
            }
        }
    });
    
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
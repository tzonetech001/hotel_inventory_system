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
    
    <div class="card scanner-card">
        <div class="card-body">
            <!-- Camera Selection -->
            <div class="camera-selector">
                <button id="toggleCameraBtn" class="btn-camera-switch">
                    <i class="fas fa-sync-alt"></i> Switch Camera
                </button>
                <span id="cameraStatus" class="camera-status">Back Camera</span>
            </div>
            
            <!-- QR Reader Container -->
            <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
            
            <div class="scanner-controls">
                <button id="startScanner" class="btn-primary" style="display: none;">
                    <i class="fas fa-camera"></i> Start Camera
                </button>
                <button id="stopScanner" class="btn-secondary">
                    <i class="fas fa-stop"></i> Stop Camera
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
    }
    
    #reader video {
        width: 100% !important;
        height: auto !important;
        border-radius: 12px;
    }
    
    #reader__scan_region {
        min-height: 300px;
    }
    
    /* Loading/Error States */
    .scanner-loading {
        text-align: center;
        padding: 40px;
        color: #6B7280;
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
        
        .btn-primary, .btn-secondary {
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
    }
    
    /* Touch-friendly button sizes for mobile */
    @media (max-width: 480px) {
        .btn-camera-switch, 
        .btn-primary, 
        .btn-secondary,
        .btn-flashlight {
            min-height: 44px;
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
    let html5QrCode;
    let isScanning = false;
    let currentCameraId = null;
    let availableCameras = [];
    let currentCameraIndex = 0;
    let html5QrCodeScanner;
    
    // DOM Elements
    const readerElement = document.getElementById('reader');
    const toggleCameraBtn = document.getElementById('toggleCameraBtn');
    const cameraStatusSpan = document.getElementById('cameraStatus');
    const startScannerBtn = document.getElementById('startScanner');
    const stopScannerBtn = document.getElementById('stopScanner');
    const flashlightControl = document.getElementById('flashlightControl');
    const toggleFlashlightBtn = document.getElementById('toggleFlashlight');
    
    // Check if running on mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Get available cameras
    async function getAvailableCameras() {
        try {
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length) {
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
        if (html5QrCode && html5QrCode.isScanning) {
            await html5QrCode.stop();
        }
        
        // Clear the reader element
        readerElement.innerHTML = '';
        
        // Create new scanner
        html5QrCode = new Html5Qrcode("reader");
        
        const config = { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };
        
        try {
            // Use specific camera if provided, otherwise use environment (back) camera
            const cameraToUse = cameraId || { facingMode: "environment" };
            
            await html5QrCode.start(
                cameraToUse,
                config,
                onScanSuccess,
                onScanFailure
            );
            
            isScanning = true;
            
            // Show flashlight control for mobile
            if (isMobile) {
                flashlightControl.style.display = 'block';
            }
            
            // Update camera status text
            if (cameraId && availableCameras.length > 0) {
                const camera = availableCameras.find(c => c.id === cameraId);
                if (camera) {
                    const isBackCamera = camera.label.toLowerCase().includes('back') || 
                                       camera.label.toLowerCase().includes('environment') ||
                                       currentCameraIndex === 0;
                    cameraStatusSpan.textContent = isBackCamera ? 'Back Camera' : 'Front Camera';
                }
            }
            
        } catch (err) {
            console.error('Error starting camera:', err);
            showError('Unable to access camera. Please check permissions.');
            isScanning = false;
        }
    }
    
    // Toggle between front and back cameras
    async function toggleCamera() {
        if (!isScanning) {
            showError('Please start the scanner first');
            return;
        }
        
        if (availableCameras.length <= 1) {
            showError('Only one camera available on this device');
            return;
        }
        
        // Toggle camera index
        currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
        const selectedCamera = availableCameras[currentCameraIndex];
        
        if (selectedCamera) {
            cameraStatusSpan.textContent = 'Switching...';
            await startScannerWithCamera(selectedCamera.id);
        }
    }
    
    // Start scanner (with back camera by default)
    async function startScanner() {
        // Check for camera permissions first
        try {
            await navigator.mediaDevices.getUserMedia({ video: true });
        } catch (err) {
            showError('Camera access denied. Please allow camera permissions and refresh.');
            return;
        }
        
        // Get available cameras
        await getAvailableCameras();
        
        // Find back camera (environment) or use first available
        let backCamera = null;
        if (availableCameras.length > 0) {
            // Try to find back camera by label
            backCamera = availableCameras.find(camera => 
                camera.label.toLowerCase().includes('back') || 
                camera.label.toLowerCase().includes('environment') ||
                camera.label.toLowerCase().includes('rear')
            );
            
            if (!backCamera) {
                backCamera = availableCameras[0];
            }
            currentCameraIndex = availableCameras.findIndex(c => c.id === backCamera.id);
        }
        
        await startScannerWithCamera(backCamera ? backCamera.id : { facingMode: "environment" });
    }
    
    // Stop scanner
    async function stopScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            await html5QrCode.stop();
            isScanning = false;
            flashlightControl.style.display = 'none';
            cameraStatusSpan.textContent = 'Camera Stopped';
        }
    }
    
    // Flashlight control (for mobile)
    let flashlightOn = false;
    let track = null;
    
    async function toggleFlashlight() {
        if (!html5QrCode || !html5QrCode.isScanning) {
            showError('Scanner must be active to use flashlight');
            return;
        }
        
        try {
            // Get video track from the scanner's stream
            const videoElement = document.querySelector('#reader video');
            if (videoElement && videoElement.srcObject) {
                const stream = videoElement.srcObject;
                track = stream.getVideoTracks()[0];
                
                if (track && track.getCapabilities().torch) {
                    flashlightOn = !flashlightOn;
                    await track.applyConstraints({
                        advanced: [{ torch: flashlightOn }]
                    });
                    
                    toggleFlashlightBtn.innerHTML = flashlightOn ? 
                        '<i class="fas fa-lightbulb"></i> Flashlight ON' : 
                        '<i class="fas fa-lightbulb"></i> Toggle Flashlight';
                    toggleFlashlightBtn.style.background = flashlightOn ? '#10B981' : '#F59E0B';
                } else {
                    showError('Flashlight not supported on this device/camera');
                }
            } else {
                showError('Unable to access camera stream for flashlight');
            }
        } catch (err) {
            console.error('Flashlight error:', err);
            showError('Flashlight feature not available');
        }
    }
    
    function onScanSuccess(decodedText, decodedResult) {
        // Stop scanning temporarily
        stopScanner();
        
        try {
            // Try to parse as base64 encoded JSON
            let data;
            try {
                data = JSON.parse(atob(decodedText));
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
            // Try to use raw text as request code
            confirmRequest(decodedText);
        }
    }
    
    function onScanFailure(error) {
        // Silent failure - keep scanning
        // You can uncomment below for debugging
        // console.log('Scan failure:', error);
    }
    
    function manualConfirm() {
        const requestCode = document.getElementById('requestCode').value.trim();
        if (!requestCode) {
            showError('Please enter a request code');
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
                    showSuccess('✓ Stock confirmed successfully!\n\nItem: ' + result.item_name + '\nQuantity: ' + result.quantity);
                    document.getElementById('requestCode').value = '';
                    // Restart scanner after 2 seconds
                    setTimeout(() => {
                        startScanner();
                    }, 2000);
                } else {
                    showError('Error: ' + result.message);
                    // Restart scanner
                    startScanner();
                }
            })
            .catch(error => {
                showError('Error processing request: ' + error);
                startScanner();
            });
        } else {
            // Restart scanner if user cancels
            startScanner();
        }
    }
    
    function showError(message) {
        alert(message);
    }
    
    function showSuccess(message) {
        alert(message);
    }
    
    // Event Listeners
    toggleCameraBtn.addEventListener('click', toggleCamera);
    stopScannerBtn.addEventListener('click', stopScanner);
    
    if (toggleFlashlightBtn) {
        toggleFlashlightBtn.addEventListener('click', toggleFlashlight);
    }
    
    // Start scanner automatically when page loads
    window.addEventListener('load', function() {
        // Small delay to ensure DOM is ready
        setTimeout(() => {
            startScanner();
        }, 500);
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
        }
    });
    
    // Handle visibility change (when user switches tabs)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, stop scanner to save battery
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop();
            }
        } else {
            // Page is visible again, restart scanner
            if (!isScanning) {
                startScanner();
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
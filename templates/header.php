<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#1E3A8A">
    <title><?php echo APP_NAME; ?> - <?php echo $_SESSION['role'] ?? 'System'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        /* ============================================
           HEADER STYLES
        ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F9FAFB;
            color: #111827;
        }
        
        /* Top Header */
        .top-header {
            background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
            color: white;
            padding: 12px 24px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Left Section - Logo */
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 200px;
        }
        
        .logo-img {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            background: white;
            padding: 3px;
        }
        
        /* Center Section - App Name */
        .header-center {
            flex: 1;
            text-align: center;
        }
        
        .app-title {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .app-subtitle {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 2px;
        }
        
        /* Right Section - User Info */
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            min-width: 200px;
            justify-content: flex-end;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            position: relative;
            padding: 5px 10px;
            border-radius: 40px;
            transition: background 0.3s;
        }
        
        .user-info:hover {
            background: rgba(255,255,255,0.15);
        }
        
        /* User Avatar Circle */
        .user-avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B6B, #ff8e8e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: white;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .user-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 600;
        }
        
        .user-role {
            font-size: 11px;
            opacity: 0.8;
        }
        
        /* Dropdown Menu */
        .user-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 220px;
            overflow: hidden;
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-header {
            padding: 16px;
            background: #F9FAFB;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .dropdown-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1E3A8A, #2563EB);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            color: white;
            overflow: hidden;
        }
        
        .dropdown-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .dropdown-info h4 {
            font-size: 14px;
            color: #1F2937;
            margin-bottom: 3px;
        }
        
        .dropdown-info p {
            font-size: 11px;
            color: #6B7280;
        }
        
        .dropdown-menu {
            list-style: none;
            padding: 8px 0;
        }
        
        .dropdown-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .dropdown-menu li a i {
            width: 20px;
            color: #6B7280;
        }
        
        .dropdown-menu li a:hover {
            background: #F3F4F6;
            color: #1E3A8A;
        }
        
        .dropdown-menu li a:hover i {
            color: #1E3A8A;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #E5E7EB;
            margin: 8px 0;
        }
        
        .logout-item a {
            color: #DC2626 !important;
        }
        
        .logout-item a i {
            color: #DC2626 !important;
        }
        
        .logout-item a:hover {
            background: #FEE2E2 !important;
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            transition: transform 0.3s;
        }
        
        .mobile-menu-btn:hover {
            transform: scale(1.1);
        }
        
        /* Overlay for mobile menu */
        .menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .menu-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* Toast Notification Container */
        .toast-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            pointer-events: none;
        }
        
        .toast {
            background: white;
            border-radius: 12px;
            padding: 16px 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastSlideIn 0.3s ease, toastFadeOut 0.3s ease 2.7s forwards;
            min-width: 280px;
            max-width: 400px;
            pointer-events: auto;
        }
        
        .toast.success {
            border-left: 4px solid #10B981;
        }
        
        .toast.error {
            border-left: 4px solid #EF4444;
        }
        
        .toast.info {
            border-left: 4px solid #1E3A8A;
        }
        
        .toast-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .toast.success .toast-icon {
            background: #D1FAE5;
            color: #10B981;
        }
        
        .toast.error .toast-icon {
            background: #FEE2E2;
            color: #EF4444;
        }
        
        .toast.info .toast-icon {
            background: #DBEAFE;
            color: #1E3A8A;
        }
        
        .toast-message {
            flex: 1;
            font-size: 14px;
            color: #374151;
        }
        
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes toastFadeOut {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }
        
        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .top-header {
                padding: 10px 16px;
            }
            
            .logo-img {
                width: 35px;
                height: 35px;
            }
            
            .app-title {
                font-size: 1rem;
            }
            
            .app-subtitle {
                font-size: 0.6rem;
            }
            
            .user-details {
                display: none;
            }
            
            .user-avatar-circle {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .header-left {
                min-width: auto;
            }
            
            .header-right {
                min-width: auto;
                gap: 10px;
            }
            
            .user-info {
                padding: 0;
            }
            
            .user-dropdown {
                top: 50px;
                right: -10px;
            }
        }
        
        @media (max-width: 480px) {
            .app-title {
                font-size: 0.85rem;
            }
            
            .app-subtitle {
                display: none;
            }
            
            .logo-img {
                width: 30px;
                height: 30px;
            }
        }
        
        /* Main Content Adjustment */
        .main-content {
            margin-left: 260px;
            margin-top: 65px;
            padding: 24px;
            min-height: calc(100vh - 65px);
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 65px;
            left: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #FFFFFF 0%, #F9FAFB 100%);
            box-shadow: 2px 0 12px rgba(0,0,0,0.08);
            overflow-y: auto;
            z-index: 999;
            transition: transform 0.3s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                top: 0;
                z-index: 1001;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Menu Overlay for Mobile -->
    <div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>
    
    <!-- Top Header -->
    <div class="top-header">
        <!-- Left Section: Logo -->
        <div class="header-left">
            <img src="<?php echo APP_URL; ?>/assets/images/hotel.jpg" alt="Hotel Logo" class="logo-img" onerror="this.src='https://via.placeholder.com/45x45?text=H'">
            <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Center Section: App Name -->
        <div class="header-center">
            <div class="app-title"><?php echo APP_NAME; ?></div>
            <div class="app-subtitle">Inventory & Procurement System</div>
        </div>
        
        <!-- Right Section: User Avatar & Dropdown -->
        <div class="header-right">
            <div class="user-info" id="userInfoBtn">
                <div class="user-avatar-circle" id="userAvatar">
                    <?php
                    // Get user profile picture
                    $profile_pic = '';
                    if (isset($_SESSION['user_id'])) {
                        global $db;
                        $pic_sql = "SELECT profile_picture FROM users WHERE id = ?";
                        $pic_stmt = $db->prepare($pic_sql);
                        $pic_stmt->bind_param("i", $_SESSION['user_id']);
                        $pic_stmt->execute();
                        $pic_result = $pic_stmt->get_result();
                        if ($pic_row = $pic_result->fetch_assoc()) {
                            $profile_pic = $pic_row['profile_picture'];
                        }
                    }
                    
                    if ($profile_pic && file_exists('../uploads/profile/' . $profile_pic)):
                    ?>
                        <img src="<?php echo APP_URL; ?>/uploads/profile/<?php echo $profile_pic; ?>" alt="Profile">
                    <?php else:
                        // Get first letter of first name and last name
                        $fullname = $_SESSION['fullname'] ?? 'User';
                        $name_parts = explode(' ', $fullname);
                        $initial = strtoupper(substr($name_parts[0], 0, 1));
                        if (isset($name_parts[1])) {
                            $initial .= strtoupper(substr($name_parts[1], 0, 1));
                        } else {
                            $initial .= strtoupper(substr($name_parts[0], 1, 1));
                        }
                    ?>
                        <?php echo $initial; ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?></span>
                    <span class="user-role"><?php echo $_SESSION['role'] ?? 'Guest'; ?></span>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
            </div>
            
            <!-- Dropdown Menu -->
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-avatar">
                        <?php if ($profile_pic && file_exists('../uploads/profile/' . $profile_pic)): ?>
                            <img src="<?php echo APP_URL; ?>/uploads/profile/<?php echo $profile_pic; ?>" alt="Profile">
                        <?php else: ?>
                            <?php
                            $fullname = $_SESSION['fullname'] ?? 'User';
                            $name_parts = explode(' ', $fullname);
                            $initial = strtoupper(substr($name_parts[0], 0, 1));
                            if (isset($name_parts[1])) {
                                $initial .= strtoupper(substr($name_parts[1], 0, 1));
                            }
                            echo $initial;
                            ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-info">
                        <h4><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?></h4>
                        <p><?php echo $_SESSION['role'] ?? 'Guest'; ?> • <?php echo $_SESSION['username'] ?? ''; ?></p>
                    </div>
                </div>
                <ul class="dropdown-menu">
                    <li>
                        <?php 
                        // Determine the correct profile page based on role
                        $role = $_SESSION['role'] ?? 'Guest';
                        $profile_link = '';
                        
                        switch($role) {
                            case 'Supplier':
                                $profile_link = APP_URL . '/supplier/profile.php';
                                break;
                            default:
                                $profile_link = APP_URL . '/templates/profile.php';
                                break;
                        }
                        ?>
                        <a href="<?php echo $profile_link; ?>">
                            <i class="fas fa-user-circle"></i> My Profile
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="dropdown-divider"></li>
                    <li class="logout-item">
                        <a href="<?php echo APP_URL; ?>/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // USER DROPDOWN TOGGLE
        // ============================================
        const userInfoBtn = document.getElementById('userInfoBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userInfoBtn) {
            userInfoBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (userDropdown && userInfoBtn) {
                if (!userInfoBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            }
        });
        
        // ============================================
        // MOBILE MENU FUNCTIONS
        // ============================================
        function toggleMenu() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('menuOverlay');
            if (sidebar) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
            }
        }
        
        function closeMenu() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('menuOverlay');
            if (sidebar) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        // Close menu on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeMenu();
            }
        });
        
        // ============================================
        // TOAST NOTIFICATION FUNCTION
        // ============================================
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let icon = '';
            if (type === 'success') icon = '<i class="fas fa-check-circle"></i>';
            else if (type === 'error') icon = '<i class="fas fa-exclamation-circle"></i>';
            else icon = '<i class="fas fa-info-circle"></i>';
            
            toast.innerHTML = `
                <div class="toast-icon">${icon}</div>
                <div class="toast-message">${message}</div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // ============================================
        // SHOW PHP SESSION MESSAGES AS TOASTS
        // ============================================
        <?php if(isset($_SESSION['toast_message'])): ?>
            showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type'] ?? 'success'; ?>');
            <?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); ?>
        <?php endif; ?>
        
        // ============================================
        // AUTO HIDE ALERT MESSAGES
        // ============================================
        document.querySelectorAll('.alert-success, .alert-error, .alert-info').forEach(alert => {
            setTimeout(() => {
                alert.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    </script>
    
    <style>
        @keyframes fadeOut {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }
        
        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #F3F4F6;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #1E3A8A;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #2563EB;
        }
    </style>
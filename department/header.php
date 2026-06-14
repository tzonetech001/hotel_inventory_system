<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <meta name="theme-color" content="#1E3A8A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Department Portal - Hotel Inventory System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #F3F4F6;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Department Header - Desktop */
        .department-header {
            background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-area i {
            font-size: 28px;
        }
        
        .logo-area h2 {
            font-size: 18px;
            margin: 0;
        }
        
        .logo-area span {
            font-size: 11px;
            opacity: 0.8;
        }
        
        /* Mobile Menu Toggle Button - Hidden on Desktop */
        .mobile-menu-toggle {
            display: none;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .mobile-menu-toggle:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-details {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-department {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        
        /* Desktop Navigation - Visible on Desktop, Hidden on Mobile */
        .department-nav {
            background: white;
            padding: 0 30px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            gap: 30px;
            position: sticky;
            top: 73px;
            z-index: 99;
        }
        
        .department-nav a {
            padding: 15px 0;
            text-decoration: none;
            color: #6B7280;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .department-nav a:hover,
        .department-nav a.active {
            color: #1E3A8A;
            border-bottom-color: #FF6B6B;
        }
        
        /* Mobile Sidebar Menu - Hidden on Desktop */
        .mobile-sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100%;
            background: white;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .mobile-sidebar.open {
            left: 0;
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
            color: white;
            padding: 25px 20px;
            text-align: center;
        }
        
        .sidebar-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
        }
        
        .sidebar-user-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .sidebar-user-dept {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            text-decoration: none;
            color: #374151;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #F3F4F6;
            color: #1E3A8A;
            border-left-color: #FF6B6B;
        }
        
        .sidebar-menu a i {
            width: 24px;
            font-size: 18px;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #E5E7EB;
            margin-top: 20px;
        }
        
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            text-decoration: none;
            color: #EF4444;
            font-weight: 500;
        }
        
        .close-sidebar {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: white;
        }
        
        /* Main Container */
        .main-container {
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        /* Profile Dropdown (Desktop) */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .profile-dropdown-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .profile-dropdown-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .profile-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 200px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 101;
            margin-top: 10px;
        }
        
        .profile-dropdown-content.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .profile-dropdown-content a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: #374151;
            transition: background 0.3s;
            border-bottom: 1px solid #F3F4F6;
        }
        
        .profile-dropdown-content a:last-child {
            border-bottom: none;
        }
        
        .profile-dropdown-content a:hover {
            background: #F3F4F6;
        }
        
        .profile-dropdown-content a i {
            width: 20px;
            color: #6B7280;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #E5E7EB;
            margin: 5px 0;
        }
        
        /* Footer */
        .footer {
            background: white;
            text-align: center;
            padding: 20px;
            color: #6B7280;
            font-size: 12px;
            border-top: 1px solid #E5E7EB;
            margin-top: auto;
        }
        
        /* Desktop Styles - 769px and above */
        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none !important;
            }
            
            .mobile-sidebar,
            .sidebar-overlay {
                display: none !important;
            }
            
            .department-nav {
                display: flex !important;
            }
            
            .user-info {
                display: flex !important;
            }
        }
        
        /* Mobile Styles - 768px and below */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .department-nav {
                display: none;
            }
            
            .user-info {
                display: none;
            }
            
            .department-header {
                padding: 12px 20px;
            }
            
            .logo-area h2 {
                font-size: 16px;
            }
            
            .logo-area span {
                display: none;
            }
            
            .main-container {
                padding: 15px;
            }
        }
        
        /* Small phones */
        @media (max-width: 480px) {
            .department-header {
                padding: 10px 15px;
            }
            
            .logo-area i {
                font-size: 22px;
            }
            
            .logo-area h2 {
                font-size: 14px;
            }
            
            .main-container {
                padding: 10px;
            }
        }
        
        /* Tablet Landscape */
        @media (min-width: 769px) and (max-width: 1024px) and (orientation: landscape) {
            .department-header {
                padding: 10px 20px;
            }
            
            .department-nav {
                top: 60px;
            }
        }
        
        /* Touch improvements for mobile */
        @media (hover: none) and (pointer: coarse) {
            .department-nav a,
            .logout-btn,
            .profile-dropdown-btn,
            .sidebar-menu a {
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="department-header">
        <div class="logo-area">
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <i class="fas fa-hotel"></i>
            <div>
                <h2>Hotel Inventory System</h2>
                <span>Department Portal</span>
            </div>
        </div>
        <div class="user-info">
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['department_user_name'] ?? 'User'); ?></div>
                <div class="user-department"><?php echo htmlspecialchars($_SESSION['department_name'] ?? ''); ?></div>
            </div>
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown">
                <button class="profile-dropdown-btn" id="profileDropdownBtn">
                    <i class="fas fa-user-circle"></i> <span>Profile</span> <i class="fas fa-chevron-down"></i>
                </button>
                <div class="profile-dropdown-content" id="profileDropdownContent">
                    <a href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="profile.php?tab=security">
                        <i class="fas fa-lock"></i> Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Desktop Navigation -->
    <div class="department-nav">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="scan_qr.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'scan_qr.php' ? 'active' : ''; ?>">
            <i class="fas fa-qrcode"></i> Scan QR
        </a>
        <a href="history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> History
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
    </div>
    
    <!-- Mobile Sidebar (Only visible on mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="mobile-sidebar" id="mobileSidebar">
        <button class="close-sidebar" id="closeSidebarBtn">
            <i class="fas fa-times"></i>
        </button>
        <div class="sidebar-header">
            <div class="sidebar-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['department_user_name'] ?? 'User'); ?></div>
            <div class="sidebar-user-dept"><?php echo htmlspecialchars($_SESSION['department_name'] ?? ''); ?></div>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="scan_qr.php">
                <i class="fas fa-qrcode"></i> Scan QR Code
            </a>
            <a href="history.php">
                <i class="fas fa-history"></i> History
            </a>
            <a href="profile.php">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="profile.php?tab=security">
                <i class="fas fa-lock"></i> Change Password
            </a>
        </div>
        <div class="sidebar-footer">
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content Container -->
    <div class="main-container">
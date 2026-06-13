<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Portal - Hotel Inventory System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #F3F4F6;
        }
        
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
        }
        
        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-area i {
            font-size: 24px;
        }
        
        .logo-area h2 {
            font-size: 18px;
            margin: 0;
        }
        
        .logo-area span {
            font-size: 12px;
            opacity: 0.8;
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
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .department-nav {
            background: white;
            padding: 0 30px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            gap: 30px;
        }
        
        .department-nav a {
            padding: 15px 0;
            text-decoration: none;
            color: #6B7280;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
        }
        
        .department-nav a:hover,
        .department-nav a.active {
            color: #1E3A8A;
            border-bottom-color: #FF6B6B;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .department-header {
                padding: 15px 20px;
            }
            
            .department-nav {
                padding: 0 20px;
                overflow-x: auto;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="department-header">
        <div class="logo-area">
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
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
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
    </div>
    <div class="container">
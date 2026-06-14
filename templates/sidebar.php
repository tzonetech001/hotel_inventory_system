<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-hotel"></i>
            <span>HIS</span>
        </div>
        <button class="sidebar-close" onclick="closeMenu()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <ul class="sidebar-menu">
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        $current_dir = basename(dirname($_SERVER['PHP_SELF']));
        $role = $_SESSION['role'] ?? 'Guest';
        
        // Dashboard - always visible
        $active_class = ($current_page == 'dashboard.php') ? 'active' : '';
        echo '<li class="' . $active_class . '"><a href="' . APP_URL . '/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>';
        
        // Role-specific menus
        switch($role) {
            case 'Admin':
                ?>
                <li class="menu-divider"></li>
                <li class="menu-header">User Management</li>
                <li class="<?php echo ($current_page == 'add_user.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/admin/add_user.php"><i class="fas fa-user-plus"></i> <span>Add User</span></a>
                </li>
                <li class="<?php echo ($current_page == 'view_users.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/admin/view_users.php"><i class="fas fa-users"></i> <span>View Users</span></a>
                </li>
                 <li><a href="<?php echo APP_URL; ?>/manager/suppliers.php"><i class="fas fa-truck"></i> <span>Suppliers</span></a></li>
                  <li><a href="<?php echo APP_URL; ?>/department/view_department_users.php"><i class="fas fa-tags"></i> <span>Departments</span></a></li>
                <li class="menu-divider"></li>
                <li class="menu-header">System</li>
                <li><a href="<?php echo APP_URL; ?>/manager/reports.php"><i class="fas fa-chart-line"></i> <span>Reports</span></a></li>
               
                <?php
                break;
                
            case 'Hotel Manager':
                ?>
                <li class="menu-divider"></li>
                <li class="menu-header">Management</li>
                <li class="<?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/manager/manage_users.php"><i class="fas fa-users-gear"></i> <span>User Management</span></a>
                </li>
                <li class="<?php echo ($current_page == 'suppliers.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/manager/suppliers.php"><i class="fas fa-truck"></i> <span>Suppliers</span></a>
                </li>
                 <li><a href="<?php echo APP_URL; ?>/department/view_department_users.php"><i class="fas fa-tags"></i> <span>Departments</span></a></li>
                <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/manager/reports.php"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
                </li>
                <li class="<?php echo ($current_page == 'approve_po.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/manager/approve_po.php"><i class="fas fa-check-circle"></i> <span>Approve Orders</span></a>
                </li>
                <li class="<?php echo ($current_page == 'view_po.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/procurement/view_po.php"><i class="fas fa-list"></i> <span>View PO</span></a>
                </li>
                
                <li><a href="<?php echo APP_URL; ?>/storekeeper/view_items.php"><i class="fas fa-boxes"></i> <span>Inventory</span></a></li>
                <?php
                break;
                
            case 'Storekeeper':
                ?>
                <li class="menu-divider"></li>
                <li class="menu-header">Inventory</li>
                <li class="<?php echo ($current_page == 'add_item.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/storekeeper/add_item.php"><i class="fas fa-plus-circle"></i> <span>Add Item</span></a>
                </li>
                <li class="<?php echo ($current_page == 'view_items.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/storekeeper/view_items.php"><i class="fas fa-list"></i> <span>View Items</span></a>
                </li>
                <li class="<?php echo ($current_page == 'stock_in.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/storekeeper/stock_in.php"><i class="fas fa-arrow-down"></i> <span>Stock In</span></a>
                </li>
                <li class="<?php echo ($current_page == 'stock_out.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/storekeeper/stock_out.php"><i class="fas fa-arrow-up"></i> <span>Stock Out</span></a>
                </li>
                <li class="menu-divider"></li>
                <li class="<?php echo ($current_page == 'confirm_delivery.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/storekeeper/confirm_delivery.php"><i class="fas fa-check-double"></i> <span>Confirm Deliveries</span></a>
                </li>
                <?php
                break;
                
            case 'Procurement Officer':
                ?>
                <li class="menu-divider"></li>
                <li class="menu-header">Procurement</li>
                <li class="<?php echo ($current_page == 'create_po.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/procurement/create_po.php"><i class="fas fa-file-invoice"></i> <span>Create PO</span></a>
                </li>
                <li class="<?php echo ($current_page == 'view_po.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/procurement/view_po.php"><i class="fas fa-list"></i> <span>View PO</span></a>
                </li>
                <li class="<?php echo ($current_page == 'track_delivery.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/procurement/track_delivery.php"><i class="fas fa-map-marker-alt"></i> <span>Track Delivery</span></a>
                </li>
                <li><a href="<?php echo APP_URL; ?>/manager/suppliers.php"><i class="fas fa-truck"></i> <span>Suppliers</span></a></li>
                <?php
                break;
                
            case 'Supplier':
                ?>
                <li class="menu-divider"></li>
                <li class="menu-header">Orders</li>
                <li class="<?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/supplier/orders.php"><i class="fas fa-clipboard-list"></i> <span>My Orders</span></a>
                </li>
                <?php
                break;
        }
        
        // Stock History - for all roles except Supplier
        if ($role !== 'Supplier' && $role !== 'Guest') {
            ?>
            <li class="<?php echo ($current_page == 'stock_history.php') ? 'active' : ''; ?>">
                <a href="<?php echo APP_URL; ?>/storekeeper/stock_history.php"><i class="fas fa-history"></i> <span>Stock History</span></a>
            </li>
            <?php
        }
        ?>
        
        <!-- Profile Section -->
        <li class="menu-divider"></li>
        <li class="menu-header">Account</li>
        <li>
            <?php if ($role === 'Supplier'): ?>
                <!-- Supplier uses their own profile page -->
                <a href="<?php echo APP_URL; ?>/supplier/profile.php">
                    <i class="fas fa-user-circle"></i> <span>My Profile</span>
                </a>
            <?php elseif ($role !== 'Guest'): ?>
                <!-- All other roles use templates/profile.php -->
                <a href="<?php echo APP_URL; ?>/templates/profile.php">
                    <i class="fas fa-user-circle"></i> <span>My Profile</span>
                </a>
            <?php endif; ?>
        </li>

        <!-- Logout -->
        <li class="menu-divider"></li>
        <li class="menu-footer">
            <a href="<?php echo APP_URL; ?>/auth/logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<style>
    /* Your existing CSS remains exactly the same */
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
    
    .sidebar-header {
        display: none;
        padding: 16px 20px;
        border-bottom: 1px solid #E5E7EB;
        align-items: center;
        justify-content: space-between;
    }
    
    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    .sidebar-logo i {
        font-size: 24px;
    }
    
    .sidebar-close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
    }
    
    .sidebar-close:hover {
        color: #EF4444;
    }
    
    .sidebar-menu {
        list-style: none;
        padding: 16px 0;
    }
    
    .sidebar-menu li {
        margin-bottom: 2px;
    }
    
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #374151;
        text-decoration: none;
        transition: all 0.3s;
        font-size: 14px;
        font-weight: 500;
    }
    
    .sidebar-menu li a i {
        width: 22px;
        font-size: 16px;
        color: #6B7280;
        transition: all 0.3s;
    }
    
    .sidebar-menu li a:hover {
        background: #F3F4F6;
        color: #1E3A8A;
        padding-left: 24px;
    }
    
    .sidebar-menu li a:hover i {
        color: #1E3A8A;
    }
    
    .sidebar-menu li.active a {
        background: linear-gradient(90deg, #1E3A8A 0%, #2563EB 100%);
        color: white;
    }
    
    .sidebar-menu li.active a i {
        color: white;
    }
    
    .menu-header {
        padding: 16px 20px 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #9CA3AF;
    }
    
    .menu-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, #E5E7EB, transparent);
        margin: 12px 20px;
    }
    
    .menu-footer {
        margin-top: 20px;
        padding-top: 10px;
    }
    
    .logout-link {
        color: #EF4444 !important;
    }
    
    .logout-link i {
        color: #EF4444 !important;
    }
    
    .logout-link:hover {
        background: #FEE2E2 !important;
        color: #DC2626 !important;
    }
    
    .role-badge {
        background: #FF6B6B;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    @media (max-width: 768px) {
        .sidebar-header {
            display: flex;
        }
        
        .sidebar {
            top: 0;
            width: 280px;
            z-index: 1001;
        }
        
        .menu-header {
            padding: 12px 20px 6px;
        }
        
        .menu-divider {
            margin: 8px 20px;
        }
        
        .logout-text {
            display: inline;
        }
    }
</style>
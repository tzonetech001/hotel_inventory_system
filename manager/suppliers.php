<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Allow access to Hotel Manager, Admin, and Procurement Officer
checkAuth(['Hotel Manager', 'Admin', 'Procurement Officer']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ============================================
// HANDLE ADD SUPPLIER
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_supplier'])) {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($company_name)) {
        $_SESSION['toast_message'] = "Company name is required!";
        $_SESSION['toast_type'] = "error";
    } elseif (empty($password) || empty($confirm_password)) {
        $_SESSION['toast_message'] = "Password is required!";
        $_SESSION['toast_type'] = "error";
    } elseif ($password !== $confirm_password) {
        $_SESSION['toast_message'] = "Passwords do not match!";
        $_SESSION['toast_type'] = "error";
    } elseif (strlen($password) < 6) {
        $_SESSION['toast_message'] = "Password must be at least 6 characters!";
        $_SESSION['toast_type'] = "error";
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM suppliers WHERE email = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['toast_message'] = "Email already exists!";
            $_SESSION['toast_type'] = "error";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO suppliers (company_name, contact_person, email, phone, address, password) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("ssssss", $company_name, $contact_person, $email, $phone, $address, $hashed_password);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'Add Supplier', "Added new supplier: $company_name");
                $_SESSION['toast_message'] = "Supplier <strong>" . htmlspecialchars($company_name) . "</strong> added successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Error adding supplier!";
                $_SESSION['toast_type'] = "error";
            }
        }
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// HANDLE EDIT SUPPLIER
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_supplier'])) {
    $supplier_id = intval($_POST['supplier_id']);
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($company_name)) {
        $_SESSION['toast_message'] = "Company name is required!";
        $_SESSION['toast_type'] = "error";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $_SESSION['toast_message'] = "Passwords do not match!";
        $_SESSION['toast_type'] = "error";
    } elseif (!empty($password) && strlen($password) < 6) {
        $_SESSION['toast_message'] = "Password must be at least 6 characters!";
        $_SESSION['toast_type'] = "error";
    } else {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE suppliers SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ?, password = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssssssi", $company_name, $contact_person, $email, $phone, $address, $status, $hashed_password, $supplier_id);
        } else {
            $sql = "UPDATE suppliers SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("ssssssi", $company_name, $contact_person, $email, $phone, $address, $status, $supplier_id);
        }
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Update Supplier', "Updated supplier: $company_name");
            $_SESSION['toast_message'] = "Supplier <strong>" . htmlspecialchars($company_name) . "</strong> updated successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error updating supplier!";
            $_SESSION['toast_type'] = "error";
        }
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// HANDLE DELETE SUPPLIER
// ============================================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Get supplier name for log
    $name_sql = "SELECT company_name FROM suppliers WHERE id = ?";
    $name_stmt = $db->prepare($name_sql);
    $name_stmt->bind_param("i", $delete_id);
    $name_stmt->execute();
    $name_result = $name_stmt->get_result();
    $supplier_name = $name_result->fetch_assoc();
    
    $sql = "DELETE FROM suppliers WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        logActivity($user_id, 'Delete Supplier', "Deleted supplier: " . ($supplier_name['company_name'] ?? "ID: $delete_id"));
        $_SESSION['toast_message'] = "Supplier deleted successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error deleting supplier!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// HANDLE TOGGLE STATUS
// ============================================
if (isset($_GET['toggle'])) {
    $toggle_id = intval($_GET['toggle']);
    $sql = "SELECT status FROM suppliers WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $toggle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    if ($supplier) {
        $new_status = ($supplier['status'] == 'active') ? 'inactive' : 'active';
        $sql = "UPDATE suppliers SET status = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $new_status, $toggle_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Supplier status updated!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error updating status!";
            $_SESSION['toast_type'] = "error";
        }
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// HANDLE RESET PASSWORD
// ============================================
if (isset($_GET['reset_password'])) {
    $reset_id = intval($_GET['reset_password']);
    
    // Set default password to "123456"
    $new_password = "123456";
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE suppliers SET password = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $reset_id);
    
    if ($stmt->execute()) {
        // Get supplier details
        $supplier_sql = "SELECT company_name, email FROM suppliers WHERE id = ?";
        $supplier_stmt = $db->prepare($supplier_sql);
        $supplier_stmt->bind_param("i", $reset_id);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        $supplier_data = $supplier_result->fetch_assoc();
        
        logActivity($user_id, 'Reset Supplier Password', "Reset password for supplier: {$supplier_data['company_name']}");
        $_SESSION['toast_message'] = "Password reset for <strong>" . htmlspecialchars($supplier_data['company_name']) . "</strong>! New password: <code>123456</code>";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error resetting password!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// GET SUPPLIER FOR EDIT (AJAX)
// ============================================
if (isset($_GET['get_supplier'])) {
    $supplier_id = intval($_GET['get_supplier']);
    $sql = "SELECT * FROM suppliers WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode($supplier);
    exit();
}

// ============================================
// GET ALL SUPPLIERS WITH SEARCH
// ============================================
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT * FROM suppliers WHERE 1=1";
if (!empty($search)) {
    $search = $db->real_escape_string($search);
    $sql .= " AND (company_name LIKE '%$search%' OR contact_person LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $status_filter = $db->real_escape_string($status_filter);
    $sql .= " AND status = '$status_filter'";
}
$sql .= " ORDER BY company_name ASC";

$result = $db->query($sql);
$suppliers = $result->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Suppliers Management</h1>
        <p>Manage your hotel's suppliers and vendors</p>
    </div>
    
    <!-- Stats Summary -->
    <div class="stats-summary">
        <div class="stat-item">
            <div class="stat-icon total">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Total Suppliers</div>
                <div class="stat-number" id="totalSuppliers"><?php echo count($suppliers); ?></div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon active">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Active Suppliers</div>
                <div class="stat-number" id="activeSuppliers">
                    <?php 
                        $active_count = 0;
                        foreach($suppliers as $s) {
                            if($s['status'] == 'active') $active_count++;
                        }
                        echo $active_count;
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <form method="GET" action="" class="search-form" id="filterForm">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" id="searchInput" placeholder="Search by company, contact, email or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
            </div>
            <div class="filter-box">
                <select name="status" id="statusFilter" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <?php if(!empty($search) || !empty($status_filter)): ?>
                <a href="suppliers.php" class="btn-clear" id="clearFilters">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
            <button type="submit" class="btn-search">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>
    
    <!-- Suppliers Grid -->
    <div class="suppliers-grid" id="suppliersGrid">
        <?php if(count($suppliers) > 0): ?>
            <?php foreach($suppliers as $index => $supplier): ?>
                <div class="supplier-card" data-id="<?php echo $supplier['id']; ?>" data-index="<?php echo $index; ?>">
                    <div class="supplier-card-header">
                        <div class="supplier-avatar">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="supplier-status <?php echo $supplier['status']; ?>">
                            <?php echo ucfirst($supplier['status']); ?>
                        </div>
                    </div>
                    <div class="supplier-card-body">
                        <h3 class="supplier-name"><?php echo htmlspecialchars($supplier['company_name']); ?></h3>
                        <?php if($supplier['contact_person']): ?>
                            <div class="supplier-contact">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($supplier['contact_person']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if($supplier['phone']): ?>
                            <div class="supplier-contact">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($supplier['phone']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if($supplier['email']): ?>
                            <div class="supplier-contact">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($supplier['email']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if($supplier['address']): ?>
                            <div class="supplier-address">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($supplier['address'], 0, 60)); ?>
                                <?php if(strlen($supplier['address']) > 60): ?>...<?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="supplier-card-footer">
                        <button class="btn-icon edit" onclick="editSupplier(<?php echo $supplier['id']; ?>)" title="Edit Supplier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon key" onclick="resetSupplierPassword(<?php echo $supplier['id']; ?>)" title="Reset Password">
                            <i class="fas fa-key"></i>
                        </button>
                        <button class="btn-icon toggle" onclick="toggleSupplierStatus(<?php echo $supplier['id']; ?>)" title="Toggle Status">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteSupplier(<?php echo $supplier['id']; ?>)" title="Delete Supplier">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-truck"></i>
                <h3>No Suppliers Found</h3>
                <p>Click the + button to add your first supplier</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Floating Action Button -->
    <button class="fab" id="fabBtn" onclick="openAddModal()">
        <i class="fas fa-plus"></i>
    </button>
</div>

<!-- Add/Edit Supplier Modal -->
<div id="supplierModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Add New Supplier</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="supplierForm">
                <input type="hidden" name="supplier_id" id="supplier_id" value="">
                <input type="hidden" name="add_supplier" id="add_supplier" value="1">
                <input type="hidden" name="edit_supplier" id="edit_supplier" value="">
                
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Company Name <span class="required">*</span></label>
                    <input type="text" name="company_name" id="company_name" placeholder="Enter company name" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person" placeholder="Enter contact person name" autocomplete="off">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" id="email" placeholder="company@example.com" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" name="phone" id="phone" placeholder="Enter phone number" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <textarea name="address" id="address" rows="3" placeholder="Enter full address"></textarea>
                </div>
                
                <div class="password-section" id="passwordSection">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password <span class="required" id="passwordRequired">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" placeholder="Enter password" autocomplete="off">
                                <i class="fas fa-eye toggle-password" data-target="password"></i>
                            </div>
                            <small>Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm Password <span class="required" id="confirmRequired">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" autocomplete="off">
                                <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="statusSection" style="display: none;">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status" id="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Save Supplier
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Stats Summary */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .stat-item {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    
    .stat-item:hover {
        transform: translateY(-2px);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    
    .stat-icon.total {
        background: #1E3A8A20;
        color: #1E3A8A;
    }
    
    .stat-icon.active {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .stat-info .stat-label {
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 5px;
    }
    
    .stat-info .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    /* Search and Filter Bar */
    .search-filter-bar {
        background: white;
        padding: 20px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .search-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-box {
        position: relative;
        flex: 1;
        min-width: 250px;
    }
    
    .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
    }
    
    .search-box input {
        width: 100%;
        padding: 12px 12px 12px 38px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .search-box input:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .filter-box select {
        padding: 12px 16px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        background: white;
        font-size: 14px;
        cursor: pointer;
    }
    
    .btn-search {
        padding: 12px 24px;
        background: #1E3A8A;
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-search:hover {
        background: #2563EB;
        transform: translateY(-1px);
    }
    
    .btn-clear {
        padding: 12px 20px;
        background: #F3F4F6;
        color: #374151;
        border-radius: 10px;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-clear:hover {
        background: #E5E7EB;
    }
    
    /* Suppliers Grid */
    .suppliers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 25px;
    }
    
    .supplier-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
        animation: fadeInUp 0.4s ease backwards;
    }
    
    .supplier-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Delete animation */
    @keyframes fadeOutLeft {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(-30px);
        }
    }
    
    .supplier-card.deleting {
        animation: fadeOutLeft 0.3s ease forwards;
    }
    
    .supplier-card-header {
        background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .supplier-avatar {
        width: 50px;
        height: 50px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    
    .supplier-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .supplier-status.active {
        background: #10B981;
    }
    
    .supplier-status.inactive {
        background: #EF4444;
    }
    
    .supplier-card-body {
        padding: 20px;
    }
    
    .supplier-name {
        font-size: 18px;
        color: #1F2937;
        margin: 0 0 12px 0;
        font-weight: 600;
    }
    
    .supplier-contact {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 10px;
    }
    
    .supplier-contact i {
        width: 18px;
        color: #1E3A8A;
    }
    
    .supplier-address {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 13px;
        color: #6B7280;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #E5E7EB;
    }
    
    .supplier-address i {
        width: 18px;
        color: #1E3A8A;
        margin-top: 2px;
    }
    
    .supplier-card-footer {
        padding: 12px 20px;
        background: #F9FAFB;
        border-top: 1px solid #E5E7EB;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
    
    /* Action Buttons */
    .btn-icon {
        width: 34px;
        height: 34px;
        background: white;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s;
        color: #6B7280;
        border: 1px solid #E5E7EB;
        cursor: pointer;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
    }
    
    .btn-icon.edit:hover {
        background: #DBEAFE;
        color: #1E3A8A;
        border-color: #1E3A8A;
    }
    
    .btn-icon.key:hover {
        background: #FEF3C7;
        color: #D97706;
        border-color: #D97706;
    }
    
    .btn-icon.toggle:hover {
        background: #FEE2E2;
        color: #EF4444;
        border-color: #EF4444;
    }
    
    .btn-icon.delete:hover {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #DC2626;
    }
    
    /* Floating Action Button */
    .fab {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 56px;
        height: 56px;
        background: #FF6B6B;
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(255,107,107,0.4);
        transition: all 0.3s;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .fab:hover {
        transform: scale(1.1);
        background: #e55a5a;
        box-shadow: 0 6px 16px rgba(255,107,107,0.5);
    }
    
    .fab:active {
        transform: scale(0.95);
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 20px;
        width: 90%;
        max-width: 520px;
        max-height: 85vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #F9FAFB;
        border-radius: 20px 20px 0 0;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .modal-header h3 i {
        margin-right: 8px;
    }
    
    .modal-header .close {
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
        line-height: 1;
    }
    
    .modal-header .close:hover {
        color: #EF4444;
    }
    
    .modal-body {
        padding: 24px;
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }
    
    .required {
        color: #EF4444;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .password-wrapper {
        position: relative;
    }
    
    .password-wrapper input {
        padding-right: 40px;
    }
    
    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #9CA3AF;
        transition: color 0.3s;
    }
    
    .toggle-password:hover {
        color: #1E3A8A;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
    
    .btn-primary {
        flex: 1;
        background: #FF6B6B;
        color: white;
        padding: 12px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        flex: 1;
        background: #F3F4F6;
        color: #374151;
        padding: 12px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    /* Empty State */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 16px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #D1D5DB;
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        color: #374151;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    /* Toast Animation */
    @keyframes toastSlideIn {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .suppliers-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .search-form {
            flex-direction: column;
        }
        
        .search-box {
            width: 100%;
        }
        
        .filter-box select,
        .btn-search,
        .btn-clear {
            width: 100%;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .fab {
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
        
        .modal-content {
            width: 95%;
            margin: 20px;
        }
        
        .stats-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // ============================================
    // TOAST NOTIFICATION SYSTEM
    // ============================================
    function showToast(message, type = 'success') {
        // Create toast container if not exists
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.style.cssText = `
            background: white;
            border-radius: 12px;
            padding: 14px 20px;
            min-width: 280px;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastSlideIn 0.3s ease;
            border-left: 4px solid ${type === 'success' ? '#10B981' : '#EF4444'};
        `;
        
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const iconColor = type === 'success' ? '#10B981' : '#EF4444';
        
        toast.innerHTML = `
            <i class="fas ${icon}" style="color: ${iconColor}; font-size: 20px;"></i>
            <span style="flex: 1; font-size: 14px; color: #374151;">${message}</span>
            <i class="fas fa-times" style="cursor: pointer; color: #9CA3AF;" onclick="this.parentElement.remove()"></i>
        `;
        
        container.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }
    
    // ============================================
    // MODAL FUNCTIONS
    // ============================================
    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Supplier';
        document.getElementById('supplier_id').value = '';
        document.getElementById('company_name').value = '';
        document.getElementById('contact_person').value = '';
        document.getElementById('email').value = '';
        document.getElementById('phone').value = '';
        document.getElementById('address').value = '';
        document.getElementById('password').value = '';
        document.getElementById('confirm_password').value = '';
        document.getElementById('status').value = 'active';
        document.getElementById('add_supplier').value = '1';
        document.getElementById('edit_supplier').value = '';
        
        // Show password fields, hide status section
        document.getElementById('passwordSection').style.display = 'block';
        document.getElementById('statusSection').style.display = 'none';
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('confirmRequired').style.display = 'inline';
        document.getElementById('password').required = true;
        document.getElementById('confirm_password').required = true;
        
        document.getElementById('supplierModal').style.display = 'flex';
        setTimeout(() => document.getElementById('company_name').focus(), 100);
    }
    
    function closeModal() {
        document.getElementById('supplierModal').style.display = 'none';
    }
    
    // Edit Supplier
    function editSupplier(id) {
        fetch(`?get_supplier=${id}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Supplier';
                document.getElementById('supplier_id').value = data.id;
                document.getElementById('company_name').value = data.company_name;
                document.getElementById('contact_person').value = data.contact_person || '';
                document.getElementById('email').value = data.email || '';
                document.getElementById('phone').value = data.phone || '';
                document.getElementById('address').value = data.address || '';
                document.getElementById('status').value = data.status;
                document.getElementById('add_supplier').value = '';
                document.getElementById('edit_supplier').value = '1';
                
                // Hide password fields for edit, show status section
                document.getElementById('passwordSection').style.display = 'none';
                document.getElementById('statusSection').style.display = 'block';
                document.getElementById('password').required = false;
                document.getElementById('confirm_password').required = false;
                
                document.getElementById('supplierModal').style.display = 'flex';
                setTimeout(() => document.getElementById('company_name').focus(), 100);
            })
            .catch(error => {
                showToast('Error loading supplier data!', 'error');
            });
    }
    
    // Reset Supplier Password
    function resetSupplierPassword(id) {
        if (confirm('Are you sure you want to reset the password for this supplier?\n\nNew password will be: 123456')) {
            window.location.href = `?reset_password=${id}`;
        }
    }
    
    // Toggle Supplier Status
    function toggleSupplierStatus(id) {
        if (confirm('Are you sure you want to change the status of this supplier?')) {
            window.location.href = `?toggle=${id}`;
        }
    }
    
    // Delete Supplier with Animation
    function deleteSupplier(id) {
        if (confirm('Are you sure you want to DELETE this supplier?\n\nThis action cannot be undone!')) {
            const card = document.querySelector(`.supplier-card[data-id="${id}"]`);
            if (card) {
                card.classList.add('deleting');
                setTimeout(() => {
                    window.location.href = `?delete=${id}`;
                }, 300);
            } else {
                window.location.href = `?delete=${id}`;
            }
        }
    }
    
    // ============================================
    // PASSWORD TOGGLE
    // ============================================
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            }
        });
    });
    
    // ============================================
    // FORM SUBMIT LOADING STATE
    // ============================================
    const form = document.getElementById('supplierForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // For add mode, validate password
            if (document.getElementById('add_supplier').value === '1') {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    showToast('Passwords do not match!', 'error');
                    return;
                }
                if (password.value.length < 6) {
                    e.preventDefault();
                    showToast('Password must be at least 6 characters!', 'error');
                    return;
                }
            }
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
        });
    }
    
    // ============================================
    // CLOSE MODAL ON OUTSIDE CLICK
    // ============================================
    window.onclick = function(event) {
        const modal = document.getElementById('supplierModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    
    // ============================================
    // KEYBOARD SHORTCUT: ESC TO CLOSE MODAL
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    // ============================================
    // ANIMATE CARDS ON SCROLL
    // ============================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.supplier-card').forEach(card => {
        card.style.animationPlayState = 'paused';
        observer.observe(card);
    });
    
    // ============================================
    // AUTO-SEARCH ON INPUT (DEBOUNCED)
    // ============================================
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    }
    
    // ============================================
    // CLEAR FILTERS BUTTON
    // ============================================
    const clearFilters = document.getElementById('clearFilters');
    if (clearFilters) {
        clearFilters.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'suppliers.php';
        });
    }
    
    // ============================================
    // PHP SESSION TOAST MESSAGES
    // ============================================
    <?php if(isset($_SESSION['toast_message'])): ?>
        showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type'] ?? 'success'; ?>');
        <?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); ?>
    <?php endif; ?>
</script>

<?php include '../templates/footer.php'; ?>
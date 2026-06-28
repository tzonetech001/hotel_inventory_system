<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager', 'Admin', 'Procurement Officer']);

$user_id = $_SESSION['user_id'];

// ============================================
// ADD SUPPLIER - Default password 123456
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_supplier'])) {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    if (empty($company_name)) {
        $_SESSION['toast_message'] = "Company name is required!";
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
            $default_password = password_hash("123456", PASSWORD_DEFAULT);
            $sql = "INSERT INTO suppliers (company_name, contact_person, email, phone, address, password) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("ssssss", $company_name, $contact_person, $email, $phone, $address, $default_password);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'Add Supplier', "Added new supplier: $company_name");
                $_SESSION['toast_message'] = "Supplier <strong>" . htmlspecialchars($company_name) . "</strong> added successfully!<br>Default password: <code>123456</code>";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Error adding supplier: " . $db->error;
                $_SESSION['toast_type'] = "error";
            }
        }
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// DELETE SUPPLIER
// ============================================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Check if supplier has purchase orders
    $check_sql = "SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count = $check_result->fetch_assoc();
    
    if ($count['count'] > 0) {
        $_SESSION['toast_message'] = "Cannot delete supplier with purchase orders. Deactivate instead.";
        $_SESSION['toast_type'] = "warning";
    } else {
        $name_sql = "SELECT company_name FROM suppliers WHERE id = ?";
        $name_stmt = $db->prepare($name_sql);
        $name_stmt->bind_param("i", $delete_id);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result();
        $supplier = $name_result->fetch_assoc();
        
        $sql = "DELETE FROM suppliers WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Delete Supplier', "Deleted supplier: " . ($supplier['company_name'] ?? "ID: $delete_id"));
            $_SESSION['toast_message'] = "Supplier deleted successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error deleting supplier!";
            $_SESSION['toast_type'] = "error";
        }
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// TOGGLE STATUS
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
            logActivity($user_id, 'Toggle Supplier', "Changed supplier ID $toggle_id status to $new_status");
            $_SESSION['toast_message'] = "Supplier status updated to <strong>" . ucfirst($new_status) . "</strong>!";
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
// RESET PASSWORD TO 123456
// ============================================
if (isset($_GET['reset_password'])) {
    $reset_id = intval($_GET['reset_password']);
    $new_password = password_hash("123456", PASSWORD_DEFAULT);
    
    $sql = "UPDATE suppliers SET password = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $new_password, $reset_id);
    
    if ($stmt->execute()) {
        $supplier_sql = "SELECT company_name FROM suppliers WHERE id = ?";
        $supplier_stmt = $db->prepare($supplier_sql);
        $supplier_stmt->bind_param("i", $reset_id);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        $supplier_data = $supplier_result->fetch_assoc();
        
        logActivity($user_id, 'Reset Supplier Password', "Reset password for supplier: {$supplier_data['company_name']}");
        $_SESSION['toast_message'] = "Password reset to <code>123456</code> for <strong>" . htmlspecialchars($supplier_data['company_name']) . "</strong>";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error resetting password!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: suppliers.php");
    exit();
}

// ============================================
// SEARCH AND FILTER
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
                <div class="stat-number"><?php echo count($suppliers); ?></div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon active">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Active Suppliers</div>
                <div class="stat-number">
                    <?php 
                        $active = 0;
                        foreach($suppliers as $s) {
                            if($s['status'] == 'active') $active++;
                        }
                        echo $active;
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="search-bar">
        <form method="GET" action="" class="filter-form">
            <div class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by company name, contact person, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button type="submit"><i class="fas fa-search"></i> Filter</button>
            <?php if($search || $status_filter): ?>
                <a href="suppliers.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
        <button class="add-btn" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Supplier</button>
    </div>
    
    <!-- Suppliers Grid -->
    <div class="suppliers-grid">
        <?php if(count($suppliers) > 0): ?>
            <?php foreach($suppliers as $supplier): ?>
                <div class="supplier-card" data-id="<?php echo $supplier['id']; ?>">
                    <div class="card-header">
                        <div class="avatar">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="status-badge <?php echo $supplier['status']; ?>">
                            <?php echo ucfirst($supplier['status']); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($supplier['company_name']); ?></h3>
                        <?php if($supplier['contact_person']): ?>
                            <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($supplier['contact_person']); ?></p>
                        <?php endif; ?>
                        <?php if($supplier['phone']): ?>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($supplier['phone']); ?></p>
                        <?php endif; ?>
                        <?php if($supplier['email']): ?>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($supplier['email']); ?></p>
                        <?php endif; ?>
                        <?php if($supplier['address']): ?>
                            <p class="address"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($supplier['address'], 0, 50)); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="edit_supplier.php?id=<?php echo $supplier['id']; ?>" class="edit-btn"><i class="fas fa-edit"></i></a>
                        <button class="reset-btn" onclick="resetPassword(<?php echo $supplier['id']; ?>)"><i class="fas fa-key"></i></button>
                        <button class="toggle-btn" onclick="toggleStatus(<?php echo $supplier['id']; ?>)"><i class="fas fa-power-off"></i></button>
                        <button class="delete-btn" onclick="deleteSupplier(<?php echo $supplier['id']; ?>)"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-truck"></i>
                <h3>No Suppliers Found</h3>
                <p>Click "Add Supplier" to get started</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Supplier Modal -->
<div id="supplierModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Supplier</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="supplierForm">
                <input type="hidden" name="add_supplier" value="1">
                
                <div class="form-group">
                    <label>Company Name <span class="required">*</span></label>
                    <input type="text" name="company_name" id="company_name" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="address" rows="3"></textarea>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <span>New supplier will have default password: <strong>123456</strong></span>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Supplier</button>
                    <button type="button" class="cancel-btn" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    .main-content {
        padding: 20px;
    }
    
    .page-header {
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 24px;
        color: #1E3A8A;
        margin-bottom: 5px;
    }
    
    .page-header p {
        color: #6B7280;
        font-size: 14px;
    }
    
    /* Stats */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    
    .stat-icon.total {
        background: #E0E7FF;
        color: #1E3A8A;
    }
    
    .stat-icon.active {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .stat-label {
        font-size: 12px;
        color: #6B7280;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1E3A8A;
    }
    
    /* Search Bar */
    .search-bar {
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        gap: 15px;
        flex-wrap: wrap;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .filter-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        flex: 1;
    }
    
    .search-input-wrapper {
        position: relative;
        flex: 1;
        min-width: 200px;
    }
    
    .search-input-wrapper i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
    }
    
    .search-input-wrapper input {
        width: 100%;
        padding: 10px 10px 10px 35px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .search-input-wrapper input:focus {
        outline: none;
        border-color: #1E3A8A;
    }
    
    .filter-form select {
        padding: 10px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        background: white;
    }
    
    .filter-form button {
        padding: 10px 20px;
        background: #1E3A8A;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .filter-form button:hover {
        background: #2563EB;
    }
    
    .clear-btn {
        padding: 10px 15px;
        background: #F3F4F6;
        color: #374151;
        text-decoration: none;
        border-radius: 8px;
    }
    
    .clear-btn:hover {
        background: #E5E7EB;
    }
    
    .add-btn {
        padding: 10px 20px;
        background: #FF6B6B;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .add-btn:hover {
        background: #e55a5a;
    }
    
    /* Suppliers Grid */
    .suppliers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    
    .supplier-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .supplier-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .card-header {
        background: linear-gradient(135deg, #1E3A8A, #2563EB);
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .avatar {
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
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: white;
    }
    
    .status-badge.active {
        background: #10B981;
    }
    
    .status-badge.inactive {
        background: #EF4444;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .card-body h3 {
        font-size: 18px;
        color: #1F2937;
        margin-bottom: 12px;
    }
    
    .card-body p {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #6B7280;
        margin-bottom: 8px;
    }
    
    .card-body p i {
        width: 16px;
        color: #1E3A8A;
    }
    
    .card-body .address {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #E5E7EB;
    }
    
    .card-footer {
        padding: 12px 20px;
        background: #F9FAFB;
        border-top: 1px solid #E5E7EB;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
    
    .card-footer button,
    .card-footer a {
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        background: white;
        color: #6B7280;
        border: 1px solid #E5E7EB;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    
    .card-footer button:hover,
    .card-footer a:hover {
        transform: translateY(-2px);
    }
    
    .edit-btn:hover {
        background: #DBEAFE;
        color: #1E3A8A;
        border-color: #1E3A8A;
    }
    
    .reset-btn:hover {
        background: #FEF3C7;
        color: #D97706;
        border-color: #D97706;
    }
    
    .toggle-btn:hover {
        background: #FEE2E2;
        color: #EF4444;
        border-color: #EF4444;
    }
    
    .delete-btn:hover {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #DC2626;
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
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
        max-height: 85vh;
        overflow-y: auto;
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        background: white;
    }
    
    .modal-header h3 {
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .modal-header .close {
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
    }
    
    .modal-header .close:hover {
        color: #EF4444;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
        font-weight: 500;
        color: #374151;
    }
    
    .required {
        color: #EF4444;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #1E3A8A;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .info-box {
        background: #D1FAE5;
        padding: 12px;
        border-radius: 10px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #065F46;
    }
    
    .info-box i {
        font-size: 18px;
    }
    
    .form-buttons {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #E5E7EB;
    }
    
    .save-btn {
        flex: 1;
        padding: 12px;
        background: #FF6B6B;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .save-btn:hover {
        background: #e55a5a;
    }
    
    .cancel-btn {
        flex: 1;
        padding: 12px;
        background: #F3F4F6;
        color: #374151;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .cancel-btn:hover {
        background: #E5E7EB;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 16px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: #D1D5DB;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        color: #374151;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .stats-summary {
            grid-template-columns: 1fr;
        }
        
        .suppliers-grid {
            grid-template-columns: 1fr;
        }
        
        .search-bar {
            flex-direction: column;
        }
        
        .filter-form {
            flex-direction: column;
        }
        
        .filter-form button,
        .clear-btn,
        .add-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<script>
    // Toast notification
    function showToast(message, type) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(container);
        }
        
        let bgColor = type === 'success' ? '#10B981' : (type === 'warning' ? '#F59E0B' : '#EF4444');
        let icon = type === 'success' ? 'fa-check-circle' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle');
        
        let toast = document.createElement('div');
        toast.style.cssText = `background:white;border-radius:12px;padding:14px 20px;min-width:280px;box-shadow:0 4px 15px rgba(0,0,0,0.2);display:flex;align-items:center;gap:12px;border-left:4px solid ${bgColor};animation:slideIn 0.3s ease;`;
        toast.innerHTML = `<i class="fas ${icon}" style="color:${bgColor};font-size:20px;"></i><span style="flex:1;font-size:14px;color:#374151;">${message}</span><i class="fas fa-times" style="cursor:pointer;color:#9CA3AF;" onclick="this.parentElement.remove()"></i>`;
        
        container.appendChild(toast);
        setTimeout(() => {
            if (toast.parentElement) toast.remove();
        }, 5000);
    }
    
    // Modal functions
    function openAddModal() {
        document.getElementById('supplierModal').style.display = 'flex';
        document.getElementById('company_name').focus();
    }
    
    function closeModal() {
        document.getElementById('supplierModal').style.display = 'none';
    }
    
    function resetPassword(id) {
        if (confirm('⚠️ Reset password to 123456 for this supplier?')) {
            window.location.href = '?reset_password=' + id;
        }
    }
    
    function toggleStatus(id) {
        if (confirm('Change supplier status?')) {
            window.location.href = '?toggle=' + id;
        }
    }
    
    function deleteSupplier(id) {
        if (confirm('⚠️ Delete this supplier? This cannot be undone.')) {
            window.location.href = '?delete=' + id;
        }
    }
    
    // Close modal on outside click
    window.onclick = function(e) {
        let modal = document.getElementById('supplierModal');
        if (e.target === modal) closeModal();
    }
    
    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    
    // Form submit
    document.getElementById('supplierForm').addEventListener('submit', function() {
        let btn = document.querySelector('.save-btn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
    });
    
    // Animation keyframes
    let style = document.createElement('style');
    style.textContent = `@keyframes slideIn{from{opacity:0;transform:translateX(100%)}to{opacity:1;transform:translateX(0)}}`;
    document.head.appendChild(style);
    
    // PHP session toast
    <?php if(isset($_SESSION['toast_message'])): ?>
        showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type'] ?? 'success'; ?>');
        <?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); ?>
    <?php endif; ?>
</script>

<?php include '../templates/footer.php'; ?>
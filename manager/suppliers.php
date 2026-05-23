<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager', 'Admin', 'Procurement Officer']);

$user_id = $_SESSION['user_id'];
$error = '';

// ============================================
// HANDLE ADD SUPPLIER (AJAX/POST)
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
        $sql = "INSERT INTO suppliers (company_name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssss", $company_name, $contact_person, $email, $phone, $address);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'Add Supplier', "Added new supplier: $company_name");
            $_SESSION['toast_message'] = "Supplier <strong>" . htmlspecialchars($company_name) . "</strong> added successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error adding supplier!";
            $_SESSION['toast_type'] = "error";
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
    
    if (empty($company_name)) {
        $_SESSION['toast_message'] = "Company name is required!";
        $_SESSION['toast_type'] = "error";
    } else {
        $sql = "UPDATE suppliers SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssssi", $company_name, $contact_person, $email, $phone, $address, $supplier_id);
        
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
// GET ALL SUPPLIERS
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
    
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <form method="GET" action="" class="search-form">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by company, contact, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-box">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <?php if(!empty($search) || !empty($status_filter)): ?>
                <a href="suppliers.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
            <button type="submit" class="btn-search">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>
    
    <!-- Suppliers Grid -->
    <div class="suppliers-grid">
        <?php if(count($suppliers) > 0): ?>
            <?php foreach($suppliers as $supplier): ?>
                <div class="supplier-card" data-id="<?php echo $supplier['id']; ?>">
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
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="supplier-card-footer">
                        <button onclick="editSupplier(<?php echo $supplier['id']; ?>)" class="btn-icon edit" title="Edit Supplier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?toggle=<?php echo $supplier['id']; ?>" class="btn-icon toggle" title="Toggle Status" onclick="return confirm('Change status for <?php echo addslashes($supplier['company_name']); ?>?')">
                            <i class="fas fa-power-off"></i>
                        </a>
                        <a href="?delete=<?php echo $supplier['id']; ?>" class="btn-icon delete" title="Delete Supplier" onclick="return confirm('Delete <?php echo addslashes($supplier['company_name']); ?>? This will affect related records!')">
                            <i class="fas fa-trash"></i>
                        </a>
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
                    <input type="text" name="company_name" id="company_name" placeholder="Enter company name" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person" placeholder="Enter contact person name">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" id="email" placeholder="company@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" name="phone" id="phone" placeholder="Enter phone number">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <textarea name="address" id="address" rows="3" placeholder="Enter full address"></textarea>
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
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
    }
    
    .supplier-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
        animation: fadeInUp 0.4s ease backwards;
        position: relative;
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
    
    .supplier-card-header {
        background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
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
        gap: 10px;
        justify-content: flex-end;
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
        max-width: 500px;
        animation: modalSlideIn 0.3s ease;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
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
    
    .btn-icon.toggle:hover {
        background: #FEF3C7;
        color: #D97706;
        border-color: #D97706;
    }
    
    .btn-icon.delete:hover {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #DC2626;
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
    }
</style>

<script>
    // Open Add Modal
    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Supplier';
        document.getElementById('supplier_id').value = '';
        document.getElementById('company_name').value = '';
        document.getElementById('contact_person').value = '';
        document.getElementById('email').value = '';
        document.getElementById('phone').value = '';
        document.getElementById('address').value = '';
        document.getElementById('add_supplier').value = '1';
        document.getElementById('edit_supplier').value = '';
        document.getElementById('supplierModal').style.display = 'flex';
        document.getElementById('company_name').focus();
    }
    
    // Edit Supplier Function
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
                document.getElementById('add_supplier').value = '';
                document.getElementById('edit_supplier').value = '1';
                document.getElementById('supplierModal').style.display = 'flex';
                document.getElementById('company_name').focus();
            });
    }
    
    // Close Modal
    function closeModal() {
        document.getElementById('supplierModal').style.display = 'none';
    }
    
    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('supplierModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    
    // Form submit loading state
    const form = document.getElementById('supplierForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(e) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
    });
    
    // Animate cards on scroll
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
    
    // Keyboard shortcut: ESC to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
</script>

<?php include '../templates/footer.php'; ?>
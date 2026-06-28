<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager', 'Admin', 'Procurement Officer']);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get supplier ID from URL
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($supplier_id <= 0) {
    header("Location: suppliers.php");
    exit();
}

// Get supplier details
$sql = "SELECT * FROM suppliers WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();

if (!$supplier) {
    header("Location: suppliers.php");
    exit();
}

// Handle form submission for updating supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];
    
    // Validation
    if (empty($company_name)) {
        $error = "Company name is required!";
    } else {
        // Check if email exists for another supplier
        $check_sql = "SELECT id FROM suppliers WHERE email = ? AND id != ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $supplier_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Email already exists for another supplier!";
        } else {
            // Update supplier without changing password
            $update_sql = "UPDATE suppliers SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("ssssssi", $company_name, $contact_person, $email, $phone, $address, $status, $supplier_id);
            
            if ($update_stmt->execute()) {
                logActivity($user_id, 'Update Supplier', "Updated supplier: $company_name (ID: $supplier_id)");
                $_SESSION['toast_message'] = "Supplier <strong>" . htmlspecialchars($company_name) . "</strong> updated successfully!";
                $_SESSION['toast_type'] = "success";
                header("Location: suppliers.php");
                exit();
            } else {
                $error = "Error updating supplier: " . $db->error;
            }
        }
    }
}

// Handle reset password
if (isset($_GET['reset_password'])) {
    $new_password = password_hash("123456", PASSWORD_DEFAULT);
    $reset_sql = "UPDATE suppliers SET password = ? WHERE id = ?";
    $reset_stmt = $db->prepare($reset_sql);
    $reset_stmt->bind_param("si", $new_password, $supplier_id);
    
    if ($reset_stmt->execute()) {
        $_SESSION['toast_message'] = "Password reset to <strong>123456</strong> for " . htmlspecialchars($supplier['company_name']);
        $_SESSION['toast_type'] = "success";
        header("Location: edit_supplier.php?id=" . $supplier_id);
        exit();
    } else {
        $error = "Error resetting password!";
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Supplier</h1>
        <p>Update supplier information and manage account</p>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <div class="edit-layout">
        <!-- Main Form Column -->
        <div class="form-column">
            <div class="form-card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Supplier Information</h3>
                    <p>Update the supplier's basic information</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="editForm">
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Company Name <span class="required">*</span></label>
                            <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($supplier['company_name']); ?>" required autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Contact Person</label>
                            <input type="text" name="contact_person" id="contact_person" value="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>" autocomplete="off">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>" autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>" autocomplete="off">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea name="address" id="address" rows="4" placeholder="Enter full address"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-toggle-on"></i> Status</label>
                                <select name="status" id="status">
                                    <option value="active" <?php echo ($supplier['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($supplier['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <small>Inactive suppliers cannot receive purchase orders</small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Date Added</label>
                                <input type="text" value="<?php echo date('d/m/Y H:i', strtotime($supplier['created_at'])); ?>" disabled class="disabled-field">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Update Supplier
                            </button>
                            <a href="suppliers.php" class="btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Column -->
        <div class="sidebar-column">
            <!-- Info Card -->
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Supplier Details</h3>
                </div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-item">
                            <span class="detail-label">Supplier ID:</span>
                            <span class="detail-value">#<?php echo $supplier['id']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="status-badge <?php echo $supplier['status']; ?>">
                                    <?php echo ucfirst($supplier['status']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Created:</span>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($supplier['created_at'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($supplier['updated_at'] ?? $supplier['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Password Card -->
            <div class="password-card">
                <div class="card-header">
                    <h3><i class="fas fa-key"></i> Password Management</h3>
                </div>
                <div class="card-body">
                    <div class="password-info">
                        <i class="fas fa-lock"></i>
                        <div>
                            <strong>Default Password: 123456</strong>
                            <p>Password cannot be changed from this form. Use the button below to reset to default.</p>
                        </div>
                    </div>
                    <a href="?reset_password=1" class="btn-warning" onclick="return confirmReset()">
                        <i class="fas fa-sync-alt"></i> Reset Password to 123456
                    </a>
                </div>
            </div>
            
            <!-- Danger Zone Card -->
            <div class="danger-card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                </div>
                <div class="card-body">
                    <div class="danger-buttons">
                        <a href="suppliers.php?delete=<?php echo $supplier_id; ?>" class="btn-danger" onclick="return confirmDelete()">
                            <i class="fas fa-trash-alt"></i> Delete Supplier
                        </a>
                    </div>
                    <p class="danger-note">
                        <i class="fas fa-shield-alt"></i>
                        Note: Suppliers with existing purchase orders cannot be deleted. They will be deactivated instead.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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
    
    /* Alerts */
    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }
    
    .alert-error {
        background: #FEF2F2;
        border-left: 4px solid #EF4444;
        color: #991B1B;
    }
    
    .alert-success {
        background: #D1FAE5;
        border-left: 4px solid #10B981;
        color: #065F46;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Layout */
    .edit-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 25px;
    }
    
    /* Cards */
    .form-card, .info-card, .password-card, .danger-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .card-header {
        padding: 18px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
        font-size: 16px;
    }
    
    .card-header p {
        margin: 5px 0 0;
        font-size: 12px;
        color: #6B7280;
    }
    
    .card-body {
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
        font-size: 13px;
    }
    
    .required {
        color: #EF4444;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .disabled-field {
        background: #F9FAFB;
        color: #6B7280;
        cursor: not-allowed;
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #6B7280;
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
        padding: 12px 20px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,107,107,0.3);
    }
    
    .btn-secondary {
        flex: 1;
        background: #F3F4F6;
        color: #374151;
        padding: 12px 20px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    /* Detail List */
    .detail-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .detail-item:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        font-size: 13px;
        color: #6B7280;
    }
    
    .detail-value {
        font-size: 13px;
        font-weight: 500;
        color: #1F2937;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-badge.active {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-badge.inactive {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    /* Password Card */
    .password-info {
        background: #FEF3C7;
        padding: 15px;
        border-radius: 12px;
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .password-info i {
        font-size: 24px;
        color: #D97706;
    }
    
    .password-info strong {
        display: block;
        color: #92400E;
        margin-bottom: 5px;
    }
    
    .password-info p {
        font-size: 12px;
        color: #92400E;
        margin: 0;
    }
    
    .btn-warning {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px;
        background: #FEF3C7;
        color: #D97706;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .btn-warning:hover {
        background: #FDE68A;
        transform: translateY(-2px);
    }
    
    /* Danger Card */
    .danger-card {
        border: 1px solid #FEE2E2;
    }
    
    .danger-card .card-header {
        background: #FEF2F2;
        border-bottom-color: #FEE2E2;
    }
    
    .danger-card .card-header h3 {
        color: #DC2626;
    }
    
    .danger-buttons {
        margin-bottom: 15px;
    }
    
    .btn-danger {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px;
        background: #FEE2E2;
        color: #DC2626;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .btn-danger:hover {
        background: #FECACA;
        transform: translateY(-2px);
    }
    
    .danger-note {
        background: #FEF2F2;
        padding: 10px;
        border-radius: 8px;
        font-size: 11px;
        color: #991B1B;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }
    
    /* Responsive */
    @media (max-width: 900px) {
        .edit-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary, .btn-secondary {
            width: 100%;
        }
    }
</style>

<script>
    function confirmReset() {
        return confirm('⚠️ Are you sure you want to reset the password?\n\nNew password will be: 123456\n\nThe supplier will need to use this password to login.');
    }
    
    function confirmDelete() {
        return confirm('⚠️ Are you sure you want to DELETE this supplier?\n\nThis action cannot be undone!\n\nIf this supplier has purchase orders, they will be deactivated instead of deleted.');
    }
    
    // Form submit loading state
    const form = document.getElementById('editForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form) {
        form.addEventListener('submit', function() {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
        });
    }
    
    // Auto focus on company name
    document.getElementById('company_name').focus();
    
    // Toast notification for session messages
    function showToast(message, type) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(container);
        }
        
        let bgColor = type === 'success' ? '#10B981' : '#EF4444';
        let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        let toast = document.createElement('div');
        toast.style.cssText = `background:white;border-radius:12px;padding:14px 20px;min-width:280px;box-shadow:0 4px 15px rgba(0,0,0,0.2);display:flex;align-items:center;gap:12px;border-left:4px solid ${bgColor};animation:slideIn 0.3s ease;`;
        toast.innerHTML = `<i class="fas ${icon}" style="color:${bgColor};font-size:20px;"></i><span style="flex:1;font-size:14px;color:#374151;">${message}</span><i class="fas fa-times" style="cursor:pointer;color:#9CA3AF;" onclick="this.parentElement.remove()"></i>`;
        
        container.appendChild(toast);
        setTimeout(() => {
            if (toast.parentElement) toast.remove();
        }, 5000);
    }
    
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
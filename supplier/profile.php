<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Only Supplier can access
checkAuth(['Supplier']);

$supplier_id = $_SESSION['supplier_id'] ?? $_SESSION['user_id'];
$user_id = $supplier_id;
$success = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'profile';

// Get supplier details
$sql = "SELECT * FROM suppliers WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();

if (!$supplier) {
    header("Location: " . APP_URL . "/dashboard.php");
    exit();
}

// ============================================
// HANDLE PROFILE UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    if (empty($company_name) || empty($email)) {
        $error = "Company name and email are required!";
    } else {
        // Check if email already exists for another supplier
        $check_sql = "SELECT id FROM suppliers WHERE email = ? AND id != ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $supplier_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Email already exists for another supplier!";
        } else {
            $update_sql = "UPDATE suppliers SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("sssssi", $company_name, $contact_person, $email, $phone, $address, $supplier_id);
            
            if ($update_stmt->execute()) {
                // Update session variables
                $_SESSION['fullname'] = $company_name;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                
                logActivity(0, 'Supplier Update Profile', "Updated profile for supplier: $company_name", 'supplier');
                $_SESSION['toast_message'] = "Profile updated successfully!";
                $_SESSION['toast_type'] = "success";
                header("Location: profile.php?tab=profile");
                exit();
            } else {
                $error = "Error updating profile!";
            }
        }
    }
}

// ============================================
// HANDLE PASSWORD CHANGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill all password fields!";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Verify current password
        $pass_sql = "SELECT password FROM suppliers WHERE id = ?";
        $pass_stmt = $db->prepare($pass_sql);
        $pass_stmt->bind_param("i", $supplier_id);
        $pass_stmt->execute();
        $pass_result = $pass_stmt->get_result();
        $supplier_data = $pass_result->fetch_assoc();
        
        if (password_verify($current_password, $supplier_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE suppliers SET password = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $supplier_id);
            
            if ($update_stmt->execute()) {
                logActivity(0, 'Supplier Change Password', "Changed password for supplier ID: $supplier_id", 'supplier');
                $_SESSION['toast_message'] = "Password changed successfully!";
                $_SESSION['toast_type'] = "success";
                header("Location: profile.php?tab=security");
                exit();
            } else {
                $error = "Error changing password!";
            }
        } else {
            $error = "Current password is incorrect!";
        }
    }
}

// ============================================
// GET ORDER HISTORY
// ============================================
$order_sql = "SELECT po.*, u.fullname as created_by_name
              FROM purchase_orders po
              JOIN users u ON po.created_by = u.id
              WHERE po.supplier_id = ?
              ORDER BY po.created_at DESC";
$order_stmt = $db->prepare($order_sql);
$order_stmt->bind_param("i", $supplier_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$orders = $order_result->fetch_all(MYSQLI_ASSOC);

// Count orders by status
$order_stats = [
    'total' => count($orders),
    'pending' => 0,
    'approved' => 0,
    'delivered' => 0,
    'rejected' => 0
];

foreach ($orders as $order) {
    if ($order['status'] == 'pending') $order_stats['pending']++;
    elseif ($order['status'] == 'approved') $order_stats['approved']++;
    elseif ($order['status'] == 'delivered') $order_stats['delivered']++;
    elseif ($order['status'] == 'rejected') $order_stats['rejected']++;
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> My Profile</h1>
        <p>Manage your account settings and view order history</p>
    </div>
    
    <!-- Profile Tabs -->
    <div class="profile-tabs">
        <a href="?tab=profile" class="profile-tab <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> Profile Info
        </a>
        <a href="?tab=security" class="profile-tab <?php echo $active_tab == 'security' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i> Security
        </a>
        <a href="?tab=orders" class="profile-tab <?php echo $active_tab == 'orders' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Order History
        </a>
    </div>
    
    <!-- Profile Tab Content -->
    <?php if($active_tab == 'profile'): ?>
    <div class="two-column-layout">
        <!-- Left Column: Company Info Card -->
        <div class="info-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Company Information</h3>
                </div>
                <div class="card-body">
                    <div class="company-details">
                        <div class="detail-item">
                            <span class="detail-label">Company Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($supplier['company_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact Person:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($supplier['contact_person'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($supplier['email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($supplier['phone'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Address:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($supplier['address'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value status-badge <?php echo $supplier['status']; ?>">
                                <?php echo ucfirst($supplier['status']); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Member Since:</span>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($supplier['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Quick Tips</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle"></i> Keep your contact information up to date</li>
                        <li><i class="fas fa-check-circle"></i> Use a strong password for security</li>
                        <li><i class="fas fa-check-circle"></i> Check order history for past purchases</li>
                        <li><i class="fas fa-check-circle"></i> Contact hotel management for any issues</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Edit Profile Form -->
        <div class="form-column">
            <div class="card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Edit Profile Information</h3>
                    <p class="card-subtitle">Update your company details</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="profileForm">
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Company Name <span class="required">*</span></label>
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars($supplier['company_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Contact Person</label>
                            <input type="text" name="contact_person" value="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>" placeholder="Enter contact person name">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email <span class="required">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($supplier['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>" placeholder="Enter phone number">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea name="address" rows="3" placeholder="Enter full address"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn-primary" id="updateProfileBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Security Tab Content -->
    <?php if($active_tab == 'security'): ?>
    <div class="two-column-layout">
        <!-- Left Column: Change Password -->
        <div class="password-column">
            <div class="card animate-card">
                <div class="card-header">
                    <h3><i class="fas fa-key"></i> Change Password</h3>
                    <p class="card-subtitle">Update your password to keep your account secure</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="passwordForm">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Current Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="current_password" id="current_password" required>
                                <i class="fas fa-eye toggle-password" data-target="current_password"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="new_password" required>
                                <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                            </div>
                            <small>Minimum 6 characters</small>
                            
                            <!-- Password Strength Indicator -->
                            <div class="password-strength" id="passwordStrength">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <div class="strength-text" id="strengthText">Enter a password</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm New Password <span class="required">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" required>
                                <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                            </div>
                            <div class="match-status" id="matchStatus"></div>
                        </div>
                        
                        <div class="password-tips">
                            <h4><i class="fas fa-shield-alt"></i> Password Tips:</h4>
                            <ul>
                                <li>Use at least 6 characters</li>
                                <li>Mix uppercase and lowercase letters</li>
                                <li>Include numbers and special characters</li>
                                <li>Avoid using common words or personal info</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn-primary" id="changePasswordBtn">
                                <i class="fas fa-sync-alt"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Security Tips -->
        <div class="security-tips-column">
            <div class="card tips-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Security Tips</h3>
                </div>
                <div class="card-body">
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle"></i> Never share your password with anyone</li>
                        <li><i class="fas fa-check-circle"></i> Use a unique password for this system</li>
                        <li><i class="fas fa-check-circle"></i> Change your password regularly</li>
                        <li><i class="fas fa-check-circle"></i> Always log out when using shared computers</li>
                        <li><i class="fas fa-check-circle"></i> Contact admin if you suspect unauthorized access</li>
                    </ul>
                </div>
            </div>
            
            <div class="card session-card animate-card-delayed">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Session Info</h3>
                </div>
                <div class="card-body">
                    <div class="session-info">
                        <div class="session-item">
                            <i class="fas fa-calendar"></i>
                            <span>Account Created: <?php echo date('d M Y', strtotime($supplier['created_at'])); ?></span>
                        </div>
                        <div class="session-item">
                            <i class="fas fa-ip"></i>
                            <span>IP Address: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></span>
                        </div>
                        <div class="session-item">
                            <i class="fas fa-browser"></i>
                            <span>Browser: <?php echo $_SERVER['HTTP_USER_AGENT'] ? substr($_SERVER['HTTP_USER_AGENT'], 0, 50) : 'Unknown'; ?>...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Orders Tab Content -->
    <?php if($active_tab == 'orders'): ?>
    <div class="card animate-card">
        <div class="card-header">
            <h3><i class="fas fa-shopping-cart"></i> Order History</h3>
            <div class="order-stats">
                <span class="stat-badge total">Total: <?php echo $order_stats['total']; ?></span>
                <span class="stat-badge pending">Pending: <?php echo $order_stats['pending']; ?></span>
                <span class="stat-badge approved">Approved: <?php echo $order_stats['approved']; ?></span>
                <span class="stat-badge delivered">Delivered: <?php echo $order_stats['delivered']; ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if(count($orders) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Order Date</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Expected Delivery</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                                <tr class="order-row">
                                    <td><strong><?php echo $order['po_number']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <button onclick="viewOrderItems(<?php echo $order['id']; ?>)" class="btn-link">
                                            <i class="fas fa-eye"></i> View Items
                                        </button>
                                     </td
                                    <td>TZS <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo $order['expected_delivery'] ? date('d M Y', strtotime($order['expected_delivery'])) : 'Not set'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                     </td
                                    <td>
                                        <?php if($order['status'] == 'approved'): ?>
                                            <button onclick="markAsDelivered(<?php echo $order['id']; ?>)" class="btn-small success">
                                                <i class="fas fa-check"></i> Mark Delivered
                                            </button>
                                        <?php endif; ?>
                                     </td
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Orders Found</h3>
                    <p>You haven't received any purchase orders yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- View Items Modal -->
<div id="itemsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-list"></i> Order Items</h3>
            <span class="close" onclick="closeItemsModal()">&times;</span>
        </div>
        <div class="modal-body" id="itemsModalBody">
            <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<style>
    /* Two Column Layout */
    .two-column-layout {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 25px;
    }
    
    /* Profile Tabs */
    .profile-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .profile-tab {
        padding: 12px 28px;
        background: white;
        border-radius: 12px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        transition: all 0.3s;
        border: 1px solid #E5E7EB;
    }
    
    .profile-tab i {
        margin-right: 8px;
    }
    
    .profile-tab:hover {
        background: #F3F4F6;
        border-color: #1E3A8A;
    }
    
    .profile-tab.active {
        background: #1E3A8A;
        color: white;
        border-color: #1E3A8A;
    }
    
    /* Cards */
    .animate-card {
        animation: fadeInUp 0.4s ease;
    }
    
    .animate-card-delayed {
        animation: fadeInUp 0.4s ease 0.1s both;
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
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
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
        font-size: 18px;
    }
    
    .card-subtitle {
        margin: 5px 0 0;
        font-size: 13px;
        color: #6B7280;
    }
    
    .card-body {
        padding: 24px;
    }
    
    /* Company Details */
    .company-details {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .detail-item:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        font-size: 13px;
        color: #6B7280;
        font-weight: 500;
    }
    
    .detail-value {
        font-size: 13px;
        font-weight: 600;
        color: #1F2937;
    }
    
    .status-badge {
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
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
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
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
    
    /* Password Styles */
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
    }
    
    .toggle-password:hover {
        color: #1E3A8A;
    }
    
    .password-strength {
        margin-top: 8px;
    }
    
    .strength-bar {
        height: 4px;
        background: #E5E7EB;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .strength-fill {
        height: 100%;
        width: 0%;
        transition: width 0.3s, background 0.3s;
    }
    
    .strength-text {
        font-size: 11px;
        margin-top: 5px;
        color: #6B7280;
    }
    
    .strength-text.weak { color: #EF4444; }
    .strength-text.medium { color: #F59E0B; }
    .strength-text.strong { color: #10B981; }
    
    .match-status {
        font-size: 12px;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .match-status.match {
        color: #10B981;
    }
    
    .match-status.not-match {
        color: #EF4444;
    }
    
    .password-tips {
        background: #F0F9FF;
        border-radius: 12px;
        padding: 15px;
        margin: 20px 0;
    }
    
    .password-tips h4 {
        margin: 0 0 10px;
        color: #1E3A8A;
        font-size: 14px;
    }
    
    .password-tips ul {
        margin: 0;
        padding-left: 20px;
    }
    
    .password-tips li {
        margin: 5px 0;
        font-size: 13px;
        color: #374151;
    }
    
    /* Tips List */
    .tips-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .tips-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 0;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
        color: #374151;
    }
    
    .tips-list li:last-child {
        border-bottom: none;
    }
    
    .tips-list li i {
        color: #10B981;
        width: 20px;
    }
    
    /* Session Info */
    .session-info {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .session-item {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        color: #374151;
    }
    
    .session-item i {
        width: 20px;
        color: #1E3A8A;
    }
    
    /* Order Stats */
    .order-stats {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .stat-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .stat-badge.total {
        background: #1E3A8A;
        color: white;
    }
    
    .stat-badge.pending {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .stat-badge.approved {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .stat-badge.delivered {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    /* Table Styles */
    .table-responsive {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table thead {
        background: #F9FAFB;
    }
    
    .data-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
        vertical-align: middle;
    }
    
    .order-row:hover {
        background: #F9FAFB;
    }
    
    /* Status Badges for Orders */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-pending {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .status-approved {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-delivered {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    .status-rejected {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    /* Buttons */
    .btn-primary {
        background: #FF6B6B;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-1px);
    }
    
    .btn-link {
        background: none;
        border: none;
        color: #1E3A8A;
        cursor: pointer;
        text-decoration: underline;
        font-size: 13px;
    }
    
    .btn-link:hover {
        color: #FF6B6B;
    }
    
    .btn-small {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-small.success {
        background: #10B981;
        color: white;
    }
    
    .btn-small.success:hover {
        background: #059669;
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
        max-width: 700px;
        max-height: 80vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #F9FAFB;
        position: sticky;
        top: 0;
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
        padding: 24px;
    }
    
    .loading-spinner {
        text-align: center;
        padding: 40px;
        color: #6B7280;
    }
    
    .items-list {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        padding: 12px;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .item-row:last-child {
        border-bottom: none;
    }
    
    .total-row {
        background: #F3F4F6;
        font-weight: bold;
        border-radius: 8px;
        margin-top: 10px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: #D1D5DB;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 18px;
        color: #374151;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .two-column-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .profile-t
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Hotel Manager', 'Admin', 'Procurement Officer']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle add/edit supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    
    if (empty($company_name)) {
        $error = "Company name is required!";
    } else {
        if ($supplier_id > 0) {
            // Update existing supplier
            $sql = "UPDATE suppliers SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssssi", $company_name, $contact_person, $email, $phone, $address, $supplier_id);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'Update Supplier', "Updated supplier: $company_name");
                $success = "Supplier updated successfully!";
            } else {
                $error = "Error updating supplier!";
            }
        } else {
            // Add new supplier
            $sql = "INSERT INTO suppliers (company_name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssss", $company_name, $contact_person, $email, $phone, $address);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'Add Supplier', "Added new supplier: $company_name");
                $success = "Supplier added successfully!";
                $_POST = array();
            } else {
                $error = "Error adding supplier!";
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $sql = "DELETE FROM suppliers WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        logActivity($user_id, 'Delete Supplier', "Deleted supplier ID: $delete_id");
        $success = "Supplier deleted successfully!";
    } else {
        $error = "Error deleting supplier!";
    }
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $toggle_id = intval($_GET['toggle']);
    $sql = "SELECT status FROM suppliers WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $toggle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    $new_status = ($supplier['status'] == 'active') ? 'inactive' : 'active';
    $sql = "UPDATE suppliers SET status = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $new_status, $toggle_id);
    
    if ($stmt->execute()) {
        $success = "Supplier status updated!";
    }
}

// Get supplier for editing
$edit_supplier = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $sql = "SELECT * FROM suppliers WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_supplier = $result->fetch_assoc();
}

// Get all suppliers
$sql = "SELECT * FROM suppliers ORDER BY company_name";
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
    
    <?php if($success): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <div class="two-columns">
        <!-- Add/Edit Supplier Form -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo $edit_supplier ? 'Edit Supplier' : 'Add New Supplier'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if($edit_supplier): ?>
                        <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Company Name *</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($edit_supplier['company_name'] ?? $_POST['company_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" value="<?php echo htmlspecialchars($edit_supplier['contact_person'] ?? $_POST['contact_person'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($edit_supplier['email'] ?? $_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($edit_supplier['phone'] ?? $_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2"><?php echo htmlspecialchars($edit_supplier['address'] ?? $_POST['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> <?php echo $edit_supplier ? 'Update Supplier' : 'Add Supplier'; ?>
                        </button>
                        <?php if($edit_supplier): ?>
                            <a href="suppliers.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Suppliers List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> All Suppliers (<?php echo count($suppliers); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($suppliers as $supplier): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($supplier['company_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['phone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['email'] ?? '-'); ?></td>
                                    <td>
                                        <span class="status-badge-<?php echo $supplier['status']; ?>">
                                            <?php echo ucfirst($supplier['status']); ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="?edit=<?php echo $supplier['id']; ?>" class="btn-icon" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?toggle=<?php echo $supplier['id']; ?>" class="btn-icon" title="Toggle Status" onclick="return confirm('Change status?')">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                        <a href="?delete=<?php echo $supplier['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this supplier? This will affect related records!')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                  </td
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .two-columns {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 25px;
    }
    
    .status-badge-active {
        background: #D1FAE5;
        color: #065F46;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-badge-inactive {
        background: #FEE2E2;
        color: #991B1B;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .two-columns {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include '../templates/footer.php'; ?>
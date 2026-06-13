<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';



// Check if admin is logged in (optional)
// For now, we'll allow direct access but you should add admin check

$error = '';
$success = '';

// Get departments
$departments_sql = "SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name";
$departments_result = $db->query($departments_sql);
$departments = $departments_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $sex = $_POST['sex'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department_id = intval($_POST['department_id']);
    $employee_id = trim($_POST['employee_id']);
    $position = trim($_POST['position']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($fullname) || empty($email) || empty($phone) || empty($department_id)) {
        $error = "Please fill in all required fields!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM department_users WHERE email = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            // Check if employee ID already exists
            if (!empty($employee_id)) {
                $check_sql = "SELECT id FROM department_users WHERE employee_id = ?";
                $check_stmt = $db->prepare($check_sql);
                $check_stmt->bind_param("s", $employee_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $error = "Employee ID already exists!";
                }
            }
            
            if (empty($error)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO department_users (fullname, sex, email, phone, department_id, employee_id, position, password) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssssisss", $fullname, $sex, $email, $phone, $department_id, $employee_id, $position, $hashed_password);
                
                if ($stmt->execute()) {
                    $success = "Department user created successfully!";
                    // Clear form
                    $_POST = [];
                } else {
                    $error = "Error creating user: " . $db->error;
                }
            }
        }
    }
}

include 'header.php';
?>

<div class="register-container">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Register Department User</h1>
        <p>Create accounts for department staff who will confirm stock requests</p>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> User Information</h3>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex">
                            <option value="">Select</option>
                            <option value="Male" <?php echo (($_POST['sex'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($_POST['sex'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (($_POST['sex'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Department <span class="required">*</span></label>
                        <select name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" name="employee_id" value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>" placeholder="Optional">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Position/Title</label>
                        <input type="text" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" placeholder="e.g., Department Head, Supervisor">
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" required>
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Register User
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <a href="dashboard.php" class="btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .register-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-header {
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        color: #1E3A8A;
        margin-bottom: 5px;
    }
    
    .page-header p {
        color: #6B7280;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .card-header {
        padding: 20px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .card-body {
        padding: 24px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 0;
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
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #1E3A8A;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #6B7280;
    }
    
    .alert {
        padding: 12px 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert.error {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 4px solid #EF4444;
    }
    
    .alert.success {
        background: #D1FAE5;
        color: #065F46;
        border-left: 4px solid #10B981;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E5E7EB;
    }
    
    .btn-primary, .btn-secondary, .btn-outline {
        padding: 12px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: #FF6B6B;
        color: white;
        border: none;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: #F3F4F6;
        color: #374151;
        border: none;
    }
    
    .btn-secondary:hover {
        background: #E5E7EB;
    }
    
    .btn-outline {
        background: transparent;
        border: 1px solid #1E3A8A;
        color: #1E3A8A;
    }
    
    .btn-outline:hover {
        background: #1E3A8A;
        color: white;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-primary, .btn-secondary, .btn-outline {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<?php include 'footer.php'; ?>
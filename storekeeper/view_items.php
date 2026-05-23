<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Storekeeper', 'Hotel Manager', 'Procurement Officer']);

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "SELECT i.*, s.company_name as supplier_name 
        FROM inventory_items i 
        LEFT JOIN suppliers s ON i.supplier_id = s.id 
        WHERE i.status = 'active'";

if (!empty($search)) {
    $sql .= " AND i.item_name LIKE '%" . $db->real_escape_string($search) . "%'";
}

if (!empty($category)) {
    $sql .= " AND i.category = '" . $db->real_escape_string($category) . "'";
}

$sql .= " ORDER BY i.item_name";

$result = $db->query($sql);
$items = $result->fetch_all(MYSQLI_ASSOC);

// Get unique categories for filter
$cat_result = $db->query("SELECT DISTINCT category FROM inventory_items WHERE category IS NOT NULL AND category != ''");
$categories = $cat_result->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-boxes"></i> Inventory Items</h1>
        <p>View and manage all inventory items</p>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-box">
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['category']; ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['category']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-secondary">Filter</button>
                    <a href="view_items.php" class="btn-outline">Reset</a>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Unit</th>
                            <th>Min Stock</th>
                            <th>Status</th>
                            <th>Supplier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($items) > 0): ?>
                            <?php foreach($items as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo ($item['current_stock'] <= $item['minimum_stock']) ? 'low' : 'normal'; ?>">
                                            <?php echo number_format($item['current_stock']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                                    <td><?php echo number_format($item['minimum_stock']); ?></td>
                                    <td>
                                        <?php if($item['current_stock'] <= $item['minimum_stock']): ?>
                                            <span class="status-badge danger">Low Stock</span>
                                        <?php elseif($item['current_stock'] >= $item['maximum_stock']): ?>
                                            <span class="status-badge warning">Over Stock</span>
                                        <?php else: ?>
                                            <span class="status-badge success">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['supplier_name'] ?? '-'); ?></td>
                                    <td class="action-buttons">
                                        <button onclick="viewItem(<?php echo $item['id']; ?>)" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editItem(<?php echo $item['id']; ?>)" class="btn-icon" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="itemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Item Details</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<style>
    .filter-section {
        padding: 15px 0;
    }
    
    .filter-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
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
        padding: 10px 10px 10px 35px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
    }
    
    .filter-box select {
        padding: 10px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        min-width: 150px;
    }
    
    .stock-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    }
    
    .stock-badge.normal {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .stock-badge.low {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-badge.success {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-badge.danger {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .status-badge.warning {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        border-radius: 6px;
        transition: all 0.3s;
        color: #6B7280;
    }
    
    .btn-icon:hover {
        background: #F3F4F6;
        color: #1E3A8A;
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: white;
        margin: 10% auto;
        padding: 0;
        width: 90%;
        max-width: 500px;
        border-radius: 12px;
        animation: slideDown 0.3s ease;
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-body {
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .close {
        font-size: 28px;
        cursor: pointer;
        color: #9CA3AF;
    }
    
    .close:hover {
        color: #374151;
    }
    
    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .text-center {
        text-align: center;
    }
</style>

<script>
function viewItem(id) {
    fetch(`get_item.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="detail-row">
                    <strong>Item Name:</strong> ${data.item_name}
                </div>
                <div class="detail-row">
                    <strong>Category:</strong> ${data.category || '-'}
                </div>
                <div class="detail-row">
                    <strong>Current Stock:</strong> ${data.current_stock} ${data.unit}
                </div>
                <div class="detail-row">
                    <strong>Minimum Stock:</strong> ${data.minimum_stock} ${data.unit}
                </div>
                <div class="detail-row">
                    <strong>Maximum Stock:</strong> ${data.maximum_stock} ${data.unit}
                </div>
                <div class="detail-row">
                    <strong>Unit Price:</strong> TZS ${parseFloat(data.unit_price).toLocaleString()}
                </div>
                <div class="detail-row">
                    <strong>Supplier:</strong> ${data.supplier_name || '-'}
                </div>
                <div class="detail-row">
                    <strong>Location:</strong> ${data.location || '-'}
                </div>
                <div class="detail-row">
                    <strong>Created:</strong> ${data.created_at}
                </div>
            `;
            document.getElementById('itemModal').style.display = 'block';
        });
}

function editItem(id) {
    window.location.href = `edit_item.php?id=${id}`;
}

document.querySelector('.close').onclick = function() {
    document.getElementById('itemModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('itemModal')) {
        document.getElementById('itemModal').style.display = 'none';
    }
}
</script>

<?php include '../templates/footer.php'; ?>
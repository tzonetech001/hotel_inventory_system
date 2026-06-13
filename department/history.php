<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

session_start();

if (!isset($_SESSION['department_user_id'])) {
    header("Location: login.php");
    exit();
}

$department_id = $_SESSION['department_id'];

// Get confirmed requests history
$sql = "SELECT sr.*, i.item_name, i.unit, u.fullname as requester_name,
        du.fullname as confirmed_by_name
        FROM stock_requests sr
        JOIN inventory_items i ON sr.item_id = i.id
        JOIN users u ON sr.requested_by = u.id
        LEFT JOIN department_users du ON sr.department_user_id = du.id
        WHERE sr.department_id = ? AND sr.status IN ('confirmed', 'cancelled', 'rejected')
        ORDER BY sr.confirmed_at DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<div class="history-page">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Confirmation History</h1>
        <p>View all confirmed stock requests for your department</p>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Request History</h3>
            <span class="count"><?php echo count($history); ?> records</span>
        </div>
        <div class="card-body">
            <?php if(count($history) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Request Code</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Requested By</th>
                                <th>Request Date</th>
                                <th>Confirmed At</th>
                                <th>Confirmed By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $record): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($record['request_code']); ?></strong></td
                                    <td><?php echo htmlspecialchars($record['item_name']); ?> (<?php echo $record['unit']; ?>)</td
                                    <td><?php echo $record['quantity']; ?></td
                                    <td><?php echo htmlspecialchars($record['requester_name']); ?></td
                                    <td><?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></td
                                    <td>
                                        <?php if($record['confirmed_at']): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($record['confirmed_at'])); ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td
                                    <td>
                                        <?php if($record['confirmed_by_name']): ?>
                                            <?php echo htmlspecialchars($record['confirmed_by_name']); ?>
                                        <?php else: ?>
                                            System
                                        <?php endif; ?>
                                    </td
                                    <td>
                                        <span class="status-badge <?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                     </td
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No History Found</h4>
                    <p>No confirmed requests yet for your department.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .history-page {
        max-width: 1400px;
        margin: 0 auto;
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
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
    }
    
    .count {
        background: #E5E7EB;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        color: #374151;
    }
    
    .card-body {
        padding: 0;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-table th {
        background: #F9FAFB;
        font-weight: 600;
        font-size: 12px;
        color: #374151;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-badge.confirmed {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-badge.cancelled,
    .status-badge.rejected {
        background: #FEE2E2;
        color: #991B1B;
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
    
    .empty-state h4 {
        color: #374151;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: #6B7280;
    }
    
    @media (max-width: 768px) {
        .data-table th,
        .data-table td {
            padding: 8px 10px;
            font-size: 12px;
        }
    }
</style>

<?php include 'footer.php'; ?>
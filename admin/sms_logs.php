// admin/sms_logs.php
<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

checkAuth(['Admin', 'Hotel Manager']);

// Get SMS logs
$limit = 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM sms_logs WHERE 1=1";
$count_sql = "SELECT COUNT(*) as total FROM sms_logs WHERE 1=1";

if (!empty($status_filter)) {
    $sql .= " AND status = '$status_filter'";
    $count_sql .= " AND status = '$status_filter'";
}
if (!empty($search)) {
    $search = $db->real_escape_string($search);
    $sql .= " AND (phone_number LIKE '%$search%' OR message LIKE '%$search%')";
    $count_sql .= " AND (phone_number LIKE '%$search%' OR message LIKE '%$search%')";
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$result = $db->query($sql);
$logs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$count_result = $db->query($count_sql);
$total = $count_result ? $count_result->fetch_assoc()['total'] : 0;

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-envelope"></i> SMS Logs</h1>
        <p>Track all SMS messages sent from the system</p>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search by phone or message..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                    <button type="submit" class="btn-primary">Filter</button>
                    <?php if(!empty($search) || !empty($status_filter)): ?>
                        <a href="sms_logs.php" class="btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> SMS History (<?php echo $total; ?> total)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Phone Number</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Response Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($logs) > 0): ?>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                    <td class="message-cell"><?php echo nl2br(htmlspecialchars(substr($log['message'], 0, 100))); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $log['status']; ?>">
                                            <?php echo $log['status'] == 'sent' ? '✅ Sent' : '❌ Failed'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $log['response_code'] ?: '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No SMS logs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total > $limit): ?>
                <div class="pagination">
                    <?php if($offset > 0): ?>
                        <a href="?offset=<?php echo max(0, $offset - $limit); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn-secondary">← Previous</a>
                    <?php endif; ?>
                    <?php if($offset + $limit < $total): ?>
                        <a href="?offset=<?php echo $offset + $limit; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn-secondary">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.filter-form .filter-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.filter-form input, .filter-form select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    min-width: 200px;
}
.message-cell {
    max-width: 300px;
    word-wrap: break-word;
    font-size: 12px;
}
.status-sent { background: #D1FAE5; color: #065F46; }
.status-failed { background: #FEE2E2; color: #991B1B; }
.pagination {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 10px;
}
</style>

<?php include '../templates/footer.php'; ?>
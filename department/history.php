<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

if (!isset($_SESSION['department_user_id'])) {
    header("Location: login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$department_name = $_SESSION['department_name'];

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
    
    <div class="stats-summary">
        <div class="stat-card small">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Records</span>
                <span class="stat-value"><?php echo count($history); ?></span>
            </div>
        </div>
        <div class="stat-card small">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">This Month</span>
                <span class="stat-value">
                    <?php 
                    $current_month = date('m');
                    $current_year = date('Y');
                    $month_count = 0;
                    foreach($history as $record) {
                        if(isset($record['confirmed_at']) && date('m', strtotime($record['confirmed_at'])) == $current_month && date('Y', strtotime($record['confirmed_at'])) == $current_year) {
                            $month_count++;
                        }
                    }
                    echo $month_count;
                    ?>
                </span>
            </div>
        </div>
        <div class="stat-card small">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Items</span>
                <span class="stat-value">
                    <?php 
                    $total_items = 0;
                    foreach($history as $record) {
                        $total_items += $record['quantity'];
                    }
                    echo $total_items;
                    ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Request History</h3>
            <div class="header-actions">
                <span class="count"><?php echo count($history); ?> records</span>
                <div class="action-buttons">
                    <button onclick="exportToCSV()" class="btn-export csv">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button onclick="exportToExcel()" class="btn-export excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button onclick="window.print()" class="btn-print">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if(count($history) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table" id="historyTable">
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
                                <tr class="status-<?php echo $record['status']; ?>">
                                    <td data-label="Request Code">
                                        <strong><?php echo htmlspecialchars($record['request_code']); ?></strong>
                                        <?php if($record['status'] == 'cancelled' || $record['status'] == 'rejected'): ?>
                                            <br>
                                            <small class="reason-text">
                                                <?php echo htmlspecialchars($record['rejection_reason'] ?? 'No reason provided'); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Item">
                                        <?php echo htmlspecialchars($record['item_name']); ?>
                                        <small>(<?php echo $record['unit']; ?>)</small>
                                    </td>
                                    <td data-label="Quantity">
                                        <span class="quantity-badge"><?php echo $record['quantity']; ?></span>
                                    </td>
                                    <td data-label="Requested By">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($record['requester_name']); ?>
                                    </td>
                                    <td data-label="Request Date">
                                        <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($record['created_at'])); ?>
                                        <br>
                                        <small><?php echo date('H:i', strtotime($record['created_at'])); ?></small>
                                    </td>
                                    <td data-label="Confirmed At">
                                        <?php if($record['confirmed_at']): ?>
                                            <i class="fas fa-check-circle"></i> <?php echo date('d/m/Y', strtotime($record['confirmed_at'])); ?>
                                            <br>
                                            <small><?php echo date('H:i', strtotime($record['confirmed_at'])); ?></small>
                                        <?php else: ?>
                                            <span class="not-confirmed">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Confirmed By">
                                        <?php if($record['confirmed_by_name']): ?>
                                            <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($record['confirmed_by_name']); ?>
                                        <?php else: ?>
                                            <span class="system-text">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php if($record['status'] == 'confirmed'): ?>
                                            <span class="status-badge confirmed">
                                                <i class="fas fa-check-circle"></i> Confirmed
                                            </span>
                                        <?php elseif($record['status'] == 'cancelled'): ?>
                                            <span class="status-badge cancelled">
                                                <i class="fas fa-times-circle"></i> Cancelled
                                            </span>
                                        <?php elseif($record['status'] == 'rejected'): ?>
                                            <span class="status-badge rejected">
                                                <i class="fas fa-ban"></i> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination" id="pagination"></div>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No History Found</h4>
                    <p>No confirmed requests yet for your department.</p>
                    <a href="dashboard.php" class="btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .history-page {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-header {
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        color: #1E3A8A;
        margin-bottom: 5px;
        font-size: 28px;
    }
    
    .page-header p {
        color: #6B7280;
    }
    
    /* Stats Summary */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .stat-card.small {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .stat-card.small .stat-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    
    .stat-card.small .stat-icon i {
        font-size: 22px;
    }
    
    .stat-card.small:nth-child(1) .stat-icon {
        background: #D1FAE5;
        color: #10B981;
    }
    
    .stat-card.small:nth-child(2) .stat-icon {
        background: #DBEAFE;
        color: #1E3A8A;
    }
    
    .stat-card.small:nth-child(3) .stat-icon {
        background: #FEF3C7;
        color: #F59E0B;
    }
    
    .stat-card.small .stat-info .stat-label {
        font-size: 12px;
        color: #6B7280;
        display: block;
    }
    
    .stat-card.small .stat-info .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #1F2937;
        display: block;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 25px;
    }
    
    .card-header {
        padding: 20px 24px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .card-header h3 {
        margin: 0;
        color: #1E3A8A;
        font-size: 18px;
    }
    
    .header-actions {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .count {
        background: #E5E7EB;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        color: #374151;
    }
    
    .btn-print {
        background: #6B7280;
        color: white;
        border: none;
        padding: 6px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-print:hover {
        background: #4B5563;
        transform: translateY(-1px);
    }
    
    .btn-export {
        padding: 6px 14px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-export.csv {
        background: #10B981;
        color: white;
    }
    
    .btn-export.csv:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .btn-export.excel {
        background: #1E3A8A;
        color: white;
    }
    
    .btn-export.excel:hover {
        background: #3B82F6;
        transform: translateY(-1px);
    }
    
    .card-body {
        padding: 0;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }
    
    .data-table th,
    .data-table td {
        padding: 14px 12px;
        text-align: left;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: middle;
    }
    
    .data-table th {
        background: #F9FAFB;
        font-weight: 600;
        font-size: 12px;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table tbody tr:hover {
        background: #F9FAFB;
    }
    
    .data-table tbody tr.status-cancelled:hover,
    .data-table tbody tr.status-rejected:hover {
        background: #FEF2F2;
    }
    
    .quantity-badge {
        display: inline-block;
        background: #EFF6FF;
        color: #1E3A8A;
        padding: 4px 10px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px;
    }
    
    .reason-text {
        font-size: 11px;
        color: #EF4444;
        display: inline-block;
        margin-top: 4px;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-badge.confirmed {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .status-badge.cancelled {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .status-badge.rejected {
        background: #FFE4E6;
        color: #BE123C;
    }
    
    .not-confirmed {
        color: #9CA3AF;
    }
    
    .system-text {
        color: #6B7280;
        font-style: italic;
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
        margin-bottom: 20px;
    }
    
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #FF6B6B;
        color: white;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-primary:hover {
        background: #e55a5a;
        transform: translateY(-2px);
    }
    
    /* Pagination */
    .pagination {
        padding: 20px 24px;
        display: flex;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
        border-top: 1px solid #E5E7EB;
    }
    
    .pagination button {
        padding: 8px 14px;
        border: 1px solid #E5E7EB;
        background: white;
        color: #374151;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .pagination button:hover {
        background: #1E3A8A;
        color: white;
        border-color: #1E3A8A;
    }
    
    .pagination button.active {
        background: #1E3A8A;
        color: white;
        border-color: #1E3A8A;
    }
    
    /* Print Styles */
    @media print {
        .department-header,
        .department-nav,
        .stats-summary,
        .btn-print,
        .btn-export,
        .pagination,
        .footer,
        .mobile-sidebar,
        .sidebar-overlay,
        .mobile-menu-toggle {
            display: none !important;
        }
        
        .history-page {
            padding: 0;
            margin: 0;
        }
        
        .card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
        }
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .history-page {
            padding: 15px;
        }
        
        .page-header h1 {
            font-size: 22px;
        }
        
        .stats-summary {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .card-header {
            padding: 15px 18px;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .header-actions {
            width: 100%;
            justify-content: space-between;
        }
        
        .action-buttons {
            width: 100%;
            justify-content: flex-start;
        }
        
        .data-table {
            min-width: 600px;
        }
        
        .data-table th,
        .data-table td {
            padding: 10px 8px;
            font-size: 12px;
        }
    }
    
    /* Small phones - Stacked card view */
    @media (max-width: 480px) {
        .data-table thead {
            display: none;
        }
        
        .data-table tbody tr {
            display: block;
            margin-bottom: 15px;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 12px;
            background: white;
        }
        
        .data-table tbody tr.status-cancelled,
        .data-table tbody tr.status-rejected {
            border-left: 4px solid #EF4444;
        }
        
        .data-table tbody tr.status-confirmed {
            border-left: 4px solid #10B981;
        }
        
        .data-table td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 8px;
            border-bottom: 1px solid #F3F4F6;
        }
        
        .data-table td:last-child {
            border-bottom: none;
        }
        
        .data-table td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .quantity-badge {
            font-size: 13px;
        }
        
        .reason-text {
            margin-top: 0;
            margin-left: 10px;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .btn-export, .btn-print {
            justify-content: center;
        }
    }
    
    /* Touch-friendly buttons */
    @media (max-width: 768px) {
        .btn-print,
        .btn-export,
        .btn-primary {
            min-height: 44px;
        }
    }
</style>

<script>
    // Check if device is mobile or desktop
    function isMobileDevice() {
        return window.innerWidth <= 768;
    }
    
    // Pagination
    const rowsPerPage = 15;
    let currentPage = 1;
    const table = document.getElementById('historyTable');
    
    if (table) {
        const tbody = table.querySelector('tbody');
        const rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
        const totalPages = Math.ceil(rows.length / rowsPerPage);
        
        function displayPage(page) {
            if (!rows.length) return;
            
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            
            rows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });
        }
        
        function setupPagination() {
            if (totalPages <= 1) return;
            
            const paginationDiv = document.getElementById('pagination');
            if (!paginationDiv) return;
            
            paginationDiv.innerHTML = '';
            
            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i> Previous';
            prevBtn.onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(currentPage);
                    updateActiveButton();
                }
            };
            paginationDiv.appendChild(prevBtn);
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.onclick = () => {
                    currentPage = i;
                    displayPage(currentPage);
                    updateActiveButton();
                };
                if (i === currentPage) {
                    pageBtn.classList.add('active');
                }
                paginationDiv.appendChild(pageBtn);
            }
            
            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
            nextBtn.onclick = () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    displayPage(currentPage);
                    updateActiveButton();
                }
            };
            paginationDiv.appendChild(nextBtn);
        }
        
        function updateActiveButton() {
            const paginationDiv = document.getElementById('pagination');
            if (!paginationDiv) return;
            
            const buttons = paginationDiv.querySelectorAll('button');
            buttons.forEach(btn => {
                if (btn.textContent === currentPage.toString()) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }
        
        // Initialize
        displayPage(1);
        setupPagination();
    }
    
    // Export to CSV
    function exportToCSV() {
        const table = document.getElementById('historyTable');
        if (!table) return;
        
        let csv = [];
        // Get headers
        const headers = [];
        const ths = table.querySelectorAll('thead th');
        ths.forEach(th => {
            headers.push(th.textContent.trim());
        });
        csv.push(headers.join(','));
        
        // Get data
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                let text = cell.textContent.trim();
                // Remove extra spaces and newlines
                text = text.replace(/\s+/g, ' ').replace(/\n/g, ' ');
                // Wrap in quotes if contains comma
                if (text.includes(',') || text.includes('"')) {
                    text = text.replace(/"/g, '""');
                    text = `"${text}"`;
                }
                rowData.push(text);
            });
            csv.push(rowData.join(','));
        });
        
        // Download
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `history_export_${new Date().toISOString().slice(0,19)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    // Export to Excel
    function exportToExcel() {
        const table = document.getElementById('historyTable');
        if (!table) return;
        
        const cloneTable = table.cloneNode(true);
        const tbody = cloneTable.querySelector('tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(row => {
                if (row.style.display === 'none') {
                    row.remove();
                }
            });
        }
        
        const html = `
            <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Stock Request History</title>
                    <style>
                        th { background: #1E3A8A; color: white; padding: 8px; }
                        td { padding: 6px; border: 1px solid #ddd; }
                        table { border-collapse: collapse; width: 100%; }
                    </style>
                </head>
                <body>
                    <h2>Stock Request History - <?php echo htmlspecialchars($department_name); ?></h2>
                    <p>Generated on: <?php echo date('d/m/Y H:i:s'); ?></p>
                    ${cloneTable.outerHTML}
                </body>
            </html>
        `;
        
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `history_export_${new Date().toISOString().slice(0,19)}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    // Mobile Sidebar Toggle Functionality
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    
    function openSidebar() {
        if (isMobileDevice()) {
            mobileSidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeSidebar() {
        if (isMobileDevice()) {
            mobileSidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    function toggleSidebar() {
        if (isMobileDevice()) {
            if (mobileSidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
    }
    
    // Attach event listeners
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', toggleSidebar);
    }
    
    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', closeSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // Profile Dropdown
    const profileDropdownBtn = document.getElementById('profileDropdownBtn');
    const profileDropdownContent = document.getElementById('profileDropdownContent');
    
    if (profileDropdownBtn) {
        profileDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdownContent.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (profileDropdownBtn && profileDropdownContent) {
                if (!profileDropdownBtn.contains(e.target) && !profileDropdownContent.contains(e.target)) {
                    profileDropdownContent.classList.remove('show');
                }
            }
        });
    }
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileSidebar && mobileSidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (!isMobileDevice()) {
                if (mobileSidebar && mobileSidebar.classList.contains('open')) {
                    closeSidebar();
                }
            }
        }, 250);
    });
    
    // Add active class to current nav item
    const currentPageUrl = window.location.pathname.split('/').pop();
    
    document.querySelectorAll('.department-nav a, .sidebar-menu a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPageUrl || (currentPageUrl === '' && href === 'dashboard.php')) {
            link.classList.add('active');
        }
    });
    
    // Prevent body scroll when sidebar is open
    if (mobileSidebar) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    if (mobileSidebar.classList.contains('open')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                }
            });
        });
        observer.observe(mobileSidebar, { attributes: true });
    }
</script>

<?php
// No footer include needed - header.php already contains footer
?>
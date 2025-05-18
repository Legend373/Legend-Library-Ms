<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = "You don't have permission to access the admin panel.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $base_url . "/index.php");
    exit;
}

// Get stats
try {
    // Total users
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $total_users = $stmt->fetchColumn();
    
    // Total materials
    $stmt = $conn->prepare("SELECT COUNT(*) FROM materials");
    $stmt->execute();
    $total_materials = $stmt->fetchColumn();
    
    // Total books
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books");
    $stmt->execute();
    $total_books = $stmt->fetchColumn();
    
    // Total downloads
    $stmt = $conn->prepare("SELECT COUNT(*) FROM material_downloads");
    $stmt->execute();
    $total_downloads = $stmt->fetchColumn();
    
    // Total borrowed books
    $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowed_books");
    $stmt->execute();
    $total_borrowed = $stmt->fetchColumn();
    
    // Active borrowed books
    $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowed_books WHERE status = 'borrowed'");
    $stmt->execute();
    $active_borrowed = $stmt->fetchColumn();
    
    // Overdue books
    $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowed_books WHERE status = 'borrowed' AND due_date < CURDATE()");
    $stmt->execute();
    $overdue_books = $stmt->fetchColumn();
    
    // New users this month
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $new_users_month = $stmt->fetchColumn();
    
    // New materials this month
    $stmt = $conn->prepare("SELECT COUNT(*) FROM materials WHERE MONTH(upload_date) = MONTH(CURRENT_DATE()) AND YEAR(upload_date) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $new_materials_month = $stmt->fetchColumn();
} catch(PDOException $e) {
    $error_message = "Error fetching stats: " . $e->getMessage();
}

// Get recent users
try {
    $stmt = $conn->prepare("
        SELECT user_id, username, email, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching recent users: " . $e->getMessage();
}

// Get recent materials
try {
    $stmt = $conn->prepare("
        SELECT m.material_id, m.title, m.category, m.upload_date, u.username as uploader
        FROM materials m
        JOIN users u ON m.uploaded_by = u.user_id
        ORDER BY m.upload_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching recent materials: " . $e->getMessage();
}

// Get recent borrowed books
try {
    $stmt = $conn->prepare("
        SELECT bb.borrow_id, bb.borrow_date, bb.due_date, bb.status,
               b.title as book_title, u.username
        FROM borrowed_books bb
        JOIN books b ON bb.book_id = b.book_id
        JOIN users u ON bb.user_id = u.user_id
        ORDER BY bb.borrow_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_borrowed = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching recent borrowed books: " . $e->getMessage();
}

// Get system logs
try {
    $stmt = $conn->prepare("
        SELECT l.log_id, l.log_type, l.message, l.timestamp, u.username
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        ORDER BY l.timestamp DESC
        LIMIT 20
    ");
    $stmt->execute();
    $system_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching system logs: " . $e->getMessage();
}

// Page title for header
$page_title = "Admin Panel";
?>
<?php include 'includes/header.php'; ?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Admin Panel</h1>
        <p>Manage your library system</p>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="admin-dashboard">
        <div class="admin-sidebar">
            <div class="admin-nav">
                <ul>
                    <li class="active"><a href="#dashboard" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#users" data-tab="users"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="#materials" data-tab="materials"><i class="fas fa-file-alt"></i> Materials</a></li>
                    <li><a href="#books" data-tab="books"><i class="fas fa-book"></i> Books</a></li>
                    <li><a href="#borrowings" data-tab="borrowings"><i class="fas fa-exchange-alt"></i> Borrowings</a></li>
                    <li><a href="#logs" data-tab="logs"><i class="fas fa-history"></i> System Logs</a></li>
                    <li><a href="#settings" data-tab="settings"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="admin-actions">
                <a href="<?php echo $base_url; ?>/admin_users.php" class="btn primary btn-block">Manage Users</a>
                <a href="<?php echo $base_url; ?>/admin_materials.php" class="btn primary btn-block">Manage Materials</a>
                <a href="<?php echo $base_url; ?>/admin_books.php" class="btn primary btn-block">Manage Books</a>
                <a href="<?php echo $base_url; ?>/admin_borrowings.php" class="btn primary btn-block">Manage Borrowings</a>
                <a href="<?php echo $base_url; ?>/admin_settings.php" class="btn secondary btn-block">System Settings</a>
            </div>
        </div>
        
        <div class="admin-content">
            <div class="admin-tab active" id="dashboard">
                <h2>Dashboard Overview</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-footer">
                            <!-- <span class="stat-change positive">+<?php echo $new_users_month; ?> this month</span> -->
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_materials); ?></div>
                            <div class="stat-label">Total Materials</div>
                        </div>
                        <div class="stat-footer">
                            <!-- <span class="stat-change positive">+<?php echo $new_materials_month; ?> this month</span> -->
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_books); ?></div>
                            <div class="stat-label">Total Books</div>
                        </div>
                        <div class="stat-footer">
                            <span class="stat-info"><?php echo number_format($active_borrowed); ?> currently borrowed</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-download"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_downloads); ?></div>
                            <div class="stat-label">Total Downloads</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="stat-content">
                            <!-- <div class="stat-value"><?php echo number_format($total_borrowed); ?></div> -->
                            <div class="stat-label">Total Borrowings</div>
                        </div>
                        <div class="stat-footer">
                            <!-- <span class="stat-info"><?php echo number_format($active_borrowed); ?> active</span> -->
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-content">
                            <!-- <div class="stat-value"><?php echo number_format($overdue_books); ?></div> -->
                            <div class="stat-label">Overdue Books</div>
                        </div>
                        <div class="stat-footer">
                            <a href="<?php echo $base_url; ?>/admin_borrowings.php?filter=overdue" class="stat-link">View all</a>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Recent Users</h3>
                            <a href="<?php echo $base_url; ?>/admin_users.php" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recent_users)): ?>
                                <p class="no-data">No users found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <!-- <th>Full Name</th> -->
                                                <th>Role</th>
                                                <!-- <th>Status</th> -->
                                                <th>Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_users as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <!-- <td><?php echo htmlspecialchars($user['full_name']); ?></td> -->
                                                    <td><span class="badge badge-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                                    <!-- <td><span class="status-badge status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td> -->
                                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Recent Materials</h3>
                            <a href="<?php echo $base_url; ?>/admin_materials.php" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recent_materials)): ?>
                                <p class="no-data">No materials found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Category</th>
                                                <th>Uploader</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_materials as $material): ?>
                                                <tr>
                                                    <td>
                                                        <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>">
                                                            <?php echo htmlspecialchars($material['title']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($material['category']); ?></td>
                                                    <td><?php echo htmlspecialchars($material['uploader']); ?></td>
                                                    <td><?php echo formatDate($material['upload_date']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Recent Borrowings</h3>
                            <a href="<?php echo $base_url; ?>/admin_borrowings.php" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recent_borrowed)): ?>
                                <p class="no-data">No borrowings found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Book</th>
                                                <th>User</th>
                                                <th>Borrow Date</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_borrowed as $borrow): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($borrow['book_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($borrow['username']); ?></td>
                                                    <td><?php echo formatDate($borrow['borrow_date']); ?></td>
                                                    <td>
                                                        <?php 
                                                            echo formatDate($borrow['due_date']);
                                                            
                                                            $due_date = new DateTime($borrow['due_date']);
                                                            $today = new DateTime();
                                                            
                                                            if ($borrow['status'] === 'borrowed' && $today > $due_date) {
                                                                echo ' <span class="badge badge-danger">Overdue</span>';
                                                            }
                                                        ?>
                                                    </td>
                                                    <td><span class="status-badge status-<?php echo $borrow['status']; ?>"><?php echo ucfirst($borrow['status']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>System Logs</h3>
                            <a href="<?php echo $base_url; ?>/admin_logs.php" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($system_logs)): ?>
                                <p class="no-data">No logs found</p>
                            <?php else: ?>
                                <div class="logs-list">
                                    <?php foreach ($system_logs as $log): ?>
                                        <div class="log-item">
                                            <div class="log-icon">
                                                <?php
                                                    $icon_class = 'fas fa-info-circle';
                                                    $log_class = '';
                                                    
                                                    switch ($log['log_type']) {
                                                        case 'error':
                                                            $icon_class = 'fas fa-exclamation-circle';
                                                            $log_class = 'log-error';
                                                            break;
                                                        case 'warning':
                                                            $icon_class = 'fas fa-exclamation-triangle';
                                                            $log_class = 'log-warning';
                                                            break;
                                                        case 'success':
                                                            $icon_class = 'fas fa-check-circle';
                                                            $log_class = 'log-success';
                                                            break;
                                                        case 'info':
                                                        default:
                                                            $icon_class = 'fas fa-info-circle';
                                                            $log_class = 'log-info';
                                                    }
                                                ?>
                                                <i class="<?php echo $icon_class; ?>"></i>
                                            </div>
                                            <div class="log-content">
                                                <div class="log-message"><?php echo htmlspecialchars($log['message']); ?></div>
                                                <div class="log-meta">
                                                    <span class="log-time"><?php echo formatDateTime($log['timestamp']); ?></span>
                                                    <?php if (!empty($log['username'])): ?>
                                                        <span class="log-user">by <?php echo htmlspecialchars($log['username']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="admin-tab" id="users">
                <h2>Users Management</h2>
                <p>This section will be available in the full version.</p>
                <a href="<?php echo $base_url; ?>/admin_users.php" class="btn primary">Go to Users Management</a>
            </div>
            
            <div class="admin-tab" id="materials">
                <h2>Materials Management</h2>
                <p>This section will be available in the full version.</p>
                <a href="<?php echo $base_url; ?>/admin_materials.php" class="btn primary">Go to Materials Management</a>
            </div>
            
            <div class="admin-tab" id="books">
                <h2>Books Management</h2>
                <p>This section will be available in the full version.</p>
                <a href="<?php echo $base_url; ?>/admin_books.php" class="btn primary">Go to Books Management</a>
            </div>
            
            <div class="admin-tab" id="borrowings">
                <h2>Borrowings Management</h2>
                <p>This section will be available in the full version.</p>
                <a href="<?php echo $base_url; ?>/admin_borrowings.php" class="btn primary">Go to Borrowings Management</a>
            </div>
            
            <div class="admin-tab" id="logs">
                <h2>System Logs</h2>
                <p>This section will be available in the full version.</p>
                <a href="<?php echo $base_url; ?>/admin_logs.php" class="btn primary">Go to System Logs</a>
            </div>
            
            <div class="admin-tab" id="settings">
                <h2>System Settings</h2>
                <p>This section will be available in the full version.</p>
                <a href="<?php echo $base_url; ?>/admin_settings.php" class="btn primary">Go to System Settings</a>
            </div>
        </div>
    </div>
</div> 

<style>
    /* Admin panel specific styles with brown theme */
    .admin-container {
        padding: 2rem 0;
    }
    
    .admin-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .admin-header h1 {
        color: #8B4513;
        margin-bottom: 0.5rem;
    }
    
    .admin-header p {
        color: #666;
        font-size: 1.1rem;
    }
    
    .admin-dashboard {
        display: grid;
        grid-template-columns: 250px 1fr;
        gap: 2rem;
    }
    
    .admin-sidebar {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        height: fit-content;
    }
    
    .admin-nav ul {
        list-style: none;
        padding: 0;
        margin: 0 0 1.5rem 0;
    }
    
    .admin-nav ul li {
        margin-bottom: 0.5rem;
    }
    
    .admin-nav ul li a {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.8rem 1rem;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        transition: all 0.3s ease;
    }
    
    .admin-nav ul li a:hover {
        background-color: #FFF8DC;
        color: #8B4513;
    }
    
    .admin-nav ul li.active a {
        background-color: #8B4513;
        color: white;
    }
    
    .admin-nav ul li a i {
        width: 20px;
        text-align: center;
    }
    
    .admin-actions {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }
    
    .admin-content {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 2rem;
    }
    
    .admin-tab {
        display: none;
    }
    
    .admin-tab.active {
        display: block;
    }
    
    .admin-tab h2 {
        color: #8B4513;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
        border-top: 4px solid #8B4513;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .stat-icon {
        font-size: 2rem;
        color: #8B4513;
        margin-bottom: 1rem;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #333;
        line-height: 1.2;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-footer {
        margin-top: 0.5rem;
        font-size: 0.85rem;
    }
    
    .stat-change {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 20px;
        font-weight: 500;
    }
    
    .stat-change.positive {
        background-color: #d4edda;
        color: #155724;
    }
    
    .stat-change.negative {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .stat-info {
        color: #666;
    }
    
    .stat-link {
        color: #8B4513;
        text-decoration: none;
        font-weight: 500;
    }
    
    .stat-link:hover {
        text-decoration: underline;
    }
    
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
    }
    
    .dashboard-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        background-color: #FFF8DC;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .card-header h3 {
        color: #8B4513;
        margin: 0;
        font-size: 1.2rem;
    }
    
    .card-header .view-all {
        color: #8B4513;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .card-header .view-all:hover {
        text-decoration: underline;
    }
    
    .card-content {
        padding: 1.5rem;
    }
    
    .no-data {
        text-align: center;
        color: #666;
        font-style: italic;
        padding: 2rem 0;
    }
    
    .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
    }
    
    .table th,
    .table td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
    }
    
    .table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
        color: #333;
        font-weight: 600;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25em 0.6em;
        font-size: 0.75em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }
    
    .status-active {
        background-color: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .status-borrowed {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .status-returned {
        background-color: #d4edda;
        color: #155724;
    }
    
    .status-overdue {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .logs-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .log-item {
        display: flex;
        gap: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .log-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    
    .log-icon {
        font-size: 1.2rem;
        width: 24px;
        text-align: center;
    }
    
    .log-error .log-icon {
        color: #721c24;
    }
    
    .log-warning .log-icon {
        color: #856404;
    }
    
    .log-success .log-icon {
        color: #155724;
    }
    
    .log-info .log-icon {
        color: #0c5460;
    }
    
    .log-content {
        flex: 1;
    }
    
    .log-message {
        margin-bottom: 0.3rem;
        color: #333;
    }
    
    .log-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        color: #666;
    }
    
    @media (max-width: 992px) {
        .admin-dashboard {
            grid-template-columns: 1fr;
        }
        
        .admin-sidebar {
            margin-bottom: 1.5rem;
        }
        
        .admin-nav ul {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .admin-nav ul li {
            margin-bottom: 0;
        }
        
        .admin-actions {
            flex-direction: row;
            flex-wrap: wrap;
        }
        
        .admin-actions .btn {
            flex: 1;
        }
        
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        
        .admin-content {
            padding: 1.5rem;
        }
    }
    
    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .admin-nav ul {
            flex-direction: column;
        }
        
        .admin-actions {
            flex-direction: column;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab navigation
        const tabLinks = document.querySelectorAll('.admin-nav a');
        const tabContents = document.querySelectorAll('.admin-tab');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and links
                tabContents.forEach(tab => tab.classList.remove('active'));
                tabLinks.forEach(link => link.parentElement.classList.remove('active'));
                
                // Add active class to current tab and link
                document.getElementById(tabId).classList.add('active');
                this.parentElement.classList.add('active');
                
                // Update URL hash
                window.location.hash = tabId;
            });
        });
        
        // Check for hash in URL
        const hash = window.location.hash.substring(1);
        if (hash && document.getElementById(hash)) {
            tabContents.forEach(tab => tab.classList.remove('active'));
            tabLinks.forEach(link => link.parentElement.classList.remove('active'));
            
            document.getElementById(hash).classList.add('active');
            document.querySelector(`[data-tab="${hash}"]`).parentElement.classList.add('active');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
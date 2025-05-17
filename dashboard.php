<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $base_url . "/dashboard.php";
    $_SESSION['flash_message'] = "Please log in to access your dashboard.";
    $_SESSION['flash_type'] = "warning";
    header("Location: " . $base_url . "/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user information
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
}

// Get user's recent activities
try {
    $stmt = $conn->prepare("
        SELECT a.activity_type, a.timestamp, a.details
        FROM user_activities a
        WHERE a.user_id = :user_id
        ORDER BY a.timestamp DESC
        LIMIT 10
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching activities: " . $e->getMessage();
}

// Get user's borrowed books
try {
    $stmt = $conn->prepare("
        SELECT b.book_id, b.title, b.author, bb.borrow_date, bb.due_date, bb.status
        FROM borrowed_books bb
        JOIN books b ON bb.book_id = b.book_id
        WHERE bb.user_id = :user_id AND bb.status != 'returned'
        ORDER BY bb.due_date ASC
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $borrowed_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching borrowed books: " . $e->getMessage();
}

// Get user's uploaded materials
try {
    $stmt = $conn->prepare("
        SELECT m.material_id, m.title, m.category, m.upload_date,
               (SELECT COUNT(*) FROM material_downloads WHERE material_id = m.material_id) as download_count
        FROM materials m
        WHERE m.uploaded_by = :user_id
        ORDER BY m.upload_date DESC
        LIMIT 5
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $uploaded_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching uploaded materials: " . $e->getMessage();
}

// Get user's favorite materials
try {
    $stmt = $conn->prepare("
        SELECT m.material_id, m.title, m.category, m.upload_date, u.username as uploader
        FROM material_favorites mf
        JOIN materials m ON mf.material_id = m.material_id
        JOIN users u ON m.uploaded_by = u.user_id
        WHERE mf.user_id = :user_id
        ORDER BY mf.date_added DESC
        LIMIT 5
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $favorite_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching favorite materials: " . $e->getMessage();
}

// Log dashboard visit
logActivity($user_id, 'visit_dashboard');

// Page title for header
$page_title = "Dashboard";
?>
<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p>Manage your library activities and resources</p>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-book"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($borrowed_books); ?></div>
                <div class="stat-label">Books Borrowed</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-upload"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($uploaded_materials); ?></div>
                <div class="stat-label">Materials Uploaded</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-heart"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($favorite_materials); ?></div>
                <div class="stat-label">Favorites</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($activities); ?></div>
                <div class="stat-label">Recent Activities</div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <!-- Borrowed Books Section -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Borrowed Books</h2>
                <a href="<?php echo $base_url; ?>/borrowed_books.php" class="view-all">View All</a>
            </div>
            
            <?php if (empty($borrowed_books)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-book"></i></div>
                    <p>You haven't borrowed any books yet.</p>
                    <a href="<?php echo $base_url; ?>/books.php" class="btn primary">Browse Books</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowed_books as $book): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td>
                                        <?php 
                                            $due_date = new DateTime($book['due_date']);
                                            $today = new DateTime();
                                            $days_remaining = $today->diff($due_date)->days;
                                            $is_overdue = $today > $due_date;
                                            
                                            echo formatDate($book['due_date']);
                                            
                                            if ($is_overdue) {
                                                echo ' <span class="badge badge-danger">Overdue</span>';
                                            } elseif ($days_remaining <= 3) {
                                                echo ' <span class="badge badge-warning">Due soon</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($book['status']); ?>">
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo $base_url; ?>/return_book.php?id=<?php echo $book['book_id']; ?>" class="btn small primary">Return</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Uploaded Materials Section -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Your Uploads</h2>
                <a href="<?php echo $base_url; ?>/my_uploads.php" class="view-all">View All</a>
            </div>
            
            <?php if (empty($uploaded_materials)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-upload"></i></div>
                    <p>You haven't uploaded any materials yet.</p>
                    <a href="<?php echo $base_url; ?>/upload.php" class="btn primary">Upload Material</a>
                </div>
            <?php else: ?>
                <div class="materials-grid">
                    <?php foreach ($uploaded_materials as $material): ?>
                        <div class="material-card">
                            <h3 class="material-title">
                                <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>">
                                    <?php echo htmlspecialchars($material['title']); ?>
                                </a>
                            </h3>
                            <div class="material-meta">
                                <span class="category"><?php echo htmlspecialchars($material['category']); ?></span>
                                <span class="date"><?php echo formatDate($material['upload_date']); ?></span>
                                <span class="downloads"><?php echo $material['download_count']; ?> downloads</span>
                            </div>
                            <div class="material-actions">
                                <a href="<?php echo $base_url; ?>/edit_material.php?id=<?php echo $material['material_id']; ?>" class="btn small secondary">Edit</a>
                                <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>" class="btn small primary">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activities Section -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Activities</h2>
                <a href="<?php echo $base_url; ?>/activities.php" class="view-all">View All</a>
            </div>
            
            <?php if (empty($activities)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-history"></i></div>
                    <p>No recent activities to display.</p>
                </div>
            <?php else: ?>
                <div class="activity-timeline">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                    $icon = 'fas fa-info-circle';
                                    switch ($activity['activity_type']) {
                                        case 'login':
                                            $icon = 'fas fa-sign-in-alt';
                                            break;
                                        case 'logout':
                                            $icon = 'fas fa-sign-out-alt';
                                            break;
                                        case 'borrow_book':
                                            $icon = 'fas fa-book';
                                            break;
                                        case 'return_book':
                                            $icon = 'fas fa-undo';
                                            break;
                                        case 'upload_material':
                                            $icon = 'fas fa-upload';
                                            break;
                                        case 'download_material':
                                            $icon = 'fas fa-download';
                                            break;
                                        case 'favorite_material':
                                            $icon = 'fas fa-heart';
                                            break;
                                        case 'visit_homepage':
                                            $icon = 'fas fa-home';
                                            break;
                                        case 'visit_dashboard':
                                            $icon = 'fas fa-tachometer-alt';
                                            break;
                                    }
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-time"><?php echo formatDateTime($activity['timestamp']); ?></div>
                                <div class="activity-description">
                                    <?php
                                        $description = '';
                                        switch ($activity['activity_type']) {
                                            case 'login':
                                                $description = 'You logged in to your account';
                                                break;
                                            case 'logout':
                                                $description = 'You logged out of your account';
                                                break;
                                            case 'borrow_book':
                                                $description = 'You borrowed a book: ' . $activity['details'];
                                                break;
                                            case 'return_book':
                                                $description = 'You returned a book: ' . $activity['details'];
                                                break;
                                            case 'upload_material':
                                                $description = 'You uploaded a new material: ' . $activity['details'];
                                                break;
                                            case 'download_material':
                                                $description = 'You downloaded a material: ' . $activity['details'];
                                                break;
                                            case 'favorite_material':
                                                $description = 'You added a material to favorites: ' . $activity['details'];
                                                break;
                                            case 'visit_homepage':
                                                $description = 'You visited the homepage';
                                                break;
                                            case 'visit_dashboard':
                                                $description = 'You accessed your dashboard';
                                                break;
                                            default:
                                                $description = ucfirst(str_replace('_', ' ', $activity['activity_type']));
                                                if (!empty($activity['details'])) {
                                                    $description .= ': ' . $activity['details'];
                                                }
                                        }
                                        echo $description;
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Favorite Materials Section -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Your Favorites</h2>
                <a href="<?php echo $base_url; ?>/favorites.php" class="view-all">View All</a>
            </div>
            
            <?php if (empty($favorite_materials)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-heart"></i></div>
                    <p>You haven't added any materials to favorites yet.</p>
                    <a href="<?php echo $base_url; ?>/search.php" class="btn primary">Browse Materials</a>
                </div>
            <?php else: ?>
                <div class="materials-grid">
                    <?php foreach ($favorite_materials as $material): ?>
                        <div class="material-card">
                            <h3 class="material-title">
                                <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>">
                                    <?php echo htmlspecialchars($material['title']); ?>
                                </a>
                            </h3>
                            <div class="material-meta">
                                <span class="category"><?php echo htmlspecialchars($material['category']); ?></span>
                                <span class="uploader"><?php echo htmlspecialchars($material['uploader']); ?></span>
                                <span class="date"><?php echo formatDate($material['upload_date']); ?></span>
                            </div>
                            <div class="material-actions">
                                <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>" class="btn small primary">View</a>
                                <a href="<?php echo $base_url; ?>/remove_favorite.php?id=<?php echo $material['material_id']; ?>" class="btn small danger">Remove</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Dashboard specific styles with brown theme */
    .dashboard-container {
        padding: 2rem 0;
    }
    
    .dashboard-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .dashboard-header h1 {
        color: #8B4513;
        margin-bottom: 0.5rem;
    }
    
    .dashboard-header p {
        color: #666;
        font-size: 1.1rem;
    }
    
    .dashboard-stats {
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
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        border-left: 4px solid #8B4513;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .stat-icon {
        font-size: 2rem;
        color: #8B4513;
        background-color: #FFF8DC;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #8B4513;
        line-height: 1.2;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }
    
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
    }
    
    .dashboard-section {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        transition: all 0.3s ease;
    }
    
    .dashboard-section:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 0.8rem;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .section-header h2 {
        color: #8B4513;
        margin: 0;
        font-size: 1.3rem;
    }
    
    .section-header .view-all {
        color: #8B4513;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .section-header .view-all:hover {
        color: #A0522D;
        text-decoration: underline;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem 1rem;
    }
    
    .empty-icon {
        font-size: 3rem;
        color: #D2B48C;
        margin-bottom: 1rem;
    }
    
    .empty-state p {
        color: #666;
        margin-bottom: 1.5rem;
    }
    
    .materials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .material-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        padding: 1rem;
        border: 1px solid #f0f0f0;
        transition: all 0.3s ease;
    }
    
    .material-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        border-color: #D2B48C;
    }
    
    .material-title {
        font-size: 1rem;
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }
    
    .material-title a {
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .material-title a:hover {
        color: #8B4513;
    }
    
    .material-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.8rem;
    }
    
    .material-meta span {
        background-color: #f8f9fa;
        padding: 0.2rem 0.5rem;
        border-radius: 20px;
    }
    
    .material-meta .category {
        background-color: #FFF8DC;
        color: #8B4513;
    }
    
    .material-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .activity-timeline {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .activity-item {
        display: flex;
        gap: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        background-color: #FFF8DC;
        color: #8B4513;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-time {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 0.2rem;
    }
    
    .activity-description {
        color: #333;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-borrowed {
        background-color: #FFF8DC;
        color: #8B4513;
    }
    
    .status-overdue {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-reserved {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
    }
    
    .table th {
        background-color: #FFF8DC;
        color: #8B4513;
        font-weight: 600;
    }
    
    .table th, .table td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(210, 180, 140, 0.1);
    }
    
    @media (max-width: 992px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-stats {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        
        .stat-card {
            flex-direction: column;
            text-align: center;
            border-left: none;
            border-top: 4px solid #8B4513;
        }
        
        .materials-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
    }
    
    @media (max-width: 576px) {
        .dashboard-stats {
            grid-template-columns: 1fr;
        }
        
        .materials-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
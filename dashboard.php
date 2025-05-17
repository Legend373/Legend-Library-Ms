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
$favorite_materials=[];

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
        SELECT a.action,  a.details,a.log_date
        FROM activity_logs a
        WHERE a.user_id = :user_id
        ORDER BY a.log_date DESC
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
        FROM borrowings bb
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

// // Get user's favorite materials
// try {
//     $stmt = $conn->prepare("
//         SELECT m.material_id, m.title, m.category, m.upload_date, u.username as uploader
//         FROM material_favorites mf
//         JOIN materials m ON mf.material_id = m.material_id
//         JOIN users u ON m.uploaded_by = u.user_id
//         WHERE mf.user_id = :user_id
//         ORDER BY mf.date_added DESC
//         LIMIT 5
//     ");
//     $stmt->bindParam(':user_id', $user_id);
//     $stmt->execute();
//     $favorite_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
// } catch(PDOException $e) {
//     $error_message = "Error fetching favorite materials: " . $e->getMessage();
// }

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
        <?php if($_SESSION['role'] == 'teacher'): ?>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-upload"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($uploaded_materials); ?></div>
                <div class="stat-label">Materials Uploaded</div>
            </div>
        </div>
    <?php endif; ?>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-heart"></i></div>
            <div class="stat-content">
                <!-- <div class="stat-value"><?php echo count($favorite_materials); ?></div> -->
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
         <?php if ($_SESSION['role'] == 'teacher'||$_SESSION['role'] == 'admin'):?>
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
<?php endif; ?>
    
        
        
        
        
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
    </div>
</div>



<?php include 'includes/footer.php'; ?>
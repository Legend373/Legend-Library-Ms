<?php
require_once 'config.php';
require_once 'includes/functions.php';
// Define upload directory path
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . $base_url . '/uploads/materials');
define('UPLOAD_URL', $base_url . '/uploads/materials');

// Get material ID from URL
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($material_id <= 0) {
    $_SESSION['flash_message'] = "Invalid material ID.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $base_url . "/search.php");
    exit;
}

// Get material details
try {
    $stmt = $conn->prepare("
        SELECT m.*, u.username as uploader, u.username as uploader_name,
               (SELECT COUNT(*) FROM material_downloads WHERE material_id = m.material_id) as download_count
        FROM materials m
        JOIN users u ON m.uploaded_by = u.user_id
        WHERE m.material_id = :material_id
    ");
    $stmt->bindParam(':material_id', $material_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['flash_message'] = "Material not found.";
        $_SESSION['flash_type'] = "danger";
        header("Location: " . $base_url . "/search.php");
        exit;
    }
    
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify file exists
    $material['file_exists'] = false;
    if (!empty($material['file_path'])) {
        $file_path = UPLOAD_DIR . '/' . $material['file_path'];
        $material['file_exists'] = file_exists($file_path);
    }
    
} catch(PDOException $e) {
    $_SESSION['flash_message'] = "Error fetching material: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $base_url . "/search.php");
    exit;
}

// Get related materials
try {
    $stmt = $conn->prepare("
        SELECT m.material_id, m.title, m.category, m.upload_date
        FROM materials m
        WHERE m.category = :category AND m.material_id != :material_id
        ORDER BY m.upload_date DESC
        LIMIT 5
    ");
    $stmt->bindParam(':category', $material['category']);
    $stmt->bindParam(':material_id', $material_id);
    $stmt->execute();
    $related_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $related_materials = [];
}

function getCategoryIcon($category) {
    global $category_icons;
    return isset($category_icons[$category]) ? $category_icons[$category] : '📚';
} 
// Page title for header
$page_title = $material['title'];
?>
<?php include 'includes/header.php'; ?>

<div class="material-view-container">
    <div class="material-header">
        <div class="material-breadcrumb">
            <a href="<?php echo $base_url; ?>/index.php">Home</a> &gt;
            <a href="<?php echo $base_url; ?>/search.php">Materials</a> &gt;
            <a href="<?php echo $base_url; ?>/search.php?category=<?php echo urlencode($material['category']); ?>"><?php echo htmlspecialchars($material['category']); ?></a> &gt;
            <span><?php echo htmlspecialchars($material['title']); ?></span>
        </div>
        
        <h1 class="material-title"><?php echo htmlspecialchars($material['title']); ?></h1>
        
        <div class="material-meta">
            <div class="meta-item">
                <i class="fas fa-user"></i>
                <span>Uploaded by <?php echo htmlspecialchars($material['uploader']); ?></span>
            </div>
            <div class="meta-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Uploaded on <?php echo formatDate($material['upload_date']); ?></span>
            </div>
            <div class="meta-item">
                <i class="fas fa-folder"></i>
                <span>Category: <?php echo htmlspecialchars($material['category']); ?></span>
            </div>
            <div class="meta-item">
                <i class="fas fa-download"></i>
                <span><?php echo $material['download_count']; ?> downloads</span>
            </div>
        </div>
    </div>
    
    <div class="material-content-wrapper">
        <div class="material-main-content">
            <div class="material-card">
                <div class="material-description">
                    <h2>Description</h2>
                    <div class="description-content">
                        <?php echo nl2br(htmlspecialchars($material['description'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($material['file_path']) && $material['file_exists']): ?>
                    <div class="material-file">
                        <h2>Material File</h2>
                        <div class="file-info">
                            <div class="file-icon">
                                <?php
                                    $file_extension = pathinfo($material['file_path'], PATHINFO_EXTENSION);
                                    $icon_class = 'fas fa-file';
                                    
                                    switch (strtolower($file_extension)) {
                                        case 'pdf':
                                            $icon_class = 'fas fa-file-pdf';
                                            break;
                                        case 'doc':
                                        case 'docx':
                                            $icon_class = 'fas fa-file-word';
                                            break;
                                        case 'xls':
                                        case 'xlsx':
                                            $icon_class = 'fas fa-file-excel';
                                            break;
                                        case 'ppt':
                                        case 'pptx':
                                            $icon_class = 'fas fa-file-powerpoint';
                                            break;
                                        case 'jpg':
                                        case 'jpeg':
                                        case 'png':
                                        case 'gif':
                                            $icon_class = 'fas fa-file-image';
                                            break;
                                        case 'zip':
                                        case 'rar':
                                            $icon_class = 'fas fa-file-archive';
                                            break;
                                        case 'txt':
                                            $icon_class = 'fas fa-file-alt';
                                            break;
                                    }
                                ?>
                                <i class="<?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="file-details">
                                <div class="file-name"><?php echo htmlspecialchars(basename($material['file_path'])); ?></div>
                                <div class="file-size">
                                    <?php
                                        $file_path = UPLOAD_DIR . '/' . $material['file_path'];
                                        echo formatFileSize(filesize($file_path));
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="<?php echo $base_url; ?>/download_material.php?id=<?php echo $material['material_id']; ?>" class="btn primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                            
                            <?php if (isLoggedIn() && ($_SESSION['user_id'] == $material['uploaded_by'] || $_SESSION['role'] === 'admin')): ?>
                                <a href="<?php echo $base_url; ?>/edit_material.php?id=<?php echo $material['material_id']; ?>" class="btn secondary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="<?php echo $base_url; ?>/delete_material.php?id=<?php echo $material['material_id']; ?>" class="btn danger" onclick="return confirm('Are you sure you want to delete this material?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (!empty($material['file_path'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> The file for this material is currently unavailable.
                    </div>
                <?php endif; ?>
                
                <div class="material-comments">
                    <!-- <h2>Comments (<?php echo count($comments); ?>)</h2> -->
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="comment-form">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="comment" class="form-label">Add a comment</label>
                                    <textarea id="comment" name="comment" class="form-control" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn primary">Post Comment</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="login-to-comment">
                            <p>Please <a href="<?php echo $base_url; ?>/login.php">login</a> to post a comment.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($comments)): ?>
                        <div class="no-comments">
                            <p>No comments yet. Be the first to comment!</p>
                        </div>
                    <?php else: ?>
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item">
                                    <div class="comment-header">
                                        <div class="comment-user">
                                            <i class="fas fa-user-circle"></i>
                                            <span class="comment-username"><?php echo htmlspecialchars($comment['username']); ?></span>
                                        </div>
                                        <div class="comment-date">
                                            <?php echo formatDateTime($comment['created_at']); ?>
                                        </div>
                                    </div>
                                    <div class="comment-content">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="material-sidebar">
            <div class="sidebar-card uploader-info">
                <h3>About the Uploader</h3>
                <div class="uploader-profile">
                    <div class="uploader-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="uploader-details">
                        <div class="uploader-name"><?php echo htmlspecialchars($material['uploader_name']); ?></div>
                        <div class="uploader-username">@<?php echo htmlspecialchars($material['uploader']); ?></div>
                    </div>
                </div>
                <a href="<?php echo $base_url; ?>/search.php?uploader=<?php echo urlencode($material['uploader']); ?>" class="btn secondary btn-block">
                    <i class="fas fa-search"></i> View All Materials by This Uploader
                </a>
            </div>
            
            <div class="sidebar-card category-info">
                <h3>Category</h3>
                <div class="category-item">
                    <div class="category-icon">
                        <?php echo getCategoryIcon($material['category']); ?>
                    </div>
                    <div class="category-name"><?php echo htmlspecialchars($material['category']); ?></div>
                </div>
                <a href="<?php echo $base_url; ?>/search.php?category=<?php echo urlencode($material['category']); ?>" class="btn secondary btn-block">
                    <i class="fas fa-folder-open"></i> Browse More in This Category
                </a>
            </div>
            
            <?php if (!empty($related_materials)): ?>
                <div class="sidebar-card related-materials">
                    <h3>Related Materials</h3>
                    <ul class="related-list">
                        <?php foreach ($related_materials as $related): ?>
                            <li>
                                <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $related['material_id']; ?>">
                                    <?php echo htmlspecialchars($related['title']); ?>
                                </a>
                                <span class="related-date"><?php echo formatDate($related['upload_date']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="sidebar-card share-material">
                <h3>Share This Material</h3>
                <div class="share-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($base_url . '/view_material.php?id=' . $material_id); ?>" target="_blank" class="share-button facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($base_url . '/view_material.php?id=' . $material_id); ?>&text=<?php echo urlencode('Check out this material: ' . $material['title']); ?>" target="_blank" class="share-button twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($base_url . '/view_material.php?id=' . $material_id); ?>&title=<?php echo urlencode($material['title']); ?>" target="_blank" class="share-button linkedin">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode('Check out this material: ' . $material['title']); ?>&body=<?php echo urlencode('I found this material that might interest you: ' . $base_url . '/view_material.php?id=' . $material_id); ?>" class="share-button email">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div> 


<?php include 'includes/footer.php'; ?>
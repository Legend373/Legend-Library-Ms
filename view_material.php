<?php
require_once 'config.php';

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
} catch(PDOException $e) {
    $_SESSION['flash_message'] = "Error fetching material: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $base_url . "/search.php");
    exit;
}


// Check if user has favorited this material
// $is_favorited = false;
// if (isLoggedIn()) {
//     try {
//         $stmt = $conn->prepare("
//             SELECT COUNT(*) FROM material_favorites
//             WHERE material_id = :material_id AND user_id = :user_id
//         ");
//         $stmt->bindParam(':material_id', $material_id);
//         $stmt->bindParam(':user_id', $_SESSION['user_id']);
//         $stmt->execute();
//         $is_favorited = ($stmt->fetchColumn() > 0);
//     } catch(PDOException $e) {
//         // Silently fail
//     }
// }

// Get related materials (same category)
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
    // Silently fail
    $related_materials = [];
}

// Get material comments
// try {
//     $stmt = $conn->prepare("
//         SELECT c.*, u.username, u.user_name
//         FROM material_comments c
//         JOIN users u ON c.user_id = u.user_id
//         WHERE c.material_id = :material_id
//         ORDER BY c.created_at DESC
//     ");
//     $stmt->bindParam(':material_id', $material_id);
//     $stmt->execute();
//     $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
// } catch(PDOException $e) {
//     // Silently fail
//     $comments = [];
// }

// // Process comment form
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && isset($_POST['comment'])) {
//     $comment_text = trim($_POST['comment']);
    
//     if (!empty($comment_text)) {
//         try {
//             $stmt = $conn->prepare("
//                 INSERT INTO material_comments (material_id, user_id, comment, created_at)
//                 VALUES (:material_id, :user_id, :comment, NOW())
//             ");
//             $stmt->bindParam(':material_id', $material_id);
//             $stmt->bindParam(':user_id', $_SESSION['user_id']);
//             $stmt->bindParam(':comment', $comment_text);
//             $stmt->execute();
            
//             // Log activity
//             logActivity($_SESSION['user_id'], 'comment_material', $material['title']);
            
//             // Redirect to avoid form resubmission
//             header("Location: " . $base_url . "/view_material.php?id=" . $material_id);
//             exit;
//         } catch(PDOException $e) {
//             $error_message = "Error posting comment: " . $e->getMessage();
//         }
//     }
// }

// Log view if user is logged in
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'view_material', $material['title']);
}

// Category icons mapping
$category_icons = [
    'Mathematics' => '📐',
    'Science' => '🔬',
    'Literature' => '📚',
    'History' => '🏛️',
    'Computer Science' => '💻',
    'Languages' => '🌐',
    'Arts' => '🎨',
    'Physics' => '⚛️',
    'Chemistry' => '🧪',
    'Biology' => '🧬',
    'Geography' => '🌍',
    'Economics' => '📊',
    'Philosophy' => '🧠',
    'Psychology' => '🧠',
    'Engineering' => '⚙️',
    'Medicine' => '🩺',
    'Law' => '⚖️',
    'Music' => '🎵',
    'Physical Education' => '🏃',
    'Religion' => '🙏',
    'Social Studies' => '👥',
    'Technology' => '📱',
    'Other' => '📋'
];

// Get default icon if category doesn't have a specific one
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
            <div class="meta-item">
                <i class="fas fa-heart"></i>
                <!-- <span><?php echo $material['favorite_count']; ?> favorites</span> -->
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
                
                <?php if (!empty($material['file_path'])): ?>
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
                                        case 'mp3':
                                        case 'wav':
                                            $icon_class = 'fas fa-file-audio';
                                            break;
                                        case 'mp4':
                                        case 'avi':
                                            $icon_class = 'fas fa-file-video';
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
                                   $file_path = __DIR__ . '/' . $material['file_path'];  // this resolves correctly

if (file_exists($file_path)) {
    $file_size = filesize($file_path);
    echo formatFileSize($file_size);
} else {
    echo "File not found";
}

                                     ?>
                                </div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="<?php echo $base_url; ?>/download_material.php?id=<?php echo $material['material_id']; ?>" class="btn primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                            
                            <?php if (isLoggedIn()): ?>
                                <?php if ($is_favorited): ?>
                                    <a href="<?php echo $base_url; ?>/remove_favorite.php?id=<?php echo $material['material_id']; ?>&redirect=view" class="btn secondary">
                                        <i class="fas fa-heart-broken"></i> Remove from Favorites
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo $base_url; ?>/add_favorite.php?id=<?php echo $material['material_id']; ?>&redirect=view" class="btn secondary">
                                        <i class="fas fa-heart"></i> Add to Favorites
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
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
<style>
    /* View Material page specific styles with brown theme */
    .material-view-container {
        padding: 2rem 0;
    }
    
    .material-header {
        margin-bottom: 2rem;
    }
    
    .material-breadcrumb {
        margin-bottom: 1rem;
        color: #666;
        font-size: 0.9rem;
    }
    
    .material-breadcrumb a {
        color: #8B4513;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .material-breadcrumb a:hover {
        color: #A0522D;
        text-decoration: underline;
    }
    
    .material-title {
        color: #333;
        font-size: 2rem;
        margin-bottom: 1rem;
        line-height: 1.3;
    }
    
    .material-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #666;
        font-size: 0.9rem;
    }
    
    .meta-item i {
        color: #8B4513;
    }
    
    .material-content-wrapper {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }
    
    .material-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .material-description h2,
    .material-file h2,
    .material-comments h2 {
        color: #8B4513;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .description-content {
        color: #333;
        line-height: 1.6;
        margin-bottom: 2rem;
    }
    
    .material-file {
        margin-bottom: 2rem;
    }
    
    .file-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        background-color: #FFF8DC;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    
    .file-icon {
        font-size: 2.5rem;
        color: #8B4513;
    }
    
    .file-details {
        flex: 1;
    }
    
    .file-name {
        font-weight: 500;
        margin-bottom: 0.3rem;
        color: #333;
    }
    
    .file-size {
        color: #666;
        font-size: 0.9rem;
    }
    
    .file-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .file-actions .btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .comment-form {
        margin-bottom: 2rem;
    }
    
    .login-to-comment {
        background-color: #FFF8DC;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .login-to-comment a {
        color: #8B4513;
        font-weight: 500;
        text-decoration: none;
    }
    
    .login-to-comment a:hover {
        text-decoration: underline;
    }
    
    .no-comments {
        color: #666;
        font-style: italic;
        margin-bottom: 2rem;
    }
    
    .comments-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .comment-item {
        background-color: #f9f9f9;
        border-radius: 8px;
        padding: 1.5rem;
        border-left: 3px solid #8B4513;
    }
    
    .comment-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.8rem;
    }
    
    .comment-user {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .comment-user i {
        color: #8B4513;
        font-size: 1.2rem;
    }
    
    .comment-username {
        font-weight: 500;
        color: #333;
    }
    
    .comment-date {
        color: #666;
        font-size: 0.85rem;
    }
    
    .comment-content {
        color: #333;
        line-height: 1.5;
    }
    
    .material-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .sidebar-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
    }
    
    .sidebar-card h3 {
        color: #8B4513;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .uploader-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .uploader-avatar {
        font-size: 2.5rem;
        color: #8B4513;
    }
    
    .uploader-name {
        font-weight: 500;
        color: #333;
        margin-bottom: 0.2rem;
    }
    
    .uploader-username {
        color: #666;
        font-size: 0.9rem;
    }
    
    .category-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        background-color: #FFF8DC;
        padding: 1rem;
        border-radius: 8px;
    }
    
    .category-icon {
        font-size: 2rem;
    }
    
    .category-name {
        font-weight: 500;
        color: #8B4513;
    }
    
    .related-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .related-list li {
        margin-bottom: 0.8rem;
        padding-bottom: 0.8rem;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .related-list li:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .related-list li a {
        color: #333;
        text-decoration: none;
        display: block;
        margin-bottom: 0.3rem;
        transition: all 0.3s ease;
    }
    
    .related-list li a:hover {
        color: #8B4513;
    }
    
    .related-date {
        color: #666;
        font-size: 0.85rem;
    }
    
    .share-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }
    
    .share-button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }
    
    .share-button:hover {
        transform: translateY(-3px);
    }
    
    .share-button.facebook {
        background-color: #3b5998;
    }
    
    .share-button.twitter {
        background-color: #1da1f2;
    }
    
    .share-button.linkedin {
        background-color: #0077b5;
    }
    
    .share-button.email {
        background-color: #8B4513;
    }
    
    .btn-block {
        display: block;
        width: 100%;
        text-align: center;
    }
    
    @media (max-width: 992px) {
        .material-content-wrapper {
            grid-template-columns: 1fr;
        }
        
        .material-sidebar {
            order: -1;
            margin-bottom: 2rem;
        }
    }
    
    @media (max-width: 768px) {
        .material-meta {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .file-actions {
            flex-direction: column;
        }
        
        .file-actions .btn {
            width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .material-card {
            padding: 1.5rem;
        }
        
        .material-title {
            font-size: 1.5rem;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
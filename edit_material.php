<?php
require_once 'config.php';
requireLogin();

// Only teachers and admins can access this page
if (!isTeacher() && !isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$material_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get material details
try {
    $stmt = $conn->prepare("
        SELECT m.*, u.username as uploader, u.user_id as uploader_id
        FROM materials m
        JOIN users u ON m.uploaded_by = u.user_id
        WHERE m.material_id = ?
    ");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$material) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Check if user has permission to edit this material
    if (!isAdmin() && $material['uploader_id'] != $user_id) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Get keywords
    $stmt = $conn->prepare("SELECT keyword FROM keywords WHERE material_id = ?");
    $stmt->execute([$material_id]);
    $keywords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $keywords_string = implode(', ', $keywords);
    
} catch(PDOException $e) {
    $errors[] = "Error retrieving material: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_material'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $keywords = trim($_POST['keywords']);
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    
    // Handle file upload if a new file is provided
    $file_path = $material['file_path']; // Default to existing file
    
    if (!empty($_FILES['file']['name'])) {
        $file_name = $_FILES['file']['name'];
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_size = $_FILES['file']['size'];
        
        // Check file size (limit to 10MB)
        if ($file_size > 10000000) {
            $errors[] = "File size must be less than 10MB";
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = "uploads/materials/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $new_file_name = uniqid() . '_' . $file_name;
        $new_file_path = $upload_dir . $new_file_name;
    }
    
    // Update material if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Upload new file if provided
            if (!empty($_FILES['file']['name'])) {
                if (move_uploaded_file($file_tmp, $new_file_path)) {
                    // Delete old file if it exists and is different
                    if (file_exists($file_path) && $file_path != $new_file_path) {
                        unlink($file_path);
                    }
                    $file_path = $new_file_path;
                } else {
                    throw new Exception("Failed to upload file");
                }
            }
            
            // Update material
            $stmt = $conn->prepare("
                UPDATE materials 
                SET title = ?, description = ?, file_path = ?, category = ?
                WHERE material_id = ?
            ");
            $stmt->execute([$title, $description, $file_path, $category, $material_id]);
            
            // Delete existing keywords
            $stmt = $conn->prepare("DELETE FROM keywords WHERE material_id = ?");
            $stmt->execute([$material_id]);
            
            // Insert new keywords
            if (!empty($keywords)) {
                $keyword_array = array_map('trim', explode(',', $keywords));
                $stmt = $conn->prepare("INSERT INTO keywords (material_id, keyword) VALUES (?, ?)");
                
                foreach ($keyword_array as $keyword) {
                    if (!empty($keyword)) {
                        $stmt->execute([$material_id, $keyword]);
                    }
                }
            }
            
            // Log activity
            logActivity($user_id, 'edit_material', "Edited material: $title");
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Material updated successfully!";
            
            // Refresh material data
            $stmt = $conn->prepare("
                SELECT m.*, u.username as uploader, u.user_id as uploader_id
                FROM materials m
                JOIN users u ON m.uploaded_by = u.user_id
                WHERE m.material_id = ?
            ");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refresh keywords
            $stmt = $conn->prepare("SELECT keyword FROM keywords WHERE material_id = ?");
            $stmt->execute([$material_id]);
            $keywords = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $keywords_string = implode(', ', $keywords);
            
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $errors[] = "Error updating material: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Material - Legend Library System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="form-container">
                <h2>Edit Educational Material</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-container">
                        <p><?php echo $success_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-container">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="edit_material.php?id=<?php echo $material_id; ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($material['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($material['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select a category</option>
                            <option value="Mathematics" <?php echo ($material['category'] == 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                            <option value="Science" <?php echo ($material['category'] == 'Science') ? 'selected' : ''; ?>>Science</option>
                            <option value="Literature" <?php echo ($material['category'] == 'Literature') ? 'selected' : ''; ?>>Literature</option>
                            <option value="History" <?php echo ($material['category'] == 'History') ? 'selected' : ''; ?>>History</option>
                            <option value="Computer Science" <?php echo ($material['category'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                            <option value="Languages" <?php echo ($material['category'] == 'Languages') ? 'selected' : ''; ?>>Languages</option>
                            <option value="Arts" <?php echo ($material['category'] == 'Arts') ? 'selected' : ''; ?>>Arts</option>
                            <option value="Other" <?php echo ($material['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="keywords">Keywords (comma separated)</label>
                        <input type="text" id="keywords" name="keywords" value="<?php echo htmlspecialchars($keywords_string); ?>" placeholder="e.g. algebra, equations, formulas">
                    </div>
                    
                    <div class="form-group">
                        <label>Current File</label>
                        <div class="current-file">
                            <a href="download_material.php?id=<?php echo $material_id; ?>" target="_blank">
                                <?php echo htmlspecialchars(basename($material['file_path'])); ?>
                            </a>
                            <span class="file-info">(<?php echo formatFileSize(filesize($material['file_path'])); ?>)</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="file">Replace File (optional)</label>
                        <input type="file" id="file" name="file">
                        <small>Max file size: 10MB. Leave empty to keep the current file.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Uploaded By</label>
                        <div><?php echo htmlspecialchars($material['uploader']); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Date</label>
                        <div><?php echo formatDate($material['upload_date']); ?></div>
                    </div>
                    
                    <button type="submit" name="update_material" class="btn primary">Update Material</button>
                    <a href="view_material.php?id=<?php echo $material_id; ?>" class="btn secondary">Cancel</a>
                </form>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
    
    <script src="js/upload.js"></script>
</body>
</html>
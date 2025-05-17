<?php
require_once 'config.php';
requireLogin();

// Only teachers and admins can access this page
if (!isTeacher() && !isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$material_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$confirm = isset($_GET['confirm']) && $_GET['confirm'] == 1;

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
    
    // Check if user has permission to delete this material
    if (!isAdmin() && $material['uploader_id'] != $user_id) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Handle deletion if confirmed
    if ($confirm) {
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Delete keywords
            $stmt = $conn->prepare("DELETE FROM keywords WHERE material_id = ?");
            $stmt->execute([$material_id]);
            
            // Delete download records
            $stmt = $conn->prepare("DELETE FROM material_downloads WHERE material_id = ?");
            $stmt->execute([$material_id]);
            
            // Delete material
            $stmt = $conn->prepare("DELETE FROM materials WHERE material_id = ?");
            $stmt->execute([$material_id]);
            
            // Delete file
            if (file_exists($material['file_path'])) {
                unlink($material['file_path']);
            }
            
            // Log activity
            logActivity($user_id, 'delete_material', "Deleted material: {$material['title']}");
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to dashboard with success message
            header("Location: dashboard.php?deleted=1");
            exit();
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error deleting material: " . $e->getMessage();
        }
    }
} catch(PDOException $e) {
    $error_message = "Error retrieving material: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Material - Legend Library System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .delete-confirmation {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .material-details {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .material-details h3 {
            margin-top: 0;
            color: #333;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 0.75rem;
        }
        
        .detail-label {
            font-weight: 600;
            width: 120px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section>
                <h2>Delete Material</h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="error-container">
                        <p><?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="delete-confirmation">
                    <h3>Are you sure you want to delete this material?</h3>
                    <p>This action cannot be undone. The material will be permanently removed from the system, including all associated files and download records.</p>
                </div>
                
                <div class="material-details">
                    <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                    
                    <div class="detail-row">
                        <div class="detail-label">Category:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($material['category']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Uploaded By:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($material['uploader']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Upload Date:</div>
                        <div class="detail-value"><?php echo formatDate($material['upload_date']); ?></div>
                    </div>
                    
                    <?php if (!empty($material['description'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Description:</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($material['description'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="detail-row">
                        <div class="detail-label">File:</div>
                        <div class="detail-value"><?php echo htmlspecialchars(basename($material['file_path'])); ?></div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="delete_material.php?id=<?php echo $material_id; ?>&confirm=1" class="btn danger">Yes, Delete Material</a>
                    <a href="view_material.php?id=<?php echo $material_id; ?>" class="btn secondary">No, Cancel</a>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
</body>
</html>
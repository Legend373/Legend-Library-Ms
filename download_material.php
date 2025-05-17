<?php
require_once 'config.php';
require_once 'includes/functions.php';
requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$material_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Get material details
    $stmt = $conn->prepare("SELECT title, file_path FROM materials WHERE material_id = ?");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$material) {
        header("Location: dashboard.php");
        exit();
    }
    
    $file_path = $material['file_path'];
    
    // Check if file exists
    if (!file_exists($file_path)) {
        die("Error: File not found.");
    }
    
    // Log the download
    $stmt = $conn->prepare("
        INSERT INTO material_downloads (material_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$material_id, $user_id]);
    
    // Log activity
    logActivity($user_id, 'download_material', "Downloaded material: {$material['title']}");
    
    // Get file information
    $file_name = basename($file_path);
    $file_size = filesize($file_path);
    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    
    // Set appropriate content type based on file extension
    switch (strtolower($file_extension)) {
        case 'pdf':
            $content_type = 'application/pdf';
            break;
        case 'doc':
            $content_type = 'application/msword';
            break;
        case 'docx':
            $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
        case 'xls':
            $content_type = 'application/vnd.ms-excel';
            break;
        case 'xlsx':
            $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            break;
        case 'ppt':
            $content_type = 'application/vnd.ms-powerpoint';
            break;
        case 'pptx':
            $content_type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            break;
        case 'jpg':
        case 'jpeg':
            $content_type = 'image/jpeg';
            break;
        case 'png':
            $content_type = 'image/png';
            break;
        case 'gif':
            $content_type = 'image/gif';
            break;
        case 'txt':
            $content_type = 'text/plain';
            break;
        default:
            $content_type = 'application/octet-stream';
    }
    
    // Clean the output buffer
    ob_clean();
    
    // Set headers for download
    header("Content-Type: $content_type");
    header("Content-Disposition: attachment; filename=\"" . $file_name . "\"");
    header("Content-Length: $file_size");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Output file
    readfile($file_path);
    exit();
    
} catch(PDOException $e) {
    die("Error retrieving material: " . $e->getMessage());
}
?>
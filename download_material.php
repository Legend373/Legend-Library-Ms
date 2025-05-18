<?php
require_once 'config.php';
require_once 'includes/functions.php';
requireLogin();

// // Define upload directory path
// define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . $base_url . '/uploads/materials');

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
    
    // Construct full file path
    $file_path = UPLOAD_DIR . '/' . basename($material['file_path']);
    
    // Check if file exists
    if (!file_exists($file_path)) {
        error_log("Download failed - File not found: " . $file_path);
        $_SESSION['flash_message'] = "The requested file is no longer available.";
        $_SESSION['flash_type'] = "danger";
        header("Location: view_material.php?id=" . $material_id);
        exit();
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
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Content type mapping
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain'
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // Clean the output buffer
    ob_clean();
    ob_start();
    
    // Set headers for download
    header("Content-Type: $content_type");
    header("Content-Disposition: attachment; filename=\"" . $file_name . "\"");
    header("Content-Length: " . $file_size);
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Output file
    readfile($file_path);
    exit();
    
} catch(PDOException $e) {
    error_log("Download error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error downloading file. Please try again.";
    $_SESSION['flash_type'] = "danger";
    header("Location: view_material.php?id=" . $material_id);
    exit();
}
?>
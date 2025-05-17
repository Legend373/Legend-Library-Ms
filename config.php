<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'library_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'Legend Library');
define('BASE_URL', 'http://localhost/myapp/legend-library-ms');
$base_url = BASE_URL;

// File upload directories
define('UPLOAD_DIR', __DIR__ . '/uploads/materials');
define('BOOK_COVER_DIR', __DIR__ . '/uploads/covers');
define('USER_AVATAR_DIR', __DIR__ . '/uploads/avatars');
define('TEMP_DIR', __DIR__ . '/uploads/temp');

// Session configuration
session_start();

// Database connection
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Include helper functions
require_once 'includes/functions.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Log user activity
function logActivity($user_id, $activity_type, $details = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO user_activities (user_id, activity_type, details, timestamp)
            VALUES (:user_id, :activity_type, :details, NOW())
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':activity_type', $activity_type);
        $stmt->bindParam(':details', $details);
        $stmt->execute();
    } catch(PDOException $e) {
        // Silently fail
    }
}

// Log system event
function logSystem($log_type, $message, $user_id = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO system_logs (log_type, message, user_id, timestamp)
            VALUES (:log_type, :message, :user_id, NOW())
        ");
        $stmt->bindParam(':log_type', $log_type);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    } catch(PDOException $e) {
        // Silently fail
    }
}


// Format date
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}
// Format date and time
function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Create required directories if they don't exist
$directories = [UPLOAD_DIR, BOOK_COVER_DIR, USER_AVATAR_DIR, TEMP_DIR];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
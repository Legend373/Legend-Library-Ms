<?php
/**
 * Additional helper functions for the Library Management System
 */

/**
 * Format a date in a human-readable format
 * 
 * @param string $date The date to format
 * @param string $format The format to use (default: 'M d, Y')
 * @return string The formatted date
 */

/**
 * Calculate days remaining until a due date
 * 
 * @param string $due_date The due date
 * @return int The number of days remaining (negative if overdue)
 */
function daysRemaining($due_date) {
    $due = new DateTime($due_date);
    $today = new DateTime();
    $interval = $today->diff($due);
    return $interval->invert ? -$interval->days : $interval->days;
}

/**
 * Get human-readable file size
 * 
 * @param int $bytes The file size in bytes
 * @param int $precision The number of decimal places (default: 2)
 * @return string The formatted file size
 */


/**
 * Generate a random password
 * 
 * @param int $length The length of the password (default: 10)
 * @return string The generated password
 */
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Sanitize a string for output
 * 
 * @param string $string The string to sanitize
 * @return string The sanitized string
 */
function sanitize($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if a string contains a search term
 * 
 * @param string $haystack The string to search in
 * @param string $needle The search term
 * @return bool True if the string contains the search term, false otherwise
 */
function containsSearchTerm($haystack, $needle) {
    return stripos($haystack, $needle) !== false;
}

/**
 * Highlight search terms in a string
 * 
 * @param string $text The text to highlight
 * @param string $search The search term
 * @return string The text with highlighted search terms
 */
function highlightSearchTerms($text, $search) {
    if (empty($search)) {
        return $text;
    }
    
    return preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark>$1</mark>', $text);
}

/**
 * Log user activity
 * 
 * @param int $user_id The user ID
 * @param string $action The action performed
 * @param string $details Additional details (optional)
 * @return bool True on success, false on failure
 */

/**
 * Check if a user has permission to perform an action
 * 
 * @param string $permission The permission to check
 * @return bool True if the user has permission, false otherwise
 */
function hasPermission($permission) {
    // Define permission hierarchy
    $permissions = [
        'view_material' => ['student', 'teacher', 'admin'],
        'download_material' => ['student', 'teacher', 'admin'],
        'upload_material' => ['teacher', 'admin'],
        'edit_material' => ['teacher', 'admin'], // Teacher can only edit their own materials
        'delete_material' => ['teacher', 'admin'], // Teacher can only delete their own materials
        'borrow_book' => ['student', 'admin'],
        'return_book' => ['student', 'admin'],
        'manage_users' => ['admin'],
        'manage_books' => ['admin'],
        'view_logs' => ['admin']
    ];
    
    if (!isLoggedIn() || !isset($permissions[$permission])) {
        return false;
    }
    
    return in_array($_SESSION['role'], $permissions[$permission]);
}

/**
 * Send a notification email
 * 
 * @param string $to The recipient email
 * @param string $subject The email subject
 * @param string $message The email message
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message) {
    // In a real application, you would use a proper email library like PHPMailer
    // For this example, we'll just return true
    return true;
}

/**
 * Create a backup of the database
 * 
 * @param string $filename The backup filename (optional)
 * @return string|bool The backup filename on success, false on failure
 */
function backupDatabase($filename = null) {
    global $host, $dbname, $username, $password;
    
    if ($filename === null) {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    }
    
    $backup_dir = __DIR__ . '/../backups/';
    $backup_file = $backup_dir . $filename;
    
    // Ensure backup directory exists
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Create backup command
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg($host),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($dbname),
        escapeshellarg($backup_file)
    );
    
    // Execute command
    system($command, $return_var);
    
    return $return_var === 0 ? $filename : false;
}
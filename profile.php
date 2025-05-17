<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// Get user information
try {
    $stmt = $conn->prepare("SELECT username, email, role, created_at FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: $base_url/logout.php");
        exit();
    }
} catch(PDOException $e) {
    $errors[] = "Error fetching user data: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists (if changed)
    if ($email != $user['email']) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email already exists";
            }
        } catch(PDOException $e) {
            $errors[] = "Error checking email: " . $e->getMessage();
        }
    }
    
    // Verify current password if provided
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        try {
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stored_password = $stmt->fetchColumn();
            
            if (!password_verify($current_password, $stored_password)) {
                $errors[] = "Current password is incorrect";
            }
            
            if (!empty($new_password)) {
                if (strlen($new_password) < 8) {
                    $errors[] = "New password must be at least 8 characters";
                }
                
                if ($new_password !== $confirm_password) {
                    $errors[] = "New passwords do not match";
                }
            }
        } catch(PDOException $e) {
            $errors[] = "Error verifying password: " . $e->getMessage();
        }
    }
    
    // Update profile if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update email
            if ($email != $user['email']) {
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $stmt->execute([$email, $user_id]);
                $user['email'] = $email;
            }
            
            // Update password if provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }
            
            // Log activity
            logActivity($user_id, 'update_profile', "User updated their profile");
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Profile updated successfully!";
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Get user activity
try {
    $stmt = $conn->prepare("
        SELECT action, details, log_date
        FROM activity_logs
        WHERE user_id = ?
        ORDER BY log_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errors[] = "Error fetching activity logs: " . $e->getMessage();
}

// Page title for header
$page_title = "My Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Legend Library System</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/css/style.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        @media (min-width: 768px) {
            .profile-container {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .profile-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #e6f0ff;
            color: #2c6ecb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 1.5rem;
        }
        
        .profile-info h3 {
            margin: 0 0 0.5rem 0;
        }
        
        .profile-info p {
            margin: 0;
            color: #666;
        }
        
        .profile-details {
            margin-bottom: 1.5rem;
        }
        
        .profile-details p {
            margin: 0.5rem 0;
            display: flex;
            justify-content: space-between;
        }
        
        .profile-details span {
            color: #666;
        }
        
        .profile-tabs {
            margin-bottom: 1.5rem;
        }
        
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 1rem;
        }
        
        .tab-button {
            padding: 0.75rem 1rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 500;
        }
        
        .tab-button.active {
            border-bottom-color: #2c6ecb;
            color: #2c6ecb;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-action {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .activity-details {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-date {
            color: #999;
            font-size: 0.8rem;
        }
        
        .password-requirements {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .password-requirements h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
        }
        
        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <h2>My Profile</h2>
            
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
            
            <div class="profile-container">
                <div class="profile-main">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <div class="profile-info">
                                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                                <p><?php echo ucfirst($user['role']); ?></p>
                            </div>
                        </div>
                        
                        <div class="profile-details">
                            <p>
                                Email: <span><?php echo htmlspecialchars($user['email']); ?></span>
                            </p>
                            <p>
                                Member Since: <span><?php echo formatDate($user['created_at']); ?></span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <div class="profile-tabs">
                            <div class="tab-buttons">
                                <button class="tab-button active" data-tab="edit-profile">Edit Profile</button>
                                <button class="tab-button" data-tab="change-password">Change Password</button>
                            </div>
                            
                            <div class="tab-content active" id="edit-profile">
                                <form action="<?php echo $base_url; ?>/profile.php" method="post">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                        <small>Username cannot be changed</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="role">Role</label>
                                        <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled>
                                        <small>Contact an administrator to change your role</small>
                                    </div>
                                    
                                    <input type="hidden" name="update_profile" value="1">
                                    <button type="submit" class="btn primary">Update Profile</button>
                                </form>
                            </div>
                            
                            <div class="tab-content" id="change-password">
                                <div class="password-requirements">
                                    <h4>Password Requirements</h4>
                                    <ul>
                                        <li>At least 8 characters long</li>
                                        <li>Include at least one uppercase letter</li>
                                        <li>Include at least one number</li>
                                        <li>Include at least one special character</li>
                                    </ul>
                                </div>
                                
                                <form action="<?php echo $base_url; ?>/profile.php" method="post">
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" id="new_password" name="new_password" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <input type="hidden" name="update_profile" value="1">
                                    <button type="submit" class="btn primary">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-sidebar">
                    <div class="profile-card">
                        <h3>Recent Activity</h3>
                        
                        <?php if (empty($activities)): ?>
                            <p>No recent activity.</p>
                        <?php else: ?>
                            <ul class="activity-list">
                                <?php foreach ($activities as $activity): ?>
                                    <li class="activity-item">
                                        <div class="activity-action"><?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?></div>
                                        <?php if (!empty($activity['details'])): ?>
                                            <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                                        <?php endif; ?>
                                        <div class="activity-date"><?php echo formatDate($activity['log_date'], 'M d, Y H:i'); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-card">
                        <h3>Account Actions</h3>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <a href="<?php echo $base_url; ?>/dashboard.php" class="btn secondary">Go to Dashboard</a>
                            <a href="<?php echo $base_url; ?>/logout.php" class="btn outline">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Password validation
            const passwordForm = document.querySelector('#change-password form');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(event) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (newPassword !== confirmPassword) {
                        event.preventDefault();
                        alert('New passwords do not match!');
                    }
                    
                    if (newPassword.length < 8) {
                        event.preventDefault();
                        alert('Password must be at least 8 characters long!');
                    }
                });
            }
        });
    </script>
</body>
</html>
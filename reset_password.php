<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";
$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;
$user_id = null;

// Validate token
if (empty($token)) {
    $errors[] = "Invalid or missing reset token";
} else {
    try {
        $stmt = $conn->prepare("
            SELECT pr.user_id, u.username
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.user_id
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset) {
            $errors[] = "Invalid or expired reset token";
        } else {
            $valid_token = true;
            $user_id = $reset['user_id'];
            $username = $reset['username'];
        }
    } catch(PDOException $e) {
        $errors[] = "Error validating token: " . $e->getMessage();
    }
}

// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Reset password if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Delete reset token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Your password has been reset successfully! You can now <a href='login.php'>login</a> with your new password.";
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $errors[] = "Error resetting password: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Legend Library System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="form-container">
                <h2>Reset Password</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-container">
                        <p><?php echo $success_message; ?></p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="error-container">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($valid_token): ?>
                        <p>Hello, <?php echo htmlspecialchars($username); ?>! Please enter your new password below.</p>
                        
                        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input type="password" id="password" name="password" required>
                                <small>Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn primary">Reset Password</button>
                        </form>
                    <?php else: ?>
                        <p>The password reset link is invalid or has expired. Please <a href="forgot_password.php">request a new password reset link</a>.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            function checkPasswordMatch() {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity("Passwords don't match");
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            }
            
            passwordInput.addEventListener('input', checkPasswordMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        });
    </script>
</body>
</html>
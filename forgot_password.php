<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $errors[] = "No account found with this email address";
            } else {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $stmt = $conn->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
                ");
                $stmt->execute([$user['user_id'], $token, $expires]);
                
                // In a real application, you would send an email with the reset link
                // For this example, we'll just display the link
                $reset_link = "http://{$_SERVER['HTTP_HOST']}/library_system/reset_password.php?token=$token";
                
                $success_message = "Password reset instructions have been sent to your email address.";
                
                // For demonstration purposes only - in a real app, this would be sent via email
                $demo_message = "
                    <div class='demo-email'>
                        <h4>Demo: Password Reset Email</h4>
                        <p>Dear {$user['username']},</p>
                        <p>You have requested to reset your password. Please click the link below to reset your password:</p>
                        <p><a href='$reset_link'>Reset Password</a></p>
                        <p>If you did not request this, please ignore this email.</p>
                        <p>The link will expire in 1 hour.</p>
                    </div>
                ";
            }
        } catch(PDOException $e) {
            $errors[] = "Error processing request: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Legend Library System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .demo-email {
            background-color: #f8f9fa;
            border: 1px dashed #6c757d;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 4px;
        }
        
        .demo-email h4 {
            color: #dc3545;
            margin-top: 0;
        }
        
        .demo-email a {
            display: inline-block;
            background-color: #2c6ecb;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section class="form-container">
                <h2>Forgot Password</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-container">
                        <p><?php echo $success_message; ?></p>
                        <?php if (isset($demo_message)): ?>
                            <?php echo $demo_message; ?>
                        <?php endif; ?>
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
                    
                    <p>Enter your email address below and we'll send you instructions to reset your password.</p>
                    
                    <form action="forgot_password.php" method="post">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        
                        <button type="submit" class="btn primary">Send Reset Link</button>
                    </form>
                    
                    <p class="form-footer">Remember your password? <a href="login.php">Login here</a></p>
                <?php endif; ?>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
</body>
</html>
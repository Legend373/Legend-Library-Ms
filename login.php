<?php
require_once 'config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    header("Location: " . $base_url . "/dashboard.php");
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username); // Allow login with email too
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $errors[] = "Your account is not active. Please contact the administrator.";
                } else {
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Set remember me cookie if requested
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expires = time() + (30 * 24 * 60 * 60); // 30 days
                            
                            // Store token in database
                            $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (:user_id, :token, :expires)");
                            $stmt->bindParam(':user_id', $user['user_id']);
                            $stmt->bindParam(':token', $token);
                            $stmt->bindParam(':expires', $expires);
                            $stmt->execute();
                            
                            // Set cookie
                            setcookie('remember_token', $token, $expires, '/', '', false, true);
                        }
                        
                        // Log activity
                        logActivity($user['user_id'], 'login');
                        
                        // Redirect to dashboard
                        $_SESSION['flash_message'] = "Welcome back, " . $user['username'] . "!";
                        $_SESSION['flash_type'] = "success";
                        
                        // Redirect to intended page if set, otherwise to dashboard
                        $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : $base_url . "/dashboard.php";
                        unset($_SESSION['redirect_after_login']);
                        
                        header("Location: " . $redirect);
                        exit;
                    } else {
                        $errors[] = "Invalid username or password";
                    }
                }
            } else {
                $errors[] = "Invalid username or password";
            }
        } catch(PDOException $e) {
            $errors[] = "Login failed: " . $e->getMessage();
        }
    }
}

// Page title for header
$page_title = "Login";
?>
<?php include 'includes/header.php'; ?>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <h2>Login to Your Account</h2>
            <p>Enter your credentials to access your account</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="login-form">
            <div class="form-group">
                <label for="username" class="form-label">Username or Email</label>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-input-container">
                    <input type="password" id="password" name="password" class="form-control" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye" id="password-toggle-icon"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" id="remember" name="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Remember me</label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn primary btn-block">Login</button>
            </div>
            
            <div class="login-footer">
                <p><a href="<?php echo $base_url; ?>/forgot_password.php">Forgot your password?</a></p>
                <p>Don't have an account? <a href="<?php echo $base_url; ?>/register.php">Register now</a></p>
            </div>
        </form>
    </div>
</div>

<style>
    /* Login page specific styles with brown theme */
    .login-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 200px);
        padding: 2rem 1rem;
    }
    
    .login-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 450px;
        padding: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .login-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #8B4513, #A0522D);
    }
    
    .login-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .login-header h2 {
        color: #8B4513;
        margin-bottom: 0.5rem;
    }
    
    .login-header p {
        color: #666;
    }
    
    .login-form .form-group {
        margin-bottom: 1.5rem;
    }
    
    .login-form .form-label {
        display: block;
        margin-bottom: 0.5rem;
        color: #333;
        font-weight: 500;
    }
    
    .login-form .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #D2B48C;
        border-radius: 5px;
        transition: all 0.3s ease;
    }
    
    .login-form .form-control:focus {
        border-color: #8B4513;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2);
        outline: none;
    }
    
    .password-input-container {
        position: relative;
    }
    
    .password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #8B4513;
        cursor: pointer;
    }
    
    .login-form .btn {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        font-weight: 500;
    }
    
    .login-form .btn-block {
        width: 100%;
    }
    
    .login-footer {
        margin-top: 1.5rem;
        text-align: center;
        color: #666;
    }
    
    .login-footer a {
        color: #8B4513;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .login-footer a:hover {
        color: #A0522D;
        text-decoration: underline;
    }
    
    .form-check {
        display: flex;
        align-items: center;
    }
    
    .form-check-input {
        margin-right: 0.5rem;
    }
    
    .form-check-label {
        margin-bottom: 0;
        cursor: pointer;
    }
    
    @media (max-width: 576px) {
        .login-card {
            padding: 1.5rem;
        }
    }
</style>

<script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const passwordToggleIcon = document.getElementById('password-toggle-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordToggleIcon.classList.remove('fa-eye');
            passwordToggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            passwordToggleIcon.classList.remove('fa-eye-slash');
            passwordToggleIcon.classList.add('fa-eye');
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
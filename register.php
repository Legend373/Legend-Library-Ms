<?php
require_once 'config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    header("Location: " . $base_url . "/dashboard.php");
    exit;
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $role = 'student'; // Default role
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $stmt = $conn->prepare("SELECT username, email FROM users WHERE username = :username OR email = :email");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_user['username'] === $username) {
                    $errors[] = "Username already exists";
                }
                
                if ($existing_user['email'] === $email) {
                    $errors[] = "Email already exists";
                }
            }
        } catch(PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
    
    // If no validation errors, create user
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user into database
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, role, created_at)
                VALUES (:username, :email, :password, :role, NOW())
            ");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            // $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            
            $user_id = $conn->lastInsertId();
            
            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            
            // Log activity
            logActivity($user_id, 'register');
            
            // Redirect to dashboard
            $_SESSION['flash_message'] = "Registration successful! Welcome to Legend Library, " . $username . "!";
            $_SESSION['flash_type'] = "success";
            header("Location: " . $base_url . "/dashboard.php");
            exit;
        } catch(PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}

// Page title for header
$page_title = "Register";
?>
<?php include 'includes/header.php'; ?>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <h2>Create an Account</h2>
            <p>Join Legend Library to access educational materials and borrow books</p>
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
        
        <form method="POST" action="" class="register-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
                    <small class="form-text">Letters, numbers, and underscores only</small>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                    <small class="form-text">Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="password-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                            <i class="fas fa-eye" id="confirm-password-toggle-icon"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" id="terms" name="terms" class="form-check-input" required>
                <label for="terms" class="form-check-label">I agree to the <a href="<?php echo $base_url; ?>/terms.php">Terms of Service</a> and <a href="<?php echo $base_url; ?>/privacy.php">Privacy Policy</a></label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn primary btn-block">Create Account</button>
            </div>
            
            <div class="register-footer">
                <p>Already have an account? <a href="<?php echo $base_url; ?>/login.php">Login here</a></p>
            </div>
        </form>
    </div>
</div>

<style>
    /* Register page specific styles with brown theme */
    .register-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 200px);
        padding: 2rem 1rem;
    }
    
    .register-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 600px;
        padding: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .register-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #8B4513, #A0522D);
    }
    
    .register-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .register-header h2 {
        color: #8B4513;
        margin-bottom: 0.5rem;
    }
    
    .register-header p {
        color: #666;
    }
    
    .register-form .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .register-form .form-label {
        display: block;
        margin-bottom: 0.5rem;
        color: #333;
        font-weight: 500;
    }
    
    .register-form .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #D2B48C;
        border-radius: 5px;
        transition: all 0.3s ease;
    }
    
    .register-form .form-control:focus {
        border-color: #8B4513;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2);
        outline: none;
    }
    
    .register-form .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.8rem;
        color: #666;
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
    
    .register-form .btn {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        font-weight: 500;
    }
    
    .register-form .btn-block {
        width: 100%;
    }
    
    .register-footer {
        margin-top: 1.5rem;
        text-align: center;
        color: #666;
    }
    
    .register-footer a {
        color: #8B4513;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .register-footer a:hover {
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
    
    .form-check-label a {
        color: #8B4513;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .form-check-label a:hover {
        color: #A0522D;
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 576px) {
        .register-card {
            padding: 1.5rem;
        }
    }
</style>

<script>
    function togglePasswordVisibility(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIconId = inputId === 'password' ? 'password-toggle-icon' : 'confirm-password-toggle-icon';
        const passwordToggleIcon = document.getElementById(toggleIconId);
        
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
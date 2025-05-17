<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = $is_logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_name = $is_logged_in ? $_SESSION['username'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Legend Library System</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Header specific styles with brown theme */
        .header {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(139, 69, 19, 0.1);
        }
        
        .logo {
            color: #8B4513; /* Saddle Brown */
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logo-icon {
            font-size: 1.8rem;
            color: #8B4513;
        }
        
        .nav-menu li a {
            position: relative;
            padding: 0.5rem 1rem;
            color: #333;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-menu li a:hover {
            color: #8B4513;
            background-color: #FFF8DC;
        }
        
        .nav-menu li a.active {
            color: #fff;
            background-color: #8B4513;
            border-radius: 4px;
        }
        
        .nav-menu li a.active:hover {
            color: #fff;
            background-color: #A0522D;
        }
        
        .user-menu-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: #FFF8DC;
            border: 1px solid #D2B48C;
            border-radius: 4px;
            color: #8B4513;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-menu-button:hover {
            background-color: #8B4513;
            color: #fff;
        }
        
        .user-menu-dropdown {
            border: 1px solid #D2B48C;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .user-menu-dropdown ul li a {
            padding: 0.75rem 1rem;
            display: block;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .user-menu-dropdown ul li a:hover {
            background-color: #FFF8DC;
            color: #8B4513;
        }
        
        .user-menu-dropdown ul li a i {
            width: 20px;
            margin-right: 0.5rem;
            color: #8B4513;
        }
        
        .mobile-menu-toggle {
            color: #8B4513;
            font-size: 1.5rem;
        }
        
        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #CD853F;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Search bar in header */
        .header-search {
            flex: 1;
            max-width: 400px;
            margin: 0 1rem;
            position: relative;
        }
        
        .header-search input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #D2B48C;
            border-radius: 20px;
            background-color: #FFF8DC;
            transition: all 0.3s ease;
        }
        
        .header-search input:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 2px rgba(139, 69, 19, 0.2);
        }
        
        .header-search i {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: #8B4513;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .header-search {
                max-width: 100%;
                margin: 1rem 0;
            }
            
            .nav-menu.show {
                padding: 1rem;
                background-color: #fff;
                border-top: 1px solid #D2B48C;
            }
            
            .nav-menu.show li {
                margin-bottom: 0.5rem;
            }
            
            .nav-menu.show li a {
                display: block;
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-container">
                <a href="<?php echo $base_url; ?>/index.php" class="logo">
                    <span class="logo-icon">📚</span>
                    <span>Legend Library</span>
                </a>
                
                <?php if ($current_page !== 'search.php'): ?>
                <div class="header-search">
                    <form action="<?php echo $base_url; ?>/search.php" method="GET">
                        <i class="fas fa-search"></i>
                        <input type="text" name="query" placeholder="Search materials...">
                    </form>
                </div>
                <?php endif; ?>
                
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <ul class="nav-menu" id="navMenu">
                    <li><a href="<?php echo $base_url; ?>/index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="<?php echo $base_url; ?>/search.php" class="<?php echo $current_page === 'search.php' ? 'active' : ''; ?>">Browse</a></li>
                    
                    <?php if ($is_logged_in): ?>
                        <li><a href="<?php echo $base_url; ?>/dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                        
                        <?php if ($is_admin): ?>
                            <li><a href="<?php echo $base_url; ?>/admin.php" class="<?php echo $current_page === 'admin.php' ? 'active' : ''; ?>">Admin</a></li>
                            <?php endif; ?>
                        <?php if($_SESSION['role'] == 'teacher'): ?>
                        
                        <li><a href="<?php echo $base_url; ?>/upload.php" class="<?php echo $current_page === 'upload.php' ? 'active' : ''; ?>">Upload</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <li><a href="<?php echo $base_url; ?>/about.php" class="<?php echo $current_page === 'about.php' ? 'active' : ''; ?>">About</a></li>
                </ul>
                
                <div class="user-menu">
                    <?php if ($is_logged_in): ?>
                        <button class="user-menu-button" id="userMenuButton">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($user_name); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-menu-dropdown" id="userMenuDropdown">
                            <ul>
                                <li><a href="<?php echo $base_url; ?>/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                                <li><a href="<?php echo $base_url; ?>/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="<?php echo $base_url; ?>/borrowed_books.php"><i class="fas fa-book"></i> My Borrowings</a></li>
                                <li><a href="<?php echo $base_url; ?>/favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                                <li><a href="<?php echo $base_url; ?>/settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                                <li><a href="<?php echo $base_url; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>/login.php" class="btn primary">Login</a>
                        <a href="<?php echo $base_url; ?>/register.php" class="btn secondary ml-2">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> mt-3">
                <?php 
                    echo $_SESSION['flash_message']; 
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_type']);
                ?>
            </div>
        <?php endif; ?>
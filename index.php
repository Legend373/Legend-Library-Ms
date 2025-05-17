<?php
require_once 'config.php';

// Get recent materials
try {
    $stmt = $conn->prepare("
        SELECT m.material_id, m.title, m.description, m.category, m.upload_date, 
               u.username as uploader,
               (SELECT COUNT(*) FROM material_downloads WHERE material_id = m.material_id) as download_count
        FROM materials m
        JOIN users u ON m.uploaded_by = u.user_id
        ORDER BY m.upload_date DESC
        LIMIT 8
    ");
    $stmt->execute();
    $recent_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching recent materials: " . $e->getMessage();
}

// Get categories for the sidebar with counts
try {
    $stmt = $conn->prepare("
        SELECT category, COUNT(*) as count
        FROM materials
        GROUP BY category
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
}

// Get total counts for stats
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM materials");
    $stmt->execute();
    $total_materials = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $total_users = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books");
    $stmt->execute();
    $total_books = $stmt->fetchColumn();
} catch(PDOException $e) {
    $error_message = "Error fetching stats: " . $e->getMessage();
}

// Log visit if user is logged in
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'visit_homepage');
}

// Page title for header
$page_title = "Home";

// Category icons mapping
$category_icons = [
    'Mathematics' => '📐',
    'Science' => '🔬',
    'Literature' => '📚',
    'History' => '🏛️',
    'Computer Science' => '💻',
    'Languages' => '🌐',
    'Arts' => '🎨',
    'Physics' => '⚛️',
    'Chemistry' => '🧪',
    'Biology' => '🧬',
    'Geography' => '🌍',
    'Economics' => '📊',
    'Philosophy' => '🧠',
    'Psychology' => '🧠',
    'Engineering' => '⚙️',
    'Medicine' => '🩺',
    'Law' => '⚖️',
    'Music' => '🎵',
    'Physical Education' => '🏃',
    'Religion' => '🙏',
    'Social Studies' => '👥',
    'Technology' => '📱',
    'Other' => '📋'
];

// Get default icon if category doesn't have a specific one
function getCategoryIcon($category) {
    global $category_icons;
    return isset($category_icons[$category]) ? $category_icons[$category] : '📚';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legend Library System</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/css/style.css">
    <style>
        /* Color Variables - Changed to Brown Theme */
        :root {
            --primary-color: #8B4513; /* Saddle Brown - Main color */
            --primary-light: #D2B48C; /* Tan - Light variant */
            --primary-dark: #5D2906; /* Dark Brown - Dark variant */
            --primary-bg: #FFF8DC; /* Cornsilk - Light background */
            --primary-hover: #A0522D; /* Sienna - Hover state */
            --primary-gradient-start: #8B4513; /* Saddle Brown */
            --primary-gradient-end: #A0522D; /* Sienna */
        }
        
        /* Hero Section Styles */
        .hero {
            
            

            background-image:linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.2)) ,url("<?php echo $base_url; ?>/assets/image.png");
            

            background-size: cover;
            padding: 8rem 2rem;
            background-position: center;
            color: white;
            padding: 6rem 2rem;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?php echo $base_url; ?>/assets/pattern.png');
            background-size: cover;
            opacity: 0.1;
            z-index: 0;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero h2 {
            font-size: 2.8rem;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .hero p {
            font-size: 1.3rem;
            max-width: 800px;
            margin: 0 auto 1.8rem;
            opacity: 0.9;
        }
        
        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 1.2rem;
            flex-wrap: wrap;
        }
        
        .hero-buttons .btn {
            padding: 0.8rem 1.8rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .hero-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Button Styles */
        .btn.primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn.primary:hover {
            background-color: var(--primary-hover);
        }
        
        .btn.secondary {
            background-color: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn.secondary:hover {
            background-color: var(--primary-bg);
            color: var(--primary-dark);
        }
        
        /* Stats Section */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border-bottom: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Features Section */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3.5rem;
        }
        
        .feature-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            padding: 2.5rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .feature-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-gradient-start), var(--primary-gradient-end));
            z-index: -1;
        }
        
        .feature-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.2rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .feature-card h3 {
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: #333;
        }
        
        .feature-card p {
            color: #666;
            line-height: 1.6;
        }
        
        /* Main Content Layout */
        .main-content {
            margin-bottom: 3rem; /* Add margin to prevent footer overlap */
        }
        
        /* Content Sections */
        .content-section {
            margin-bottom: 3rem;
            clear: both;
            width: 100%;
        }
        
        /* Category Browse Section - Full width */
        .category-browse {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10rem;
            margin-bottom: 8rem;
        }
        
        .category-item {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            padding: 0.8rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid transparent;
        }
        
        .category-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border-color: var(--primary-light);
            color: var(--primary-color);
        }
        
        .category-item-icon {
            font-size: 1.8rem;
            background-color: var(--primary-bg);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .category-item:hover .category-item-icon {
            background-color: var(--primary-color);
            color: white;
        }
        
        .category-item-name {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .category-item-count {
            background-color: var(--primary-bg);
            color: var(--primary-color);
            padding: 0.15rem 0.4rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .category-item:hover .category-item-count {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Materials Section */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 0.8rem;
        }
        
        .section-header h3 {
            font-size: 1.5rem;
            color: #333;
            margin: 0;
        }
        
        .section-header .view-all {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s ease;
        }
        
        .section-header .view-all:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Adaptive Material Cards Grid */
        .recent-materials {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 10rem;
        }
        
        /* For screens larger than 1200px, show more cards per row */
        @media (min-width: 1200px) {
            .recent-materials {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        /* For screens larger than 1600px, show even more cards per row */
        @media (min-width: 1600px) {
            .recent-materials {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }
        
        .material-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            padding: 1.3rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid transparent;
        }
        
        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border-color: var(--primary-light);
        }
        
        .material-card::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-gradient-start), var(--primary-gradient-end));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        
        .material-card:hover::after {
            transform: scaleX(1);
        }
        
        .material-card h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 1rem;
            line-height: 1.3;
        }
        
        .material-card h4 a {
            color: #333;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .material-card h4 a:hover {
            color: var(--primary-color);
        }
        
        .material-card p {
            color: #666;
            margin-bottom: 0.8rem;
            line-height: 1.3;
            font-size: 0.85rem;
            flex-grow: 1;
        }
        
        .material-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.8rem;
            font-size: 0.75rem;
        }
        
        .material-meta span {
            background-color: #f8f9fa;
            padding: 0.15rem 0.4rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.2rem;
            transition: all 0.2s ease;
        }
        
        .material-meta span:hover {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }
        
        .material-meta .category {
            background-color: var(--primary-bg);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .material-meta .uploader {
            background-color: #f0f0f0;
        }
        
        .material-meta .date {
            background-color: #f0f0f0;
        }
        
        .material-meta .downloads {
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        
        .material-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: auto;
        }
        
        .material-actions .btn {
            flex: 1;
            text-align: center;
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }
        
        .material-actions .btn:hover {
            transform: translateY(-3px);
        }
        
        /* Login Box - Now below material cards */
        .login-section {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2.5rem;
            
            overflow: hidden;
            position: relative;
            clear: both;
            width: 100%;
        }
        
        .login-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-gradient-start), var(--primary-gradient-end));
        }
        
        .login-header {
            background-color: var(--primary-bg);
            padding: 1.2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .login-header h3 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 1.5rem;
        }
        
        .login-content {
            padding: 1.8rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .login-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1.2rem;
            background-color: var(--primary-bg);
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .login-content p {
            color: #666;
            margin-bottom: 1.5rem;
            max-width: 600px;
            line-height: 1.6;
        }
        
        .login-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .login-buttons .btn {
            padding: 0.8rem 1.8rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .login-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .hero h2 {
                font-size: 2.2rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .feature-card {
                padding: 2rem 1.5rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .category-browse {
                grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            }
            
            .recent-materials {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .login-buttons {
                flex-direction: column;
                width: 100%;
                max-width: 300px;
            }
            
            .category-browse {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            
            }
            
            .recent-materials {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }
        
        @media (max-width: 576px) {
            .hero {
                padding: 3rem 1.5rem;
            }
            
            .hero h2 {
                font-size: 1.8rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .feature-icon {
                font-size: 2.5rem;
            }
            
            .feature-card h3 {
                font-size: 1.3rem;
            }
            
            .material-card {
                padding: 1rem;
            }
            
            .category-browse {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            }
            
            .recent-materials {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 0.8rem;
            }
            
            .login-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <section class="hero">
                <div class="hero-content">
                    <h2>Welcome to Legend Library</h2>
                    <p>Access a wide range of educational materials and books to enhance your learning experience.</p>
                    <div class="hero-buttons">
                        <a href="<?php echo $base_url; ?>/search.php" class="btn primary">Browse Materials</a>
                        <?php if (!isLoggedIn()): ?>
                            <a href="<?php echo $base_url; ?>/register.php" class="btn secondary">Create Account</a>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>/dashboard.php" class="btn secondary">Go to Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <section class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-number"><?php echo number_format($total_materials); ?></div>
                    <div class="stat-label">Educational Materials</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📖</div>
                    <div class="stat-number"><?php echo number_format($total_books); ?></div>
                    <div class="stat-label">Physical Books</div>
                </div>
            </section>
            
            <section class="features">
                <div class="feature-card">
                    <div class="feature-icon">📚</div>
                    <h3>Educational Materials</h3>
                    <p>Access a vast collection of educational resources uploaded by teachers and educators to enhance your learning journey.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <h3>Easy Search</h3>
                    <p>Find exactly what you need with our powerful search and filtering system designed for efficient resource discovery.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Borrow Books</h3>
                    <p>Borrow physical books from our library with just a few clicks and manage your borrowings through your personal dashboard.</p>
                </div>
            </section>
            
            <!-- Browse by Category Section - Full width -->
            <section class="content-section">
                <div class="section-header">
                    <h3>Browse by Category</h3>
                    <a href="<?php echo $base_url; ?>/search.php" class="view-all">View All Categories →</a>
                </div>
                
                <?php if (empty($categories)): ?>
                    <p>No categories available yet.</p>
                <?php else: ?>
                    <div class="category-browse">
                        <?php foreach ($categories as $category): ?>
                            <a href="<?php echo $base_url; ?>/search.php?category=<?php echo urlencode($category['category']); ?>" class="category-item">
                                <div class="category-item-icon"><?php echo getCategoryIcon($category['category']); ?></div>
                                <div class="category-item-name"><?php echo htmlspecialchars($category['category']); ?></div>
                                <div class="category-item-count"><?php echo $category['count']; ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- Recently Added Materials Section - Full width -->
            <section class="content-section">
                <div class="section-header">
                    <h3>Recently Added Materials</h3>
                    <a href="<?php echo $base_url; ?>/search.php" class="view-all">View All →</a>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="error-container">
                        <p><?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($recent_materials)): ?>
                    <p>No materials available yet.</p>
                <?php else: ?>
                    <div class="recent-materials">
                        <?php foreach ($recent_materials as $material): ?>
                            <div class="material-card">
                                <h4>
                                    <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>">
                                        <?php echo htmlspecialchars($material['title']); ?>
                                    </a>
                                </h4>
                                <p>
                                    <?php 
                                        $desc = htmlspecialchars($material['description']);
                                        echo (strlen($desc) > 60) ? substr($desc, 0, 60) . '...' : $desc;
                                    ?>
                                </p>
                                <div class="material-meta">
                                    <span class="category">
                                        <?php echo getCategoryIcon($material['category']); ?> 
                                        <?php echo htmlspecialchars($material['category']); ?>
                                    </span>
                                    <span class="uploader">
                                        👤 <?php echo htmlspecialchars($material['uploader']); ?>
                                    </span>
                                </div>
                                <div class="material-meta">
                                    <span class="date">
                                        📅 <?php echo formatDate($material['upload_date']); ?>
                                    </span>
                                    <?php if (isset($material['download_count'])): ?>
                                        <span class="downloads">
                                            ⬇️ <?php echo $material['download_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="material-actions">
                                    <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>" class="btn primary">View</a>
                                    <a href="<?php echo $base_url; ?>/download_material.php?id=<?php echo $material['material_id']; ?>" class="btn secondary">Download</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- Login Box Section - Full width -->
            <?php if (!isLoggedIn()): ?>
                <section class="content-section login-section">
                    <div class="login-header">
                        <h3>Already a Member?</h3>
                    </div>
                    <div class="login-content">
                        <div class="login-icon">👤</div>
                        <p>Log in to access your dashboard, borrow books, and download educational materials. Registered users can track their borrowings, save favorite materials, and receive notifications about new resources.</p>
                        <div class="login-buttons">
                            <a href="<?php echo $base_url; ?>/login.php" class="btn primary">Login Now</a>
                            <a href="<?php echo $base_url; ?>/register.php" class="btn secondary">Create Account</a>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to stats numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            
            statNumbers.forEach(statNumber => {
                const finalValue = parseInt(statNumber.textContent.replace(/,/g, ''));
                let startValue = 0;
                const duration = 2000; // 2 seconds
                const increment = finalValue / (duration / 16); // 60fps
                
                function updateNumber() {
                    if (startValue < finalValue) {
                        startValue += increment;
                        if (startValue > finalValue) {
                            startValue = finalValue;
                        }
                        statNumber.textContent = Math.floor(startValue).toLocaleString();
                        requestAnimationFrame(updateNumber);
                    }
                }
                
                // Start animation when element is in viewport
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateNumber();
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1 });
                
                observer.observe(statNumber);
            });
            
            // Add hover effect to material cards
            const materialCards = document.querySelectorAll('.material-card');
            
            materialCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.12)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
            
            // Add hover effect to category items
            const categoryItems = document.querySelectorAll('.category-item');
            
            categoryItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.12)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
        });
    </script>
</body>
</html>
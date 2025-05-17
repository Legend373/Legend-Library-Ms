<?php
require_once 'config.php';

// Get search parameters
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;

// Build search query
$search_query = "
    SELECT m.material_id, m.title, m.description, m.category, m.upload_date, 
           u.username as uploader,
           (SELECT COUNT(*) FROM material_downloads WHERE material_id = m.material_id) as download_count
    FROM materials m
    JOIN users u ON m.uploaded_by = u.user_id
    WHERE 1=1
";

$params = [];

if (!empty($query)) {
    $search_query .= " AND (m.title LIKE :query OR m.description LIKE :query OR m.category LIKE :query)";
    $params[':query'] = "%$query%";
}

if (!empty($category)) {
    $search_query .= " AND m.category = :category";
    $params[':category'] = $category;
}

// Add sorting
switch ($sort) {
    case 'title_asc':
        $search_query .= " ORDER BY m.title ASC";
        break;
    case 'title_desc':
        $search_query .= " ORDER BY m.title DESC";
        break;
    case 'downloads':
        $search_query .= " ORDER BY download_count DESC";
        break;
    case 'oldest':
        $search_query .= " ORDER BY m.upload_date ASC";
        break;
    case 'newest':
    default:
        $search_query .= " ORDER BY m.upload_date DESC";
        break;
}

// Get total count for pagination
try {
    $count_query = str_replace("m.material_id, m.title, m.description, m.category, m.upload_date, 
           u.username as uploader,
           (SELECT COUNT(*) FROM material_downloads WHERE material_id = m.material_id) as download_count", "COUNT(*) as total", $search_query);
    
    // Remove ORDER BY for count query
    $count_query = preg_replace('/ORDER BY.*$/i', '', $count_query);
    
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_count = $stmt->fetchColumn();
    
    $total_pages = ceil($total_count / $per_page);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;
    
    // Add pagination to search query
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', (int)$per_page, PDO::PARAM_INT);

    
    // Execute search query
    $stmt = $conn->prepare($search_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error searching materials: " . $e->getMessage();
}

// Get all categories for filter
try {
    $stmt = $conn->prepare("SELECT DISTINCT category FROM materials ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
}

// Log search if user is logged in
if (isLoggedIn() && !empty($query)) {
    logActivity($_SESSION['user_id'], 'search', $query);
}

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

// Page title for header
$page_title = "Search Materials";
?>
<?php include 'includes/header.php'; ?>

<div class="search-container">
    <div class="search-header">
        <h1>Search Educational Materials</h1>
        <p>Find the resources you need for your studies</p>
    </div>
    
    <div class="search-filters">
        <form method="GET" action="" class="search-form">
            <div class="search-input-container">
                <input type="text" name="query" placeholder="Search by title, description, or category..." value="<?php echo htmlspecialchars($query); ?>" class="search-input">
                <button type="submit" class="search-button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            
            <div class="filter-container">
                <div class="filter-group">
                    <label for="category" class="filter-label">Category</label>
                    <select name="category" id="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort" class="filter-label">Sort By</label>
                    <select name="sort" id="sort" class="filter-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                        <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                        <option value="downloads" <?php echo $sort === 'downloads' ? 'selected' : ''; ?>>Most Downloads</option>
                    </select>
                </div>
                
                <button type="submit" class="btn primary filter-button">Apply Filters</button>
                <a href="<?php echo $base_url; ?>/search.php" class="btn secondary reset-button">Reset</a>
            </div>
        </form>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="search-results">
        <div class="results-header">
            <?php if (!empty($query) || !empty($category)): ?>
                <h2>
                    <?php if (!empty($query) && !empty($category)): ?>
                        Search results for "<?php echo htmlspecialchars($query); ?>" in <?php echo htmlspecialchars($category); ?>
                    <?php elseif (!empty($query)): ?>
                        Search results for "<?php echo htmlspecialchars($query); ?>"
                    <?php elseif (!empty($category)): ?>
                        Materials in <?php echo htmlspecialchars($category); ?>
                    <?php endif; ?>
                </h2>
            <?php else: ?>
                <h2>All Materials</h2>
            <?php endif; ?>
            
            <div class="results-count">
                <?php echo number_format($total_count); ?> materials found
            </div>
        </div>
        
        <?php if (empty($materials)): ?>
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>No materials found</h3>
                <p>Try adjusting your search criteria or browse all materials</p>
                <a href="<?php echo $base_url; ?>/search.php" class="btn primary">Browse All Materials</a>
            </div>
        <?php else: ?>
            <div class="materials-grid">
                <?php foreach ($materials as $material): ?>
                    <div class="material-card">
                        <h3 class="material-title">
                            <a href="<?php echo $base_url; ?>/view_material.php?id=<?php echo $material['material_id']; ?>">
                                <?php echo htmlspecialchars($material['title']); ?>
                            </a>
                        </h3>
                        <p class="material-description">
                            <?php 
                                $desc = htmlspecialchars($material['description']);
                                echo (strlen($desc) > 100) ? substr($desc, 0, 100) . '...' : $desc;
                            ?>
                        </p>
                        <div class="material-meta">
                            <span class="category">
                                <?php echo getCategoryIcon($material['category']); ?> 
                                <?php echo htmlspecialchars($material['category']); ?>
                            </span>
                            <span class="uploader">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($material['uploader']); ?>
                            </span>
                        </div>
                        <div class="material-meta">
                            <span class="date">
                                <i class="fas fa-calendar-alt"></i> <?php echo formatDate($material['upload_date']); ?>
                            </span>
                            <?php if (isset($material['download_count'])): ?>
                                <span class="downloads">
                                    <i class="fas fa-download"></i> <?php echo $material['download_count']; ?>
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
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <ul>
                        <?php if ($page > 1): ?>
                            <li>
                                <a href="<?php echo $base_url; ?>/search.php?query=<?php echo urlencode($query); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li><a href="' . $base_url . '/search.php?query=' . urlencode($query) . '&category=' . urlencode($category) . '&sort=' . $sort . '&page=1">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="pagination-ellipsis">...</li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li' . ($i === $page ? ' class="active"' : '') . '>';
                                echo '<a href="' . $base_url . '/search.php?query=' . urlencode($query) . '&category=' . urlencode($category) . '&sort=' . $sort . '&page=' . $i . '">' . $i . '</a>';
                                echo '</li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="pagination-ellipsis">...</li>';
                                }
                                echo '<li><a href="' . $base_url . '/search.php?query=' . urlencode($query) . '&category=' . urlencode($category) . '&sort=' . $sort . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                            }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li>
                                <a href="<?php echo $base_url; ?>/search.php?query=<?php echo urlencode($query); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Search page specific styles with brown theme */
    .search-container {
        padding: 2rem 0;
    }
    
    .search-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .search-header h1 {
        color: #8B4513;
        margin-bottom: 0.5rem;
    }
    
    .search-header p {
        color: #666;
        font-size: 1.1rem;
    }
    
    .search-filters {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .search-form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .search-input-container {
        position: relative;
    }
    
    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 3rem;
        border: 1px solid #D2B48C;
        border-radius: 5px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    .search-input:focus {
        border-color: #8B4513;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2);
        outline: none;
    }
    
    .search-button {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #8B4513;
        font-size: 1.2rem;
        cursor: pointer;
    }
    
    .filter-container {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 0.5rem;
        color: #333;
        font-weight: 500;
    }
    
    .filter-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #D2B48C;
        border-radius: 5px;
        font-size: 1rem;
        background-color: #fff;
        transition: all 0.3s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%238B4513' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1rem;
    }
    
    .filter-select:focus {
        border-color: #8B4513;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2);
        outline: none;
    }
    
    .filter-button, .reset-button {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        font-weight: 500;
    }
    
    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 0.8rem;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .results-header h2 {
        color: #333;
        margin: 0;
        font-size: 1.3rem;
    }
    
    .results-count {
        color: #666;
        font-size: 0.9rem;
    }
    
    .no-results {
        text-align: center;
        padding: 3rem 1rem;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    }
    
    .no-results-icon {
        font-size: 3rem;
        color: #D2B48C;
        margin-bottom: 1rem;
    }
    
    .no-results h3 {
        color: #333;
        margin-bottom: 0.5rem;
    }
    
    .no-results p {
        color: #666;
        margin-bottom: 1.5rem;
    }
    
    .materials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .material-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        height: 100%;
        border: 1px solid transparent;
    }
    
    .material-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        border-color: #D2B48C;
    }
    
    .material-title {
        font-size: 1.2rem;
        margin-bottom: 0.8rem;
        line-height: 1.3;
    }
    
    .material-title a {
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .material-title a:hover {
        color: #8B4513;
    }
    
    .material-description {
        color: #666;
        margin-bottom: 1rem;
        line-height: 1.5;
        flex-grow: 1;
    }
    
    .material-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.8rem;
        margin-bottom: 1rem;
        font-size: 0.85rem;
    }
    
    .material-meta span {
        background-color: #f8f9fa;
        padding: 0.3rem 0.6rem;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 0.3rem;
        transition: all 0.2s ease;
    }
    
    .material-meta span:hover {
        background-color: #FFF8DC;
        color: #8B4513;
    }
    
    .material-meta .category {
        background-color: #FFF8DC;
        color: #8B4513;
        font-weight: 500;
    }
    
    .material-actions {
        display: flex;
        gap: 0.8rem;
        margin-top: auto;
    }
    
    .material-actions .btn {
        flex: 1;
        text-align: center;
    }
    
    .pagination {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }
    
    .pagination ul {
        display: flex;
        list-style: none;
        gap: 0.5rem;
    }
    
    .pagination ul li {
        margin: 0;
    }
    
    .pagination ul li a {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        background-color: #fff;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 1px solid #D2B48C;
    }
    
    .pagination ul li a:hover {
        background-color: #FFF8DC;
        color: #8B4513;
    }
    
    .pagination ul li.active a {
        background-color: #8B4513;
        color: #fff;
        border-color: #8B4513;
    }
    
    .pagination-ellipsis {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        color: #666;
    }
    
    @media (max-width: 992px) {
        .materials-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .filter-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .materials-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }
    
    @media (max-width: 576px) {
        .materials-grid {
            grid-template-columns: 1fr;
        }
        
        .results-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
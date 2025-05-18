<?php
require_once 'config.php';

// Initialize variables to prevent undefined errors
$books = [];
$categories = [];
$authors = [];
$total_count = 0;
$total_pages = 1;
$error_message = null;

// Get search parameters with proper null checks
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$author = isset($_GET['author']) ? trim($_GET['author']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$errors = [];
$user_id = $_SESSION['user_id'];
$success_message = "";

// Handle book borrowing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['borrow'])) {
    $book_id = $_POST['book_id'];
    $due_date = date('Y-m-d H:i:s', strtotime('+14 days')); // 2 weeks from now
    
    try {
        // Check if book is available
        $stmt = $conn->prepare("SELECT status FROM books WHERE book_id = ?");
        $stmt->execute([$book_id]);
        $book_status = $stmt->fetchColumn();
        
        if ($book_status != 'available') {
            $errors[] = "This book is not available for borrowing.";
        } else {
            // Check if user has reached borrowing limit (max 5 books)
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM borrowings 
                WHERE user_id = ? AND status IN ('active', 'overdue')
            ");
            $stmt->execute([$user_id]);
            $active_borrowings = $stmt->fetchColumn();
            
            if ($active_borrowings >= 5) {
                $errors[] = "You have reached the maximum limit of 5 borrowed books.";
            } else {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update book status
                $stmt = $conn->prepare("UPDATE books SET status = 'borrowed' WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Create borrowing record
                $stmt = $conn->prepare("
                    INSERT INTO borrowings (book_id, user_id, due_date, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$book_id, $user_id, $due_date]);
                
                // Get book title for logging
                $stmt = $conn->prepare("SELECT title FROM books WHERE book_id = ?");
                $stmt->execute([$book_id]);
                $book_title = $stmt->fetchColumn();
                
                // Log activity
                logActivity($user_id, 'borrow_book', "Borrowed book: $book_title");
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Book borrowed successfully! Due date: " . formatDate($due_date);
            }
        }
    } catch(PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $errors[] = "Error borrowing book: " . $e->getMessage();
    }
}

// Build base query using only the books table
$base_query = "SELECT 
    book_id,
    isbn,
    title, 
    author, 
    category, 
    status,
    added_by,
    added_date
FROM books
WHERE 1=1";

$params = [];

// Add search conditions
if (!empty($query)) {
    $base_query .= " AND (title LIKE :query OR author LIKE :query OR isbn LIKE :query OR category LIKE :query)";
    $params[':query'] = "%$query%";
}

if (!empty($category)) {
    $base_query .= " AND category = :category";
    $params[':category'] = $category;
}

if (!empty($author)) {
    $base_query .= " AND author LIKE :author";
    $params[':author'] = "%$author%";
}

if (!empty($status) && in_array($status, ['available', 'borrowed', 'reserved'])) {
    $base_query .= " AND status = :status";
    $params[':status'] = $status;
}

// Add sorting
switch ($sort) {
    case 'title_asc':
        $base_query .= " ORDER BY title ASC";
        break;
    case 'title_desc':
        $base_query .= " ORDER BY title DESC";
        break;
    case 'author':
        $base_query .= " ORDER BY author ASC";
        break;
    case 'oldest':
        $base_query .= " ORDER BY added_date ASC";
        break;
    case 'newest':
    default:
        $base_query .= " ORDER BY added_date DESC";
        break;
}

try {
    // Get total count
    $count_query = "SELECT COUNT(*) FROM books WHERE 1=1";
    if (!empty($query)) $count_query .= " AND (title LIKE :query OR author LIKE :query OR isbn LIKE :query OR category LIKE :query)";
    if (!empty($category)) $count_query .= " AND category = :category";
    if (!empty($author)) $count_query .= " AND author LIKE :author";
    if (!empty($status)) $count_query .= " AND status = :status";
    
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_count = $stmt->fetchColumn();
    
    // Calculate pagination
    $total_pages = max(1, ceil($total_count / $per_page));
    $page = min(max(1, $page), $total_pages);
    $offset = ($page - 1) * $per_page;
    
    // Get paginated results
    $search_query = $base_query . " LIMIT :offset, :per_page";
    $stmt = $conn->prepare($search_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get distinct categories
    $stmt = $conn->query("SELECT DISTINCT category FROM books ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get distinct authors
    $stmt = $conn->query("SELECT DISTINCT author FROM books ORDER BY author");
    $authors = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $error_message = "Error searching books: " . $e->getMessage();
}

// Category icons mapping
$category_icons = [
    'Fiction' => '📖',
    'Non-Fiction' => '📚',
    'Science Fiction' => '🚀',
    'Fantasy' => '🧙',
    'Mystery' => '🕵️',
    'Romance' => '💖',
    'Biography' => '👤',
    'History' => '🏛️',
    'Science' => '🔬',
    'Technology' => '💻',
    'Self-Help' => '💪',
    'Business' => '💼',
    'Children' => '🧸',
    'Poetry' => '✒️',
    'Drama' => '🎭',
    'Horror' => '👻',
    'Thriller' => '🔪',
    'Cookbooks' => '🍳',
    'Art' => '🎨',
    'Travel' => '✈️',
    'Religion' => '🙏',
    'Other' => '📕'
];

function getCategoryIcon($category) {
    global $category_icons;
    return $category_icons[$category] ?? '📚';
}

function getBookStatus($status) {
    switch ($status) {
        case 'available': return 'Available';
        case 'borrowed': return 'Borrowed';
        case 'reserved': return 'Reserved';
        default: return ucfirst($status);
    }
}


$page_title = "Search Books";
include 'includes/header.php';
?>

<div class="search-container">
    <div class="search-header">
        <h1>Search Library Books</h1>
        <p>Find books to borrow from our collection</p>
    </div>
    
    <div class="search-filters">
        <form method="GET" action="" class="search-form">
            <div class="search-input-container">
                <input type="text" name="query" placeholder="Search by title, author, ISBN, or category..." 
                       value="<?php echo htmlspecialchars($query); ?>" class="search-input">
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
                    <label for="author" class="filter-label">Author</label>
                    <select name="author" id="author" class="filter-select">
                        <option value="">All Authors</option>
                        <?php foreach ($authors as $auth): ?>
                            <option value="<?php echo htmlspecialchars($auth); ?>" <?php echo $author === $auth ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($auth); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status" class="filter-label">Status</label>
                    <select name="status" id="status" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="borrowed" <?php echo $status === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="reserved" <?php echo $status === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort" class="filter-label">Sort By</label>
                    <select name="sort" id="sort" class="filter-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest Additions</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest Additions</option>
                        <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                        <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                        <option value="author" <?php echo $sort === 'author' ? 'selected' : ''; ?>>Author (A-Z)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn primary filter-button">Apply Filters</button>
                <a href="<?php echo $base_url; ?>/books.php" class="btn secondary reset-button">Reset</a>
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
            <?php if (!empty($query) || !empty($category) || !empty($author) || !empty($status)): ?>
                <h2>
                    <?php
                    $search_terms = [];
                    if (!empty($query)) $search_terms[] = '"' . htmlspecialchars($query) . '"';
                    if (!empty($category)) $search_terms[] = 'category: ' . htmlspecialchars($category);
                    if (!empty($author)) $search_terms[] = 'author: ' . htmlspecialchars($author);
                    if (!empty($status)) $search_terms[] = 'status: ' . htmlspecialchars($status);
                    echo "Search results for " . implode(', ', $search_terms);
                    ?>
                </h2>
            <?php else: ?>
                <h2>All Books</h2>
            <?php endif; ?>
            
            <div class="results-count">
                <?php echo number_format($total_count); ?> books found
            </div>
        </div>
        
        <?php if (empty($books)): ?>
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3>No books found</h3>
                <p>Try adjusting your search criteria or browse all books</p>
                <a href="<?php echo $base_url; ?>/books.php" class="btn primary">Browse All Books</a>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php foreach ($books as $book): ?>
                    <div class="book-card">
                        <div class="book-cover-container">
                            <div class="book-cover-placeholder">
                                <i class="fas fa-book-open"></i>
                                <span>No Cover Available</span>
                            </div>
                        </div>
                        
                        <div class="book-details">
                            <h3 class="book-title">
                                <a href="<?php echo $base_url; ?>/view_book.php?id=<?php echo $book['book_id']; ?>">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </a>
                            </h3>
                            <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                            <p class="book-isbn">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></p>
                            
                            <div class="book-meta">
                                <span class="category">
                                    <?php echo getCategoryIcon($book['category']); ?> 
                                    <?php echo htmlspecialchars($book['category']); ?>
                                </span>
                                <span class="year">
                                    <i class="fas fa-calendar-alt"></i> 
                                    <?php echo formatDate($book['added_date']); ?>
                                </span>
                            </div>
                            
                            <div class="book-status">
                                <span class="status-badge status-<?php echo $book['status']; ?>">
                                    <?php echo getBookStatus($book['status']); ?>
                                </span>
                            </div>
                              
                                            
                            
                            <div class="book-actions">
                                <a href="<?php echo $base_url; ?>/view_book.php?id=<?php echo $book['book_id']; ?>" class="btn primary">View Details</a>
                                <?php if ($book['status'] === 'available' && isLoggedIn()): ?>
                                    <form action="<?php echo $base_url; ?>/books.php" method="post">
                                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                <button type="submit" name="borrow" class="btn primary">Borrow </button>
                                            </form>
                                <?php elseif ($book['status'] === 'borrowed' && isLoggedIn()): ?>
                                    <a href="<?php echo $base_url; ?>/reserve_book.php?id=<?php echo $book['book_id']; ?>" class="btn secondary">Reserve</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <ul>
                        <?php if ($page > 1): ?>
                            <li>
                                <a href="<?php echo $base_url; ?>/books.php?<?php 
                                    echo http_build_query([
                                        'query' => $query,
                                        'category' => $category,
                                        'author' => $author,
                                        'status' => $status,
                                        'sort' => $sort,
                                        'page' => $page - 1
                                    ]); 
                                ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li><a href="' . $base_url . '/books.php?' . http_build_query([
                                    'query' => $query,
                                    'category' => $category,
                                    'author' => $author,
                                    'status' => $status,
                                    'sort' => $sort,
                                    'page' => 1
                                ]) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="pagination-ellipsis">...</li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li' . ($i === $page ? ' class="active"' : '') . '>';
                                echo '<a href="' . $base_url . '/books.php?' . http_build_query([
                                    'query' => $query,
                                    'category' => $category,
                                    'author' => $author,
                                    'status' => $status,
                                    'sort' => $sort,
                                    'page' => $i
                                ]) . '">' . $i . '</a>';
                                echo '</li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="pagination-ellipsis">...</li>';
                                }
                                echo '<li><a href="' . $base_url . '/books.php?' . http_build_query([
                                    'query' => $query,
                                    'category' => $category,
                                    'author' => $author,
                                    'status' => $status,
                                    'sort' => $sort,
                                    'page' => $total_pages
                                ]) . '">' . $total_pages . '</a></li>';
                            }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li>
                                <a href="<?php echo $base_url; ?>/books.php?<?php 
                                    echo http_build_query([
                                        'query' => $query,
                                        'category' => $category,
                                        'author' => $author,
                                        'status' => $status,
                                        'sort' => $sort,
                                        'page' => $page + 1
                                    ]); 
                                ?>">
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
    /* Search page specific styles with brown theme for books */
.search-container {
    padding: 2rem 0;
    max-width: 1200px;
    margin: 0 auto;
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

/* BOOK GRID STYLES */
.books-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.book-card {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
    border: 1px solid transparent;
    overflow: hidden;
}

.book-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    border-color: #D2B48C;
}

.book-cover-container {
    height: 200px;
    background-color: #FFF8DC;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #f0f0f0;
}

.book-cover {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
}

.book-cover-placeholder {
    text-align: center;
    color: #8B4513;
}

.book-cover-placeholder i {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    display: block;
}

.book-details {
    padding: 1.5rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.book-title {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.book-title a {
    color: #333;
    text-decoration: none;
    transition: all 0.3s ease;
}

.book-title a:hover {
    color: #8B4513;
}

.book-author {
    color: #666;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.book-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
}

.book-meta span {
    background-color: #f8f9fa;
    padding: 0.3rem 0.6rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.2s ease;
}

.book-meta span:hover {
    background-color: #FFF8DC;
    color: #8B4513;
}

.book-meta .category {
    background-color: #FFF8DC;
    color: #8B4513;
    font-weight: 500;
}

.book-status {
    margin-top: auto;
    margin-bottom: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-available {
    background-color: #e6f7ee;
    color: #28a745;
}

.status-borrowed {
    background-color: #fff3cd;
    color: #856404;
}

.status-reserved {
    background-color: #e2e3e5;
    color: #383d41;
}

.borrow-count {
    color: #666;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.book-actions {
    display: flex;
    gap: 0.8rem;
    margin-top: 1rem;
}

.book-actions .btn {
    flex: 1;
    text-align: center;
    padding: 0.5rem;
    font-size: 0.9rem;
}

/* Pagination styles (same as before) */
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

/* Responsive styles */
@media (max-width: 992px) {
    .books-grid {
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
    
    .books-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
}

@media (max-width: 576px) {
    .books-grid {
        grid-template-columns: 1fr;
    }
    
    .results-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .book-actions {
        flex-direction: column;
    }
    
    .book-actions .btn {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
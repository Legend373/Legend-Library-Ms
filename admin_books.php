<?php
require_once 'config.php';

// Verify admin access
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = "Access denied - Admin only";
    $_SESSION['flash_type'] = "danger";
    header("Location: login.php");
    exit;
}

// Initialize variables
$books = [];
$total_books = 0;
$total_pages = 1;
$error = null;

// Handle book actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        if (isset($_POST['delete_book'])) {
            $book_id = (int)$_POST['book_id'];
            
            // Check if book can be deleted (not currently borrowed)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = :book_id AND status != 'returned'");
            $stmt->bindParam(':book_id', $book_id);
            $stmt->execute();
            $active_borrowings = $stmt->fetchColumn();
            
            if ($active_borrowings > 0) {
                throw new Exception("Cannot delete book with active borrowings");
            }
            
            $stmt = $conn->prepare("DELETE FROM books WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id);
            $stmt->execute();
            
            // Log action
            $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, message)
                                  VALUES (:admin_id, 'warning', 'Deleted book ID: ".$book_id."')");
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $_SESSION['flash_message'] = "Book deleted successfully!";
            $_SESSION['flash_type'] = "success";
        }
        elseif (isset($_POST['update_status'])) {
            $book_id = (int)$_POST['book_id'];
            $new_status = in_array($_POST['status'], ['available', 'borrowed', 'reserved', 'maintenance']) ? $_POST['status'] : 'available';
            
            // Additional checks for status changes
            if ($new_status === 'borrowed') {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = :book_id AND status = 'active'");
                $stmt->bindParam(':book_id', $book_id);
                $stmt->execute();
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception("Cannot set status to 'borrowed' - no active borrow record found");
                }
            }
            
            $stmt = $conn->prepare("UPDATE books SET status = :status WHERE book_id = :book_id");
            $stmt->bindParam(':status', $new_status);
            $stmt->bindParam(':book_id', $book_id);
            $stmt->execute();
            
            // Log action
            $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, message)
                                  VALUES (:admin_id, 'info', 'Updated status for book ID: ".$book_id." to ".$new_status."')");
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $_SESSION['flash_message'] = "Book status updated successfully!";
            $_SESSION['flash_type'] = "success";
        }
        
        $conn->commit();
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['flash_message'] = "Error: ".$e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: admin_books.php");
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

// Build query
$query = "SELECT b.book_id, b.title, b.author, b.isbn, b.category, b.status, b.added_date,
                 u.username AS added_by_name
          FROM books b
          JOIN users u ON b.added_by = u.user_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $query .= " AND b.category = :category";
    $params[':category'] = $category_filter;
}

if (!empty($status_filter) && in_array($status_filter, ['available', 'borrowed', 'reserved', 'maintenance'])) {
    $query .= " AND b.status = :status";
    $params[':status'] = $status_filter;
}

// Get total count
try {
    $count_query = "SELECT COUNT(*) FROM ($query) AS total";
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_books = $stmt->fetchColumn();
    
    $total_pages = ceil($total_books / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get paginated results
    $query .= " ORDER BY b.title ASC LIMIT :offset, :per_page";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all distinct categories for filter dropdown
    $categories = $conn->query("SELECT DISTINCT category FROM books ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $error = "Database error: ".$e->getMessage();
}

$page_title = "Admin - Book Management";
include 'includes/header.php';
?>

<div class="admin-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <!-- Header Section -->
    <div class="admin-header" style="margin-bottom: 30px; border-bottom: 2px solid #8B4513; padding-bottom: 15px;">
        <h1 style="color: #8B4513; margin: 0;">Book Management</h1>
        <p style="color: #666;">Manage library book collection</p>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_type']; ?>" 
             style="padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px;
                    <?php echo $_SESSION['flash_type'] === 'success' ? 'background-color: #dff0d8; color: #3c763d;' : 'background-color: #f2dede; color: #a94442;' ?>">
            <?php echo $_SESSION['flash_message']; unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filter Bar -->
    <div class="search-filter-bar" style="background: #FFF8DC; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <form method="GET" action="" class="search-form" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <div class="search-box" style="flex: 1; min-width: 200px;">
                <input type="text" name="search" placeholder="Search by title, author or ISBN..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       style="width: 100%; padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
            </div>
            
            <div class="filter-group">
                <select name="category" style="padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" 
                            <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="status" style="padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
                    <option value="">All Statuses</option>
                    <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="borrowed" <?php echo $status_filter === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                    <option value="reserved" <?php echo $status_filter === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                    <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                </select>
            </div>
            
            <button type="submit" class="btn-search" 
                    style="padding: 8px 15px; background-color: #8B4513; color: white; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="admin_books.php" class="btn-reset"
               style="padding: 8px 15px; background-color: #D2B48C; color: #333; text-decoration: none; border-radius: 4px;">
                <i class="fas fa-sync-alt"></i> Reset
            </a>
        </form>
    </div>

    <!-- Book Count and Add Book Button -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div class="book-count" style="color: #666;">
            Showing <?php echo count($books); ?> of <?php echo $total_books; ?> books
        </div>
        <a href="admin_add_book.php" class="btn-add-book"
           style="padding: 8px 15px; background-color: #8B4513; color: white; text-decoration: none; border-radius: 4px;">
            <i class="fas fa-book-medical"></i> Add New Book
        </a>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
        <div class="alert alert-danger" style="padding: 15px; margin-bottom: 20px; background-color: #f2dede; color: #a94442; border: 1px solid transparent; border-radius: 4px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Books Table -->
    <div class="books-table" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #FFF8DC;">
                    <th style="padding: 12px 15px; text-align: left;">Title</th>
                    <th style="padding: 12px 15px; text-align: left;">Author</th>
                    <th style="padding: 12px 15px; text-align: left;">ISBN</th>
                    <th style="padding: 12px 15px; text-align: left;">Category</th>
                    <th style="padding: 12px 15px; text-align: left;">Status</th>
                    <th style="padding: 12px 15px; text-align: left;">Added By</th>
                    <th style="padding: 12px 15px; text-align: left;">Date Added</th>
                    <th style="padding: 12px 15px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($books)): ?>
                    <tr>
                        <td colspan="8" style="padding: 20px; text-align: center; color: #666;">
                            <?php echo $error ? 'Error loading books' : 'No books found matching your criteria'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px 15px;">
                                <a href="admin_book_details.php?id=<?php echo $book['book_id']; ?>" 
                                   style="color: #8B4513; text-decoration: none; font-weight: 500;">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </a>
                            </td>
                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($book['author']); ?></td>
                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($book['isbn']); ?></td>
                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($book['category']); ?></td>
                            <td style="padding: 12px 15px;">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                    <select name="status" onchange="this.form.submit()"
                                            style="padding: 5px; border: 1px solid #D2B48C; border-radius: 4px;
                                                   background-color: <?php 
                                                       echo $book['status'] === 'available' ? '#d4edda' : 
                                                              ($book['status'] === 'borrowed' ? '#f8d7da' : 
                                                              ($book['status'] === 'reserved' ? '#fff3cd' : '#e2e3e5'));
                                                   ?>">
                                        <option value="available" <?php echo $book['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="borrowed" <?php echo $book['status'] === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                                        <option value="reserved" <?php echo $book['status'] === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                                        <option value="maintenance" <?php echo $book['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    </select>
                                    <noscript><button type="submit" name="update_status">Update</button></noscript>
                                </form>
                            </td>
                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($book['added_by_name']); ?></td>
                            <td style="padding: 12px 15px;"><?php echo date('M j, Y', strtotime($book['added_date'])); ?></td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <a href="admin_edit_book.php?id=<?php echo $book['book_id']; ?>" 
                                   class="btn-action"
                                   style="padding: 5px 5px; background-color: #8B4513; color: white; 
                                          text-decoration: none; border-radius: 4px; margin-right: 5px;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                    <button type="submit" name="delete_book" class="btn-action"
                                            style="padding: 5px 5px; background-color: #dc3545; color: white; 
                                                   border: none; border-radius: 4px; cursor: pointer;"
                                            onclick="return confirm('Are you sure you want to delete this book?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                
                                
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 20px; display: flex; justify-content: center;">
            <ul style="display: flex; list-style: none; padding: 0; margin: 0; gap: 5px;">
                <?php if ($page > 1): ?>
                    <li>
                        <a href="admin_books.php?<?php echo http_build_query(array_merge(
                            $_GET,
                            ['page' => $page - 1]
                        )); ?>"
                           style="display: block; padding: 8px 12px; background-color: #FFF8DC; 
                                  color: #8B4513; text-decoration: none; border-radius: 4px;">
                            &laquo; Previous
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li>
                        <a href="admin_books.php?<?php echo http_build_query(array_merge(
                            $_GET,
                            ['page' => $i]
                        )); ?>"
                           style="display: block; padding: 8px 12px; 
                                  
                                  text-decoration: none; border-radius: 4px;"<?php echo $i === $page ? 'background-color: #8B4513; color: white;' : 'background-color: #FFF8DC; color: #8B4513;' ?>>
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li>
                        <a href="admin_books.php?<?php echo http_build_query(array_merge(
                            $_GET,
                            ['page' => $page + 1]
                        )); ?>"
                           style="display: block; padding: 8px 12px; background-color: #FFF8DC; 
                                  color: #8B4513; text-decoration: none; border-radius: 4px;">
                            Next &raquo;
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Hover effects */
    .btn-search:hover, .btn-add-book:hover {
        background-color: #A0522D !important;
        transition: background-color 0.3s;
    }
    
    .btn-reset:hover, .btn-action:hover {
        opacity: 0.9;
        transition: opacity 0.3s;
    }
    
    tr:hover {
        background-color: #FFF8DC !important;
    }
    
    /* Responsive adjustments */
    @media (max-width: 992px) {
        .books-table table {
            display: block;
            overflow-x: auto;
        }
        
        .books-table td, .books-table th {
            white-space: nowrap;
        }
    }
    
    @media (max-width: 768px) {
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-box, .filter-group {
            width: 100%;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
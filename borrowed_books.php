<?php
require_once 'config.php';

// Initialize variables
$borrowed_books = [];
$error_message = null;
$success_message = null;

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle return book action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $borrowing_id = (int)$_POST['borrowing_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE borrowings 
                               SET return_date = NOW(), status = 'returned' 
                               WHERE borrowing_id = :borrowing_id AND user_id = :user_id");
        $stmt->bindParam(':borrowing_id', $borrowing_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $success_message = "Book successfully returned!";
            
            // Update book status in books table
            $stmt = $conn->prepare("UPDATE books SET status = 'available' 
                                   WHERE book_id = (SELECT book_id FROM borrowings WHERE borrowing_id = :borrowing_id)");
            $stmt->bindParam(':borrowing_id', $borrowing_id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $error_message = "Failed to return book. It may have already been returned.";
        }
    } catch(PDOException $e) {
        $error_message = "Error returning book: " . $e->getMessage();
    }
}

// Get current borrowed books
try {
    $stmt = $conn->prepare("SELECT 
                               b.borrowing_id,
                               bk.book_id,
                               bk.title,
                               bk.author,
                               bk.isbn,
                               b.borrow_date,
                               b.due_date,
                               b.return_date,
                               b.status
                           FROM borrowings b
                           JOIN books bk ON b.book_id = bk.book_id
                           WHERE b.user_id = :user_id
                           ORDER BY 
                               CASE 
                                   WHEN b.status = 'overdue' THEN 1
                                   WHEN b.status = 'active' THEN 2
                                   ELSE 3
                               END,
                               b.due_date ASC");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $borrowed_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for overdue books
    $current_date = new DateTime();
    foreach ($borrowed_books as &$book) {
        if ($book['status'] === 'active') {
            $due_date = new DateTime($book['due_date']);
            if ($current_date > $due_date) {
                // Update status to overdue
                $update_stmt = $conn->prepare("UPDATE borrowings 
                                             SET status = 'overdue' 
                                             WHERE borrowing_id = :borrowing_id");
                $update_stmt->bindParam(':borrowing_id', $book['borrowing_id'], PDO::PARAM_INT);
                $update_stmt->execute();
                $book['status'] = 'overdue';
            }
        }
    }
    
} catch(PDOException $e) {
    $error_message = "Error fetching borrowed books: " . $e->getMessage();
}

// Page title
$page_title = "My Borrowed Books";
include 'includes/header.php';
?>

<div class="borrowed-books-container">
    <div class="page-header">
        <h1>My Borrowed Books</h1>
        <p>View and manage the books you've borrowed from the library</p>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($borrowed_books)): ?>
        <div class="no-books">
            <div class="no-books-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <h3>No books currently borrowed</h3>
            <p>You haven't borrowed any books yet. Visit our <a href="books.php">library catalog</a> to find books to borrow.</p>
        </div>
    <?php else: ?>
        <div class="borrowed-books-list">
            <?php foreach ($borrowed_books as $book): ?>
                <div class="book-card status-<?php echo htmlspecialchars($book['status']); ?>">
                    <div class="book-info">
                        <h3 class="book-title">
                            <a href="view_book.php?id=<?php echo htmlspecialchars($book['book_id']); ?>">
                                <?php echo htmlspecialchars($book['title']); ?>
                            </a>
                        </h3>
                        <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                        <p class="book-isbn">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></p>
                        
                        <div class="borrow-details">
                            <div class="detail-row">
                                <span class="detail-label">Borrowed:</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($book['borrow_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Due Date:</span>
                                <span class="detail-value <?php echo $book['status'] === 'overdue' ? 'text-danger' : ''; ?>">
                                    <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                                   <?php if ($book['status'] === 'overdue'): ?>
                                        <span class="badge badge-danger">Overdue</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($book['return_date'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Returned:</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($book['return_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="book-actions">
                        <?php if ($book['status'] === 'active' || $book['status'] === 'overdue'): ?>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to return this book?');">
                                <input type="hidden" name="borrowing_id" value="<?php echo htmlspecialchars($book['borrowing_id']); ?>">
                                <button type="submit" name="return_book" class="btn btn-primary">
                                    <i class="fas fa-undo"></i> Return Book
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge-success">Returned</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .borrowed-books-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .page-header {
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .page-header h1 {
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }
    
    .no-books {
        text-align: center;
        padding: 3rem;
        background-color: #f8f9fa;
        border-radius: 8px;
    }
    
    .no-books-icon {
        font-size: 3rem;
        color: #7f8c8d;
        margin-bottom: 1rem;
    }
    
    .no-books h3 {
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }
    
    .borrowed-books-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }
    
    .book-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        border-left: 4px solid #3498db;
    }
    
    .book-card.status-overdue {
        border-left-color: #e74c3c;
        background-color: #fff5f5;
    }
    
    .book-card.status-returned {
        border-left-color: #2ecc71;
        opacity: 0.8;
    }
    
    .book-info {
        flex-grow: 1;
    }
    
    .book-title {
        margin-top: 0;
        margin-bottom: 0.5rem;
    }
    
    .book-title a {
        color: #2c3e50;
        text-decoration: none;
    }
    
    .book-author, .book-isbn {
        color: #7f8c8d;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    
    .borrow-details {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #ecf0f1;
    }
    
    .detail-row {
        display: flex;
        margin-bottom: 0.5rem;
    }
    
    .detail-label {
        font-weight: 600;
        width: 100px;
        color: #34495e;
    }
    
    .detail-value {
        flex-grow: 1;
    }
    
    .text-danger {
        color: #e74c3c;
    }
    
    .badge {
        display: inline-block;
        padding: 0.25em 0.4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }
    
    .badge-danger {
        color: #fff;
        background-color: #e74c3c;
    }
    
    .badge-success {
        color: #fff;
        background-color: #2ecc71;
    }
    
    .book-actions {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #ecf0f1;
        text-align: right;
    }
    
    @media (max-width: 768px) {
        .borrowed-books-list {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
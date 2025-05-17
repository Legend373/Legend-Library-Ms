<?php
require_once 'config.php';
requireLogin();

// Only students can access this page
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
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

// Get available books
try {
    $stmt = $conn->prepare("
        SELECT book_id, title, author, isbn, category
        FROM books
        WHERE status = 'available'
        ORDER BY title
    ");
    $stmt->execute();
    $available_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errors[] = "Error fetching available books: " . $e->getMessage();
}

// Get user's currently borrowed books
try {
    $stmt = $conn->prepare("
        SELECT b.borrowing_id, bk.title, bk.author, b.borrow_date, b.due_date, b.status
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.book_id
        WHERE b.user_id = ? AND b.status IN ('active', 'overdue')
        ORDER BY b.due_date ASC
    ");
    $stmt->execute([$user_id]);
    $borrowed_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errors[] = "Error fetching borrowed books: " . $e->getMessage();
}

// Page title for header
$page_title = "Borrow Books";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Books - Legend Library System</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/css/style.css">
    <style>
        .borrow-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        @media (min-width: 768px) {
            .borrow-container {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .book-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .book-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .book-card h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .book-author {
            color: #666;
            font-style: italic;
            margin-bottom: 1rem;
        }
        
        .book-details {
            margin-bottom: 1rem;
        }
        
        .book-details span {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        .book-actions form {
            margin: 0;
        }
        
        .borrowed-books {
            margin-bottom: 1.5rem;
        }
        
        .borrowed-books .book-card {
            margin-bottom: 1rem;
        }
        
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
        
        .book-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .book-status.active {
            background-color: #e6f7ff;
            color: #0070f3;
        }
        
        .book-status.overdue {
            background-color: #fff2f0;
            color: #dc3545;
        }
        
        .book-status.returned {
            background-color: #f6ffed;
            color: #52c41a;
        }
        
        .borrowing-rules {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .borrowing-rules h4 {
            margin-top: 0;
            color: #333;
        }
        
        .borrowing-rules ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }
        
        .borrowing-rules li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <section>
                <h2>Borrow Books</h2>
                <p>Browse and borrow physical books from our library collection.</p>
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-container">
                        <p><?php echo $success_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-container">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="borrow-container">
                    <div class="available-books">
                        <h3>Available Books</h3>
                        
                        <?php if (empty($available_books)): ?>
                            <p>No books are currently available for borrowing.</p>
                        <?php else: ?>
                            <div class="book-list">
                                <?php foreach ($available_books as $book): ?>
                                    <div class="book-card">
                                        <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                        <p class="book-author">By: <?php echo htmlspecialchars($book['author']); ?></p>
                                        <div class="book-details">
                                            <span><strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?></span>
                                            <span><strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?></span>
                                        </div>
                                        <div class="book-actions">
                                            <form action="<?php echo $base_url; ?>/borrow.php" method="post">
                                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                <button type="submit" name="borrow" class="btn primary">Borrow This Book</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sidebar">
                        <div class="borrowing-rules">
                            <h4>Borrowing Rules</h4>
                            <ul>
                                <li>You can borrow up to 5 books at a time.</li>
                                <li>The standard loan period is 14 days.</li>
                                <li>Return books on time to avoid penalties.</li>
                                <li>Handle books with care and return them in good condition.</li>
                                <li>Lost or damaged books must be replaced or paid for.</li>
                            </ul>
                        </div>
                        
                        <h3>Your Borrowed Books</h3>
                        <?php if (empty($borrowed_books)): ?>
                            <p>You don't have any borrowed books.</p>
                        <?php else: ?>
                            <div class="borrowed-books">
                                <?php foreach ($borrowed_books as $book): ?>
                                    <div class="book-card">
                                        <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                        <p class="book-author">By: <?php echo htmlspecialchars($book['author']); ?></p>
                                        <div class="book-details">
                                            <span><strong>Borrowed:</strong> <?php echo formatDate($book['borrow_date']); ?></span>
                                            <span class="<?php echo (strtotime($book['due_date']) < time()) ? 'overdue' : ''; ?>">
                                                <strong>Due:</strong> <?php echo formatDate($book['due_date']); ?>
                                                <?php if (strtotime($book['due_date']) < time()): ?>
                                                    (OVERDUE)
                                                <?php endif; ?>
                                            </span>
                                            <span><strong>Status:</strong> <span class="book-status <?php echo $book['status']; ?>"><?php echo ucfirst($book['status']); ?></span></span>
                                        </div>
                                        <div class="book-actions">
                                            <a href="<?php echo $base_url; ?>/return_book.php?id=<?php echo $book['borrowing_id']; ?>" class="btn secondary">Return Book</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
</body>
</html>
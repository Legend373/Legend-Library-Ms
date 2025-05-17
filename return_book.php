<?php
require_once 'config.php';
requireLogin();

$errors = [];
$success_message = "";
$borrowing = null;

// Get borrowing ID from URL
$borrowing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($borrowing_id <= 0) {
    header("Location: $base_url/dashboard.php");
    exit();
}

// Check if the borrowing exists and belongs to the current user
try {
    $stmt = $conn->prepare("
        SELECT b.borrowing_id, b.book_id, b.borrow_date, b.due_date, b.status,
               bk.title, bk.author
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.book_id
        WHERE b.borrowing_id = ? AND b.user_id = ? AND b.status IN ('active', 'overdue')
    ");
    $stmt->execute([$borrowing_id, $_SESSION['user_id']]);
    $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$borrowing) {
        // If admin, check if the borrowing exists regardless of user
        if (isAdmin()) {
            $stmt = $conn->prepare("
                SELECT b.borrowing_id, b.book_id, b.user_id, b.borrow_date, b.due_date, b.status,
                       bk.title, bk.author, u.username
                FROM borrowings b
                JOIN books bk ON b.book_id = bk.book_id
                JOIN users u ON b.user_id = u.user_id
                WHERE b.borrowing_id = ? AND b.status IN ('active', 'overdue')
            ");
            $stmt->execute([$borrowing_id]);
            $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$borrowing) {
                header("Location: $base_url/dashboard.php");
                exit();
            }
        } else {
            header("Location: $base_url/dashboard.php");
            exit();
        }
    }
} catch(PDOException $e) {
    $errors[] = "Error fetching borrowing details: " . $e->getMessage();
}

// Handle return confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_return'])) {
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Update borrowing status
        $stmt = $conn->prepare("
            UPDATE borrowings
            SET status = 'returned', return_date = NOW()
            WHERE borrowing_id = ?
        ");
        $stmt->execute([$borrowing_id]);
        
        // Update book status
        $stmt = $conn->prepare("
            UPDATE books
            SET status = 'available'
            WHERE book_id = ?
        ");
        $stmt->execute([$borrowing['book_id']]);
        
        // Log activity
        $user_id = isAdmin() && isset($borrowing['user_id']) ? $borrowing['user_id'] : $_SESSION['user_id'];
        $book_title = $borrowing['title'];
        logActivity($user_id, 'return_book', "Returned book: $book_title");
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Book returned successfully!";
    } catch(PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $errors[] = "Error returning book: " . $e->getMessage();
    }
}

// Page title for header
$page_title = "Return Book";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book - Legend Library System</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/css/style.css">
    <style>
        .return-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .book-details {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .book-title {
            font-size: 1.5rem;
            margin-top: 0;
            margin-bottom: 0.5rem;
        }
        
        .book-author {
            color: #666;
            font-style: italic;
            margin-bottom: 1.5rem;
        }
        
        .borrowing-info {
            margin-bottom: 1.5rem;
        }
        
        .borrowing-info p {
            margin: 0.5rem 0;
            display: flex;
            justify-content: space-between;
        }
        
        .borrowing-info span {
            font-weight: 500;
        }
        
        .overdue {
            color: #dc3545;
        }
        
        .return-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .success-message {
            text-align: center;
            padding: 2rem;
        }
        
        .success-message h3 {
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .success-message p {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <main>
            <div class="return-container">
                <h2>Return Book</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-container">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <h3>Book Returned Successfully!</h3>
                        <p>Thank you for returning the book on time.</p>
                        <div class="return-actions">
                            <a href="<?php echo $base_url; ?>/dashboard.php" class="btn primary">Go to Dashboard</a>
                            <a href="<?php echo $base_url; ?>/borrow.php" class="btn secondary">Borrow Another Book</a>
                        </div>
                    </div>
                <?php elseif ($borrowing): ?>
                    <div class="book-details">
                        <h3 class="book-title"><?php echo htmlspecialchars($borrowing['title']); ?></h3>
                        <p class="book-author">By: <?php echo htmlspecialchars($borrowing['author']); ?></p>
                        
                        <div class="borrowing-info">
                            <?php if (isAdmin() && isset($borrowing['username'])): ?>
                                <p>
                                    Borrowed By: <span><?php echo htmlspecialchars($borrowing['username']); ?></span>
                                </p>
                            <?php endif; ?>
                            
                            <p>
                                Borrow Date: <span><?php echo formatDate($borrowing['borrow_date']); ?></span>
                            </p>
                            
                            <p>
                                Due Date: 
                                <span class="<?php echo (strtotime($borrowing['due_date']) < time()) ? 'overdue' : ''; ?>">
                                    <?php echo formatDate($borrowing['due_date']); ?>
                                    <?php if (strtotime($borrowing['due_date']) < time()): ?>
                                        (OVERDUE)
                                    <?php endif; ?>
                                </span>
                            </p>
                            
                            <p>
                                Status: <span><?php echo ucfirst($borrowing['status']); ?></span>
                            </p>
                        </div>
                        
                        <form action="<?php echo $base_url; ?>/return_book.php?id=<?php echo $borrowing_id; ?>" method="post" onsubmit="return confirm('Are you sure you want to return this book?');">
                            <input type="hidden" name="confirm_return" value="1">
                            
                            <div class="return-actions">
                                <button type="submit" class="btn primary">Confirm Return</button>
                                <a href="<?php echo $base_url; ?>/dashboard.php" class="btn secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
</body>
</html>
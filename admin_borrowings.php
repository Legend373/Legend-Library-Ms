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
$borrowings = [];
$total_borrowings = 0;
$total_pages = 1;
$error = null;

// Handle borrowing actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        if (isset($_POST['mark_returned'])) {
            $borrowing_id = (int)$_POST['borrowing_id'];
            
            $stmt = $conn->prepare("UPDATE borrowings SET return_date = NOW(), status = 'returned' WHERE borrowing_id = :borrowing_id");
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->execute();
            
            // Update book availability
            $stmt = $conn->prepare("UPDATE books b 
                                   JOIN borrowings br ON b.book_id = br.book_id
                                   SET b.available_copies = b.available_copies + 1 
                                   WHERE br.borrowing_id = :borrowing_id");
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->execute();
            
            // Log action
            $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, message)
                                  VALUES (:admin_id, 'info', 'Marked borrowing ID: ".$borrowing_id." as returned')");
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $_SESSION['flash_message'] = "Book marked as returned successfully!";
            $_SESSION['flash_type'] = "success";
        }
        elseif (isset($_POST['extend_due_date'])) {
            $borrowing_id = (int)$_POST['borrowing_id'];
            $extension_days = (int)$_POST['extension_days'];
            
            $stmt = $conn->prepare("UPDATE borrowings 
                                   SET due_date = DATE_ADD(due_date, INTERVAL :days DAY),
                                       status = CASE 
                                           WHEN DATE_ADD(due_date, INTERVAL :days DAY) < NOW() THEN 'overdue'
                                           ELSE 'active'
                                       END
                                   WHERE borrowing_id = :borrowing_id");
            $stmt->bindParam(':days', $extension_days);
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->execute();
            
            // Log action
            $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, message)
                                  VALUES (:admin_id, 'info', 'Extended due date for borrowing ID: ".$borrowing_id." by ".$extension_days." days')");
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $_SESSION['flash_message'] = "Due date extended by ".$extension_days." days!";
            $_SESSION['flash_type'] = "success";
        }
        
        $conn->commit();
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['flash_message'] = "Error: ".$e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: admin_borrowings.php");
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

// Build query
$query = "SELECT b.borrowing_id, b.borrow_date, b.due_date, b.return_date, b.status,
                 bk.title AS book_title, bk.isbn, 
                 u.username AS user_name, u.email AS user_email
          FROM borrowings b
          JOIN books bk ON b.book_id = bk.book_id
          JOIN users u ON b.user_id = u.user_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (bk.title LIKE :search OR bk.isbn LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter) && in_array($status_filter, ['active', 'returned', 'overdue'])) {
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
    $total_borrowings = $stmt->fetchColumn();
    
    $total_pages = ceil($total_borrowings / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get paginated results
    $query .= " ORDER BY b.borrow_date DESC LIMIT :offset, :per_page";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update overdue statuses
    $conn->exec("UPDATE borrowings SET status = 'overdue' WHERE status = 'active' AND due_date < NOW()");
} catch(PDOException $e) {
    $error = "Database error: ".$e->getMessage();
}

$page_title = "Admin - Borrowing Management";
include 'includes/header.php';
?>

<div class="admin-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <!-- Header Section -->
    <div class="admin-header" style="margin-bottom: 30px; border-bottom: 2px solid #8B4513; padding-bottom: 15px;">
        <h1 style="color: #8B4513; margin: 0;">Borrowing Management</h1>
        <p style="color: #666;">Manage book loans and returns</p>
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
                <input type="text" name="search" placeholder="Search by book, user or ISBN..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       style="width: 100%; padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
            </div>
            
            <div class="filter-group">
                <select name="status" style="padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                </select>
            </div>
            
            <button type="submit" class="btn-search" 
                    style="padding: 8px 15px; background-color: #8B4513; color: white; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="admin_borrowings.php" class="btn-reset"
               style="padding: 8px 15px; background-color: #D2B48C; color: #333; text-decoration: none; border-radius: 4px;">
                <i class="fas fa-sync-alt"></i> Reset
            </a>
        </form>
    </div>

    <!-- Borrowing Count and New Loan Button -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div class="borrowing-count" style="color: #666;">
            Showing <?php echo count($borrowings); ?> of <?php echo $total_borrowings; ?> borrowings
        </div>
        <a href="admin_new_loan.php" class="btn-new-loan"
           style="padding: 8px 15px; background-color: #8B4513; color: white; text-decoration: none; border-radius: 4px;">
            <i class="fas fa-book-medical"></i> Create New Loan
        </a>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
        <div class="alert alert-danger" style="padding: 15px; margin-bottom: 20px; background-color: #f2dede; color: #a94442; border: 1px solid transparent; border-radius: 4px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Borrowings Table -->
    <div class="borrowings-table" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #FFF8DC;">
                    <th style="padding: 12px 15px; text-align: left;">Book</th>
                    <th style="padding: 12px 15px; text-align: left;">Borrower</th>
                    <th style="padding: 12px 15px; text-align: left;">Borrow Date</th>
                    <th style="padding: 12px 15px; text-align: left;">Due Date</th>
                    <th style="padding: 12px 15px; text-align: left;">Return Date</th>
                    <th style="padding: 12px 15px; text-align: left;">Status</th>
                    <th style="padding: 12px 15px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($borrowings)): ?>
                    <tr>
                        <td colspan="7" style="padding: 20px; text-align: center; color: #666;">
                            <?php echo $error ? 'Error loading borrowings' : 'No borrowings found matching your criteria'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($borrowings as $borrowing): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px 15px;">
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($borrowing['book_title']); ?></div>
                                <div style="font-size: 0.9em; color: #666;">ISBN: <?php echo htmlspecialchars($borrowing['isbn']); ?></div>
                            </td>
                            <td style="padding: 12px 15px;">
                                <div><?php echo htmlspecialchars($borrowing['user_name']); ?></div>
                                <div style="font-size: 0.9em; color: #666;"><?php echo htmlspecialchars($borrowing['user_email']); ?></div>
                            </td>
                            <td style="padding: 12px 15px;"><?php echo date('M j, Y', strtotime($borrowing['borrow_date'])); ?></td>
                            <td style="padding: 12px 15px; 
                                <?php echo $borrowing['status'] === 'overdue' ? 'color: #dc3545; font-weight: bold;' : ''; ?>">
                                <?php echo date('M j, Y', strtotime($borrowing['due_date'])); ?>
                                <?php if ($borrowing['status'] === 'overdue'): ?>
                                    <div style="font-size: 0.8em; color: #dc3545;">
                                        <?php 
                                            $days_overdue = floor((time() - strtotime($borrowing['due_date'])) / (60 * 60 * 24));
                                            echo $days_overdue.' day'.($days_overdue != 1 ? 's' : '').' overdue';
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 15px;">
                                <?php echo $borrowing['return_date'] ? date('M j, Y', strtotime($borrowing['return_date'])) : 'Not returned'; ?>
                            </td>
                            <td style="padding: 12px 15px;">
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 4px;
                                      <?php 
                                        if ($borrowing['status'] === 'active') echo 'background-color: #d4edda; color: #155724;';
                                        elseif ($borrowing['status'] === 'overdue') echo 'background-color: #f8d7da; color: #721c24;';
                                        else echo 'background-color: #e2e3e5; color: #383d41;';
                                      ?>">
                                    <?php echo ucfirst($borrowing['status']); ?>
                                </span>
                            </td>
                            <td style=" display:flex;padding: 12px 15px; text-align: center;">
                                <?php if ($borrowing['status'] !== 'returned'): ?>
                                    <form method="POST" action="" style="display: inline-block; margin-right: 5px;">
                                        <input type="hidden" name="borrowing_id" value="<?php echo $borrowing['borrowing_id']; ?>">
                                        <button type="submit" name="mark_returned" class="btn-action"
                                                style="padding: 5px 5px; background-color: #28a745; color: white; 
                                                       border: none; border-radius: 4px; cursor: pointer;">
                                            <i class="fas fa-check"></i> Return
                                        </button>
                                    </form>
                                    
                                    <button type="button" onclick="showExtensionForm(<?php echo $borrowing['borrowing_id']; ?>)" 
                                            class="btn-action"
                                            style="padding: 5px 5px; background-color: #17a2b8; color: white; 
                                                   border: none; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-calendar-plus"></i> Extend
                                    </button>
                                    
                                    <div id="extension-form-<?php echo $borrowing['borrowing_id']; ?>" 
                                         style="display: none; margin-top: 5px; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                                        <form method="POST" action="" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="borrowing_id" value="<?php echo $borrowing['borrowing_id']; ?>">
                                            <select name="extension_days" style="padding: 5px; border: 1px solid #D2B48C; border-radius: 4px;">
                                                <option value="7">7 days</option>
                                                <option value="14">14 days</option>
                                                <option value="21">21 days</option>
                                                <option value="30">30 days</option>
                                            </select>
                                            <button type="submit" name="extend_due_date" 
                                                    style="padding: 5px 10px; background-color: #17a2b8; color: white; 
                                                           border: none; border-radius: 4px; cursor: pointer;">
                                                Apply
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                
                                
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
                        <a href="admin_borrowings.php?<?php echo http_build_query(array_merge(
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
                        <a href="admin_borrowings.php?<?php echo http_build_query(array_merge(
                            $_GET,
                            ['page' => $i]
                        )); ?>"
                           style="display: block; padding: 8px 12px; text-decoration: none; border-radius: 4px;"<?php echo $i === $page ?'background-color: #8B4513; color: white;' : 'background-color: #FFF8DC; color: #8B4513;' ?>>
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li>
                        <a href="admin_borrowings.php?<?php echo http_build_query(array_merge(
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

<script>
    function showExtensionForm(borrowingId) {
        // Hide all extension forms first
        document.querySelectorAll('[id^="extension-form-"]').forEach(form => {
            form.style.display = 'none';
        });
        
        // Show the selected one
        const form = document.getElementById('extension-form-' + borrowingId);
        if (form) {
            form.style.display = 'block';
        }
    }
</script>

<style>
    /* Hover effects */
    .btn-search:hover, .btn-new-loan:hover {
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
        .borrowings-table table {
            display: block;
            overflow-x: auto;
        }
        
        .borrowings-table td, .borrowings-table th {
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
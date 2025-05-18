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
$users = [];
$total_users = 0;
$total_pages = 1;
$error = null;

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        if (isset($_POST['update_role'])) {
            $user_id = (int)$_POST['user_id'];
            $new_role = in_array($_POST['role'], ['admin', 'librarian', 'member']) ? $_POST['role'] : 'member';
            
            $stmt = $conn->prepare("UPDATE users SET role = :role WHERE user_id = :user_id");
            $stmt->bindParam(':role', $new_role);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Log action
            $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, message)
                                  VALUES (:admin_id, 'info', 'Updated role for user ID: ".$user_id." to ".$new_role."')");
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $_SESSION['flash_message'] = "User role updated successfully!";
            $_SESSION['flash_type'] = "success";
        }
        elseif (isset($_POST['toggle_status'])) {
            $user_id = (int)$_POST['user_id'];
            
            // // Get current status
            // $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = :user_id");
            // $stmt->bindParam(':user_id', $user_id);
            // $stmt->execute();
            // $current_status = $stmt->fetchColumn();
            
            // $new_status = $current_status === 'active' ? 'suspended' : 'active';
            
            // $stmt = $conn->prepare("UPDATE users SET status = :status WHERE user_id = :user_id");
            // $stmt->bindParam(':status', $new_status);
            // $stmt->bindParam(':user_id', $user_id);
            // $stmt->execute();
            
            // Log action
            // $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, message)
            //                       VALUES (:admin_id, 'warning', 'Changed status for user ID: ".$user_id." to ".$new_status."')");
            // $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            // $stmt->execute();
            
            // $_SESSION['flash_message'] = "User status updated to ".$new_status."!";
            // $_SESSION['flash_type'] = "success";
        }
        
        $conn->commit();
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['flash_message'] = "Error: ".$e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: admin_users.php");
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
// $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

// Build query
$query = "SELECT user_id, username, email, role, created_at FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($role_filter) && in_array($role_filter, ['admin', 'librarian', 'member'])) {
    $query .= " AND role = :role";
    $params[':role'] = $role_filter;
}

// if (!empty($status_filter) && in_array($status_filter, ['active', 'suspended'])) {
//     $query .= " AND status = :status";
//     $params[':status'] = $status_filter;
// }

// Get total count
try {
    $count_query = "SELECT COUNT(*) FROM users WHERE 1=1".strstr($query, "WHERE 1=1 AND");
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_users = $stmt->fetchColumn();
    
    $total_pages = ceil($total_users / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get paginated results
    $query .= " ORDER BY created_at DESC LIMIT :offset, :per_page";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Database error: ".$e->getMessage();
}

$page_title = "Admin - User Management";
include 'includes/header.php';
?>

<div class="admin-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Header Section -->
    <div class="admin-header" style="margin-bottom: 30px; border-bottom: 2px solid #8B4513; padding-bottom: 15px;">
        <h1 style="color: #8B4513; margin: 0;">User Management</h1>
        <p style="color: #666;">Manage system users and permissions</p>
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
                <input type="text" name="search" placeholder="Search users..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       style="width: 100%; padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
            </div>
            
            <div class="filter-group">
                <select name="role" style="padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="librarian" <?php echo $role_filter === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                    <option value="member" <?php echo $role_filter === 'member' ? 'selected' : ''; ?>>Member</option>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="status" style="padding: 8px; border: 1px solid #D2B48C; border-radius: 4px;">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            
            <button type="submit" class="btn-search" 
                    style="padding: 8px 15px; background-color: #8B4513; color: white; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="admin_users.php" class="btn-reset"
               style="padding: 8px 15px; background-color: #D2B48C; color: #333; text-decoration: none; border-radius: 4px;">
                <i class="fas fa-sync-alt"></i> Reset
            </a>
        </form>
    </div>

    <!-- User Count and Add User Button -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div class="user-count" style="color: #666;">
            Showing <?php echo count($users); ?> of <?php echo $total_users; ?> users
        </div>
        <a href="admin_add_user.php" class="btn-add-user"
           style="padding: 8px 15px; background-color: #8B4513; color: white; text-decoration: none; border-radius: 4px;">
            <i class="fas fa-user-plus"></i> Add New User
        </a>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
        <div class="alert alert-danger" style="padding: 15px; margin-bottom: 20px; background-color: #f2dede; color: #a94442; border: 1px solid transparent; border-radius: 4px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="users-table" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #FFF8DC;">
                    <th style="padding: 12px 15px; text-align: left;">Username</th>
                    <th style="padding: 12px 15px; text-align: left;">Email</th>
                    <th style="padding: 12px 15px; text-align: left;">Role</th>
                    <!-- <th style="padding: 12px 15px; text-align: left;">Status</th> -->
                    <th style="padding: 12px 15px; text-align: left;">Joined</th>
                    <th style="padding: 12px 15px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="padding: 20px; text-align: center; color: #666;">
                            <?php echo $error ? 'Error loading users' : 'No users found matching your criteria'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px 15px;">
                                <a href="admin_user_details.php?id=<?php echo $user['user_id']; ?>" 
                                   style="color: #8B4513; text-decoration: none; font-weight: 500;">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </a>
                            </td>
                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td style="padding: 12px 15px;">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <select name="role" onchange="this.form.submit()"
                                            style="padding: 5px; border: 1px solid #D2B48C; border-radius: 4px;
                                                   background-color: <?php echo $user['role'] === 'admin' ? '#f0e6cc' : ($user['role'] === 'librarian' ? '#e8e8e8' : '#fff'); ?>">
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="librarian" <?php echo $user['role'] === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                                        <option value="member" <?php echo $user['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                                    </select>
                                    <noscript><button type="submit" name="update_role">Update</button></noscript>
                                </form>
                            </td>
                            <!-- <td style="padding: 12px 15px;">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" name="toggle_status" 
                                            style="padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;
                                                   background-color: <?php echo $user['status'] === 'active' ? '#d4edda' : '#f8d7da'; ?>;
                                                   color: <?php echo $user['status'] === 'active' ? '#155724' : '#721c24'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </button>
                                </form>
                            </td> -->
                            <td style="padding: 12px 15px;"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <a href="admin_user_details.php?id=<?php echo $user['user_id']; ?>" 
                                   class="btn-action"
                                   style="padding: 5px 10px; background-color: #D2B48C; color: #333; 
                                          text-decoration: none; border-radius: 4px; margin-right: 5px;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="admin_edit_user.php?id=<?php echo $user['user_id']; ?>" 
                                   class="btn-action"
                                   style="padding: 5px 10px; background-color: #8B4513; color: white; 
                                          text-decoration: none; border-radius: 4px;">
                                    <i class="fas fa-edit"></i>
                                </a>
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
                        <a href="admin_users.php?<?php echo http_build_query(array_merge(
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
                        <a href="admin_users.php?<?php echo http_build_query(array_merge(
                            $_GET,
                            ['page' => $i]
                        )); ?>"
                           style="display: block; padding: 8px 12px;text-decoration: none; border-radius:4px <?php echo $i === $page ?'background-color: #8B4513; color: white;' : 'background-color: #FFF8DC; color: #8B4513;' ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li>
                        <a href="admin_users.php?<?php echo http_build_query(array_merge(
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
    .btn-search:hover, .btn-add-user:hover {
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
    @media (max-width: 768px) {
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-box, .filter-group {
            width: 100%;
        }
        
        table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
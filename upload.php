<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $base_url . "/upload.php";
    $_SESSION['flash_message'] = "Please log in to upload materials.";
    $_SESSION['flash_type'] = "warning";
    header("Location: " . $base_url . "/login.php");
    exit;
}

// Get categories for dropdown
try {
    $stmt = $conn->prepare("SELECT DISTINCT category FROM materials ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
}

// Process upload form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $custom_category = isset($_POST['custom_category']) ? trim($_POST['custom_category']) : '';
    
    // Use custom category if selected
    if ($category === 'other' && !empty($custom_category)) {
        $category = $custom_category;
    }
    
    // Validate input
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    
    // Validate file upload
    if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Material file is required";
    } else {
        $file = $_FILES['material_file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed file extensions
        $allowed_exts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif'];
        
        // Check for errors
        if ($file_error !== UPLOAD_ERR_OK) {
            switch ($file_error) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "File size exceeds the maximum limit";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = "File was only partially uploaded";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errors[] = "Missing a temporary folder";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = "Failed to write file to disk";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errors[] = "A PHP extension stopped the file upload";
                    break;
                default:
                    $errors[] = "Unknown upload error";
            }
        } elseif (!in_array($file_ext, $allowed_exts)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowed_exts);
        } elseif ($file_size > 50 * 1024 * 1024) { // 50MB limit
            $errors[] = "File size exceeds the maximum limit of 50MB";
        }
    }
    
    // If no validation errors, upload file and save material
    if (empty($errors)) {
        try {
            // Create upload directory if it doesn't exist
            if (!file_exists(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            
            // Generate unique filename
            $new_file_name = uniqid('material_') . '.' . $file_ext;
            $upload_path = UPLOAD_DIR . '/' . $new_file_name;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Save material to database
                $stmt = $conn->prepare("
                    INSERT INTO materials (title, description, category, file_path, uploaded_by, upload_date)
                    VALUES (:title, :description, :category, :file_path, :uploaded_by, NOW())
                ");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':file_path', $new_file_name);
                $stmt->bindParam(':uploaded_by', $_SESSION['user_id']);
                $stmt->execute();
                
                $material_id = $conn->lastInsertId();
                
                // Log activity
                logActivity($_SESSION['user_id'], 'upload_material', $title);
                
                // Redirect to view material page
                $_SESSION['flash_message'] = "Material uploaded successfully!";
                $_SESSION['flash_type'] = "success";
                header("Location: " . $base_url . "/view_material.php?id=" . $material_id);
                exit;
            } else {
                $errors[] = "Failed to move uploaded file";
            }
        } catch(PDOException $e) {
            $errors[] = "Upload failed: " . $e->getMessage();
        }
    }
}

// Page title for header
$page_title = "Upload Material";
?>
<?php include 'includes/header.php'; ?>

<div class="upload-container">
    <div class="upload-header">
        <h1>Upload Educational Material</h1>
        <p>Share your knowledge with the community</p>
    </div>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="upload-card">
        <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
            <div class="form-group">
                <label for="title" class="form-label">Title</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                <small class="form-text">Choose a descriptive title for your material</small>
            </div>
            
            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="5" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                <small class="form-text">Provide a detailed description of your material</small>
            </div>
            
            <div class="form-group">
                <label for="category" class="form-label">Category</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($category) && $category === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other" <?php echo (isset($category) && !in_array($category, $categories)) ? 'selected' : ''; ?>>Other (specify)</option>
                </select>
            </div>
            
            <div class="form-group custom-category-group" style="display: none;">
                <label for="custom_category" class="form-label">Custom Category</label>
                <input type="text" id="custom_category" name="custom_category" class="form-control" value="<?php echo isset($custom_category) ? htmlspecialchars($custom_category) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="material_file" class="form-label">Material File</label>
                <div class="file-upload-container">
                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="material_file" name="material_file" class="file-input" required>
                        <div class="file-upload-content">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Drag & drop your file here or click to browse</p>
                            <span class="file-types">Allowed file types: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, RAR, JPG, JPEG, PNG, GIF</span>
                            <span class="file-size">Maximum file size: 50MB</span>
                        </div>
                    </div>
                    <div class="selected-file" id="selectedFile" style="display: none;">
                        <div class="selected-file-info">
                            <i class="fas fa-file"></i>
                            <span class="selected-file-name"></span>
                            <span class="selected-file-size"></span>
                        </div>
                        <button type="button" class="remove-file" id="removeFile">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <small class="form-text">Upload your educational material file</small>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" id="terms" name="terms" class="form-check-input" required>
                <label for="terms" class="form-check-label">I confirm that I have the right to share this material and it does not violate any copyright laws</label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn primary">Upload Material</button>
                <a href="<?php echo $base_url; ?>/dashboard.php" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
    
    <div class="upload-tips">
        <h3>Tips for Uploading</h3>
        <ul>
            <li>Choose a clear and descriptive title that accurately represents your material</li>
            <li>Provide a detailed description to help others understand what your material contains</li>
            <li>Select the most appropriate category for your material</li>
            <li>Make sure your file is in one of the supported formats</li>
            <li>Ensure you have the right to share the material and it doesn't violate any copyright laws</li>
        </ul>
    </div>
</div>

<style>
    /* Upload page specific styles with brown theme */
    .upload-container {
        padding: 2rem 0;
    }
    
    .upload-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .upload-header h1 {
        color: #8B4513;
        margin-bottom: 0.5rem;
    }
    
    .upload-header p {
        color: #666;
        font-size: 1.1rem;
    }
    
    .upload-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .upload-form .form-group {
        margin-bottom: 1.5rem;
    }
    
    .upload-form .form-label {
        display: block;
        margin-bottom: 0.5rem;
        color: #333;
        font-weight: 500;
    }
    
    .upload-form .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #D2B48C;
        border-radius: 5px;
        transition: all 0.3s ease;
    }
    
    .upload-form .form-control:focus {
        border-color: #8B4513;
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2);
        outline: none;
    }
    
    .upload-form .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.8rem;
        color: #666;
    }
    
    .file-upload-container {
        margin-bottom: 1rem;
    }
    
    .file-upload-area {
        position: relative;
        border: 2px dashed #D2B48C;
        border-radius: 5px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .file-upload-area:hover {
        border-color: #8B4513;
        background-color: #FFF8DC;
    }
    
    .file-upload-area.dragover {
        border-color: #8B4513;
        background-color: #FFF8DC;
    }
    
    .file-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    
    .file-upload-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    
    .file-upload-content i {
        font-size: 3rem;
        color: #8B4513;
        margin-bottom: 1rem;
    }
    
    .file-upload-content p {
        font-size: 1.1rem;
        color: #333;
        margin-bottom: 0.5rem;
    }
    
    .file-types, .file-size {
        display: block;
        font-size: 0.8rem;
        color: #666;
    }
    
    .selected-file {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #FFF8DC;
        padding: 1rem;
        border-radius: 5px;
        margin-top: 1rem;
    }
    
    .selected-file-info {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }
    
    .selected-file-info i {
        font-size: 1.5rem;
        color: #8B4513;
    }
    
    .selected-file-name {
        font-weight: 500;
        color: #333;
    }
    
    .selected-file-size {
        color: #666;
        font-size: 0.85rem;
        margin-left: 0.5rem;
    }
    
    .remove-file {
        background: none;
        border: none;
        color: #8B4513;
        cursor: pointer;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }
    
    .remove-file:hover {
        color: #A0522D;
    }
    
    .form-check {
        display: flex;
        align-items: flex-start;
    }
    
    .form-check-input {
        margin-right: 0.5rem;
        margin-top: 0.3rem;
    }
    
    .form-check-label {
        margin-bottom: 0;
        font-size: 0.9rem;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
    }
    
    .upload-tips {
        background-color: #FFF8DC;
        border-radius: 10px;
        padding: 1.5rem;
    }
    
    .upload-tips h3 {
        color: #8B4513;
        margin-bottom: 1rem;
        font-size: 1.2rem;
    }
    
    .upload-tips ul {
        padding-left: 1.5rem;
        color: #333;
    }
    
    .upload-tips ul li {
        margin-bottom: 0.5rem;
    }
    
    @media (max-width: 768px) {
        .upload-card {
            padding: 1.5rem;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .form-actions .btn {
            width: 100%;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('category');
        const customCategoryGroup = document.querySelector('.custom-category-group');
        const customCategoryInput = document.getElementById('custom_category');
        const fileInput = document.getElementById('material_file');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const selectedFile = document.getElementById('selectedFile');
        const selectedFileName = document.querySelector('.selected-file-name');
        const selectedFileSize = document.querySelector('.selected-file-size');
        const removeFileBtn = document.getElementById('removeFile');
        
        // Show/hide custom category input based on selection
        categorySelect.addEventListener('change', function() {
            if (this.value === 'other') {
                customCategoryGroup.style.display = 'block';
                customCategoryInput.setAttribute('required', 'required');
            } else {
                customCategoryGroup.style.display = 'none';
                customCategoryInput.removeAttribute('required');
            }
        });
        
        // Trigger change event to initialize state
        categorySelect.dispatchEvent(new Event('change'));
        
        // File upload handling
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const file = this.files[0];
                selectedFileName.textContent = file.name;
                selectedFileSize.textContent = formatFileSize(file.size);
                
                // Update file icon based on type
                const fileIcon = selectedFile.querySelector('i');
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                switch (fileExt) {
                    case 'pdf':
                        fileIcon.className = 'fas fa-file-pdf';
                        break;
                    case 'doc':
                    case 'docx':
                        fileIcon.className = 'fas fa-file-word';
                        break;
                    case 'xls':
                    case 'xlsx':
                        fileIcon.className = 'fas fa-file-excel';
                        break;
                    case 'ppt':
                    case 'pptx':
                        fileIcon.className = 'fas fa-file-powerpoint';
                        break;
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        fileIcon.className = 'fas fa-file-image';
                        break;
                    case 'zip':
                    case 'rar':
                        fileIcon.className = 'fas fa-file-archive';
                        break;
                    case 'txt':
                        fileIcon.className = 'fas fa-file-alt';
                        break;
                    default:
                        fileIcon.className = 'fas fa-file';
                }
                
                fileUploadArea.style.display = 'none';
                selectedFile.style.display = 'flex';
            }
        });
        
        // Remove selected file
        removeFileBtn.addEventListener('click', function() {
            fileInput.value = '';
            fileUploadArea.style.display = 'block';
            selectedFile.style.display = 'none';
        });
        
        // Drag and drop handling
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            fileUploadArea.classList.add('dragover');
        }
        
        function unhighlight() {
            fileUploadArea.classList.remove('dragover');
        }
        
        fileUploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
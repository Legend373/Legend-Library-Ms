-- Create database (uncomment if you need to create the database)
-- CREATE DATABASE library_system;
-- USE library_system;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Materials table
CREATE TABLE materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Keywords table for material search
CREATE TABLE keywords (
    keyword_id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    keyword VARCHAR(50) NOT NULL,
    FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE
);

-- Books table
CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) UNIQUE,
    category VARCHAR(100) NOT NULL,
    status ENUM('available', 'borrowed', 'reserved', 'maintenance') NOT NULL DEFAULT 'available',
    added_by INT NOT NULL,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Borrowings table
CREATE TABLE borrowings (
    borrowing_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date TIMESTAMP NOT NULL,
    return_date TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active', 'returned', 'overdue') NOT NULL DEFAULT 'active',
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Material downloads tracking
CREATE TABLE material_downloads (
    download_id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    user_id INT NOT NULL,
    download_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Password reset tokens
CREATE TABLE password_resets (
    user_id INT PRIMARY KEY,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Activity logs
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create an admin user (password: admin123)
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@example.com', '$2y$10$8WxhJkOqEzk1jXAJUQVqPOij3x.VG9a/mwZWcnqPrYAGIVEX5tUhC', 'admin');

-- Create a teacher user (password: teacher123)
INSERT INTO users (username, email, password, role) VALUES 
('teacher', 'teacher@example.com', '$2y$10$Oe5amMg3xdd0cZsKnRyKLe1Zeg0xJ/xjH6oMVx2UmZBR0eJwWKvMi', 'teacher');

-- Create a student user (password: student123)
INSERT INTO users (username, email, password, role) VALUES 
('student', 'student@example.com', '$2y$10$Oe5amMg3xdd0cZsKnRyKLe1Zeg0xJ/xjH6oMVx2UmZBR0eJwWKvMi', 'student');

-- Add some sample books
INSERT INTO books (title, author, isbn, category, status, added_by) VALUES
('Introduction to Computer Science', 'John Smith', '9781234567897', 'Computer Science', 'available', 1),
('Advanced Mathematics', 'Jane Doe', '9789876543210', 'Mathematics', 'available', 1),
('World History', 'Michael Johnson', '9785432167890', 'History', 'available', 1),
('English Literature', 'Sarah Williams', '9784567890123', 'Literature', 'available', 1),
('Physics Fundamentals', 'Robert Brown', '9787890123456', 'Science', 'available', 1);

-- Add sample materials (file paths will need to be created)
INSERT INTO materials (title, description, file_path, category, uploaded_by) VALUES
('Introduction to Algebra', 'Basic concepts of algebra for beginners', 'uploads/materials/sample_algebra.pdf', 'Mathematics', 2),
('Programming in Python', 'Learn the basics of Python programming', 'uploads/materials/python_basics.pdf', 'Computer Science', 2),
('Essay Writing Guide', 'A comprehensive guide to writing essays', 'uploads/materials/essay_guide.pdf', 'Literature', 2);

-- Add keywords for the materials
INSERT INTO keywords (material_id, keyword) VALUES
(1, 'algebra'),
(1, 'mathematics'),
(1, 'equations'),
(2, 'python'),
(2, 'programming'),
(2, 'coding'),
(3, 'writing'),
(3, 'essays'),
(3, 'literature');
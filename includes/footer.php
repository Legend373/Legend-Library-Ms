        <footer class="footer">
            <div class="container">
                <div class="footer-container">
                    <div class="footer-section">
                        <h3>Legend Library</h3>
                        <p>Your gateway to knowledge and educational resources. Access a wide range of materials to enhance your learning experience.</p>
                        <div class="social-links mt-3">
                            <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                    
                    <div class="footer-section">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="<?php echo $base_url; ?>/index.php">Home</a></li>
                            <li><a href="<?php echo $base_url; ?>/search.php">Browse Materials</a></li>
                            <li><a href="<?php echo $base_url; ?>/about.php">About Us</a></li>
                            <li><a href="<?php echo $base_url; ?>/contact.php">Contact</a></li>
                            <li><a href="<?php echo $base_url; ?>/faq.php">FAQ</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h3>Categories</h3>
                        <ul>
                            <?php
                            // Display top 5 categories in footer
                            try {
                                $stmt = $conn->prepare("
                                    SELECT category, COUNT(*) as count
                                    FROM materials
                                    GROUP BY category
                                    ORDER BY count DESC
                                    LIMIT 5
                                ");
                                $stmt->execute();
                                $footer_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($footer_categories as $category) {
                                    echo '<li><a href="' . $base_url . '/search.php?category=' . urlencode($category['category']) . '">' . htmlspecialchars($category['category']) . '</a></li>';
                                }
                            } catch(PDOException $e) {
                                // Silently fail
                            }
                            ?>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h3>Contact Us</h3>
                        <p><i class="fas fa-map-marker-alt"></i> 123 Library Street, Education City</p>
                        <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                        <p><i class="fas fa-envelope"></i> info@legendlibrary.com</p>
                    </div>
                </div>
                
                <div class="footer-bottom">
                    <p>&copy; <?php echo date('Y'); ?> Legend Library. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
    
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (mobileMenuToggle && navMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('show');
                });
            }
            
            // User menu dropdown
            const userMenuButton = document.getElementById('userMenuButton');
            const userMenuDropdown = document.getElementById('userMenuDropdown');
            
            if (userMenuButton && userMenuDropdown) {
                userMenuButton.addEventListener('click', function() {
                    userMenuDropdown.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenuDropdown.contains(event.target)) {
                        userMenuDropdown.classList.remove('show');
                    }
                });
            }
        });
    </script>
    
    <style>
        /* Footer specific styles with brown theme */
        .footer {
            background-color: #FFF8DC;
            border-top: 1px solid #D2B48C;
            padding: 3rem 0 1.5rem;
            margin-top: 3rem;
            color: #333;
        }
        
        .footer-section h3 {
            color: #8B4513;
            font-size: 1.3rem;
            margin-bottom: 1.2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background-color: #8B4513;
        }
        
        .footer-section p {
            color: #555;
            line-height: 1.6;
        }
        
        .footer-section ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-section ul li {
            margin-bottom: 0.5rem;
        }
        
        .footer-section ul li a {
            color: #555;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .footer-section ul li a:hover {
            color: #8B4513;
            transform: translateX(5px);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
        }
        
        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background-color: #8B4513;
            color: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background-color: #A0522D;
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #D2B48C;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        
        .footer-section i {
            color: #8B4513;
            width: 20px;
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .footer-container {
                flex-direction: column;
                gap: 2rem;
            }
            
            .footer-section {
                width: 100%;
            }
        }
    </style>
</body>
</html>
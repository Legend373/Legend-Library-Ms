document.addEventListener('DOMContentLoaded', function () {
    // Mobile navigation toggle
    const createMobileNav = function () {
        const header = document.querySelector('header');

        if (header) {
            const nav = header.querySelector('nav');
            const navList = nav.querySelector('ul');

            // Only create mobile nav if it doesn't exist and there are nav items
            if (navList && !header.querySelector('.mobile-nav-toggle')) {
                // Create toggle button
                const toggleButton = document.createElement('button');
                toggleButton.className = 'mobile-nav-toggle';
                toggleButton.innerHTML = '<span></span><span></span><span></span>';
                toggleButton.setAttribute('aria-label', 'Toggle navigation');

                // Add toggle functionality
                toggleButton.addEventListener('click', function () {
                    this.classList.toggle('active');
                    navList.classList.toggle('active');
                });

                // Insert toggle before nav
                header.insertBefore(toggleButton, nav);

                // Add mobile class to nav
                nav.classList.add('mobile-nav');
            }
        }
    };

    // Call on page load for screens under 768px
    if (window.innerWidth < 768) {
        createMobileNav();
    }

    // Call on resize
    window.addEventListener('resize', function () {
        if (window.innerWidth < 768) {
            createMobileNav();
        } else {
            // Remove mobile nav elements if screen is large
            const toggleButton = document.querySelector('.mobile-nav-toggle');
            if (toggleButton) {
                toggleButton.remove();
            }

            const mobileNav = document.querySelector('.mobile-nav');
            if (mobileNav) {
                mobileNav.classList.remove('mobile-nav');
                const navList = mobileNav.querySelector('ul');
                if (navList) {
                    navList.classList.remove('active');
                }
            }
        }
    });

    // Add active class to current nav item
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('nav ul li a');

    navLinks.forEach(function (link) {
        const linkPage = link.getAttribute('href');

        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });

    // Add CSS for mobile navigation
    if (!document.getElementById('mobile-nav-styles')) {
        const style = document.createElement('style');
        style.id = 'mobile-nav-styles';
        style.textContent = `
            @media (max-width: 768px) {
                .mobile-nav-toggle {
                    display: block;
                    position: absolute;
                    top: 1rem;
                    right: 1rem;
                    background: none;
                    border: none;
                    cursor: pointer;
                    padding: 0.5rem;
                    z-index: 1000;
                }
                
                .mobile-nav-toggle span {
                    display: block;
                    width: 25px;
                    height: 3px;
                    background-color: #333;
                    margin: 5px 0;
                    transition: all 0.3s ease;
                }
                
                .mobile-nav-toggle.active span:nth-child(1) {
                    transform: rotate(45deg) translate(5px, 5px);
                }
                
                .mobile-nav-toggle.active span:nth-child(2) {
                    opacity: 0;
                }
                
                .mobile-nav-toggle.active span:nth-child(3) {
                    transform: rotate(-45deg) translate(7px, -7px);
                }
                
                .mobile-nav ul {
                    display: none;
                    flex-direction: column;
                    width: 100%;
                    text-align: center;
                    padding: 1rem 0;
                }
                
                .mobile-nav ul.active {
                    display: flex;
                }
                
                .mobile-nav ul li {
                    margin: 0.5rem 0;
                }
                
                header {
                    position: relative;
                    padding-top: 3rem;
                }
            }
        `;
        document.head.appendChild(style);
    }
});
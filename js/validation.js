document.addEventListener('DOMContentLoaded', function () {
    const registerForm = document.getElementById('registerForm');

    if (registerForm) {
        registerForm.addEventListener('submit', function (event) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            let isValid = true;
            const errors = [];

            // Validate username
            if (username.length < 3) {
                errors.push('Username must be at least 3 characters');
                isValid = false;
            }

            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errors.push('Please enter a valid email address');
                isValid = false;
            }

            // Validate password
            if (password.length < 6) {
                errors.push('Password must be at least 6 characters');
                isValid = false;
            }

            // Check if passwords match
            if (password !== confirmPassword) {
                errors.push('Passwords do not match');
                isValid = false;
            }

            // If validation fails, prevent form submission and show errors
            if (!isValid) {
                event.preventDefault();

                // Create or update error container
                let errorContainer = document.querySelector('.error-container');

                if (!errorContainer) {
                    errorContainer = document.createElement('div');
                    errorContainer.className = 'error-container';
                    registerForm.insertBefore(errorContainer, registerForm.firstChild);
                }

                // Add errors to container
                let errorList = '<ul>';
                errors.forEach(function (error) {
                    errorList += '<li>' + error + '</li>';
                });
                errorList += '</ul>';

                errorContainer.innerHTML = errorList;

                // Scroll to top of form
                window.scrollTo({
                    top: registerForm.offsetTop - 20,
                    behavior: 'smooth'
                });
            }
        });

        // Real-time password match validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        function validatePasswordMatch() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        password.addEventListener('change', validatePasswordMatch);
        confirmPassword.addEventListener('keyup', validatePasswordMatch);
    }
});
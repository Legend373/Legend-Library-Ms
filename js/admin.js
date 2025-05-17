document.addEventListener('DOMContentLoaded', function () {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));

            // Add active class to clicked button and corresponding pane
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // ISBN validation
    const isbnInput = document.getElementById('isbn');
    if (isbnInput) {
        isbnInput.addEventListener('blur', function () {
            const isbn = this.value.trim().replace(/-/g, '');

            // Basic ISBN-10 or ISBN-13 validation
            const isValidISBN10 = /^[0-9]{9}[0-9X]$/.test(isbn);
            const isValidISBN13 = /^[0-9]{13}$/.test(isbn);

            if (!isValidISBN10 && !isValidISBN13) {
                this.setCustomValidity('Please enter a valid ISBN-10 or ISBN-13');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Confirm delete actions
    const deleteForms = document.querySelectorAll('form[onsubmit]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function (event) {
            const confirmMessage = this.getAttribute('onsubmit').replace('return confirm(\'', '').replace('\')', '');
            if (!confirm(confirmMessage)) {
                event.preventDefault();
            }
        });
    });
});
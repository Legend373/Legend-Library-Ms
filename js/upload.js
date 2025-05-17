document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('file');
    const maxSize = 10 * 1024 * 1024; // 10MB in bytes

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];

            if (file) {
                // Check file size
                if (file.size > maxSize) {
                    alert('File size exceeds the maximum limit of 10MB.');
                    this.value = ''; // Clear the file input
                }

                // Display file name
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB

                // You could add a span to show the selected file info
                const fileInfoSpan = document.createElement('span');
                fileInfoSpan.className = 'file-info';
                fileInfoSpan.textContent = `Selected: ${fileName} (${fileSize} MB)`;

                // Remove any existing file info
                const existingFileInfo = this.parentNode.querySelector('.file-info');
                if (existingFileInfo) {
                    existingFileInfo.remove();
                }

                // Add the new file info
                this.parentNode.appendChild(fileInfoSpan);
            }
        });
    }

    // Form validation
    const uploadForm = document.querySelector('form');

    if (uploadForm) {
        uploadForm.addEventListener('submit', function (event) {
            const title = document.getElementById('title').value.trim();
            const category = document.getElementById('category').value;
            const file = document.getElementById('file').files[0];

            let isValid = true;
            const errors = [];

            if (!title) {
                errors.push('Title is required');
                isValid = false;
            }

            if (!category) {
                errors.push('Category is required');
                isValid = false;
            }

            if (!file) {
                errors.push('File is required');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                alert('Please fix the following errors:\n' + errors.join('\n'));
            }
        });
    }
});
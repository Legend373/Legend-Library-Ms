document.addEventListener('DOMContentLoaded', function () {
    // Live search functionality (optional enhancement)
    const searchInput = document.getElementById('query');
    const categorySelect = document.getElementById('category');

    if (searchInput && categorySelect) {
        let debounceTimer;

        const performSearch = function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                // Only auto-search if there's a query with at least 3 characters
                if (searchInput.value.trim().length >= 3 || categorySelect.value) {
                    document.querySelector('.search-form').submit();
                }
            }, 500); // 500ms delay
        };

        // Uncomment these lines to enable auto-search
        // searchInput.addEventListener('input', performSearch);
        // categorySelect.addEventListener('change', performSearch);
    }

    // Highlight search terms in results
    const highlightSearchTerms = function () {
        const searchQuery = searchInput ? searchInput.value.trim() : '';

        if (searchQuery.length < 3) return;

        const resultTitles = document.querySelectorAll('.result-card h4 a');
        const resultDescriptions = document.querySelectorAll('.result-description');

        const highlightText = function (elements, query) {
            const regex = new RegExp('(' + query + ')', 'gi');

            elements.forEach(function (element) {
                const originalText = element.textContent;
                const highlightedText = originalText.replace(regex, '<mark>$1</mark>');

                if (originalText !== highlightedText) {
                    element.innerHTML = highlightedText;
                }
            });
        };

        highlightText(resultTitles, searchQuery);
        highlightText(resultDescriptions, searchQuery);
    };

    // Call highlight function when page loads
    highlightSearchTerms();
});
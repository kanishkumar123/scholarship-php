document.addEventListener('DOMContentLoaded', () => {
    // Select all dropdown toggle buttons
    const allToggleButtons = document.querySelectorAll('.action-toggle-btn');

    allToggleButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            event.stopPropagation(); // Prevent the click from bubbling up to the window
            const dropdown = button.closest('.action-dropdown');
            
            // Close all other open dropdowns first
            closeAllDropdowns(dropdown);

            // Toggle the 'active' class on the current dropdown
            dropdown.classList.toggle('active');
        });
    });

    // Function to close all dropdowns except the one passed as an argument
    function closeAllDropdowns(exceptThisOne = null) {
        const allDropdowns = document.querySelectorAll('.action-dropdown');
        allDropdowns.forEach(dropdown => {
            if (dropdown !== exceptThisOne) {
                dropdown.classList.remove('active');
            }
        });
    }

    // Add a click listener to the whole window to close dropdowns when clicking outside
    window.addEventListener('click', () => {
        closeAllDropdowns();
    });
});
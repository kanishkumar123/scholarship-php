document.addEventListener("DOMContentLoaded", () => {
  // Find all the toggle buttons in the table
  const toggleButtons = document.querySelectorAll(".toggle-details-btn");

  toggleButtons.forEach((button) => {
    button.addEventListener("click", () => {
      // 1. Get the main row (tr)
      const mainRow = button.closest("tr.main-row");

      // 2. Get the details row (tr) that comes *immediately after* the main row
      const detailsRow = mainRow.nextElementSibling;

      // 3. Get the content *inside* the details row
      const detailsContent = detailsRow.querySelector(".app-details-content");

      // 4. Toggle the 'active' class on the button
      button.classList.toggle("active");

      // 5. Check if the row is currently open (by checking its maxHeight)
      if (detailsContent.style.maxHeight) {
        // It's open, so close it
        detailsContent.style.maxHeight = null;
        detailsRow.classList.remove("details-open");
      } else {
        // It's closed, so open it
        // We set its maxHeight to its *actual* full height
        detailsContent.style.maxHeight = detailsContent.scrollHeight + "px";
        detailsRow.classList.add("details-open");
      }
    });
  });
});

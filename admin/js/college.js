document.addEventListener("DOMContentLoaded", () => {
  const showButton = document.getElementById("show-add-form");
  const formSection = document.getElementById("add-form-section");
  const cancelButton = document.getElementById("cancel-add-form");

  // Check if all elements exist on the page
  if (showButton && formSection && cancelButton) {
    // Show the form when the 'Add New' button is clicked
    showButton.addEventListener("click", () => {
      formSection.style.display = "block";
      showButton.style.display = "none";
    });

    // Hide the form when the 'Cancel' button is clicked
    cancelButton.addEventListener("click", () => {
      formSection.style.display = "none";
      showButton.style.display = "block";
    });
  }
});

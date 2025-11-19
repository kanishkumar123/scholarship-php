document.addEventListener("DOMContentLoaded", () => {
  // --- 1. Logic for the "Add New Scholarship" form ---
  const showButton = document.getElementById("show-add-form");
  const formSection = document.getElementById("add-form-section");
  const cancelButton = document.getElementById("cancel-add-form");

  if (showButton && formSection && cancelButton) {
    showButton.addEventListener("click", () => {
      formSection.style.display = "block";
      showButton.style.display = "none";
    });

    cancelButton.addEventListener("click", () => {
      formSection.style.display = "none";
      showButton.style.display = "block";
    });
  }

  // --- 2. Logic for the new Collapsible Card ---
  // Select all the *clickable toggle areas*
  const accordionToggles = document.querySelectorAll(
    ".scholarship-card-toggle"
  );

  accordionToggles.forEach((toggle) => {
    toggle.addEventListener("click", () => {
      // Get the main header
      const header = toggle.closest(".scholarship-card-header");
      // Get the main card
      const card = toggle.closest(".scholarship-card");
      // Find the body *within* that card
      const accordionBody = card.querySelector(".scholarship-card-body");

      // Toggle 'active' class on the header (for the icon)
      header.classList.toggle("active");

      // Check if the panel is open or closed
      if (accordionBody.style.maxHeight) {
        // If it's open (has maxHeight), close it
        accordionBody.style.maxHeight = null;
      } else {
        // If it's closed, open it by setting maxHeight to its full scrollHeight
        accordionBody.style.maxHeight = accordionBody.scrollHeight + "px";
      }
    });
  });
});

document.addEventListener("DOMContentLoaded", () => {
  // --- 1. Login Panel Toggle ---
  const loginOpenBtn = document.getElementById("login-open-btn");
  const loginCloseBtn = document.getElementById("login-close-btn");
  const loginPanel = document.getElementById("login-panel");
  const loginOverlay = document.getElementById("login-overlay");

  const openPanel = () => {
    if (loginPanel) loginPanel.classList.add("is-open");
    if (loginOverlay) loginOverlay.classList.add("is-open");
  };

  const closePanel = () => {
    if (loginPanel) loginPanel.classList.remove("is-open");
    if (loginOverlay) loginOverlay.classList.remove("is-open");
  };

  // ⭐️ FIX: Changed from 'click' to 'mousedown'
  // This fires *before* the form's required-field check,
  // giving the panel time to open before the form validation runs.
  if (loginOpenBtn) loginOpenBtn.addEventListener("mousedown", openPanel);

  if (loginCloseBtn) loginCloseBtn.addEventListener("click", closePanel);
  if (loginOverlay) loginOverlay.addEventListener("click", closePanel);

  // --- 2. Sticky Header Color Change ---
  const header = document.querySelector(".site-header");

  if (header) {
    window.addEventListener("scroll", () => {
      if (window.scrollY > 50) {
        document.body.classList.add("scrolled");
      } else {
        document.body.classList.remove("scrolled");
      }
    });
  }

  // --- 3. Scroll-Reveal Animations ---
  const scrollElements = document.querySelectorAll(".scroll-animate");

  if (scrollElements.length > 0) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            // Optional: stop observing once it's visible
            // observer.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.1, // Trigger when 10% of the element is visible
      }
    );

    scrollElements.forEach((el) => {
      observer.observe(el);
    });
  }
});

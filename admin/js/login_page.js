document.addEventListener("DOMContentLoaded", () => {
  // --- Login Panel Toggle ---
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

  if (loginOpenBtn) loginOpenBtn.addEventListener("click", openPanel);
  if (loginCloseBtn) loginCloseBtn.addEventListener("click", closePanel);
  if (loginOverlay) loginOverlay.addEventListener("click", closePanel);
});

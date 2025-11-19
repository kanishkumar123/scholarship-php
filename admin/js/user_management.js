document.addEventListener("DOMContentLoaded", () => {
  const roleSelect = document.getElementById("role");
  const collegeSelect = document.getElementById("college_id");

  if (roleSelect && collegeSelect) {
    const toggleCollegeSelect = () => {
      const selectedRole = roleSelect.value;

      // Enable the college dropdown ONLY if the role is *not* Admin
      if (selectedRole === "Admin" || selectedRole === "") {
        collegeSelect.disabled = true;
        collegeSelect.value = ""; // Reset selection
      } else {
        collegeSelect.disabled = false;
      }
    };

    // Add event listener to the role select
    roleSelect.addEventListener("change", toggleCollegeSelect);

    // Run it once on page load
    toggleCollegeSelect();
  }
});

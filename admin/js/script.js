// ===================================================================
// ⭐️ MASTER SCRIPT FILE ⭐️
// This file controls:
// 1. Theme (Dark/Light)
// 2. Header Dropdowns (User & Notifications)
// 3. Page-Specific: Dashboard Charts
// 4. Page-Specific: Program Mapping Modal
// 5. Page-Specific: College/Scholarship "Add Form" toggle
// 6. Page-Specific: View Applications expandable rows
// 7. Page-Specific: User Management role dropdown
// ===================================================================

// --- Global helper function for chart drawing ---
// Must be global so Google Charts can call it
function initializeDashboard() {
  const filterForm = document.getElementById("filterForm");
  if (filterForm) {
    // Guard
    filterForm.addEventListener("change", fetchAndDrawCharts);
    fetchAndDrawCharts(); // Initial draw
  }
}

// ===================================================================
// --- MAIN DOMContentLoaded WRAPPER ---
// All code that interacts with the page runs after it's loaded.
// ===================================================================
document.addEventListener("DOMContentLoaded", () => {
  // --- 1. GLOBAL: THEME TOGGLE LOGIC ---
  const themeToggle = document.getElementById("themeToggle");
  const body = document.body;

  const applyTheme = (theme) => {
    if (theme === "dark") {
      body.classList.add("dark-theme");
      if (themeToggle) themeToggle.checked = true;
    } else {
      body.classList.remove("dark-theme");
      if (themeToggle) themeToggle.checked = false;
    }
    // Redraw charts *only if* the chart function exists
    if (
      typeof fetchAndDrawCharts === "function" &&
      document.getElementById("filterForm")
    ) {
      setTimeout(fetchAndDrawCharts, 50);
    }
  };

  const savedTheme = localStorage.getItem("theme");
  const prefersDark =
    window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches;

  if (savedTheme) {
    applyTheme(savedTheme);
  } else {
    applyTheme(prefersDark ? "dark" : "light"); // Default to system, or light
  }

  if (themeToggle) {
    themeToggle.addEventListener("change", () => {
      const newTheme = themeToggle.checked ? "dark" : "light";
      localStorage.setItem("theme", newTheme);
      applyTheme(newTheme);
    });
  }

  // --- 2. GLOBAL: HEADER DROPDOWNS LOGIC ---
  const userDropdown = document.getElementById("user-profile-dropdown");
  const notificationDropdown = document.getElementById("notification-dropdown");
  const notificationDot = document.getElementById("notification-dot");

  // Function to close all open dropdowns
  const closeAllDropdowns = () => {
    userDropdown?.classList.remove("active");
    notificationDropdown?.classList.remove("active");
  };

  // Toggle User Profile Dropdown
  if (userDropdown) {
    userDropdown
      .querySelector(".user-profile-toggle")
      .addEventListener("click", (e) => {
        e.stopPropagation();
        notificationDropdown?.classList.remove("active");
        userDropdown.classList.toggle("active");
      });
  }

  // Toggle Notification Dropdown
  if (notificationDropdown) {
    notificationDropdown
      .querySelector(".action-icon")
      .addEventListener("click", (e) => {
        e.stopPropagation();
        userDropdown?.classList.remove("active");
        const wasActive = notificationDropdown.classList.contains("active");
        notificationDropdown.classList.toggle("active");

        if (!wasActive && notificationDot) {
          notificationDot.style.display = "none";
          fetch("ajax_mark_read.php")
            .then((response) => response.json())
            .then((data) => {
              if (data.status !== "success")
                console.error("Failed to mark notifications as read.");
            })
            .catch((err) => console.error("AJAX error:", err));
        }
      });
  }

  // Close all dropdowns when clicking anywhere else
  window.addEventListener("click", (e) => {
    if (
      !userDropdown?.contains(e.target) &&
      !notificationDropdown?.contains(e.target)
    ) {
      closeAllDropdowns();
    }
  });

  // --- 3. PAGE-SPECIFIC: DASHBOARD (Google Charts) ---
  // We check if the chart library is loaded AND the form exists
  if (
    typeof google !== "undefined" &&
    google.charts &&
    document.getElementById("filterForm")
  ) {
    google.charts.load("current", { packages: ["corechart"] });
    google.charts.setOnLoadCallback(initializeDashboard); // Calls the global function
  }

  // --- 4. PAGE-SPECIFIC: PROGRAM MAPPING MODAL ---
  const mappingModal = document.getElementById("mapping-modal");
  if (mappingModal) {
    // CRITICAL FIX: Check if the PHP data was injected before running
    if (
      typeof allPrograms === "undefined" ||
      typeof currentMappings === "undefined"
    ) {
      console.error(
        "FATAL ERROR: 'allPrograms' or 'currentMappings' JS variables are not defined. Did you include the <script> block in map_programs.php?"
      );
    } else {
      // --- All logic from map_programs.js goes here ---
      let sortablePool = null;
      let sortableCollege = null;

      const modalTitle = document.getElementById("modal-title");
      const modalCollegeName = document.getElementById("modal-college-name");
      const modalCollegeIdInput = document.getElementById("modal_college_id");
      const poolList = document.getElementById("modal-program-pool");
      const collegeList = document.getElementById("modal-college-programs");
      const searchInput = document.getElementById("program-search");
      const saveBtn = document.getElementById("modal-save-btn");
      const closeBtn = document.getElementById("modal-close-btn");
      const cancelBtn = document.getElementById("modal-cancel-btn");
      const manageButtons = document.querySelectorAll(".manage-button");
      const collegeGrid = document.getElementById("college-card-grid");

      const createProgramCheckbox = (id, name, isChecked) => {
        const div = document.createElement("div");
        div.className = "program-checkbox-item";
        div.innerHTML = `
                <input type="checkbox" name="program_ids[]" value="${id}" id="prog-modal-${id}" ${
          isChecked ? "checked" : ""
        }>
                <label for="prog-modal-${id}">${name}</label>
            `;
        return div;
      };

      const openModal = (collegeId, collegeName) => {
        currentEditingCollegeId = collegeId;
        modalTitle.textContent = `Manage Programs`;
        modalCollegeName.textContent = collegeName;
        modalCollegeIdInput.value = collegeId;

        const assignedProgramIds = allMappings[collegeId] || {};
        programListDiv.innerHTML = "";

        allPrograms.forEach((program) => {
          const isChecked = assignedProgramIds.hasOwnProperty(program.id);
          const item = createProgramCheckbox(
            program.id,
            program.name,
            isChecked
          );
          programListDiv.appendChild(item);
        });

        modal.style.display = "flex";
      };

      const closeModal = () => {
        modal.style.display = "none";
        programListDiv.innerHTML = "";
        programSearchInput.value = "";
        currentEditingCollegeId = null;
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save Changes';
      };

      const filterPrograms = () => {
        const searchTerm = programSearchInput.value.toLowerCase();
        const items = programListDiv.querySelectorAll(".program-checkbox-item");
        items.forEach((item) => {
          const label = item.querySelector("label");
          const itemName = label.textContent.toLowerCase();
          item.style.display = itemName.includes(searchTerm) ? "flex" : "none";
        });
      };

      const handleAjaxSave = () => {
        saveBtn.disabled = true;
        saveBtn.innerHTML =
          '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        const checkedBoxes = programListDiv.querySelectorAll(
          'input[type="checkbox"]:checked'
        );
        const programIds = Array.from(checkedBoxes).map((box) => box.value);

        fetch("save_mapping_ajax.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({
            college_id: currentEditingCollegeId,
            program_ids: programIds,
          }),
        })
          .then((response) => {
            if (!response.ok)
              return response.text().then((text) => {
                throw new Error("Server error: " + text);
              });
            return response.json();
          })
          .then((data) => {
            if (data.status === "success") {
              const newMappingsForCollege = {};
              programIds.forEach((id) => {
                newMappingsForCollege[id] = true;
              });
              allMappings[currentEditingCollegeId] = newMappingsForCollege;
              updateCollegeCard(currentEditingCollegeId);
              closeModal();
            } else {
              throw new Error(data.message);
            }
          })
          .catch((error) => {
            console.error("Fetch error:", error);
            alert(
              "Save failed. Please try again.\n\nError details: " +
                error.message
            );
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save Changes';
          });
      };

      const updateCollegeCard = (collegeId) => {
        const card = document.querySelector(
          `.map-card[data-college-id="${collegeId}"]`
        );
        if (!card) return;

        const countSpan = document.getElementById(`count-${collegeId}`);
        const pillList = document.getElementById(`pills-${collegeId}`);
        const mappedProgramIds = allMappings[collegeId] || {};
        const mappedCount = Object.keys(mappedProgramIds).length;

        countSpan.textContent = mappedCount;
        card.querySelector(".map-card-programs-title").innerHTML = `
                <span id="count-${collegeId}">${mappedCount}</span> Program${
          mappedCount != 1 ? "s" : ""
        } Mapped
            `;

        pillList.innerHTML = "";
        let pills_shown = 0;
        if (mappedCount > 0) {
          for (const p_id in mappedProgramIds) {
            if (pills_shown >= 2) break;
            if (allProgramsMap[p_id]) {
              const pill = document.createElement("li");
              pill.className = "program-pill";
              pill.textContent = allProgramsMap[p_id];
              pillList.appendChild(pill);
              pills_shown++;
            }
          }
          if (mappedCount > pills_shown) {
            const morePill = document.createElement("li");
            morePill.className = "program-pill-more";
            morePill.textContent = `+${mappedCount - pills_shown} more`;
            pillList.appendChild(morePill);
          }
        } else {
          const noPill = document.createElement("li");
          noPill.className = "program-pill-none";
          noPill.textContent = "No programs mapped.";
          pillList.appendChild(noPill);
        }
      };

      // Attach all event listeners for this page
      collegeGrid.addEventListener("click", (e) => {
        const manageButton = e.target.closest(".manage-button");
        if (manageButton) {
          const card = manageButton.closest(".map-card");
          openModal(card.dataset.collegeId, card.dataset.collegeName);
        }
      });
      closeBtn.addEventListener("click", closeModal);
      cancelBtn.addEventListener("click", closeModal);
      modal.addEventListener("click", (e) => {
        if (e.target === modal) closeModal();
      });
      programSearchInput.addEventListener("input", filterPrograms);
      saveBtn.addEventListener("click", handleAjaxSave);
    }
  }

  // --- 5. PAGE-SPECIFIC: "Add New" Form Toggle (for College & Scholarship pages) ---
  const showAddFormBtn = document.getElementById("show-add-form");
  if (showAddFormBtn) {
    const formSection = document.getElementById("add-form-section");
    const cancelButton = document.getElementById("cancel-add-form");
    if (formSection && cancelButton) {
      showAddFormBtn.addEventListener("click", () => {
        formSection.style.display = "block";
        showAddFormBtn.style.display = "none";
      });
      cancelButton.addEventListener("click", () => {
        formSection.style.display = "none";
        showAddFormBtn.style.display = "block";
      });
    }
  }

  // --- 6. PAGE-SPECIFIC: Scholarship Card Accordion ---
  const scholarshipGrid = document.querySelector(".scholarship-card-grid");
  if (scholarshipGrid) {
    scholarshipGrid.addEventListener("click", (e) => {
      const toggle = e.target.closest(".scholarship-card-toggle");
      if (toggle) {
        const header = toggle.closest(".scholarship-card-header");
        const card = toggle.closest(".scholarship-card");
        const accordionBody = card.querySelector(".scholarship-card-body");

        header.classList.toggle("active");
        if (accordionBody.style.maxHeight) {
          accordionBody.style.maxHeight = null;
        } else {
          accordionBody.style.maxHeight = accordionBody.scrollHeight + "px";
        }
      }
    });
  }

  // --- 7. PAGE-SPECIFIC: View Applications Expandable Row ---
  const firstToggleBtn = document.querySelector(".toggle-details-btn");
  if (firstToggleBtn) {
    const table = firstToggleBtn.closest("table");
    table.addEventListener("click", (e) => {
      const button = e.target.closest(".toggle-details-btn");
      if (button) {
        const mainRow = button.closest("tr.main-row");
        const detailsRow = mainRow.nextElementSibling;
        const detailsContent = detailsRow.querySelector(".app-details-content");

        button.classList.toggle("active");

        if (detailsContent.style.maxHeight) {
          detailsContent.style.maxHeight = null;
          detailsRow.classList.remove("details-open");
        } else {
          detailsContent.style.maxHeight = detailsContent.scrollHeight + "px";
          detailsRow.classList.add("details-open");
        }
      }
    });
  }

  // --- 8. PAGE-SPECIFIC: User Management Role Dropdown ---
  const roleSelect = document.getElementById("role");
  if (roleSelect) {
    const collegeSelect = document.getElementById("college_id");
    if (collegeSelect) {
      const toggleCollegeSelect = () => {
        const selectedRole = roleSelect.value;
        if (selectedRole === "Admin" || selectedRole === "") {
          collegeSelect.disabled = true;
          collegeSelect.value = "";
        } else {
          collegeSelect.disabled = false;
        }
      };
      roleSelect.addEventListener("change", toggleCollegeSelect);
      toggleCollegeSelect(); // Run on page load
    }
  }
});
// --- END OF Main DOMContentLoaded ---

// ===================================================================
// --- GLOBAL FUNCTIONS (CHARTING) ---
// Must be global for Google Charts loader
// ===================================================================

function fetchAndDrawCharts() {
  const filterForm = document.getElementById("filterForm");
  if (!filterForm) return; // Final guard

  const formData = new FormData(filterForm);
  const queryString = new URLSearchParams(formData).toString();

  fetch(`fetch_dashboard_data.php?${queryString}`)
    .then((response) => {
      if (!response.ok) throw new Error("Network response was not ok");
      return response.json();
    })
    .then((data) => {
      if (document.getElementById("gender_chart"))
        drawGenderChart(data.gender || {});
      if (document.getElementById("community_chart"))
        drawCommunityChart(data.community || {});
      if (document.getElementById("institution_chart"))
        drawInstitutionChart(data.institution || {});
    })
    .catch((error) => {
      console.error("Error fetching chart data:", error);
      if (document.getElementById("gender_chart"))
        document.getElementById("gender_chart").innerHTML =
          '<p class="no-applications">Error loading chart.</p>';
    });
}

function getChartOptions() {
  const isDarkMode = document.body.classList.contains("dark-theme");
  const textColor = isDarkMode ? "#f0f8ff" : "#333";
  const gridColor = isDarkMode ? "rgba(255, 255, 255, 0.15)" : "#ccc";

  return {
    backgroundColor: "transparent",
    legend: {
      textStyle: { color: textColor, fontSize: 12 },
      position: "bottom",
    },
    titleTextStyle: { color: textColor, fontSize: 16, bold: false },
    hAxis: {
      textStyle: { color: textColor },
      gridlines: { color: "transparent" },
    },
    vAxis: {
      textStyle: { color: textColor },
      gridlines: { color: gridColor },
      baselineColor: gridColor,
    },
    chartArea: { left: "15%", top: "10%", width: "80%", height: "75%" },
    colors: [
      "#00c6ff",
      "#0072ff",
      "#80d0c7",
      "#13547a",
      "#f5a623",
      "#f8e71c",
      "#7ed321",
    ],
  };
}

function drawPieChart(elementId, chartData) {
  const chartElement = document.getElementById(elementId);
  if (!chartElement) return;

  const data = new google.visualization.DataTable();
  data.addColumn("string", "Category");
  data.addColumn("number", "Count");

  const rows = Object.entries(chartData);
  if (rows.length === 0) {
    chartElement.innerHTML = `<p class="no-applications" style="padding-top:100px;">No data available.</p>`;
    return;
  }
  data.addRows(rows);

  const options = { ...getChartOptions(), is3D: true };
  const chart = new google.visualization.PieChart(chartElement);
  chart.draw(data, options);
}

function drawGenderChart(genderData) {
  drawPieChart("gender_chart", genderData);
}
function drawCommunityChart(communityData) {
  drawPieChart("community_chart", communityData);
}

function drawInstitutionChart(institutionData) {
  const chartElement = document.getElementById("institution_chart");
  if (!chartElement) return;

  const data = new google.visualization.DataTable();
  data.addColumn("string", "College");
  data.addColumn("number", "Applications");

  const rows = Object.entries(institutionData);
  if (rows.length === 0) {
    chartElement.innerHTML = `<p class="no-applications" style="padding-top:100px;">No data available.</p>`;
    return;
  }
  data.addRows(rows);

  const baseOptions = getChartOptions();
  const options = {
    ...baseOptions,
    legend: { position: "none" },
    hAxis: {
      ...baseOptions.hAxis,
      title: "Number of Applications",
      titleTextStyle: { color: baseOptions.hAxis.textStyle.color },
    },
    vAxis: {
      ...baseOptions.vAxis,
      textStyle: { ...baseOptions.vAxis.textStyle, fontSize: 10 },
    },
    bar: { groupWidth: "80%" },
  };

  const chart = new google.visualization.BarChart(chartElement);
  chart.draw(data, options);
}

// --- Global Resize Listener for Charts ---
window.addEventListener("resize", () => {
  if (
    typeof fetchAndDrawCharts === "function" &&
    document.getElementById("filterForm")
  ) {
    clearTimeout(window.resizedFinished);
    window.resizedFinished = setTimeout(fetchAndDrawCharts, 250);
  }
});

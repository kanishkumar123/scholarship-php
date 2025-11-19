document.addEventListener("DOMContentLoaded", () => {
  // Check if we are on the mapping page
  const collegeGrid = document.getElementById("college-card-grid");
  if (!collegeGrid) {
    return; // Not on the mapping page, do nothing
  }

  // --- Global Vars ---
  let currentEditingCollegeId = null; // Track which college modal is for

  // --- Page Elements ---
  // const collegeSearchInput = document.getElementById('college-search-bar'); // No longer needed
  const allCollegeCards = document.querySelectorAll(".map-card");
  // const noCollegesFoundMsg = document.getElementById('no-colleges-found'); // No longer needed

  // --- Modal Elements ---
  const modal = document.getElementById("mapping-modal");
  const modalTitle = document.getElementById("modal-title");
  const modalCollegeName = document.getElementById("modal-college-name");
  const modalCollegeIdInput = document.getElementById("modal_college_id");
  const programListDiv = document.getElementById("modal-program-list");
  const programSearchInput = document.getElementById("program-search");

  const closeBtn = document.getElementById("modal-close-btn");
  const cancelBtn = document.getElementById("modal-cancel-btn");
  const saveBtn = document.getElementById("modal-save-btn");

  // --- Data from PHP (injected in map_programs.php) ---
  // allPrograms = Array of {id, name}
  // allMappings = Object of { college_id: { program_id: true } }
  // allProgramsMap = Object of { id: "name" }

  // --- Function to create a checkbox list item ---
  const createProgramCheckbox = (id, name, isChecked) => {
    const div = document.createElement("div");
    div.className = "program-checkbox-item";

    const input = document.createElement("input");
    input.type = "checkbox";
    input.name = "program_ids[]";
    input.value = id;
    input.id = `prog-modal-${id}`;
    input.checked = isChecked;

    const label = document.createElement("label");
    label.htmlFor = `prog-modal-${id}`;
    label.textContent = name;

    div.appendChild(input);
    div.appendChild(label);
    return div;
  };

  // --- Function to open and populate the modal ---
  const openModal = (collegeId, collegeName) => {
    currentEditingCollegeId = collegeId; // Set the college we're editing

    // 1. Set modal titles and hidden values
    modalTitle.textContent = `Manage Programs`;
    modalCollegeName.textContent = collegeName;
    modalCollegeIdInput.value = collegeId;

    // 2. Check that PHP data is loaded
    if (
      typeof allPrograms === "undefined" ||
      typeof allMappings === "undefined"
    ) {
      console.error("Data (allPrograms or allMappings) is not loaded.");
      programListDiv.innerHTML = "<p>Error loading programs.</p>";
      return;
    }

    // 3. Get the list of currently assigned program IDs for this college
    const assignedProgramIds = allMappings[collegeId] || {};

    // 4. Populate the checkbox list
    programListDiv.innerHTML = ""; // Clear old list
    allPrograms.forEach((program) => {
      const isChecked = assignedProgramIds.hasOwnProperty(program.id);
      const item = createProgramCheckbox(program.id, program.name, isChecked);
      programListDiv.appendChild(item);
    });

    // 5. Show the modal
    modal.style.display = "flex";
  };

  // --- Function to close the modal ---
  const closeModal = () => {
    modal.style.display = "none";
    programListDiv.innerHTML = ""; // Clear list
    programSearchInput.value = ""; // Clear search
    currentEditingCollegeId = null;

    // Reset save button state
    saveBtn.disabled = false;
    saveBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save Changes';
  };

  // --- Function for the modal's program search bar ---
  const filterPrograms = () => {
    const searchTerm = programSearchInput.value.toLowerCase();

    // Loop over all items in the checkbox list
    const items = programListDiv.querySelectorAll(".program-checkbox-item");
    items.forEach((item) => {
      const label = item.querySelector("label");
      const itemName = label.textContent.toLowerCase();
      if (itemName.includes(searchTerm)) {
        item.style.display = "flex";
      } else {
        item.style.display = "none";
      }
    });
  };

  // --- (REMOVED) filterColleges() function is no longer needed ---

  // --- Function to handle AJAX Save ---
  const handleAjaxSave = () => {
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    // 1. Get all checked program IDs
    const checkedBoxes = programListDiv.querySelectorAll(
      'input[type="checkbox"]:checked'
    );
    const programIds = [];
    checkedBoxes.forEach((box) => {
      programIds.push(box.value);
    });

    // 2. Prepare data to send
    const dataToSend = {
      college_id: currentEditingCollegeId,
      program_ids: programIds,
    };

    // 3. Send to the new PHP file
    fetch("save_mapping_ajax.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(dataToSend),
    })
      .then((response) => {
        if (!response.ok) {
          return response.text().then((text) => {
            throw new Error("Server returned an error: " + text);
          });
        }
        return response.json();
      })
      .then((data) => {
        if (data.status === "success") {
          // 4. Update the global JS variable with the new reality
          const newMappingsForCollege = {};
          programIds.forEach((id) => {
            newMappingsForCollege[id] = true;
          });
          allMappings[currentEditingCollegeId] = newMappingsForCollege;

          // 5. Update the college card on the main page
          updateCollegeCard(currentEditingCollegeId);

          // 6. Close the modal
          closeModal();
        } else {
          throw new Error(data.message);
        }
      })
      .catch((error) => {
        console.error("Fetch error:", error);
        alert(
          "Save failed. Please try again.\n\nError details: " + error.message
        );

        // Re-enable save button on error
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save Changes';
      });
  };

  // --- Function to update a single college card UI after saving ---
  const updateCollegeCard = (collegeId) => {
    const card = document.querySelector(
      `.map-card[data-college-id="${collegeId}"]`
    );
    if (!card) return;

    const countSpan = document.getElementById(`count-${collegeId}`);
    const pillList = document.getElementById(`pills-${collegeId}`);

    const mappedProgramIds = allMappings[collegeId] || {};
    const mappedCount = Object.keys(mappedProgramIds).length;

    // 1. Update count
    countSpan.textContent = mappedCount;
    card.querySelector(".map-card-programs-title").innerHTML = `
            <span id="count-${collegeId}">${mappedCount}</span> Program${
      mappedCount != 1 ? "s" : ""
    } Mapped
        `;

    // 2. Update pills
    pillList.innerHTML = ""; // Clear old pills
    let pills_shown = 0;
    if (mappedCount > 0) {
      for (const p_id in mappedProgramIds) {
        if (pills_shown >= 2) break; // Show max 2 pills
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

  // --- Attach Event Listeners ---
  // Open modal when any "Manage" button is clicked
  collegeGrid.addEventListener("click", (e) => {
    const manageButton = e.target.closest(".manage-button");
    if (manageButton) {
      const card = manageButton.closest(".map-card");
      const collegeId = card.dataset.collegeId;
      const collegeName = card.dataset.collegeName;
      openModal(collegeId, collegeName);
    }
  });

  // Close modal listeners
  closeBtn.addEventListener("click", closeModal);
  cancelBtn.addEventListener("click", closeModal);
  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      closeModal();
    }
  });

  // Modal search bar listener
  programSearchInput.addEventListener("input", filterPrograms);

  // (REMOVED) College search bar listener

  // Save button listener
  saveBtn.addEventListener("click", handleAjaxSave);
});

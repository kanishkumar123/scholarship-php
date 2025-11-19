// =================================================================
// 1. GLOBAL FUNCTIONS
// =================================================================

let currentPage = 1;
const totalPages = 3;
let pages,
  navTabs,
  prevBtn,
  nextBtn,
  submitBtn,
  modal,
  errorMessage,
  signaturePad;

/**
 * Updates the visibility of the Next, Prev, and Submit buttons
 */
function updateNavButtons() {
  if (!prevBtn || !nextBtn || !submitBtn) return;
  prevBtn.style.visibility = currentPage > 1 ? "visible" : "hidden";
  nextBtn.style.display = currentPage < totalPages ? "inline-flex" : "none";
  submitBtn.style.display = currentPage === totalPages ? "inline-flex" : "none";
}

/**
 * Shows the specified page number and hides all others
 */
function showPage(pageNumber) {
  if (!pages || !navTabs) return;

  pages.forEach((p) => p.classList.remove("active"));
  const targetPageElement = document.getElementById(`page${pageNumber}`);
  if (targetPageElement) {
    targetPageElement.classList.add("active");
    currentPage = pageNumber;
  }

  navTabs.forEach((t) => t.classList.remove("active"));
  document
    .querySelector(`.nav-tab[data-page='${pageNumber}']`)
    ?.classList.add("active");

  updateNavButtons();
}

/**
 * Shows the error modal with a specific message
 */
function showModal(msg) {
  if (!modal) return;
  document.getElementById("errorMessage").innerHTML = msg;
  modal.style.display = "flex";
}

/**
 * Closes the error modal
 */
function closeModal() {
  if (modal) modal.style.display = "none";
}

/**
 * Shows or hides a conditional section
 */
function toggleSection(radioName, sectionId) {
  const radios = document.querySelectorAll(`input[name="${radioName}"]`);
  const section = document.getElementById(sectionId);
  if (!section || radios.length === 0) return;

  const checkState = () => {
    const selectedValue = document.querySelector(
      `input[name="${radioName}"]:checked`
    )?.value;
    const shouldShow = selectedValue === "Yes";
    section.style.display = shouldShow ? "block" : "none";

    if (!shouldShow) {
      section.querySelectorAll(".form-group").forEach((wrapper) => {
        wrapper.classList.remove("validated", "error");
      });
    }
  };
  radios.forEach((radio) => radio.addEventListener("change", checkState));
  checkState(); // Run on load
}

/**
 * Populates the Course dropdown based on the selected Institution
 */
function updatePrograms() {
  const collegeSelect = document.getElementById("institution_name");
  const programSelect = document.getElementById("course");
  if (!collegeSelect || !programSelect) return;

  const selectedCollegeId = collegeSelect.value;
  programSelect.innerHTML = '<option value="">-- SELECT COURSE --</option>';

  if (
    selectedCollegeId &&
    typeof collegePrograms !== "undefined" &&
    collegePrograms[selectedCollegeId]
  ) {
    collegePrograms[selectedCollegeId].programs.forEach((program) => {
      const option = document.createElement("option");
      option.value = program.id;
      option.textContent = program.name;
      programSelect.appendChild(option);
    });
    programSelect.disabled = false;
  } else {
    programSelect.disabled = true;
  }

  validateField(collegeSelect);
  validateField(programSelect);
}

/**
 * Populates the Semester dropdown based on the selected Year (with Roman Numerals)
 */
function updateSemesters() {
  const yearSelect = document.getElementById("year_of_study");
  const semSelect = document.getElementById("semester");
  if (!yearSelect || !semSelect) return;

  const year = parseInt(yearSelect.value);
  const roman_semesters = [
    "I",
    "II",
    "III",
    "IV",
    "V",
    "VI",
    "VII",
    "VIII",
    "IX",
    "X",
  ];

  semSelect.innerHTML = '<option value="">-- Select Semester --</option>';

  if (year > 0) {
    semSelect.disabled = false;
    const startSem = (year - 1) * 2 + 1;
    const endSem = year * 2;
    for (let i = startSem; i <= endSem; i++) {
      const opt = document.createElement("option");
      opt.value = i;
      opt.textContent = roman_semesters[i - 1];
      semSelect.appendChild(opt);
    }
  } else {
    semSelect.disabled = true;
    semSelect.innerHTML = '<option value=""></option>'; // Reset message
  }

  // Re-validate both fields
  validateField(yearSelect);
  if (semSelect.classList.contains("is-dirty")) {
    validateField(semSelect);
  }
}

/**
 * Handles the "Next" button click
 */
function nextPage() {
  if (validatePage(currentPage)) {
    document
      .querySelector(".card")
      .scrollIntoView({ behavior: "smooth", block: "start" });
    setTimeout(() => showPage(currentPage + 1), 10);
  }
}

/**
 * Handles the "Previous" button click
 */
function prevPage() {
  document
    .querySelector(".card")
    .scrollIntoView({ behavior: "smooth", block: "start" });
  showPage(currentPage - 1);
}

/**
 * ⭐️ Validates a single input/select/textarea
 * This is the new, corrected logic
 */
function validateField(input) {
  const parentWrapper = input.closest(".form-group");
  if (!parentWrapper) return true;

  const conditionalParent = input.closest(".conditional-section");
  if (conditionalParent && conditionalParent.style.display === "none") {
    parentWrapper.classList.remove("validated", "error");
    return true;
  }

  const value = input.value.trim();
  const type = input.getAttribute("data-validate-type");
  const isRequired = input.hasAttribute("required"); // Check if HTML 'required' tag exists
  let isValid = true;

  if (value === "") {
    // If it's empty, it's ONLY invalid if it's required
    isValid = !isRequired;
  } else {
    // If it's NOT empty, then validate its content
    switch (type) {
      case "email":
        isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        break;
      case "text_strict":
        isValid = /^[a-zA-Z\s\-'\.]+$/.test(value) && value.length >= 2;
        break;
      case "tel_10":
        isValid = /^\d{10}$/.test(value);
        break;
      case "number_strict":
        isValid = /^\d+$/.test(value) && parseInt(value) > 0;
        break;
      case "textarea":
        isValid = value.length >= 5;
        break;
      case "select":
        isValid = value !== "";
        break;
      default:
        // For non-required, simple text fields (like on page 2)
        isValid = true;
        break;
    }
  }

  // Update UI
  if (isValid) {
    parentWrapper.classList.add("validated");
    parentWrapper.classList.remove("error");
  } else {
    // Only show error if the field has been touched
    if (input.classList.contains("is-dirty")) {
      parentWrapper.classList.add("error");
      parentWrapper.classList.remove("validated");
    }
  }
  return isValid;
}

/**
 * Validates all visible fields on the current page
 */
function validatePage(pageNumber) {
  let pageElement = document.getElementById(`page${pageNumber}`);
  if (!pageElement) return false;

  let isPageValid = true;
  let errorMessages = [];
  let firstErrorElement = null;

  const fieldsToValidate = pageElement.querySelectorAll(
    ".form-group input, .form-group select, .form-group textarea"
  );

  fieldsToValidate.forEach((field) => {
    // Mark all as "dirty" to force validation
    field.classList.add("is-dirty");

    if (!validateField(field)) {
      isPageValid = false;
      const label = pageElement.querySelector(`label[for='${field.id}']`);
      const errorMsg = field.getAttribute("data-error-message");

      let msg = `<b>${
        label ? label.textContent.replace(":", "") : field.name
      }:</b> ${errorMsg}`;
      errorMessages.push(`<li>${msg}</li>`);

      if (!firstErrorElement) firstErrorElement = field;
    }
  });

  // --- Page 2 (Educational) is now OPTIONAL, so we don't run checks for it ---

  // --- Page 3 Specific Validation ---
  if (pageNumber === 3) {
    // 1. Check radios
    const requiredRadios = ["ex_servicemen", "disabled", "parent_vmrf"];
    requiredRadios.forEach((name) => {
      if (!document.querySelector(`input[name="${name}"]:checked`)) {
        isPageValid = false;
        let label = name
          .replace(/_/g, " ")
          .replace(/\b\w/g, (l) => l.toUpperCase());
        errorMessages.push(
          `<li><b>${label}:</b> Please select an option.</li>`
        );
        if (!firstErrorElement)
          firstErrorElement = document.querySelector(`input[name="${name}"]`);
      }
    });

    // 2. Check conditional uploads (only if 'Yes' is checked)
    const checkUpload = (radioYesId, inputName, errorMsg) => {
      if (document.getElementById(radioYesId)?.checked) {
        const fileInput = document.querySelector(`input[name="${inputName}"]`);
        if (fileInput && fileInput.files.length === 0) {
          isPageValid = false;
          errorMessages.push(
            `<li><b>${errorMsg}:</b> Proof document is missing.</li>`
          );
          if (!firstErrorElement)
            firstErrorElement = fileInput.closest(".upload-area");
        }
      }
    };
    checkUpload("disabled_yes", "disability_proof", "Disability Proof");
    checkUpload("parent_vmrf_yes", "parent_vmrf_proof", "VMRF Proof");

    // --- ⭐️ FIX: Signature Validation (Any one is fine) ---
    const sigType = document.getElementById("signature_type").value;
    const sigData = document.getElementById("signature_data");
    const sigFileInput = document.querySelector('input[name="signature_file"]');
    let signatureProvided = false;

    if (sigType === "draw") {
      if (signaturePad && !signaturePad.isEmpty()) {
        sigData.value = signaturePad.toDataURL(); // Save data
        signatureProvided = true;
      }
    } else if (sigType === "type") {
      const typed = document.getElementById("typed-signature").value.trim();
      if (typed) {
        sigData.value = typed; // Save data
        signatureProvided = true;
      }
    } else if (sigType === "upload") {
      if (sigFileInput && sigFileInput.files.length > 0) {
        signatureProvided = true;
      }
    }

    if (!signatureProvided) {
      isPageValid = false;
      errorMessages.push(
        `<li><b>Signature:</b> Please provide a signature using one of the three methods (Draw, Type, or Upload).</li>`
      );
      if (!firstErrorElement)
        firstErrorElement = document.getElementById("signature-pad"); // Just point to the first tab
    }
    // --- End of Signature Fix ---
  }

  if (!isPageValid) {
    const header = `<h3>⚠️ Missing Information!</h3><p>Please complete the following required fields:</p><ul>`;
    const footer = "</ul>";
    const message = header + errorMessages.join("") + footer;
    showModal(message);

    if (firstErrorElement) {
      firstErrorElement.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }
  return isPageValid;
}

// =================================================================
// 6. INITIALIZATION (DOM CONTENT LOADED)
// =================================================================

document.addEventListener("DOMContentLoaded", () => {
  // 1. Assign global elements
  pages = document.querySelectorAll(".page");
  navTabs = document.querySelectorAll(".nav-tab");
  prevBtn = document.getElementById("prevBtn");
  nextBtn = document.getElementById("nextBtn");
  submitBtn = document.getElementById("submitBtn");
  modal = document.getElementById("errorModal");
  errorMessage = document.getElementById("errorMessage");
  const mainForm = document.getElementById("main-application-form");

  // 2. Initial Page Load
  showPage(1);

  // 3. Attach Nav Tab click handlers
  navTabs.forEach((tab) => {
    tab.addEventListener("click", (e) => {
      const targetPage = parseInt(e.target.getAttribute("data-page"));
      if (targetPage > currentPage && !validatePage(currentPage)) {
        return;
      }
      showPage(targetPage);
    });
  });

  // 4. Attach Main Form Submit Handler
  if (mainForm) {
    mainForm.addEventListener("submit", (e) => {
      // Mark all fields as dirty to check everything on submit
      mainForm
        .querySelectorAll(
          ".form-group input, .form-group select, .form-group textarea"
        )
        .forEach((field) => field.classList.add("is-dirty"));

      if (!validatePage(1) || !validatePage(2) || !validatePage(3)) {
        e.preventDefault(); // Stop submission
      }
    });
  }

  // 5. Attach Real-Time Validation Listeners
  const validatedFields = document.querySelectorAll(
    ".form-group input, .form-group select, .form-group textarea"
  );
  validatedFields.forEach((field) => {
    const markDirty = () => field.classList.add("is-dirty");
    field.addEventListener("input", markDirty);
    field.addEventListener("change", markDirty);

    // Validate on blur (when user clicks away)
    field.addEventListener("blur", () => validateField(field));

    // For selects, also validate immediately on change
    if (field.tagName === "SELECT") {
      field.addEventListener("change", () => validateField(field));
    }
  });

  // 6. Dynamic Dropdown Listeners
  const institutionSelect = document.getElementById("institution_name");
  if (institutionSelect) {
    institutionSelect.addEventListener("change", updatePrograms);
  }
  const yearOfStudySelect = document.getElementById("year_of_study");
  if (yearOfStudySelect) {
    yearOfStudySelect.addEventListener("change", updateSemesters);
  }
  updateSemesters(); // Run on load

  // 7. Conditional Section Toggling (Page 3)
  toggleSection("ex_servicemen", "ex_servicemen_section");
  toggleSection("disabled", "disabled_section");
  toggleSection("parent_vmrf", "parent_vmrf_section");

  // 8. File Upload Listeners
  const uploadAreas = document.querySelectorAll(".upload-area");

  const showUploadPreview = (uploadArea, file) => {
    const oldPreview = uploadArea.querySelector(".file-preview");
    if (oldPreview) oldPreview.remove();
    const preview = document.createElement("div");
    preview.classList.add("file-preview");
    preview.innerHTML = `<i class="fas fa-file-alt"></i> <span>${file.name}</span>`;

    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.textContent = "×";
    removeBtn.className = "file-preview-remove";
    removeBtn.style.cssText =
      "position:absolute; top:-5px; right:-5px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; font-weight:bold;";

    removeBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      const fileInput = uploadArea.querySelector("input[type='file']");
      fileInput.value = "";
      preview.remove();
      validatePage(currentPage); // Re-validate
    });

    preview.appendChild(removeBtn);
    uploadArea.appendChild(preview);
    validatePage(currentPage); // Re-validate
  };

  uploadAreas.forEach((uploadArea) => {
    const fileInput = uploadArea.querySelector("input[type='file']");
    if (!fileInput) return;
    uploadArea.addEventListener("click", (e) => {
      if (!e.target.closest(".file-preview-remove")) {
        fileInput.click();
      }
    });
    uploadArea.addEventListener("dragover", (e) => {
      e.preventDefault();
      uploadArea.classList.add("dragover");
    });
    uploadArea.addEventListener("dragleave", () =>
      uploadArea.classList.remove("dragover")
    );
    uploadArea.addEventListener("drop", (e) => {
      e.preventDefault();
      uploadArea.classList.remove("dragover");
      if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        showUploadPreview(uploadArea, fileInput.files[0]);
      }
    });
    fileInput.addEventListener("change", () => {
      if (fileInput.files.length > 0) {
        showUploadPreview(uploadArea, fileInput.files[0]);
      }
    });
  });

  // 9. Close modal on outside click
  if (modal) {
    modal.addEventListener("click", (event) => {
      if (event.target === modal) closeModal();
    });
  }

  // --- 10. Signature Pad Logic ---
  const sigPadCanvas = document.getElementById("signature-pad");
  if (sigPadCanvas) {
    signaturePad = new SignaturePad(sigPadCanvas);

    document
      .getElementById("clear")
      .addEventListener("click", () => signaturePad.clear());

    const sigTabs = document.querySelectorAll(".tab");
    sigTabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        sigTabs.forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");
        document
          .querySelectorAll(".tab-content")
          .forEach((tc) => (tc.style.display = "none"));
        document.getElementById("tab-" + tab.dataset.target).style.display =
          "block";
        document.getElementById("signature_type").value = tab.dataset.target;
      });
    });

    document
      .getElementById("typed-signature")
      .addEventListener("input", (e) => {
        document.getElementById("typed-preview").innerText =
          e.target.value || "Your signature preview";
      });
  }
});

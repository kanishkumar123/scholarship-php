<?php
// --- 1. ALL PHP LOGIC GOES FIRST ---
session_start();
include("../config.php"); 

// --- 2. Security & Login Checks ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 3. Get Application ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}
$application_id = mysqli_real_escape_string($conn, $_GET['id']);

// --- 4. Fetch Main Application Data ---
$app_query = "
    SELECT a.*, ss.id AS ss_student_id, ss.scholarship_id AS ss_scholarship_id, ss.application_no, ss.name, ss.dob, ss.academic_year
    FROM applications a
    JOIN scholarship_students ss ON a.student_id = ss.id 
    WHERE a.id = '$application_id'
    LIMIT 1
";
$application = mysqli_fetch_assoc(mysqli_query($conn, $app_query));

if (!$application) {
    echo "<script>alert('Error: Application record not found.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Extract data for pre-filling
$student_id = $application['ss_student_id'];
$scholarship_id = $application['ss_scholarship_id'];
$academic_year_id = $application['academic_year']; 

// --- 5. Fetch Scholarship & Academic Year Names ---
$info_query = "
    SELECT s.name AS scholarship_name, ay.year_range AS academic_year_name
    FROM scholarships s
    LEFT JOIN academic_years ay ON '$academic_year_id' = ay.year_range 
    WHERE s.id = '$scholarship_id'
";
$info_result = mysqli_fetch_assoc(mysqli_query($conn, $info_query));
$scholarship_name = $info_result['scholarship_name'] ?? 'N/A';
$academic_year_name = $info_result['academic_year_name'] ?? htmlspecialchars($academic_year_id);

// --- 6. Fetch College-Program Mappings ---
$mapping_query = mysqli_query($conn, "
    SELECT c.id AS college_id, c.name AS college_name, p.id AS program_id, p.name AS program_name
    FROM college_program_mapping m
    JOIN colleges c ON m.college_id = c.id
    JOIN programs p ON m.program_id = p.id
    ORDER BY c.name ASC, p.name ASC
");
$college_programs = [];
while ($row = mysqli_fetch_assoc($mapping_query)) {
    $college_programs[$row['college_id']]['name'] = $row['college_name'];
    $college_programs[$row['college_id']]['programs'][] = [
        'id' => $row['program_id'],
        'name' => $row['program_name']
    ];
}

// --- 7. Fetch Uploaded Files ---
$files_query = mysqli_query($conn, "SELECT file_type, file_path FROM application_files WHERE application_id = '$application_id'");
$files = [];
while ($file_row = mysqli_fetch_assoc($files_query)) {
    $files[$file_row['file_type']] = $file_row['file_path'];
}

// --- 8. Helper functions ---
function get_value($field, $data) {
    return htmlspecialchars($data[$field] ?? '');
}
function check_select($field, $data, $value) {
    return (isset($data[$field]) && $data[$field] == $value) ? 'selected' : '';
}
function check_radio($field, $data, $value) {
    return (isset($data[$field]) && $data[$field] == $value) ? 'checked' : '';
}
$sports_levels_applied = isset($application['sports_level']) ? explode(',', $application['sports_level']) : [];

// --- 9. Set Page Variables ---
$currentPage = 'applications';
$pageTitle = "Edit Application";
$pageSubtitle = "Editing #" . htmlspecialchars($application['application_no']);

// --- 10. Include Header (HTML starts here) ---
include('header.php'); 
?>

<div class="container" style="max-width: 1000px; margin: 0 auto;">
    
    <?php
    // Display success/error messages
    if (isset($_SESSION['message'])) {
        echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
        unset($_SESSION['message']);
    }
    ?>

    <div class="card box"> 
        <div class="info-bar">
            <p><b>Scholarship:</b> <?= htmlspecialchars($scholarship_name) ?></p>
            <p><b>Applicant:</b> <?= htmlspecialchars($application['name']) ?></p>
            <p><b>App No:</b> <?= htmlspecialchars($application['application_no']) ?></p>
            <p><b>DOB:</b> <?= !empty($application['dob']) ? date("d-m-Y", strtotime($application['dob'])) : '' ?></p>
            <p><b>Academic Year:</b> <?= htmlspecialchars($academic_year_name) ?></p>
        </div>

        <div class="progress-bar">
            <div class="progress-bar-fill" id="formProgressBar"></div>
        </div>

        <div class="error-message" id="validationAlert" style="display: none;">
            Please correct the highlighted fields before proceeding.
        </div>

        <div class="top-nav">
            <button type="button" class="nav-tab active" data-page="1">Personal & Academic</button>
            <button type="button" class="nav-tab" data-page="2">Educational Qualification</button>
            <button type="button" class="nav-tab" data-page="3">Special Claims & Other</button>
        </div>

        <form action="update_application.php" method="post" enctype="multipart/form-data" onsubmit="return validatePage(3)">
            <input type="hidden" name="application_id" value="<?= htmlspecialchars($application_id) ?>">
            <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
            <input type="hidden" name="scholarship_id" value="<?= htmlspecialchars($scholarship_id) ?>">
            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year_id) ?>">

            <div id="page1" class="page active">
                <h3 class="page-header">Personal & Academic Details</h3>

                <div class="filter-group">
                    <label for="institution_name">Institution Name:</label>
                    <select name="institution_name" id="institution_name">
                        <option value="">-- SELECT INSTITUTION --</option>
                        <?php foreach ($college_programs as $college_id_key => $data): ?>
                            <option value="<?= $college_id_key ?>" <?= check_select('institution_name', $application, $college_id_key) ?>>
                                <?= htmlspecialchars($data['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="course">Course / Programme of Study:</label>
                    <select name="course" id="course">
                        <option value="">-- SELECT COURSE --</option>
                        <?php
                        $selected_college_id = get_value('institution_name', $application);
                        $selected_course_id = get_value('course', $application);
                        if ($selected_college_id && isset($college_programs[$selected_college_id])) {
                            foreach ($college_programs[$selected_college_id]['programs'] as $program) {
                                $selected = ($program['id'] == $selected_course_id) ? 'selected' : '';
                                echo "<option value='{$program['id']}' $selected>".htmlspecialchars($program['name'])."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group-inline">
                    <div class="filter-group">
                        <label for="year_of_study">Year of Study:</label>
                        <select name="year_of_study" id="year_of_study">
                            <option value="">-- Select Year --</option>
                            <?php for ($y=1; $y<=5; $y++): ?>
                                <option value="<?= $y ?>" <?= check_select('year_of_study', $application, $y) ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="semester">Semester:</label>
                        <select name="semester" id="semester">
                            <option value="">-- Select Semester --</option>
                            <?php
                            $current_semester = get_value('semester', $application);
                            if ($current_semester): ?>
                                <option value="<?= $current_semester ?>" selected><?= $current_semester ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <?php
                $fields_page1 = [
                    ['father_name','Father\'s Name'],
                    ['mother_name','Mother\'s Name'],
                    ['gender','Gender',['Male','Female','Other']],
                    ['community','Community',['OC','BC','OBC','MBC','DNC','SC','ST']],
                    ['caste','Caste'],
                    ['family_income','Annual Family Income (₹)'],
                    ['address','Permanent Address', 'textarea'],
                    ['phone_std','Phone No. with STD code'],
                    ['mobile','Mobile Number'],
                    ['email','Email ID']
                ];
                
                foreach ($fields_page1 as $f):
                    $type = $f[2] ?? 'text';
                    $field_name = $f[0];
                    $field_value = get_value($field_name, $application);
                    echo '<div class="filter-group">'; // Use filter-group for consistent styling
                    echo "<label for='$field_name'>".$f[1].":</label>";
                    if ($type==='textarea') {
                        echo "<textarea name='$field_name' id='$field_name' rows='3'>$field_value</textarea>";
                    } elseif (is_array($type)) {
                        echo "<select name='$field_name' id='$field_name'>";
                        echo "<option value=''>-- Select --</option>";
                        foreach($type as $opt) {
                            $selected = check_select($field_name, $application, $opt);
                            echo "<option value='$opt' $selected>$opt</option>";
                        }
                        echo "</select>";
                    } else {
                        echo "<input type='text' name='$field_name' id='$field_name' value='$field_value'>";
                    }
                    echo '</div>';
                endforeach;
                ?>
            </div>

            <div id="page2" class="page">
                <h3 class="page-header">Educational Qualification</h3>

                <div class="form-section">
                    <h4>Previous Examination Details</h4>
                    <table class="styled-table">
                        <thead>
                        <tr>
                            <th>Examination</th>
                            <th>Year of Passing & Reg. No.</th>
                            <th>Board / University</th>
                            <th>Class / Grade</th>
                            <th>Marks (%)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php for($i=1;$i<=2;$i++): ?>
                        <tr>
                            <td><input type="text" name="exam_name_<?= $i ?>" value="<?= get_value("exam_name_$i", $application) ?>"></td>
                            <td><input type="text" name="exam_year_reg_<?= $i ?>" value="<?= get_value("exam_year_reg_$i", $application) ?>"></td>
                            <td><input type="text" name="exam_board_<?= $i ?>" value="<?= get_value("exam_board_$i", $application) ?>"></td>
                            <td><input type="text" name="exam_class_<?= $i ?>" value="<?= get_value("exam_class_$i", $application) ?>"></td>
                            <td><input type="text" name="exam_marks_<?= $i ?>" value="<?= get_value("exam_marks_$i", $application) ?>"></td>
                        </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-section">
                    <h4>Lateral Entry Students</h4>
                    <div class="form-group">
                        <label for="lateral_exam_name">Exam Passed (Degree/Diploma):</label>
                        <input type="text" name="lateral_exam_name" id="lateral_exam_name" value="<?= get_value('lateral_exam_name', $application) ?>">
                    </div>
                    <div class="form-group">
                        <label for="lateral_exam_year_reg">Month & Year of Passing with Reg. No.:</label>
                        <input type="text" name="lateral_exam_year_reg" id="lateral_exam_year_reg" value="<?= get_value('lateral_exam_year_reg', $application) ?>">
                    </div>
                    <div class="form-group">
                        <label for="lateral_percentage">Percentage Obtained:</label>
                        <input type="text" name="lateral_percentage" id="lateral_percentage" value="<?= get_value('lateral_percentage', $application) ?>">
                    </div>
                </div>
            </div>

            <div id="page3" class="page">
                <h3 class="page-header">Special Claims & Uploads</h3>

                <div class="form-section">
                    <h4>Sports (attach proof)</h4>
                    <div class="checkbox-group" id="sports_checkbox_group">
                        <label><input type="checkbox" name="sports_level[]" value="District" <?= in_array('District', $sports_levels_applied) ? 'checked' : '' ?>> District</label>
                        <label><input type="checkbox" name="sports_level[]" value="State" <?= in_array('State', $sports_levels_applied) ? 'checked' : '' ?>> State</label>
                        <label><input type="checkbox" name="sports_level[]" value="National" <?= in_array('National', $sports_levels_applied) ? 'checked' : '' ?>> National</label>
                        <label><input type="checkbox" name="sports_level[]" value="International" <?= in_array('International', $sports_levels_applied) ? 'checked' : '' ?>> International</label>
                    </div>
                    <div id="sports_section" class="conditional-section" style="display:none;">
                        <div class="upload-area">
                            <i class="fa-solid fa-cloud-arrow-up icon"></i>
                            <strong>Click to upload or drag & drop files</strong>
                            <div class="instructions">(PDF, JPEG, PNG)</div>
                            <input type="file" name="sports_proof" accept=".pdf,.jpg,.jpeg,.png">
                            
                            <?php if (!empty($files['sports'])): ?>
                                <a href="../<?= htmlspecialchars($files['sports']) ?>" target="_blank" class="file-link" style="display: none;">Sports Proof (Current)</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h4>Ex-Servicemen (attach proof)</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="ex_servicemen" value="Yes" onclick="toggleSection('ex_servicemen_section', true)" <?= check_radio('ex_servicemen', $application, 'Yes') ?>> Yes</label>
                        <label><input type="radio" name="ex_servicemen" value="No" onclick="toggleSection('ex_servicemen_section', false)" <?= check_radio('ex_servicemen', $application, 'No') ?>> No</label>
                    </div>
                    <div id="ex_servicemen_section" class="conditional-section" style="display:<?= check_radio('ex_servicemen', $application, 'Yes') ? 'block' : 'none' ?>;">
                        <div class="upload-area">
                            <i class="fa-solid fa-cloud-arrow-up icon"></i>
                            <strong>Click to upload or drag & drop files</strong>
                            <div class="instructions">(PDF, JPEG, PNG)</div>
                            <input type="file" name="ex_servicemen_proof" accept=".pdf,.jpg,.jpeg,.png">
                            
                            <?php if (!empty($files['ex_servicemen'])): ?>
                                <a href="../<?= htmlspecialchars($files['ex_servicemen']) ?>" target="_blank" class="file-link" style="display: none;">Ex-Servicemen Proof (Current)</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Disabled Person (attach proof)</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="disabled" value="Yes" onclick="toggleSection('disabled_section', true)" <?= check_radio('disabled', $application, 'Yes') ?>> Yes</label>
                        <label><input type="radio" name="disabled" value="No" onclick="toggleSection('disabled_section', false)" <?= check_radio('disabled', $application, 'No') ?>> No</label>
                    </div>
                    <div id="disabled_section" class="conditional-section" style="display:<?= check_radio('disabled', $application, 'Yes') ? 'block' : 'none' ?>;">
                        <div class="form-group">
                            <label for="disability_category">Category:</label>
                            <input type="text" name="disability_category" id="disability_category" value="<?= get_value('disability_category', $application) ?>">
                        </div>
                        <div class="upload-area">
                            <i class="fa-solid fa-cloud-arrow-up icon"></i>
                            <strong>Click to upload or drag & drop files</strong>
                            <div class="instructions">(PDF, JPEG, PNG)</div>
                            <input type="file" name="disability_proof" accept=".pdf,.jpg,.jpeg,.png">
                            
                            <?php if (!empty($files['disabled'])): ?>
                                <a href="../<?= htmlspecialchars($files['disabled']) ?>" target="_blank" class="file-link" style="display: none;">Disability Proof (Current)</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Parent working in VMRF-DU?</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="parent_vmrf" value="Yes" onclick="toggleSection('parent_vmrf_section', true)" <?= check_radio('parent_vmrf', $application, 'Yes') ?>> Yes</label>
                        <label><input type="radio" name="parent_vmrf" value="No" onclick="toggleSection('parent_vmrf_section', false)" <?= check_radio('parent_vmrf', $application, 'No') ?>> No</label>
                    </div>
                    <div id="parent_vmrf_section" class="conditional-section" style="display:<?= check_radio('parent_vmrf', $application, 'Yes') ? 'block' : 'none' ?>;">
                        <div class="form-group">
                            <label for="parent_vmrf_details">Furnish details:</label>
                            <input type="text" name="parent_vmrf_details" id="parent_vmrf_details" value="<?= get_value('parent_vmrf_details', $application) ?>">
                        </div>
                        <div class="upload-area">
                            <i class="fa-solid fa-cloud-arrow-up icon"></i>
                            <strong>Click to upload or drag & drop files</strong>
                            <div class="instructions">(PDF, JPEG, PNG)</div>
                            <input type="file" name="parent_vmrf_proof" accept=".pdf,.jpg,.jpeg,.png">
                            
                            <?php if (!empty($files['parent_vmrf'])): ?>
                                <a href="../<?= htmlspecialchars($files['parent_vmrf']) ?>" target="_blank" class="file-link" style="display: none;">Parent VMRF Proof (Current)</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="navigation-buttons">
                <button type="button" id="prevBtn" class="nav-btn prev" onclick="navigate(-1)">Previous</button>
                <button type="button" id="nextBtn" class="nav-btn next" onclick="navigate(1)">Next</button>
                <button type="submit" id="submitBtn" class="nav-btn submit">Save & Update Application</button>
            </div>
        </form>
    </div>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script>
// --- Global variables ---
const collegePrograms = <?= json_encode($college_programs) ?>;
const preSelectedCollegeId = '<?= get_value('institution_name', $application) ?>';
const preSelectedCourseId = '<?= get_value('course', $application) ?>';
let currentPage = 1;
const totalPages = 3;

// --- Global Functions (FIX: Moved to global scope so onclick can find them) ---
let progressBarFill; // Will be defined after DOM loads

function updateProgressBar() {
    if (!progressBarFill) return; // Don't run if element not found
    const progressValue = (currentPage - 1) / (totalPages - 1); 
    const width = progressValue * 100;
    progressBarFill.style.width = width + '%';
}

function showPage(pageNumber) {
    currentPage = pageNumber;
    
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    const activePage = document.getElementById(`page${currentPage}`);
    if (activePage) activePage.classList.add('active');

    const activeTab = document.querySelector(`.nav-tab[data-page="${currentPage}"]`);
    if (activeTab) activeTab.classList.add('active');

    document.getElementById('prevBtn').style.display = currentPage > 1 ? 'inline-block' : 'none';
    document.getElementById('nextBtn').style.display = currentPage < totalPages ? 'inline-block' : 'none';
    document.getElementById('submitBtn').style.display = currentPage === totalPages ? 'inline-block' : 'none';
    
    updateProgressBar();
}

function validatePage(page) {
    // We can add real validation here later if needed
    return true; 
}

function navigate(direction) {
    if (direction === 1) { // Moving forward
        if (validatePage(currentPage) && currentPage < totalPages) {
            showPage(currentPage + 1);
        }
    } else if (direction === -1) { // Moving backward
        if (currentPage > 1) {
            showPage(currentPage - 1);
        }
    }
}

function showModal(message) {
    const modal = document.getElementById('errorModal');
    if(modal) {
        document.getElementById('errorMessage').textContent = message;
        modal.style.display = 'block';
    }
}
function closeModal() {
    const modal = document.getElementById('errorModal');
    if(modal) {
        modal.style.display = 'none';
    }
}

function toggleSection(sectionId, show) {
    const el = document.getElementById(sectionId);
    if (el) {
        el.style.display = show ? 'block' : 'none';
    }
}

// --- Main execution block that runs after the page is loaded ---
document.addEventListener("DOMContentLoaded", () => {
    
    // --- Define elements *after* DOM is loaded ---
    progressBarFill = document.getElementById('formProgressBar');
    const institutionSelect = document.getElementById('institution_name');
    const courseSelect = document.getElementById('course');
    const yearOfStudy = document.getElementById('year_of_study');
    const semesterSelect = document.getElementById('semester');
    const navTabs = document.querySelectorAll('.nav-tab');
    const uploadAreas = document.querySelectorAll(".upload-area");
    
    // (FIX) New elements for sports section
    const sportsCheckboxGroup = document.getElementById('sports_checkbox_group');
    const sportsCheckboxes = sportsCheckboxGroup ? sportsCheckboxGroup.querySelectorAll('input[type="checkbox"]') : [];

    // Initial UI setup
    showPage(currentPage); // Show page 1
    
    // --- College/Program Dropdown Logic ---
    function updateCourseDropdown() {
        const selectedCollegeId = institutionSelect.value;
        courseSelect.innerHTML = '<option value="">-- SELECT COURSE --</option>';

        if (collegePrograms[selectedCollegeId]) {
            collegePrograms[selectedCollegeId].programs.forEach(program => {
                const option = document.createElement('option');
                option.value = program.id;
                option.textContent = program.name;
                if (program.id.toString() === preSelectedCourseId.toString()) {
                    option.selected = true;
                }
                courseSelect.appendChild(option);
            });
        }
    }
    if(institutionSelect) institutionSelect.addEventListener('change', updateCourseDropdown);
    if (preSelectedCollegeId) {
        updateCourseDropdown();
    }
    
    // --- (FIX) Tab Navigation Event Listeners ---
    navTabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            const page = parseInt(e.target.dataset.page);
            if (validatePage(currentPage)) {
                showPage(page);
            }
        });
    });

    // --- (FIX) Sports Conditional Logic ---
    function checkSportsSection() {
        let anyChecked = false;
        sportsCheckboxes.forEach(box => {
            if (box.checked) {
                anyChecked = true;
            }
        });
        toggleSection('sports_section', anyChecked);
    }
    
    sportsCheckboxes.forEach(box => {
        box.addEventListener('change', checkSportsSection);
    });
    // Run once on load to set initial state
    checkSportsSection();

    // --- Pre-fill Other Conditional Sections ---
    const exServicemen = document.querySelector('input[name="ex_servicemen"]:checked');
    if (exServicemen && exServicemen.value === 'Yes') {
        toggleSection('ex_servicemen_section', true);
    }
    const disabled = document.querySelector('input[name="disabled"]:checked');
    if (disabled && disabled.value === 'Yes') {
        toggleSection('disabled_section', true);
    }
    const parentVmrf = document.querySelector('input[name="parent_vmrf"]:checked');
    if (parentVmrf && parentVmrf.value === 'Yes') {
        toggleSection('parent_vmrf_section', true);
    }

    // --- Dynamic Upload Area Logic ---
    function showPreview(uploadArea, file, inputName) {
        const existingFileLink = uploadArea.querySelector(".file-link");
        const fileHref = existingFileLink ? existingFileLink.href : '#';
        
        uploadArea.querySelectorAll(".file-preview, .file-link").forEach(el => el.remove());

        const preview = document.createElement("div");
        preview.className = "file-preview";
        
        const icon = document.createElement("i");
        if (file.name.toLowerCase().includes('pdf')) {
            icon.className = "fa-solid fa-file-pdf";
        } else if (file.name.toLowerCase().match(/\.(jpg|jpeg|png|gif)$/)) {
            icon.className = "fa-solid fa-file-image";
        } else {
            icon.className = "fa-solid fa-file-invoice";
        }
        preview.appendChild(icon);

        if (file.type === 'prefilled') {
            const link = document.createElement("a");
            link.href = fileHref;
            link.target = "_blank";
            link.textContent = file.name;
            preview.appendChild(link);
        } else {
            const span = document.createElement("span");
            span.textContent = file.name;
            preview.appendChild(span);
        }

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.innerHTML = "&times;";
        removeBtn.className = "file-remove-btn";

        removeBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            const fileInput = uploadArea.querySelector("input[type='file']");
            fileInput.value = ""; 
            preview.remove(); 

            if (file.type === 'prefilled') {
                let hiddenRemove = document.createElement("input");
                hiddenRemove.type = "hidden";
                hiddenRemove.name = inputName + "_clear_flag";
                hiddenRemove.value = "1";
                uploadArea.appendChild(hiddenRemove);
            }
        });

        preview.appendChild(removeBtn);
        uploadArea.appendChild(preview);
    }

    uploadAreas.forEach(uploadArea => {
        const fileInput = uploadArea.querySelector("input[type='file']");
        if (!fileInput) return;

        const existingFileLink = uploadArea.querySelector(".file-link");
        if (existingFileLink) {
            showPreview(uploadArea, { name: existingFileLink.textContent, type: 'prefilled' }, fileInput.name);
        }

        uploadArea.addEventListener("click", (e) => {
            if (!e.target.closest('.file-preview')) {
                fileInput.click();
            }
        });
        uploadArea.addEventListener("dragover", e => {
            e.preventDefault();
            uploadArea.classList.add("dragover");
        });
        uploadArea.addEventListener("dragleave", () => {
            uploadArea.classList.remove("dragover");
        });
        uploadArea.addEventListener("drop", e => {
            e.preventDefault();
            uploadArea.classList.remove("dragover");
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                showPreview(uploadArea, fileInput.files[0], fileInput.name);
            }
        });
        fileInput.addEventListener("change", () => {
            if (fileInput.files.length > 0) {
                showPreview(uploadArea, fileInput.files[0], fileInput.name);
            }
        });
    });
    
    // --- Semester Dropdown Logic ---
    const updateSemesters = () => {
        const year = parseInt(yearOfStudy.value);
        const prefilledSemester = '<?= get_value('semester', $application) ?>';

        semesterSelect.innerHTML = '<option value="">-- Select Semester --</option>';

        if (year > 0) {
            const startSem = (year - 1) * 2 + 1;
            const endSem = year * 2;

            for (let s = startSem; s <= endSem; s++) {
                const option = document.createElement('option');
                option.value = s;
                option.textContent = s;
                if (s.toString() === prefilledSemester) {
                    option.selected = true;
                }
                semesterSelect.appendChild(option);
            }
        } else if (prefilledSemester) {
            const option = document.createElement('option');
            option.value = prefilledSemester;
            option.textContent = prefilledSemester;
            option.selected = true;
            semesterSelect.appendChild(option);
        }
    };
    if(yearOfStudy) yearOfStudy.addEventListener('change', updateSemesters);
    updateSemesters(); // Initial run
});
</script>
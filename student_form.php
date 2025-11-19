<?php
// ==========================================================
// 1. AUTHENTICATION & SESSION SETUP
// ==========================================================
session_start();

if (!isset($_SESSION['student_id'], $_SESSION['scholarship_id'])) {
    header("Location: index.php"); // Redirect to your login page
    exit;
}

$student_id = $_SESSION['student_id'];
$scholarship_id = $_SESSION['scholarship_id'];

// 2. CONFIGURATION & DB CONNECTION
include("config.php");

if (mysqli_connect_errno()) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ==========================================================
// 3. DATA FETCHING
// ==========================================================
$query = "
    SELECT ss.*, s.name AS scholarship_name, ay.year_range AS academic_year_name
    FROM scholarship_students ss
    JOIN scholarships s ON ss.scholarship_id = s.id
    LEFT JOIN academic_years ay ON ss.academic_year = ay.id
    WHERE ss.id = '$student_id'
";
$student_result = mysqli_query($conn, $query);

if (!$student_result) {
    die("FATAL SQL QUERY ERROR: " . mysqli_error($conn));
}
$student = mysqli_fetch_assoc($student_result);
if (!$student) {
    die("Error: Student record not found for ID: " . htmlspecialchars($student_id));
}

// Fetch college-program mappings
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

// ==========================================================
// 4. PREVENT DUPLICATE SUBMISSION
// ==========================================================
$check_query = "SELECT id FROM applications WHERE student_id='$student_id' AND scholarship_id='$scholarship_id' LIMIT 1";
$existingApp_result = mysqli_query($conn, $check_query);
$existingApp = mysqli_fetch_assoc($existingApp_result);

if ($existingApp) {
    $_SESSION['application_id'] = $existingApp['id'];
    header("Location: confirmation.php");
    exit;
}
// ==========================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Scholarship Application Form</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="application_style.css">
<link rel="stylesheet" href="pad.css">
</head>
<body> 
<a href="logout.php" class="logout-button">
    <i class="fas fa-sign-out-alt"></i> Logout
</a>
<div id="gradient-switcher" class="gradient-switcher">
    <div id="gradient-circle-btn" class="circle"></div> 
</div>
<div class="container">
<h2 class="card-header">
    <span class="l">S</span><span class="l">C</span><span class="l">H</span><span class="l">O</span><span class="l">L</span><span class="l">A</span><span class="l">R</span><span class="l">S</span><span class="l">H</span><span class="l">I</span><span class="l">P</span>
    <span class="l">A</span><span class="l">P</span><span class="l">P</span><span class="l">L</span><span class="l">I</span><span class="l">C</span><span class="l">A</span><span class="l">T</span><span class="l">I</span><span class="l">O</span><span class="l">N</span> 
    <span class="l">F</span><span class="l">O</span><span class="l">R</span><span class="l">M</span>
</h2>
    <div class="card">
        
        <div class="info-bar">
            <div class="info-item full-width-name">
                <div class="label">Student Name:</div>
                <div class="value"><?= htmlspecialchars($student['name'] ?? 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="label">App No:</div>
                <div class="value"><?= htmlspecialchars($student['application_no']) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Academic Year:</div>
                <div class="value"><?= htmlspecialchars($student['academic_year_name'] ?? (string)$student['academic_year']) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Scholarship:</div>
                <div class="value"><?= htmlspecialchars($student['scholarship_name']) ?></div>
            </div>
        </div>

        <div class="top-nav">
            <button type="button" class="nav-tab active" data-page="1">1. Personal & Academic</button>
            <button type="button" class="nav-tab" data-page="2">2. Educational Qualification</button>
            <button type="button" class="nav-tab" data-page="3">3. Special Claims & Documents</button>
        </div>

        <form action="submit_application.php" method="post" enctype="multipart/form-data" id="main-application-form">
            <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
            <input type="hidden" name="scholarship_id" value="<?= htmlspecialchars($scholarship_id) ?>">
            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($student['academic_year']) ?>">
            
            <input type="hidden" name="signature_type" id="signature_type" value="draw">
            <input type="hidden" name="signature_data" id="signature_data">

                <div id="page1" class="page active">
                <h3 class="page-header"><i class="fas fa-user-check"></i> Personal & Academic Details</h3>

                <div class="form-group">
                    <select name="institution_name" id="institution_name" data-validate-type="select" data-error-message="Please select your institution." required>
                        <option value="">-- SELECT INSTITUTION --</option>
                        <?php foreach ($college_programs as $college_id => $data): ?>
                            <option value="<?= htmlspecialchars($college_id) ?>"><?= htmlspecialchars($data['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="institution_name">Institution Name:</label>
                    <div class="input-bg"></div>
                    <div class="validation-icon">
                        <i class="fas fa-check tick-mark"></i>
                        <i class="fas fa-times cross-mark" title=""></i>
                    </div>
                    <span class="tooltip-text">Please select your institution.</span>
                </div>

                <div class="form-group">
                    <select name="course" id="course" data-validate-type="select" data-error-message="Please select your course." required>
                        <option value="">-- SELECT COURSE --</option>
                    </select>
                    <label for="course">Course / Programme of Study:</label>
                    <div class="input-bg"></div>
                    <div class="validation-icon">
                        <i class="fas fa-check tick-mark"></i>
                        <i class="fas fa-times cross-mark" title=""></i>
                    </div>
                    <span class="tooltip-text">Please select your course.</span>
                </div>

                <div class="form-group-inline">
                    <div class="form-group">
                        <select name="year_of_study" id="year_of_study" data-validate-type="select" data-error-message="Please select your year of study." required>
                            <option value="">-- Select Year --</option>
                            <?php
                            $roman_years = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];
                            foreach ($roman_years as $num => $roman):
                            ?>
                                <option value="<?= $num ?>"><?= $roman ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="year_of_study">Year of Study:</label>
                        <div class="input-bg"></div>
                        <div class="validation-icon">
                            <i class="fas fa-check tick-mark"></i>
                            <i class="fas fa-times cross-mark" title=""></i>
                        </div>
                        <span class="tooltip-text">Please select your year of study.</span>
                    </div>
                    
                    <div class="form-group">
                        <select name="semester" id="semester" data-validate-type="select" data-error-message="Please select your current semester.">
                            <option value="">-</option>
                        </select>
                        <label for="semester">Semester:</label>
                        <div class="input-bg"></div>
                        <div class="validation-icon">
                            <i class="fas fa-check tick-mark"></i>
                            <i class="fas fa-times cross-mark" title=""></i>
                        </div>
                        <span class="tooltip-text">Please select your current semester.</span>
                    </div>
                </div>

                <?php
                // ⭐️ FIX: Added a 5th item (true/false) to mark as required
                $fields = [
                    // Field Name, Label, Type, Error Message, Is Required?
                    ['gender','Gender',['Male','Female','Other'], "This field is required.", true],
                    ['father_name','Father\'s Name', 'text_strict', 'Name must contain only letters.', true],
                    ['mother_name','Mother\'s Name', 'text_strict', 'Name must contain only letters.', true], 
                    ['community','Community',['OC','BC','OBC','MBC','DNC','SC','ST'], "This field is required.", true],
                    ['caste','Caste', 'text_strict', 'Caste must contain only letters.', false], // <-- FIX: Set to false
                    ['family_income','Annual Family Income (₹)', 'number_strict', 'Enter only numbers (no symbols or spaces).', true],
                    ['address','Permanent Address', 'textarea', "This field is required.", true],
                    // ['phone_std','Phone Number', 'tel_10', 'Enter exactly 10 digits (no country code).', true], // <-- FIX: Removed
                    ['mobile','Mobile Number', 'tel_10', 'Enter exactly 10 digits (no country code).', true],
                    ['email','Email ID', 'email', 'Enter a valid email address (e.g., user@domain.com).', true]
                ];

                foreach ($fields as $f):
                    $name = $f[0];
                    $label = $f[1];
                    $type = $f[2] ?? 'text';
                    $error_msg = $f[3] ?? "This field is required."; 
                    $is_required = $f[4] ?? false; // ⭐️ FIX: Get new required flag
                    $attribute_type_value = is_array($type) ? 'select' : $type;
                    
                    // ⭐️ FIX: Build required attribute string
                    $required_attr = $is_required ? 'required' : '';
                    
                    echo '<div class="form-group">';
                    
                    if ($type === 'textarea') {
                        $attributes = "name='{$name}' id='{$name}' data-validate-type='{$attribute_type_value}' data-error-message='{$error_msg}' placeholder='' $required_attr autocomplete='off'";
                        echo "<textarea {$attributes}></textarea>";
                    } elseif (is_array($type)) { 
                        $attributes = "name='{$name}' id='{$name}' data-validate-type='{$attribute_type_value}' data-error-message='{$error_msg}' $required_attr";
                        echo "<select {$attributes}>";
                        echo "<option value=''>-- Select --</option>";
                        foreach($type as $opt) echo "<option value='".htmlspecialchars($opt)."'>".htmlspecialchars($opt)."</option>";
                        echo "</select>";
                    } else { 
                        $input_html_type = ($type === 'email' ? 'email' : 'text');
                        if ($type === 'number_strict' || $type === 'tel_10') $input_html_type = 'text';
                        $attributes = "name='{$name}' id='{$name}' data-validate-type='{$attribute_type_value}' data-error-message='{$error_msg}' placeholder='' $required_attr autocomplete='off'"; 
                        echo "<input type='{$input_html_type}' {$attributes}>";
                    }

                    echo "<label for='{$name}'>{$label}</label>";
                    echo '<div class="input-bg"></div>';
                    echo '<div class="validation-icon">';
                    echo '<i class="fas fa-check tick-mark"></i>';
                    echo '<i class="fas fa-times cross-mark" title=""></i>';
                    echo '</div>';
                    echo '<span class="tooltip-text">'.$error_msg.'</span>';
                    echo '</div>';
                endforeach;
                ?>
            </div>
            
            <div id="page2" class="page">
                <h3 class="page-header"><i class="fas fa-graduation-cap"></i> Educational Qualification (Optional)</h3>
                
                <div class="form-section">
                    <h4>Previous Examination Details (Last Two)</h4>
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
                            <td data-label="Examination"><input type="text" name="exam_name_<?= $i ?>" id="exam_name_<?= $i ?>"></td>
                            <td data-label="Year & Reg. No."><input type="text" name="exam_year_reg_<?= $i ?>" id="exam_year_reg_<?= $i ?>"></td>
                            <td data-label="Board / Univ."><input type="text" name="exam_board_<?= $i ?>" id="exam_board_<?= $i ?>"></td>
                            <td data-label="Class / Grade"><input type="text" name="exam_class_<?= $i ?>" id="exam_class_<?= $i ?>"></td>
                            <td data-label="Marks (%)"><input type="text" name="exam_marks_<?= $i ?>" id="exam_marks_<?= $i ?>"></td>
                        </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-section">
                    <h4>Lateral Entry Students (If Applicable)</h4>
                    
                    <div class="form-group">
                        <input type="text" name="lateral_exam_name" id="lateral_exam_name" placeholder="" autocomplete="off">
                        <label for="lateral_exam_name">Exam Passed (Degree/Diploma):</label>
                        <div class="input-bg"></div>
                        </div>
                    
                    <div class="form-group">
                        <input type="text" name="lateral_exam_year_reg" id="lateral_exam_year_reg" placeholder="" autocomplete="off">
                        <label for="lateral_exam_year_reg">Month & Year of Passing with Reg. No.:</label>
                        <div class="input-bg"></div>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" name="lateral_percentage" id="lateral_percentage" placeholder="" autocomplete="off">
                        <label for="lateral_percentage">Percentage Obtained:</label>
                        <div class="input-bg"></div>
                    </div>
                </div>
            </div>

            <div id="page3" class="page">
                <h3 class="page-header"><i class="fas fa-clipboard-list"></i> Special Claims & Documents</h3>

                <div class="form-section">
                    <h4>Sports Achievements (Attach Proof)</h4>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="sports_level[]" value="District"> District</label>
                        <label><input type="checkbox" name="sports_level[]" value="State"> State</label>
                        <label><input type="checkbox" name="sports_level[]" value="National"> National</label>
                        <label><input type="checkbox" name="sports_level[]" value="International"> International</label>
                    </div>
                    <div class="upload-area">
                        <span class="icon"><i class="fas fa-upload"></i></span>
                        <strong>Click to upload or drag & drop files</strong>
                        <div class="instructions">(PDF, JPEG, PNG | Max 2MB)</div>
                        <input type="file" name="sports_proof" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>

                <div class="form-section">
                    <h4>Ex-Servicemen Quota (Attach Proof)</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="ex_servicemen" value="Yes"> Yes</label>
                        <label><input type="radio" name="ex_servicemen" value="No" checked> No</label>
                    </div>
                    <div id="ex_servicemen_section" class="conditional-section" style="display:none;">
                        <div class="upload-area">
                            <span class="icon"><i class="fas fa-file-upload"></i></span>
                            <strong>Click to upload Ex-Servicemen Proof</strong>
                            <div class="instructions">(PDF, JPEG, PNG)</div>
                            <input type="file" name="ex_servicemen_proof" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Disabled Person Status (Attach Proof)</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="disabled" value="Yes" id="disabled_yes"> Yes</label>
                        <label><input type="radio" name="disabled" value="No" checked id="disabled_no"> No</label>
                    </div>
                    <div id="disabled_section" class="conditional-section" style="display:none;">
                        <div class="form-group">
                            <input type="text" name="disability_category" id="disability_category" placeholder="" autocomplete="off">
                            <label for="disability_category">Category:</label>
                            <div class="input-bg"></div>
                        </div>
                        <div class="upload-area">
                            <span class="icon"><i class="fas fa-wheelchair"></i></span>
                            <strong>Click to upload Disability Proof</strong>
                            <div class="instructions">(PDF, JPEG, PNG)</div>
                            <input type="file" name="disability_proof" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Parent working in VMRF-DU?</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="parent_vmrf" value="Yes" id="parent_vmrf_yes"> Yes</label>
                        <label><input type="radio" name="parent_vmrf" value="No" checked id="parent_vmrf_no"> No</label>
                    </div>
                    <div id="parent_vmrf_section" class="conditional-section" style="display:none;">
                        <div class="form-group">
                            <input type="text" name="parent_vmrf_details" id="parent_vmrf_details" placeholder="" autocomplete="off">
                            <label for="parent_vmrf_details">Furnish details (Name, Dept, Emp. ID):</label>
                            <div class="input-bg"></div>
                        </div>
                        <div class="upload-area">
                            <span class="icon"><i class="fas fa-id-card"></i></span>
                            <strong>Click to upload VMRF Employment Proof</strong>
                            <div class="instructions">(PDF, JPEG, PNG)</div>
                            <input type="file" name="parent_vmrf_proof" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>
                
                <h2>Choose Your Signature Type (Required)</h2>
                <div>
                    <div class="tab active" data-target="draw">Draw</div>
                    <div class="tab" data-target="type">Type</div>
                    <div class="tab" data-target="upload">Upload</div>
                </div>

                <div class="tab-content" id="tab-draw">
                    <canvas id="signature-pad"></canvas><br>
                    <button type="button" id="clear" class="nav-btn prev" style="visibility: visible; background: var(--text-muted-dark);">Clear</button>
                </div>

                <div class="tab-content" id="tab-type" style="display:none;">
                    <div class="form-group">
                        <input type="text" id="typed-signature" placeholder="" autocomplete="off">
                        <label for="typed-signature">Type your name</label>
                        <div class="input-bg"></div>
                    </div>
                    <div class="typed-preview" id="typed-preview">Your signature preview</div>
                </div>

                <div class="tab-content" id="tab-upload" style="display:none;">
                    <div class="upload-area">
                        <span class="icon"><i class="fas fa-signature"></i></span>
                        <strong>Click to upload signature image</strong>
                        <div class="instructions">(PNG, JPG | Max 1MB)</div>
                        <input type="file" name="signature_file" id="signature_file" accept="image/*">
                    </div>
                </div>
            </div> <div class="navigation-buttons">
                <button type="button" class="nav-btn prev" id="prevBtn" onclick="prevPage()">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="nav-btn next" id="nextBtn" onclick="nextPage()">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" class="nav-btn submit" id="submitBtn">
                    <i class="fas fa-check-circle"></i>Submit Application
                </button>
            </div>
            
        </form> 
    </div>
</div>

<div id="errorModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <div class="modal-header"><h3>Validation Error!</h3></div>
        <div class="modal-body"><p id="errorMessage"></p></div>
        <div class="modal-footer"><button class="modal-close-btn" onclick="closeModal()">Got it</button></div>
    </div>
</div>

<script>
    // This defines the variable with the data generated by PHP
    const collegePrograms = <?= json_encode($college_programs) ?>;
</script>

<script src="application_scripts.js"></script> 

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
</body>
</html>
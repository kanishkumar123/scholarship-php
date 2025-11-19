<?php
session_start();
// NOTE: Assuming config.php defines $conn as a mysqli object.
include("../config.php"); 

// --- 1. AUTHENTICATION & INITIAL SETUP ---

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("No application selected.");
}

$app_id = (int)$_GET['id'];
$success_message = '';
$error_message = '';

// --- 2. FETCH APPLICATION DETAILS (Added academic_year) ---

// Use prepared statement for initial fetch for security
$stmt_app = $conn->prepare("
    SELECT a.*, 
           c.name AS institution_name_str,
           p.name AS course_str,
           st.name AS student_name, 
           sc.name AS scholarship_name,
           a.student_id  -- --- NEW: Fetch student_id for reg no lookup ---
    FROM applications a
    LEFT JOIN colleges c ON a.institution_name = c.id
    LEFT JOIN programs p ON a.course = p.id
    LEFT JOIN scholarship_students st ON a.student_id = st.id
    LEFT JOIN scholarships sc ON a.scholarship_id = sc.id
    WHERE a.id = ?
    LIMIT 1
");

if (!$stmt_app) {
    die("Database error during preparation: " . $conn->error);
}

$stmt_app->bind_param("i", $app_id);
$stmt_app->execute();
$result_app = $stmt_app->get_result();

if ($result_app->num_rows == 0) {
    die('Application not found.');
}

$app = $result_app->fetch_assoc();
$stmt_app->close();

// Fallback for institution/course names
$app_institution = htmlspecialchars($app['institution_name_str'] ?? $app['institution_name']);
$app_course = htmlspecialchars($app['course_str'] ?? $app['course']);


// --- 3. --- NEW: FETCH PREVIOUS UNIVERSITY REG NO (Request 3) ---

$prefilled_reg_no = '';
// We search for any renewal from this student (not just this application)
// as the university number is tied to the student.
if (!empty($app['student_id'])) {
    $stmt_reg = $conn->prepare("
        SELECT r.university_reg_no 
        FROM renewals r
        JOIN applications a ON r.application_id = a.id
        WHERE a.student_id = ? 
          AND r.university_reg_no IS NOT NULL 
          AND r.university_reg_no != '' 
        ORDER BY r.submitted_at DESC, r.id DESC 
        LIMIT 1
    ");
    $stmt_reg->bind_param("i", $app['student_id']);
    $stmt_reg->execute();
    $result_reg = $stmt_reg->get_result();
    
    if ($reg_row = $result_reg->fetch_assoc()) {
        $prefilled_reg_no = $reg_row['university_reg_no'];
    }
    $stmt_reg->close();
}


// --- 4. HELPER FUNCTIONS FOR FILE UPLOAD (Unchanged) ---
function handle_file_upload($file_key, $upload_dir, $conn) {
    global $app_id;
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] != UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$file_key];
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = "app_{$app_id}_{$file_key}_" . time() . "." . $file_extension;
    $target_file = $upload_dir . $safe_filename; 
    $base_upload_path = "../uploads/renewals/"; 
    $final_target_path = $base_upload_path . $target_file; 
    $final_directory = dirname($final_target_path); 

    if (!is_dir($final_directory)) {
        if (!mkdir($final_directory, 0777, true)) {
            return "Error: Could not create upload directory. Check permissions.";
        }
    }
    
    if (move_uploaded_file($file["tmp_name"], $final_target_path)) {
        return $final_target_path; 
    } else {
        return "Error moving uploaded file. Check folder permissions.";
    }
}

// --- 5. FORM SUBMISSION HANDLER (Updated for new fields and validation) ---

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic Server-Side Validation & Type Casting
    $year_of_study = filter_input(INPUT_POST, 'year_of_study', FILTER_VALIDATE_INT);
    
    $semester_post = $_POST['semester'] ?? '';
    $semester = null; 
    if ($semester_post !== '') {
        $semester = (int)$semester_post;
        if ($semester < 1 || $semester > 10) { 
            $error_message = "Invalid Semester provided.";
        }
    } 
    
    $university_reg_no = trim($_POST['university_reg_no'] ?? '');
    
    // --- NEW: Get New Fields (Request 1) ---
    $previous_year_of_study = filter_input(INPUT_POST, 'previous_year_of_study', FILTER_VALIDATE_INT);
    $previous_attendance_percentage = trim($_POST['previous_attendance_percentage'] ?? '');
    
    // --- NEW: Mark 'marks_sem1' as a variable to check validation ---
    $marks_sem1 = trim($_POST['marks_sem1'] ?? '');
    $marks_sem2 = trim($_POST['marks_sem2'] ?? '');
    
    $scholarship_receipt = trim($_POST['scholarship_receipt'] ?? 'No');
    $scholarship_particulars = trim($_POST['scholarship_particulars'] ?? '');
    
    $post_institution_name = $app_institution;
    $post_course = $app_course;

    // --- Data Validation (Request 2) ---
    if (!$year_of_study || $year_of_study < 1 || $year_of_study > 5) { 
        $error_message = "Invalid Year of Study provided.";
    } else if (empty($university_reg_no)) {
        $error_message = "University Register Number is required.";
    } 
    // --- NEW: Mandatory Field Validation ---
    else if (!$previous_year_of_study || $previous_year_of_study < 1 || $previous_year_of_study > 5) {
        $error_message = "Previous Year of Study is required.";
    } else if (empty($previous_attendance_percentage)) {
        $error_message = "Previous Attendance Percentage is required.";
    } else if (empty($marks_sem1)) {
        $error_message = "Marks / CGPA (Previous Semester) is required.";
    }
    // --- End New Validation ---
    else if ($scholarship_receipt == 'Yes' && empty($scholarship_particulars)) {
        $error_message = "Scholarship Particulars are required if receipt is selected as 'Yes'.";
    }

    // --- File Uploads ---
    if (empty($error_message)) {
        $marks_sem1_file_path = handle_file_upload('marks_sem1_file', 'marks_sem1/', $conn);
        $marks_sem2_file_path = handle_file_upload('marks_sem2_file', 'marks_sem2/', $conn);
        $scholarship_file_path = handle_file_upload('scholarship_file', 'scholarship_proof/', $conn);

        if (strpos($marks_sem1_file_path, 'Error') !== false || strpos($marks_sem2_file_path, 'Error') !== false || strpos($scholarship_file_path, 'Error') !== false) {
             $error_message = "One or more file uploads failed. Please try again.";
        }
    }

    // --- Database Insertion (Updated Duplicate Check) ---
    if (empty($error_message)) {
        // Check for duplicate renewal for the same year/semester
        if ($semester === null) {
            $stmt_check = $conn->prepare("SELECT id FROM renewals WHERE application_id = ? AND year_of_study = ? AND semester IS NULL");
            $stmt_check->bind_param("ii", $app_id, $year_of_study);
        } else {
            $stmt_check = $conn->prepare("SELECT id FROM renewals WHERE application_id = ? AND year_of_study = ? AND semester = ?");
            $stmt_check->bind_param("iii", $app_id, $year_of_study, $semester);
        }
        
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        
        if ($check_result->num_rows > 0) {
            $sem_text = $semester ? ", Semester " . $roman[$semester-1] : "";
            $error_message = "A renewal record for Year {$year_of_study}{$sem_text} already exists for this application.";
        }
        $stmt_check->close();
    }

    if (empty($error_message)) {
        
        // --- NEW: Updated INSERT query ---
        $insert_query = "INSERT INTO renewals 
            (application_id, institution_name, course, year_of_study, semester, 
             previous_year_of_study, previous_attendance_percentage,
             university_reg_no, marks_sem1, marks_sem2, 
             marks_sem1_file_path, marks_sem2_file_path,
             scholarship_receipt, scholarship_particulars, scholarship_file_path, submitted_at)
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($insert_query);

        // --- NEW: Updated bind_param string and variables ---
        // Bind parameters: isssiisssssssss
        $stmt->bind_param("isssiisssssssss", 
            $app_id, 
            $post_institution_name, 
            $post_course, 
            $year_of_study, 
            $semester, // This will be bound as NULL if $semester is null
            $previous_year_of_study,           // --- NEW
            $previous_attendance_percentage,   // --- NEW
            $university_reg_no, 
            $marks_sem1, 
            $marks_sem2,
            $marks_sem1_file_path,
            $marks_sem2_file_path,
            $scholarship_receipt, 
            $scholarship_particulars,
            $scholarship_file_path
        );

        if ($stmt->execute()) {
            header("Location: view_applications.php?id={$app_id}&status=renewal_success"); 
            exit;
        } else {
            $error_message = "Database Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renewal Form</title>
    <style>
        /* Base Styling */
        body {
            font-family: "Segoe UI", Roboto, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 40px 20px;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 1.8rem;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        /* Form Layout */
        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px 35px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        label {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
        }
        /* --- NEW: Red asterisk for mandatory fields --- */
        label .mandatory {
            color: #D90429;
            font-weight: bold;
            margin-left: 2px;
        }
        
        input:not([type="file"]), textarea, select {
            padding: 12px 16px;
            border: 1px solid #dcdcdc;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #fff; /* Ensure selects have white background */
        }
        input:focus:not([type="file"]), textarea:focus, select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            outline: none;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .full-width {
            grid-column: span 2;
        }
        /* Fieldset for structure */
        fieldset {
            grid-column: span 2;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            
            /* --- NEW: Added grid for consistency --- */
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px 35px;
        }
        legend {
            font-weight: 700;
            font-size: 1.3rem;
            color: #007bff;
            padding: 0 10px;
            margin-left: -10px; /* Aligns with padding */
        }

        /* Readonly Header Card (UX Improvement) */
        .header-card {
            background: #e9f5ff;
            border: 1px solid #cce5ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .card-item {
            display: flex;
            flex-direction: column;
        }
        .card-label {
            font-size: 0.9rem;
            color: #5a7b8e;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .card-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        /* Form Actions & Messaging */
        .form-actions {
            grid-column: span 2;
            text-align: right;
            padding-top: 10px;
        }
        button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            background: #007bff;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.1s;
        }
        button:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        .message-box {
            grid-column: span 2;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* File Input Styling */
        input[type="file"] {
            border: 1px solid #dcdcdc;
            padding: 8px;
            border-radius: 6px;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            form, fieldset { /* --- NEW: Added fieldset here --- */
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .full-width, fieldset, .form-actions, .message-box {
                grid-column: span 1;
            }
            .header-card {
                grid-template-columns: 1fr;
            }
            body {
                padding: 20px 10px;
            }
            .container {
                padding: 20px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <h2><i class="fas fa-redo-alt"></i> Scholarship Renewal Application</h2>

    <?php if ($error_message): ?>
        <div class="message-box error">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="header-card">
        <div class="card-item">
            <div class="card-label">Student Name</div>
            <div class="card-value"><?= htmlspecialchars($app['student_name']) ?></div>
        </div>
        <div class="card-item">
            <div class="card-label">Application ID</div>
            <div class="card-value">#<?= htmlspecialchars($app['id']) ?></div>
        </div>
        <div class="card-item">
            <div class="card-label">Existing Scholarship Category</div>
            <div class="card-value"><?= htmlspecialchars($app['scholarship_name'] ?? 'Not Assigned') ?></div>
        </div>
        <div class="card-item">
            <div class="card-label">Institution</div>
            <div class="card-value"><?= $app_institution ?></div>
        </div>
        <div class="card-item">
            <div class="card-label">Programme / Course</div>
            <div class="card-value"><?= $app_course ?></div>
        </div>
        <div class="card-item">
            <div class="card-label">Academic Year</div>
            <div class="card-value"><?= htmlspecialchars($app['academic_year'] ?? 'N/A') ?></div>
        </div>
    </div>
    
    <form method="post" enctype="multipart/form-data" onsubmit="return validateForm()">

        <fieldset>
            <legend><i class="fas fa-book-open"></i> Current Academic Status</legend>
            <div class="form-group">
                <label for="year_of_study">Year of Study <span class="mandatory">*</span></label>
                <select id="year_of_study" name="year_of_study" required>
                    <option value="">-- Select Year --</option>
                    <option value="1" <?= (!empty($year_of_study) && $year_of_study == 1) ? 'selected' : '' ?>>I</option>
                    <option value="2" <?= (!empty($year_of_study) && $year_of_study == 2) ? 'selected' : '' ?>>II</option>
                    <option value="3" <?= (!empty($year_of_study) && $year_of_study == 3) ? 'selected' : '' ?>>III</option>
                    <option value="4" <?= (!empty($year_of_study) && $year_of_study == 4) ? 'selected' : '' ?>>IV</option>
                    <option value="5" <?= (!empty($year_of_study) && $year_of_study == 5) ? 'selected' : '' ?>>V</option>
                </select>
            </div>

            <div class="form-group">
                <label for="semester">Semester</label>
                <select id="semester" name="semester">
                    <option value="">-- Select Semester --</option>
                    <?php
                    $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
                    for ($i = 1; $i <= 10; $i++):
                        $selected = (!empty($semester) && $semester == $i) ? 'selected' : '';
                    ?>
                        <option value="<?= $i ?>" <?= $selected ?>><?= $roman[$i-1] ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="university_reg_no">University Register Number <span class="mandatory">*</span></label>
                <input type="text" id="university_reg_no" name="university_reg_no" required 
                       value="<?= htmlspecialchars($prefilled_reg_no ?: ($university_reg_no ?? '')) ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend><i class="fas fa-user-check"></i> Previous Year Attendance</legend>
            <div class="form-group">
                <label for="previous_year_of_study">Previous Year of Study <span></span></label>
                <select id="previous_year_of_study" name="previous_year_of_study">
                    <option value="">-- Select Year --</option>
                    <option value="1" <?= (!empty($previous_year_of_study) && $previous_year_of_study == 1) ? 'selected' : '' ?>>I</option>
                    <option value="2" <?= (!empty($previous_year_of_study) && $previous_year_of_study == 2) ? 'selected' : '' ?>>II</option>
                    <option value="3" <?= (!empty($previous_year_of_study) && $previous_year_of_study == 3) ? 'selected' : '' ?>>III</option>
                    <option value="4" <?= (!empty($previous_year_of_study) && $previous_year_of_study == 4) ? 'selected' : '' ?>>IV</option>
                    <option value="5" <?= (!empty($previous_year_of_study) && $previous_year_of_study == 5) ? 'selected' : '' ?>>V</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="previous_attendance_percentage">Attendance Percentage (Previous Year) <span class="mandatory">*</span></label>
                <input type="text" id="previous_attendance_percentage" name="previous_attendance_percentage" 
                       placeholder="e.g. 85%" required 
                       value="<?= htmlspecialchars($previous_attendance_percentage ?? '') ?>">
            </div>
        </fieldset>
        
        <fieldset>
            <legend><i class="fas fa-percent"></i> Academic Performance & Documents</legend>
            
            <div class="form-group">
                <label for="marks_sem1">Marks / CGPA (Previous Semester) <span class="mandatory">*</span></label>
                <input type="text" id="marks_sem1" name="marks_sem1" placeholder="e.g. 85% or SGPA 8.5" required
                       value="<?= htmlspecialchars($marks_sem1 ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="marks_sem1_file">Upload Mark Sheet (Previous Sem)</label>
                <input type="file" id="marks_sem1_file" name="marks_sem1_file" accept=".pdf, .jpg, .png">
            </div>

            <div class="form-group">
                <label for="marks_sem2">Marks / SGPA (Current Semester)</label>
                <input type="text" id="marks_sem2" name="marks_sem2" placeholder="e.g. 82% or SGPA 8.2" 
                       value="<?= htmlspecialchars($marks_sem2 ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="marks_sem2_file">Upload Mark Sheet (Current Sem)</label>
                <input type="file" id="marks_sem2_file" name="marks_sem2_file" accept=".pdf, .jpg, .png">
            </div>

        </fieldset>
        
        <fieldset>
            <legend><i class="fas fa-hand-holding-usd"></i> Other Scholarship Details</legend>

            <div class="form-group">
                <label for="scholarship_receipt">Any other Scholarship?</label>
                <select name="scholarship_receipt" id="scholarship_receipt" onchange="toggleScholarshipDetails()">
                    <option value="No" <?= ($scholarship_receipt ?? 'No') == 'No' ? 'selected' : '' ?>>No</option>
                    <option value="Yes" <?= ($scholarship_receipt ?? '') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                </select>
            </div>
            
            <div class="form-group" id="scholarship_file_box" style="display:<?= ($scholarship_receipt ?? '') == 'Yes' ? 'grid' : 'none' ?>;">
                <label for="scholarship_file">Upload Other Scholarship Proof</label>
                <input type="file" id="scholarship_file" name="scholarship_file" accept=".pdf, .jpg, .png">
            </div>
            
            <div class="form-group full-width" id="scholarship_details_box" style="display:<?= ($scholarship_receipt ?? '') == 'Yes' ? 'grid' : 'none' ?>;">
                <label for="scholarship_particulars">If yes, provide particulars (Scheme name, amount, etc.) <span id="scholarship_particulars_required" class="mandatory" style="display:<?= ($scholarship_receipt ?? '') == 'Yes' ? 'inline' : 'none' ?>;">*</span></label>
                <textarea id="scholarship_particulars" name="scholarship_particulars" rows="3"><?= htmlspecialchars($scholarship_particulars ?? '') ?></textarea>
            </div>
        </fieldset>
        
        <div class="form-actions">
            <button type="submit"><i class="fas fa-save"></i> Save Renewal</button>
        </div>
    </form>
</div>

<script>
function toggleScholarshipDetails() {
    const select = document.getElementById('scholarship_receipt');
    const particulars_box = document.getElementById('scholarship_details_box');
    const file_box = document.getElementById('scholarship_file_box');
    const required_span = document.getElementById('scholarship_particulars_required');
    const particulars_field = document.getElementById('scholarship_particulars');

    if (select.value === 'Yes') {
        particulars_box.style.display = 'grid'; 
        file_box.style.display = 'grid';
        required_span.style.display = 'inline';
        particulars_field.setAttribute('required', 'required');
    } else {
        particulars_box.style.display = 'none';
        file_box.style.display = 'none';
        required_span.style.display = 'none';
        particulars_field.removeAttribute('required');
    }
}

// --- NEW: Updated Client-Side Validation (Request 2) ---
function validateForm() {
    // Check mandatory fields
    const yearOfStudy = document.getElementById('year_of_study');
    const regNo = document.getElementById('university_reg_no');
    const prevYear = document.getElementById('previous_year_of_study');
    const prevAttend = document.getElementById('previous_attendance_percentage');
    const marksSem1 = document.getElementById('marks_sem1');

    if (yearOfStudy.value === '') {
        alert('Year of Study is required.');
        yearOfStudy.focus();
        return false;
    }
    if (regNo.value.trim() === '') {
        alert('University Register Number is required.');
        regNo.focus();
        return false;
    }
    if (prevYear.value === '') {
        alert('Previous Year of Study is required.');
        prevYear.focus();
        return false;
    }
    if (prevAttend.value.trim() === '') {
        alert('Previous Attendance Percentage is required.');
        prevAttend.focus();
        return false;
    }
    if (marksSem1.value.trim() === '') {
        alert('Marks / CGPA (Previous Semester) is required.');
        marksSem1.focus();
        return false;
    }

    // Check file types
    const sem1File = document.getElementById('marks_sem1_file');
    const sem2File = document.getElementById('marks_sem2_file');
    const fileInputs = [sem1File, sem2File];
    
    for (const input of fileInputs) {
        if (input.files.length > 0) {
            const fileName = input.files[0].name;
            const extension = fileName.substring(fileName.lastIndexOf('.') + 1).toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png'].includes(extension)) {
                alert(`Invalid file type for ${input.name}. Only PDF, JPG, and PNG are allowed.`);
                return false;
            }
        }
    }
    
    // Check 'Other Scholarship' logic
    const select = document.getElementById('scholarship_receipt');
    const particulars_field = document.getElementById('scholarship_particulars');
    
    if (select.value === 'Yes' && particulars_field.value.trim() === '') {
        alert('Scholarship Particulars are required if you are in receipt of another scholarship.');
        particulars_field.focus();
        return false;
    }
    
    return true;
}

// Initialize state on load
document.addEventListener('DOMContentLoaded', toggleScholarshipDetails);

</script>
</body>
</html>
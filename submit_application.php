<?php
session_start();
include("config.php"); // Must define $conn

// Check if $conn is defined and connection is successful
if (!isset($conn) || mysqli_connect_errno()) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (!isset($_SESSION['student_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// --- 1. SANITIZE AND COLLECT FORM DATA ---
// NOTE: We don't need mysqli_real_escape_string because we use prepared statements later.
// We still filter/validate as needed.

$student_id     = $_POST['student_id'] ?? null;
$scholarship_id = $_POST['scholarship_id'] ?? null;
$academic_year  = $_POST['academic_year'] ?? null;

// Basic validation check (essential fields)
if (!$student_id || !$scholarship_id) {
    die("Error: Missing core student or scholarship IDs.");
}

// --- 2. PREVENT DUPLICATE SUBMISSION ---
// Use prepared statement for checking existence
$stmt_check = $conn->prepare("SELECT id FROM applications WHERE student_id=? AND scholarship_id=? LIMIT 1");
$stmt_check->bind_param("ss", $student_id, $scholarship_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $existing = $result_check->fetch_assoc();
    $_SESSION['application_id'] = $existing['id'];
    header("Location: confirmation.php");
    exit;
}
$stmt_check->close();

// --- 3. FETCH REQUIRED STUDENT INFO ---
$stmt_student = $conn->prepare("SELECT name, application_no, dob FROM scholarship_students WHERE id = ?");
$stmt_student->bind_param("s", $student_id);
$stmt_student->execute();
$student_info = $stmt_student->get_result()->fetch_assoc();
$stmt_student->close();

if (!$student_info) {
    die("Student record not found in scholarship_students table.");
}

$name           = $student_info['name'];
$application_no = $student_info['application_no'];
$dob            = $student_info['dob'];

// --- 4. COLLECT AND PREPARE ALL OTHER FIELDS ---
// Coalesce operator (??) safely handles missing fields, defaults to empty string or 'No'
$institution_name     = $_POST['institution_name'] ?? '';
$course               = $_POST['course'] ?? '';
$year_of_study        = $_POST['year_of_study'] ?? '';
$semester             = $_POST['semester'] ?? '';
$gender               = $_POST['gender'] ?? '';
$father_name          = $_POST['father_name'] ?? '';
$mother_name          = $_POST['mother_name'] ?? '';
$community            = $_POST['community'] ?? '';
$caste                = $_POST['caste'] ?? '';
$family_income        = $_POST['family_income'] ?? '';
$address              = $_POST['address'] ?? '';
$phone_std            = $_POST['phone_std'] ?? '';
$mobile               = $_POST['mobile'] ?? '';
$email                = $_POST['email'] ?? '';

// Educational Fields
$exam_name_1          = $_POST['exam_name_1'] ?? '';
$exam_year_reg_1      = $_POST['exam_year_reg_1'] ?? '';
$exam_board_1         = $_POST['exam_board_1'] ?? '';
$exam_class_1         = $_POST['exam_class_1'] ?? '';
$exam_marks_1         = $_POST['exam_marks_1'] ?? '';
$exam_name_2          = $_POST['exam_name_2'] ?? '';
$exam_year_reg_2      = $_POST['exam_year_reg_2'] ?? '';
$exam_board_2         = $_POST['exam_board_2'] ?? '';
$exam_class_2         = $_POST['exam_class_2'] ?? '';
$exam_marks_2         = $_POST['exam_marks_2'] ?? '';

// Lateral Entry Fields
$lateral_exam_name    = $_POST['lateral_exam_name'] ?? '';
$lateral_exam_year_reg= $_POST['lateral_exam_year_reg'] ?? '';
$lateral_percentage   = $_POST['lateral_percentage'] ?? '';

// Claim Fields
$sports_level         = isset($_POST['sports_level']) ? implode(", ", $_POST['sports_level']) : "";
$ex_servicemen        = $_POST['ex_servicemen'] ?? 'No';
$disabled             = $_POST['disabled'] ?? 'No';
$disability_category  = $_POST['disability_category'] ?? '';
$parent_vmrf          = $_POST['parent_vmrf'] ?? 'No';
$parent_vmrf_details  = $_POST['parent_vmrf_details'] ?? '';


// --- 5. INSERT INTO applications TABLE (Prepared Statement) ---
$sql_insert = "INSERT INTO applications (
    scholarship_id, student_id, academic_year, application_no, dob, name, family_income,
    institution_name, course, year_of_study, semester, gender, father_name, mother_name,
    community, caste, address, phone_std, mobile, email,
    exam_name_1, exam_year_reg_1, exam_board_1, exam_class_1, exam_marks_1,
    exam_name_2, exam_year_reg_2, exam_board_2, exam_class_2, exam_marks_2,
    lateral_exam_name, lateral_exam_year_reg, lateral_percentage,
    sports_level, ex_servicemen, disabled, disability_category,
    parent_vmrf, parent_vmrf_details
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_insert = $conn->prepare($sql_insert);

if (!$stmt_insert) {
    die("SQL Prepare Error: " . $conn->error);
}

// Bind parameters (s = string, i = integer, d = double). All are treated as strings (s) here for simplicity.
$stmt_insert->bind_param("sssssssssssssssssssssssssssssssssssssss",
    $scholarship_id, $student_id, $academic_year, $application_no, $dob, $name, $family_income,
    $institution_name, $course, $year_of_study, $semester, $gender, $father_name, $mother_name,
    $community, $caste, $address, $phone_std, $mobile, $email,
    $exam_name_1, $exam_year_reg_1, $exam_board_1, $exam_class_1, $exam_marks_1,
    $exam_name_2, $exam_year_reg_2, $exam_board_2, $exam_class_2, $exam_marks_2,
    $lateral_exam_name, $lateral_exam_year_reg, $lateral_percentage,
    $sports_level, $ex_servicemen, $disabled, $disability_category,
    $parent_vmrf, $parent_vmrf_details
);

if (!$stmt_insert->execute()) {
    $error = $stmt_insert->error;
    $stmt_insert->close();
    die("Error submitting application: " . $error);
}

$application_id = $conn->insert_id;
$stmt_insert->close();

// --- 6. HANDLE FILE UPLOADS INTO application_files ---
function handleFileUpload($field_name, $file_type, $application_id, $conn) {
    if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Basic file security checks (MIME type and size limit should be added here for production)
        $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES[$field_name]['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime)) {
             // LOGGING or an error is better than silently failing
             error_log("Attempted upload of disallowed MIME type: " . $mime_type);
             return; 
        }

        $ext = pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION);
        $safe_filename = uniqid($file_type . "_" . $application_id . "_") . "." . strtolower($ext);
        $target_path = $upload_dir . $safe_filename;

        if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_path)) {
            // Use prepared statement for file insertion
            $stmt_file = $conn->prepare("INSERT INTO application_files (application_id, file_type, file_path) VALUES (?, ?, ?)");
            $stmt_file->bind_param("iss", $application_id, $file_type, $target_path); // i for integer ID, s for strings
            $stmt_file->execute();
            $stmt_file->close();
        } else {
             error_log("Failed to move uploaded file: " . $_FILES[$field_name]['tmp_name'] . " to " . $target_path);
        }
    }
}

// Call uploads for each document
handleFileUpload('sports_proof', 'sports', $application_id, $conn);
handleFileUpload('ex_servicemen_proof', 'ex_servicemen', $application_id, $conn);
handleFileUpload('disability_proof', 'disabled', $application_id, $conn);
handleFileUpload('parent_vmrf_proof', 'parent_vmrf', $application_id, $conn);

// --- 7. REDIRECT TO CONFIRMATION ---
$_SESSION['application_id'] = $application_id;
header("Location: confirmation.php");
exit;
?>
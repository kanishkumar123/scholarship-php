<?php
session_start();
include("config.php"); 

if (!isset($conn) || mysqli_connect_errno()) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (!isset($_SESSION['student_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// --- 1. SANITIZE AND COLLECT FORM DATA ---
$student_id     = $_POST['student_id'] ?? null;
$scholarship_id = $_POST['scholarship_id'] ?? null;
$academic_year  = $_POST['academic_year'] ?? null;

if (!$student_id || !$scholarship_id) {
    die("Error: Missing core student or scholarship IDs.");
}

// --- 2. PREVENT DUPLICATE SUBMISSION ---
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

// --- 3. FETCH STUDENT INFO ---
$stmt_student = $conn->prepare("SELECT name, application_no, dob FROM scholarship_students WHERE id = ?");
$stmt_student->bind_param("s", $student_id);
$stmt_student->execute();
$student_info = $stmt_student->get_result()->fetch_assoc();
$stmt_student->close();

if (!$student_info) {
    die("Student record not found.");
}

$name           = $student_info['name'];
$application_no = $student_info['application_no'];
$dob            = $student_info['dob'];

// --- 4. COLLECT ALL OTHER FIELDS ---
$institution_name      = $_POST['institution_name'] ?? '';
$course                = $_POST['course'] ?? '';
$year_of_study         = $_POST['year_of_study'] ?? '';
$semester              = $_POST['semester'] ?? '';
$gender                = $_POST['gender'] ?? '';
$father_name           = $_POST['father_name'] ?? '';
$mother_name           = $_POST['mother_name'] ?? '';
$community             = $_POST['community'] ?? '';
$caste                 = $_POST['caste'] ?? '';
// FIX: Clean the income
$family_income         = str_replace(',', '', $_POST['family_income'] ?? '0');
$address       = $_POST['address'] ?? '';
$parent_mobile = $_POST['parent_mobile'] ?? ''; // <--- NEW
$mobile        = $_POST['mobile'] ?? '';
$email                 = $_POST['email'] ?? '';

$exam_name = $_POST['exam_name'];
$exam_year_reg = $_POST['exam_year_reg'];
$exam_board = $_POST['exam_board'];
$exam_class = $_POST['exam_class'];
$exam_marks = $_POST['exam_marks'];

$lateral_exam_name     = $_POST['lateral_exam_name'] ?? '';
$lateral_exam_year_reg = $_POST['lateral_exam_year_reg'] ?? '';
$lateral_percentage    = $_POST['lateral_percentage'] ?? '';

$sports_level          = isset($_POST['sports_level']) ? implode(", ", $_POST['sports_level']) : "";
$ex_servicemen         = $_POST['ex_servicemen'] ?? 'No';
$disabled              = $_POST['disabled'] ?? 'No';
$disability_category   = $_POST['disability_category'] ?? '';
$parent_vmrf           = $_POST['parent_vmrf'] ?? 'No';
$parent_vmrf_details   = $_POST['parent_vmrf_details'] ?? '';

// --- 5. HANDLE SIGNATURE (NEW) ---
$sig_path = '';
$signature_type = $_POST['signature_type'] ?? '';
$upload_dir = "uploads/signatures/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// Case A: Draw or Type (Base64)
if (($signature_type === 'draw' || $signature_type === 'type') && !empty($_POST['signature_data'])) {
    $data_uri = $_POST['signature_data'];
    if (strpos($data_uri, 'data:image') === 0) {
        $data_uri = substr($data_uri, strpos($data_uri, ',') + 1);
        $decodedData = base64_decode($data_uri);
        $filename = "sig_" . $student_id . "_" . time() . ".png";
        if (file_put_contents($upload_dir . $filename, $decodedData)) {
            $sig_path = $upload_dir . $filename;
        }
    } else {
        // If it's just text (typed), store it as text or handle appropriately. 
        // For now, we'll skip saving if it's not image data.
    }
}
// Case B: Upload
elseif ($signature_type === 'upload' && isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION);
    $filename = "sig_" . $student_id . "_" . time() . "." . $ext;
    if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $upload_dir . $filename)) {
        $sig_path = $upload_dir . $filename;
    }
}

// --- 6. INSERT INTO applications TABLE ---
// CORRECTION: Ensure the number of columns (35) matches the number of '?' (35) and 's' (35)

$sql_insert = "INSERT INTO applications (
    scholarship_id, student_id, academic_year, application_no, dob, name, family_income,
    institution_name, course, year_of_study, semester, gender, father_name, mother_name,
    community, caste, address, parent_mobile, mobile, email,
    exam_name, exam_year_reg, exam_board, exam_class, exam_marks,
    lateral_exam_name, lateral_exam_year_reg, lateral_percentage,
    sports_level, ex_servicemen, disabled, disability_category,
    parent_vmrf, parent_vmrf_details, signature_path
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_insert = $conn->prepare($sql_insert);

if (!$stmt_insert) {
    die("SQL Prepare Error: " . $conn->error);
}

// CORRECTION: The type string now has exactly 35 's' characters
$stmt_insert->bind_param("sssssssssssssssssssssssssssssssssss",
    $scholarship_id, $student_id, $academic_year, $application_no, $dob, $name, $family_income,
    $institution_name, $course, $year_of_study, $semester, $gender, $father_name, $mother_name,
    $community, $caste, $address, $parent_mobile, $mobile, $email,
    $exam_name, $exam_year_reg, $exam_board, $exam_class, $exam_marks,
    $lateral_exam_name, $lateral_exam_year_reg, $lateral_percentage,
    $sports_level, $ex_servicemen, $disabled, $disability_category,
    $parent_vmrf, $parent_vmrf_details, $sig_path
);

if (!$stmt_insert->execute()) {
    $error = $stmt_insert->error;
    $stmt_insert->close();
    die("Error submitting application: " . $error);
}

$application_id = $conn->insert_id;
$stmt_insert->close();

// --- 7. HANDLE FILE UPLOADS ---
function handleFileUpload($field_name, $file_type, $application_id, $conn) {
    if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES[$field_name]['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime)) return; 

        $ext = pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION);
        $safe_filename = $file_type . "_" . $application_id . "_" . time() . "." . $ext;
        $target_path = $upload_dir . $safe_filename;

        if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_path)) {
            $stmt_file = $conn->prepare("INSERT INTO application_files (application_id, file_type, file_path) VALUES (?, ?, ?)");
            $stmt_file->bind_param("iss", $application_id, $file_type, $target_path);
            $stmt_file->execute();
            $stmt_file->close();
        }
    }
}

handleFileUpload('sports_proof', 'sports', $application_id, $conn);
handleFileUpload('ex_servicemen_proof', 'ex_servicemen', $application_id, $conn);
handleFileUpload('disability_proof', 'disabled', $application_id, $conn);
handleFileUpload('parent_vmrf_proof', 'parent_vmrf', $application_id, $conn);

// --- 8. REDIRECT TO CONFIRMATION ---
$_SESSION['application_id'] = $application_id;
header("Location: confirmation.php");
exit;
?>
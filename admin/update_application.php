<?php
session_start();
// The config file is in the parent directory relative to admin/
include("../config.php"); 

// --- 1. Admin Authentication Check ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); // Redirect to admin login if not authenticated
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// === 2. SANITIZE AND COLLECT FORM DATA ===
$application_id = $_POST['application_id'] ?? 0;
// Validation for Admin access
if (!is_numeric($application_id) || $application_id <= 0) {
    echo "<script>alert('Error: Invalid application ID provided.'); window.location.href='dashboard.php';</script>";
    exit;
}

// --- Hidden fields (Read-only data) ---
$student_id     = $_POST['student_id'];
$scholarship_id = $_POST['scholarship_id'];
$academic_year  = $_POST['academic_year'] ?? null;

// --- User-Editable Fields (SANITIZED) ---
$institution_name      = mysqli_real_escape_string($conn, $_POST['institution_name']);
$course                = mysqli_real_escape_string($conn, $_POST['course']);
$year_of_study         = (int)$_POST['year_of_study'];
$semester              = (int)$_POST['semester'];
$gender                = mysqli_real_escape_string($conn, $_POST['gender']);
$father_name           = mysqli_real_escape_string($conn, $_POST['father_name']);
$mother_name           = mysqli_real_escape_string($conn, $_POST['mother_name']);
$community             = mysqli_real_escape_string($conn, $_POST['community']);
$caste                 = mysqli_real_escape_string($conn, $_POST['caste']);

$raw_income            = str_replace(',', '', $_POST['family_income']); 
$family_income         = mysqli_real_escape_string($conn, $raw_income);

$address               = mysqli_real_escape_string($conn, $_POST['address']);
$phone_std             = mysqli_real_escape_string($conn, $_POST['phone_std']);
$mobile                = mysqli_real_escape_string($conn, $_POST['mobile']);
$email                 = mysqli_real_escape_string($conn, $_POST['email']);

// Page 2
$exam_name_1           = mysqli_real_escape_string($conn, $_POST['exam_name_1']);
$exam_year_reg_1       = mysqli_real_escape_string($conn, $_POST['exam_year_reg_1']);
$exam_board_1          = mysqli_real_escape_string($conn, $_POST['exam_board_1']);
$exam_class_1          = mysqli_real_escape_string($conn, $_POST['exam_class_1']);
$exam_marks_1          = mysqli_real_escape_string($conn, $_POST['exam_marks_1']);
$exam_name_2           = mysqli_real_escape_string($conn, $_POST['exam_name_2']);
$exam_year_reg_2       = mysqli_real_escape_string($conn, $_POST['exam_year_reg_2']);
$exam_board_2          = mysqli_real_escape_string($conn, $_POST['exam_board_2']);
$exam_class_2          = mysqli_real_escape_string($conn, $_POST['exam_class_2']);
$exam_marks_2          = mysqli_real_escape_string($conn, $_POST['exam_marks_2']);
$lateral_exam_name     = mysqli_real_escape_string($conn, $_POST['lateral_exam_name']);
$lateral_exam_year_reg = mysqli_real_escape_string($conn, $_POST['lateral_exam_year_reg']);
$lateral_percentage    = mysqli_real_escape_string($conn, $_POST['lateral_percentage']);

// Page 3
$sports_level          = isset($_POST['sports_level']) ? mysqli_real_escape_string($conn, implode(",", $_POST['sports_level'])) : "";
$ex_servicemen         = $_POST['ex_servicemen'] ?? 'No';
$disabled              = $_POST['disabled'] ?? 'No';
$disability_category   = mysqli_real_escape_string($conn, $_POST['disability_category']);
$parent_vmrf           = $_POST['parent_vmrf'] ?? 'No';
$parent_vmrf_details   = mysqli_real_escape_string($conn, $_POST['parent_vmrf_details']);

// --- (FIX) ADDED sports_claim (if you added the radio buttons) ---
// If you did NOT add "Yes/No" radios, you can delete this line.
$sports_claim          = !empty($sports_level) ? 'Yes' : 'No';


// === 3. UPDATE applications TABLE (Data Fields) ===

$update_sql = "
    UPDATE applications SET
        institution_name      = '$institution_name',
        course                = '$course',
        year_of_study         = '$year_of_study',
        semester              = '$semester',
        gender                = '$gender',
        father_name           = '$father_name',
        mother_name           = '$mother_name',
        community             = '$community',
        caste                 = '$caste',
        family_income         = '$family_income',
        address               = '$address',
        phone_std             = '$phone_std',
        mobile                = '$mobile',
        email                 = '$email',
        exam_name_1           = '$exam_name_1',
        exam_year_reg_1       = '$exam_year_reg_1',
        exam_board_1          = '$exam_board_1',
        exam_class_1          = '$exam_class_1',
        exam_marks_1          = '$exam_marks_1',
        exam_name_2           = '$exam_name_2',
        exam_year_reg_2       = '$exam_year_reg_2',
        exam_board_2          = '$exam_board_2',
        exam_class_2          = '$exam_class_2',
        exam_marks_2          = '$exam_marks_2',
        lateral_exam_name     = '$lateral_exam_name',
        lateral_exam_year_reg = '$lateral_exam_year_reg',
        lateral_percentage    = '$lateral_percentage',
        sports_level          = '$sports_level',
        ex_servicemen         = '$ex_servicemen',
        disabled              = '$disabled',
        disability_category   = '$disability_category',
        parent_vmrf           = '$parent_vmrf',
        parent_vmrf_details   = '$parent_vmrf_details'
    WHERE id = '$application_id'
";

if (!mysqli_query($conn, $update_sql)) {
    $error = mysqli_error($conn);
    die("Error updating application: " . $error);
}


// === 4. HANDLE FILE UPLOADS (REMOVALS AND NEW UPLOADS) ===

// --- (FIX) Corrected path logic ---
function handleFileUpdate($field_name, $file_type, $application_id, $conn) {
    // This is the PHYSICAL path on the server for moving the file
    // `../uploads/` means "go up one level from 'admin' and into 'uploads'"
    $upload_dir_server = "../uploads/"; 
    
    // This is the WEB path we store in the DB
    // `uploads/` means "from the project root, go into 'uploads'"
    $upload_dir_db = "uploads/";

    
    // Check if the user clicked the 'X' to clear an existing file
    if (isset($_POST[$field_name . '_clear_flag']) && $_POST[$field_name . '_clear_flag'] === '1') {
        
        // Find existing file path to delete from server
        $res = mysqli_query($conn, "SELECT file_path FROM application_files WHERE application_id='$application_id' AND file_type='$file_type'");
        if ($row = mysqli_fetch_assoc($res)) {
            // Rebuild the SERVER path to delete: `../` + `uploads/file.pdf`
            $server_path_to_delete = "../" . $row['file_path'];
            @unlink($server_path_to_delete); // Delete file from disk
        }
        // Delete record from database
        mysqli_query($conn, "DELETE FROM application_files WHERE application_id='$application_id' AND file_type='$file_type'");
    }

    // Check for a NEW file upload
    if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($upload_dir_server)) {
            mkdir($upload_dir_server, 0777, true);
        }

        $ext = pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION);
        $safe_filename = uniqid($file_type . "_") . "." . strtolower($ext);
        
        // (FIX) Use the two different paths
        $target_path_server = $upload_dir_server . $safe_filename;
        $target_path_db = $upload_dir_db . $safe_filename;

        if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_path_server)) {
            // Check if a record already exists for this file_type
            $check_res = mysqli_query($conn, "SELECT file_path FROM application_files WHERE application_id='$application_id' AND file_type='$file_type'");
            
            if ($row = mysqli_fetch_assoc($check_res)) {
                // UPDATE: Delete old file and update file path
                @unlink("../" . $row['file_path']); // Rebuild server path to delete
                $sql = "UPDATE application_files SET file_path = '$target_path_db' WHERE application_id = '$application_id' AND file_type = '$file_type'";
            } else {
                // INSERT: New file record
                $sql = "INSERT INTO application_files (application_id, file_type, file_path) VALUES ('$application_id', '$file_type', '$target_path_db')";
            }
            mysqli_query($conn, $sql);
        }
    }
}

// Call updates for each document
handleFileUpdate('sports_proof', 'sports', $application_id, $conn);
handleFileUpdate('ex_servicemen_proof', 'ex_servicemen', $application_id, $conn);
handleFileUpdate('disability_proof', 'disabled', $application_id, $conn);
handleFileUpdate('parent_vmrf_proof', 'parent_vmrf', $application_id, $conn);


// === 5. REDIRECT ===
// Add a success message to the session to be displayed on the edit page
$_SESSION['message'] = ['type' => 'success', 'text' => "Application ID $application_id updated successfully."];
header("Location: edit_application.php?id=$application_id");
exit;
?>
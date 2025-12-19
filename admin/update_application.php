<?php
session_start();

// FIX: Go up one level to find config.php
include("../config.php");

// 1. ADMIN CHECK
if (!isset($_SESSION['admin_id'])) {
    die("Access Denied.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['application_id'])) {
    die("Invalid Request.");
}

$app_id = intval($_POST['application_id']);

// 2. COLLECT DATA (Sanitize)
$name = mysqli_real_escape_string($conn, $_POST['name']);
$institution_name = mysqli_real_escape_string($conn, $_POST['institution_name']);
$course = mysqli_real_escape_string($conn, $_POST['course']);
$year_of_study = mysqli_real_escape_string($conn, $_POST['year_of_study']);
$semester = mysqli_real_escape_string($conn, $_POST['semester']);
$gender = mysqli_real_escape_string($conn, $_POST['gender']);
$father_name = mysqli_real_escape_string($conn, $_POST['father_name']);
$mother_name = mysqli_real_escape_string($conn, $_POST['mother_name']);
$community = mysqli_real_escape_string($conn, $_POST['community']);
$caste = mysqli_real_escape_string($conn, $_POST['caste']);
$family_income = str_replace(',', '', $_POST['family_income']); 
$address = mysqli_real_escape_string($conn, $_POST['address']);
$mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
$email = mysqli_real_escape_string($conn, $_POST['email']);

// Exams
$exam_name = mysqli_real_escape_string($conn, $_POST['exam_name']);
$exam_year_reg = mysqli_real_escape_string($conn, $_POST['exam_year_reg']);
$exam_board = mysqli_real_escape_string($conn, $_POST['exam_board']);
$exam_class = mysqli_real_escape_string($conn, $_POST['exam_class']);
$exam_marks = mysqli_real_escape_string($conn, $_POST['exam_marks']);

// Lateral
$lateral_exam_name = mysqli_real_escape_string($conn, $_POST['lateral_exam_name']);
$lateral_exam_year_reg = mysqli_real_escape_string($conn, $_POST['lateral_exam_year_reg']);
$lateral_percentage = mysqli_real_escape_string($conn, $_POST['lateral_percentage']);

// Special Claims
$sports_level = isset($_POST['sports_level']) ? implode(", ", $_POST['sports_level']) : "";
$ex_servicemen = $_POST['ex_servicemen'] ?? 'No';
$disabled = $_POST['disabled'] ?? 'No';
$disability_category = mysqli_real_escape_string($conn, $_POST['disability_category'] ?? '');
$parent_vmrf = $_POST['parent_vmrf'] ?? 'No';
$parent_vmrf_details = mysqli_real_escape_string($conn, $_POST['parent_vmrf_details'] ?? '');

// 3. UPDATE QUERY
$sql_update = "UPDATE applications SET 
    name = '$name',
    institution_name = '$institution_name',
    course = '$course',
    year_of_study = '$year_of_study',
    semester = '$semester',
    gender = '$gender',
    father_name = '$father_name',
    mother_name = '$mother_name',
    community = '$community',
    caste = '$caste',
    family_income = '$family_income',
    address = '$address',
    mobile = '$mobile',
    email = '$email',
    exam_name = '$exam_name',
    exam_year_reg = '$exam_year_reg',
    exam_board = '$exam_board',
    exam_class = '$exam_class',
    exam_marks = '$exam_marks',
    lateral_exam_name = '$lateral_exam_name',
    lateral_exam_year_reg = '$lateral_exam_year_reg',
    lateral_percentage = '$lateral_percentage',
    sports_level = '$sports_level',
    ex_servicemen = '$ex_servicemen',
    disabled = '$disabled',
    disability_category = '$disability_category',
    parent_vmrf = '$parent_vmrf',
    parent_vmrf_details = '$parent_vmrf_details'
    WHERE id = '$app_id'";

if (!mysqli_query($conn, $sql_update)) {
    die("Error updating record: " . mysqli_error($conn));
}

// 4. HANDLE FILE UPLOADS 
function handleAdminFileUpload($field_name, $file_type, $app_id, $conn) {
    if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
        
        // FIX: Save files to the main 'uploads' folder (one level up), not 'admin/uploads'
        $physical_dir = "../uploads/"; 
        if (!is_dir($physical_dir)) mkdir($physical_dir, 0777, true);

        $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES[$field_name]['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime_type, $allowed_mime)) {
            $ext = pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION);
            // Filename format: type_appid_timestamp_ADMIN.ext
            $filename = $file_type . "_" . $app_id . "_" . time() . "_ADM." . $ext;
            
            $target_path_physical = $physical_dir . $filename; // For moving file
            $target_path_db = "uploads/" . $filename; // For Database (Relative to Root)

            if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_path_physical)) {
                
                // Check if record exists
                $check = mysqli_query($conn, "SELECT id FROM application_files WHERE application_id='$app_id' AND file_type='$file_type'");
                
                if (mysqli_num_rows($check) > 0) {
                    // Update existing path
                    $stmt = $conn->prepare("UPDATE application_files SET file_path=? WHERE application_id=? AND file_type=?");
                    $stmt->bind_param("sis", $target_path_db, $app_id, $file_type);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Insert new record
                    $stmt = $conn->prepare("INSERT INTO application_files (application_id, file_type, file_path) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $app_id, $file_type, $target_path_db);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

// Process Files
handleAdminFileUpload('sports_proof', 'sports', $app_id, $conn);
handleAdminFileUpload('ex_servicemen_proof', 'ex_servicemen', $app_id, $conn);
handleAdminFileUpload('disability_proof', 'disabled', $app_id, $conn);
handleAdminFileUpload('parent_vmrf_proof', 'parent_vmrf', $app_id, $conn);

// 5. REDIRECT BACK
echo "<script>alert('Application updated successfully!'); window.location.href='edit_application.php?id=$app_id';</script>";
exit;
?>
<?php
session_start();
include("../config.php"); 

// 1. Security
if (!isset($_SESSION['admin_id'])) { die("Access Denied"); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die("Invalid Request"); }

// 2. Data Setup
$app_id = intval($_POST['app_id']);
if ($app_id <= 0) die("Invalid Application ID");

// 3. Update Text Data
$sql = "UPDATE applications SET 
        institution_name='".mysqli_real_escape_string($conn, $_POST['institution_name'])."', 
        course='".mysqli_real_escape_string($conn, $_POST['course'])."', 
        year_of_study='".intval($_POST['year_of_study'])."', 
        semester='".intval($_POST['semester'])."',
        gender='".mysqli_real_escape_string($conn, $_POST['gender'])."', 
        father_name='".mysqli_real_escape_string($conn, $_POST['father_name'])."', 
        mother_name='".mysqli_real_escape_string($conn, $_POST['mother_name'])."', 
        community='".mysqli_real_escape_string($conn, $_POST['community'])."', 
        caste='".mysqli_real_escape_string($conn, $_POST['caste'])."',
        family_income='".mysqli_real_escape_string($conn, str_replace(',', '', $_POST['family_income']))."', 
        address='".mysqli_real_escape_string($conn, $_POST['address'])."', 
        mobile='".mysqli_real_escape_string($conn, $_POST['mobile'])."', 
        email='".mysqli_real_escape_string($conn, $_POST['email'])."',
        exam_name_1='".mysqli_real_escape_string($conn, $_POST['exam_name_1'])."', 
        exam_year_reg_1='".mysqli_real_escape_string($conn, $_POST['exam_year_reg_1'])."', 
        exam_board_1='".mysqli_real_escape_string($conn, $_POST['exam_board_1'])."', 
        exam_class_1='".mysqli_real_escape_string($conn, $_POST['exam_class_1'])."', 
        exam_marks_1='".mysqli_real_escape_string($conn, $_POST['exam_marks_1'])."',
        exam_name_2='".mysqli_real_escape_string($conn, $_POST['exam_name_2'])."', 
        exam_year_reg_2='".mysqli_real_escape_string($conn, $_POST['exam_year_reg_2'])."', 
        exam_board_2='".mysqli_real_escape_string($conn, $_POST['exam_board_2'])."', 
        exam_class_2='".mysqli_real_escape_string($conn, $_POST['exam_class_2'])."', 
        exam_marks_2='".mysqli_real_escape_string($conn, $_POST['exam_marks_2'])."',
        lateral_exam_name='".mysqli_real_escape_string($conn, $_POST['lateral_exam_name'])."', 
        lateral_exam_year_reg='".mysqli_real_escape_string($conn, $_POST['lateral_exam_year_reg'])."', 
        lateral_percentage='".mysqli_real_escape_string($conn, $_POST['lateral_percentage'])."',
        sports_level='".(isset($_POST['sports_level']) ? mysqli_real_escape_string($conn, implode(",", $_POST['sports_level'])) : "")."' , 
        ex_servicemen='".($_POST['ex_servicemen'] ?? 'No')."', 
        disabled='".($_POST['disabled'] ?? 'No')."', 
        disability_category='".mysqli_real_escape_string($conn, $_POST['disability_category'])."',
        parent_vmrf='".($_POST['parent_vmrf'] ?? 'No')."', 
        parent_vmrf_details='".mysqli_real_escape_string($conn, $_POST['parent_vmrf_details'])."'
        WHERE id='$app_id'";

if (!mysqli_query($conn, $sql)) {
    die("Database Error: " . mysqli_error($conn));
}

// 4. Signature Handler
$signature_updated = $_POST['signature_updated'] ?? '0';
if ($signature_updated === '1') {
    // Physical path for saving (Admin is in /admin, uploads is in root)
    $upload_dir_server = "../uploads/signatures/"; 
    // DB path (relative to root)
    $upload_dir_db = "uploads/signatures/"; 
    
    if (!is_dir($upload_dir_server)) mkdir($upload_dir_server, 0777, true);

    $sig_path = '';
    $sig_type = $_POST['signature_type'];

    if (($sig_type === 'draw' || $sig_type === 'type') && !empty($_POST['signature_data'])) {
        $data_uri = $_POST['signature_data'];
        // If it's a base64 image string
        if (strpos($data_uri, 'data:image') === 0) {
            $data_uri = substr($data_uri, strpos($data_uri, ',') + 1);
            $decoded = base64_decode($data_uri);
            $filename = "sig_" . $app_id . "_" . time() . ".png";
            if (file_put_contents($upload_dir_server . $filename, $decoded)) {
                $sig_path = $upload_dir_db . $filename;
            }
        } 
        // If it's just text (fallback for 'type' without canvas conversion), we skip or handle differently.
        // Assuming the JS converts type to image on canvas before submit.
    } 
    elseif ($sig_type === 'upload' && isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION);
        $filename = "sig_" . $app_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $upload_dir_server . $filename)) {
            $sig_path = $upload_dir_db . $filename;
        }
    }

    if ($sig_path) {
        mysqli_query($conn, "UPDATE applications SET signature_path = '$sig_path' WHERE id = '$app_id'");
    }
}

// 5. File Upload Handler (Documents)
function handleFileUpload($fileInputName, $dbFileType, $appId, $conn) {
    $uploadDirServer = "../uploads/"; // Physical: Go up one folder from admin
    $uploadDirDB = "uploads/";        // DB: Store relative to root
    
    if (!is_dir($uploadDirServer)) mkdir($uploadDirServer, 0777, true);

    // Handle New Upload
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($ext, $allowed)) {
            $newFileName = $dbFileType . "_" . $appId . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $uploadDirServer . $newFileName)) {
                // Delete old DB entry
                mysqli_query($conn, "DELETE FROM application_files WHERE application_id='$appId' AND file_type='$dbFileType'");
                // Insert new DB entry
                $dbPath = $uploadDirDB . $newFileName;
                $stmt = $conn->prepare("INSERT INTO application_files (application_id, file_type, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $appId, $dbFileType, $dbPath);
                $stmt->execute();
            }
        }
    }
    // Handle Delete Request
    elseif (isset($_POST[$fileInputName . '_clear_flag']) && $_POST[$fileInputName . '_clear_flag'] == '1') {
        $q = mysqli_query($conn, "SELECT file_path FROM application_files WHERE application_id='$appId' AND file_type='$dbFileType'");
        if ($row = mysqli_fetch_assoc($q)) {
            $fileToDelete = "../" . $row['file_path'];
            if (file_exists($fileToDelete)) unlink($fileToDelete);
        }
        mysqli_query($conn, "DELETE FROM application_files WHERE application_id='$appId' AND file_type='$dbFileType'");
    }
}

handleFileUpload('sports_proof', 'sports', $app_id, $conn);
handleFileUpload('ex_servicemen_proof', 'ex_servicemen', $app_id, $conn);
handleFileUpload('disability_proof', 'disabled', $app_id, $conn);
handleFileUpload('parent_vmrf_proof', 'parent_vmrf', $app_id, $conn);

$_SESSION['message'] = ['type' => 'success', 'text' => "Application updated successfully!"];
header("Location: edit_application.php?id=$app_id");
exit;
?>
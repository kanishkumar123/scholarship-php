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
    die("No renewal ID selected.");
}

$renewal_id = (int)$_GET['id'];
$success_message = '';
$error_message = '';

// --- 2. FETCH RENEWAL & APPLICATION DETAILS ---

// Join renewals with applications/students to get names for the header
$stmt = $conn->prepare("
    SELECT r.*, 
           st.name AS student_name, 
           a.application_no,
           sc.name AS scholarship_name,
           a.id AS original_app_id
    FROM renewals r
    JOIN applications a ON r.application_id = a.id
    LEFT JOIN scholarship_students st ON a.student_id = st.id
    LEFT JOIN scholarships sc ON a.scholarship_id = sc.id
    WHERE r.id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $renewal_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Renewal record not found.');
}

$renewal = $result->fetch_assoc();
$stmt->close();

// --- 3. HELPER FUNCTIONS (File Upload) ---

function handle_file_upload($file_key, $upload_dir, $current_path) {
    global $renewal_id; // Use renewal ID for filename uniqueness

    // If no new file is selected, return the OLD path (don't overwrite)
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] == UPLOAD_ERR_NO_FILE) {
        return $current_path;
    }

    // If upload error occurred (other than no file)
    if ($_FILES[$file_key]['error'] != UPLOAD_ERR_OK) {
        return "Error: Upload failed code " . $_FILES[$file_key]['error'];
    }

    $file = $_FILES[$file_key];
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    // Naming convention: renewal_{id}_{type}_{timestamp}.ext
    $safe_filename = "renewal_{$renewal_id}_{$file_key}_" . time() . "." . $file_extension;
    
    $base_upload_path = "../uploads/renewals/"; 
    $target_sub_dir = $base_upload_path . $upload_dir;
    $final_target_path = $target_sub_dir . $safe_filename; 

    // Ensure directory exists
    if (!is_dir($target_sub_dir)) {
        if (!mkdir($target_sub_dir, 0777, true)) {
            return "Error: Could not create upload directory.";
        }
    }
    
    if (move_uploaded_file($file["tmp_name"], $final_target_path)) {
        // Optional: Delete old file if it exists and isn't empty
        if (!empty($current_path) && file_exists($base_upload_path . str_replace($base_upload_path, '', $current_path))) {
            // unlink($current_path); // Uncomment to auto-delete old files
        }
        return $final_target_path; 
    } else {
        return "Error moving uploaded file.";
    }
}

// --- 4. FORM SUBMISSION HANDLER ---

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Inputs
    $year_of_study = filter_input(INPUT_POST, 'year_of_study', FILTER_VALIDATE_INT);
    $previous_year_of_study = filter_input(INPUT_POST, 'previous_year_of_study', FILTER_VALIDATE_INT);
    
    $semester_post = $_POST['semester'] ?? '';
    $semester = ($semester_post !== '') ? (int)$semester_post : null;
    
    $university_reg_no = trim($_POST['university_reg_no'] ?? '');
    $previous_attendance_percentage = trim($_POST['previous_attendance_percentage'] ?? '');
    $marks_sem1 = trim($_POST['marks_sem1'] ?? '');
    $marks_sem2 = trim($_POST['marks_sem2'] ?? '');
    
    $scholarship_receipt = trim($_POST['scholarship_receipt'] ?? 'No');
    $scholarship_particulars = trim($_POST['scholarship_particulars'] ?? '');

    // Validation
    if (!$year_of_study) $error_message = "Current Year of Study is required.";
    elseif (!$previous_year_of_study) $error_message = "Previous Year of Study is required.";
    elseif (empty($university_reg_no)) $error_message = "Register Number is required.";
    elseif (empty($previous_attendance_percentage)) $error_message = "Attendance Percentage is required.";
    elseif (empty($marks_sem1)) $error_message = "Previous Semester Marks are required.";
    elseif ($scholarship_receipt == 'Yes' && empty($scholarship_particulars)) {
        $error_message = "Scholarship Particulars are required if 'Yes' is selected.";
    }

    // File Handling & DB Update
    if (empty($error_message)) {
        
        // Handle files (pass existing path to keep it if no new file uploaded)
        $path_sem1 = handle_file_upload('marks_sem1_file', 'marks_sem1/', $renewal['marks_sem1_file_path']);
        $path_sem2 = handle_file_upload('marks_sem2_file', 'marks_sem2/', $renewal['marks_sem2_file_path']);
        $path_schol = handle_file_upload('scholarship_file', 'scholarship_proof/', $renewal['scholarship_file_path']);

        // Check for upload errors strings
        if (strpos($path_sem1, 'Error') === 0 || strpos($path_sem2, 'Error') === 0 || strpos($path_schol, 'Error') === 0) {
            $error_message = "File upload error. Please try again.";
        } else {
            // Update Database
            $update_sql = "UPDATE renewals SET 
                year_of_study = ?, 
                semester = ?, 
                previous_year_of_study = ?, 
                previous_attendance_percentage = ?, 
                university_reg_no = ?, 
                marks_sem1 = ?, 
                marks_sem2 = ?, 
                marks_sem1_file_path = ?, 
                marks_sem2_file_path = ?, 
                scholarship_receipt = ?, 
                scholarship_particulars = ?, 
                scholarship_file_path = ?
                WHERE id = ?";

            $stmt_upd = $conn->prepare($update_sql);
            // i i i s s s s s s s s s i
            $stmt_upd->bind_param("iiisssssssssi", 
                $year_of_study, 
                $semester, 
                $previous_year_of_study, 
                $previous_attendance_percentage, 
                $university_reg_no, 
                $marks_sem1, 
                $marks_sem2, 
                $path_sem1, 
                $path_sem2, 
                $scholarship_receipt, 
                $scholarship_particulars, 
                $path_schol, 
                $renewal_id
            );

            if ($stmt_upd->execute()) {
                $success_message = "Renewal updated successfully!";
                // Refresh data
                $renewal['year_of_study'] = $year_of_study;
                $renewal['semester'] = $semester;
                $renewal['previous_year_of_study'] = $previous_year_of_study;
                $renewal['previous_attendance_percentage'] = $previous_attendance_percentage;
                $renewal['university_reg_no'] = $university_reg_no;
                $renewal['marks_sem1'] = $marks_sem1;
                $renewal['marks_sem2'] = $marks_sem2;
                $renewal['marks_sem1_file_path'] = $path_sem1;
                $renewal['marks_sem2_file_path'] = $path_sem2;
                $renewal['scholarship_receipt'] = $scholarship_receipt;
                $renewal['scholarship_particulars'] = $scholarship_particulars;
                $renewal['scholarship_file_path'] = $path_schol;
            } else {
                $error_message = "Database Update Failed: " . $stmt_upd->error;
            }
            $stmt_upd->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Renewal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Simplified CSS (Match your theme) */
        body { font-family: "Segoe UI", sans-serif; background: #f5f7fa; padding: 40px 20px; color: #333; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        h2 { text-align: center; margin-bottom: 25px; color: #2c3e50; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; }
        form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        fieldset { grid-column: span 2; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        legend { font-weight: bold; color: #007bff; padding: 0 5px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .form-actions { grid-column: span 2; text-align: right; margin-top: 20px; }
        button { padding: 10px 20px; background: #ffc107; color: #000; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #e0a800; }
        .btn-back { text-decoration: none; color: #6c757d; display: inline-block; margin-bottom: 15px; }
        .current-file { font-size: 0.85em; color: #28a745; margin-top: 5px; display: block; }
        .message-box { padding: 15px; border-radius: 5px; margin-bottom: 20px; grid-column: span 2; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @media (max-width: 700px) { form, fieldset { grid-template-columns: 1fr; } .full-width, fieldset, .form-actions { grid-column: span 1; } }
    </style>
</head>
<body>

<div class="container">
    <a href="view_applications.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to List</a>
    <h2><i class="fas fa-pen-to-square"></i> Edit Renewal Record</h2>

    <?php if ($success_message): ?>
        <div class="message-box success"><i class="fas fa-check-circle"></i> <?= $success_message ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="message-box error"><i class="fas fa-exclamation-triangle"></i> <?= $error_message ?></div>
    <?php endif; ?>

    <div style="grid-column: span 2; background: #e9f5ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; flex-wrap: wrap;">
        <div><strong>Student:</strong> <?= htmlspecialchars($renewal['student_name']) ?></div>
        <div><strong>App No:</strong> <?= htmlspecialchars($renewal['application_no']) ?></div>
        <div><strong>Scholarship:</strong> <?= htmlspecialchars($renewal['scholarship_name']) ?></div>
    </div>

    <form method="post" enctype="multipart/form-data">
        
        <fieldset>
            <legend>Current Status</legend>
            <div>
                <label>Year of Study *</label>
                <select name="year_of_study" required>
                    <?php for($i=1; $i<=5; $i++): ?>
                        <option value="<?= $i ?>" <?= ($renewal['year_of_study'] == $i) ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label>Semester</label>
                <select name="semester">
                    <option value="">-- Select --</option>
                    <?php 
                    $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
                    for($i=1; $i<=10; $i++): ?>
                        <option value="<?= $i ?>" <?= ($renewal['semester'] == $i) ? 'selected' : '' ?>><?= $roman[$i-1] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="full-width">
                <label>University Reg No *</label>
                <input type="text" name="university_reg_no" value="<?= htmlspecialchars($renewal['university_reg_no']) ?>" required>
            </div>
        </fieldset>

        <fieldset>
            <legend>Previous Attendance</legend>
            <div>
                <label>Previous Year *</label>
                <select name="previous_year_of_study" required>
                    <?php for($i=1; $i<=5; $i++): ?>
                        <option value="<?= $i ?>" <?= ($renewal['previous_year_of_study'] == $i) ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label>Attendance % *</label>
                <input type="text" name="previous_attendance_percentage" value="<?= htmlspecialchars($renewal['previous_attendance_percentage']) ?>" required>
            </div>
        </fieldset>

        <fieldset>
            <legend>Academic Performance</legend>
            <div>
                <label>Previous Sem Marks *</label>
                <input type="text" name="marks_sem1" value="<?= htmlspecialchars($renewal['marks_sem1']) ?>" required>
            </div>
            <div>
                <label>Previous Sem File</label>
                <input type="file" name="marks_sem1_file">
                <?php if(!empty($renewal['marks_sem1_file_path'])): ?>
                    <span class="current-file"><i class="fas fa-file-check"></i> File exists. Upload new to replace.</span>
                <?php endif; ?>
            </div>
            <div>
                <label>Current Sem Marks</label>
                <input type="text" name="marks_sem2" value="<?= htmlspecialchars($renewal['marks_sem2']) ?>">
            </div>
            <div>
                <label>Current Sem File</label>
                <input type="file" name="marks_sem2_file">
                <?php if(!empty($renewal['marks_sem2_file_path'])): ?>
                    <span class="current-file"><i class="fas fa-file-check"></i> File exists. Upload new to replace.</span>
                <?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Other Scholarships</legend>
            <div>
                <label>Received Other Scholarship?</label>
                <select name="scholarship_receipt" id="scholarship_receipt" onchange="toggleDetails()">
                    <option value="No" <?= ($renewal['scholarship_receipt'] == 'No') ? 'selected' : '' ?>>No</option>
                    <option value="Yes" <?= ($renewal['scholarship_receipt'] == 'Yes') ? 'selected' : '' ?>>Yes</option>
                </select>
            </div>
            <div id="file_box" style="display:none;">
                <label>Proof File</label>
                <input type="file" name="scholarship_file">
                 <?php if(!empty($renewal['scholarship_file_path'])): ?>
                    <span class="current-file"><i class="fas fa-file-check"></i> File exists. Upload new to replace.</span>
                <?php endif; ?>
            </div>
            <div class="full-width" id="details_box" style="display:none;">
                <label>Particulars *</label>
                <textarea name="scholarship_particulars" rows="2"><?= htmlspecialchars($renewal['scholarship_particulars']) ?></textarea>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit">Update Renewal</button>
        </div>
    </form>
</div>

<script>
function toggleDetails() {
    const val = document.getElementById('scholarship_receipt').value;
    const display = (val === 'Yes') ? 'block' : 'none';
    document.getElementById('details_box').style.display = display;
    document.getElementById('file_box').style.display = display;
}
// Run on load to set correct state
toggleDetails();
</script>

</body>
</html>
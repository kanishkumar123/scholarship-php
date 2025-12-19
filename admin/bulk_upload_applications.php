<?php
session_start();
include("../config.php");

// --- 1. SECURITY ---
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$message = "";
$messageType = "";

// --- 2. HANDLE FILE UPLOAD ---
if (isset($_POST['upload'])) {
    if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        
        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header
        fgetcsv($handle);
        
        // Pre-fetch mappings for ID lookup
        $colleges = [];
        $res = mysqli_query($conn, "SELECT id, name FROM colleges");
        while($r = mysqli_fetch_assoc($res)) $colleges[strtoupper(trim($r['name']))] = $r['id'];

        $programs = [];
        $res = mysqli_query($conn, "SELECT id, name FROM programs");
        while($r = mysqli_fetch_assoc($res)) $programs[strtoupper(trim($r['name']))] = $r['id'];

        $scholarships = [];
        $res = mysqli_query($conn, "SELECT id, name FROM scholarships");
        while($r = mysqli_fetch_assoc($res)) $scholarships[strtoupper(trim($r['name']))] = $r['id'];

        $success_count = 0;
        $error_count = 0;
        $errors = [];
        $row_num = 1; 

        while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
            $row_num++;
            
            // --- DATA MAPPING (Matches the CSV Template) ---
            // Basic Info
            $name = mysqli_real_escape_string($conn, trim($data[0] ?? ''));
            $app_no = mysqli_real_escape_string($conn, trim($data[1] ?? '')); // If empty, generate?
            $academic_year = mysqli_real_escape_string($conn, trim($data[2] ?? ''));
            $dob = mysqli_real_escape_string($conn, trim($data[3] ?? ''));
            $gender = mysqli_real_escape_string($conn, trim($data[4] ?? ''));
            $mobile = mysqli_real_escape_string($conn, trim($data[5] ?? ''));
            $email = mysqli_real_escape_string($conn, trim($data[6] ?? ''));
            
            // Parent Info
            $father = mysqli_real_escape_string($conn, trim($data[7] ?? ''));
            $mother = mysqli_real_escape_string($conn, trim($data[8] ?? ''));
            $parent_mobile = mysqli_real_escape_string($conn, trim($data[9] ?? ''));
            $income = floatval(preg_replace('/[^0-9.]/', '', $data[10] ?? 0));
            
            // Details
            $community = mysqli_real_escape_string($conn, trim($data[11] ?? ''));
            $caste = mysqli_real_escape_string($conn, trim($data[12] ?? ''));
            $address = mysqli_real_escape_string($conn, trim($data[13] ?? ''));
            
            // Academic 
            $college_str = strtoupper(trim($data[14] ?? ''));
            $course_str = strtoupper(trim($data[15] ?? ''));
            $year_study = intval($data[16] ?? 1);
            $semester = intval($data[17] ?? 1);
            $scholarship_str = strtoupper(trim($data[18] ?? ''));
            
            // Exam Marks (Last Exam)
            $exam_name = mysqli_real_escape_string($conn, trim($data[19] ?? ''));
            $exam_reg = mysqli_real_escape_string($conn, trim($data[20] ?? ''));
            $exam_board = mysqli_real_escape_string($conn, trim($data[21] ?? ''));
            $exam_class = mysqli_real_escape_string($conn, trim($data[22] ?? ''));
            $exam_marks = mysqli_real_escape_string($conn, trim($data[23] ?? ''));
            
            // Lateral Entry (Optional)
            $lat_name = mysqli_real_escape_string($conn, trim($data[24] ?? ''));
            $lat_reg = mysqli_real_escape_string($conn, trim($data[25] ?? ''));
            $lat_pct = mysqli_real_escape_string($conn, trim($data[26] ?? ''));
            
            // Special Claims (Optional)
            $sports = mysqli_real_escape_string($conn, trim($data[27] ?? ''));
            $ex_service = mysqli_real_escape_string($conn, trim($data[28] ?? 'No'));
            $disabled = mysqli_real_escape_string($conn, trim($data[29] ?? 'No'));
            $disability_cat = mysqli_real_escape_string($conn, trim($data[30] ?? ''));
            $parent_vmrf = mysqli_real_escape_string($conn, trim($data[31] ?? 'No'));
            $parent_vmrf_det = mysqli_real_escape_string($conn, trim($data[32] ?? ''));

            // --- VALIDATION ---
            $row_errors = [];

            // 1. Mandatory Fields check
            if(empty($name) || empty($mobile) || empty($dob) || empty($academic_year)) $row_errors[] = "Missing Basic Info";
            
            // 2. ID Mapping Check
            if(empty($college_str) || !isset($colleges[$college_str])) $row_errors[] = "Invalid College: " . htmlspecialchars($data[14]);
            if(empty($course_str) || !isset($programs[$course_str])) $row_errors[] = "Invalid Course: " . htmlspecialchars($data[15]);
            if(empty($scholarship_str) || !isset($scholarships[$scholarship_str])) $row_errors[] = "Invalid Scholarship: " . htmlspecialchars($data[18]);

            // 3. Application No Logic
            if (empty($app_no)) {
                $app_no = mt_rand(10000000, 99999999); // Auto-generate if missing
            }

            if (empty($row_errors)) {
                $college_id = $colleges[$college_str];
                $course_id = $programs[$course_str];
                $scholarship_id = $scholarships[$scholarship_str];

                // INSERT QUERY (Matches all columns in your DB table)
                $sql = "INSERT INTO applications 
                (application_no, name, academic_year, dob, gender, mobile, email, 
                 father_name, mother_name, parent_mobile, family_income, 
                 community, caste, address, 
                 institution_name, course, year_of_study, semester, scholarship_id, 
                 exam_name, exam_year_reg, exam_board, exam_class, exam_marks, 
                 lateral_exam_name, lateral_exam_year_reg, lateral_percentage, 
                 sports_level, ex_servicemen, disabled, disability_category, 
                 parent_vmrf, parent_vmrf_details, 
                 status, submitted_at) 
                VALUES 
                ('$app_no', '$name', '$academic_year', '$dob', '$gender', '$mobile', '$email', 
                 '$father', '$mother', '$parent_mobile', '$income', 
                 '$community', '$caste', '$address', 
                 '$college_id', '$course_id', '$year_study', '$semester', '$scholarship_id', 
                 '$exam_name', '$exam_reg', '$exam_board', '$exam_class', '$exam_marks', 
                 '$lat_name', '$lat_reg', '$lat_pct', 
                 '$sports', '$ex_service', '$disabled', '$disability_cat', 
                 '$parent_vmrf', '$parent_vmrf_det', 
                 'Pending', NOW())";

                if (mysqli_query($conn, $sql)) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = "Row $row_num: DB Error - " . mysqli_error($conn);
                }
            } else {
                $error_count++;
                $errors[] = "Row $row_num: " . implode(", ", $row_errors);
            }
        }
        fclose($handle);

        if ($error_count == 0) {
            $message = "Success! Uploaded $success_count applications.";
            $messageType = "success";
        } else {
            $message = "Uploaded $success_count. Failed $error_count rows.";
            $messageType = "warning";
        }
    } else {
        $message = "Please upload a valid CSV file.";
        $messageType = "error";
    }
}

// --- 3. HANDLE TEMPLATE DOWNLOAD ---
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="full_application_template.csv"');
    $output = fopen('php://output', 'w');
    
    // Headers
    $headers = [
        'Student Name*', 'Application No (Optional)', 'Academic Year*', 'DOB (YYYY-MM-DD)*', 'Gender*', 'Mobile*', 'Email*', 
        'Father Name*', 'Mother Name*', 'Parent Mobile', 'Annual Income*', 
        'Community*', 'Caste', 'Address*', 
        'College Name (Exact)*', 'Course Name (Exact)*', 'Year (1-5)*', 'Semester (1-10)', 'Scholarship Name (Exact)*', 
        'Exam Name (HSC)', 'Exam Reg No', 'Exam Board', 'Exam Class', 'Exam Marks', 
        'Lateral Exam Name', 'Lateral Reg No', 'Lateral %', 
        'Sports Level', 'Ex-Servicemen (Yes/No)', 'Disabled (Yes/No)', 'Disability Category', 'Parent VMRF (Yes/No)', 'Parent Details'
    ];
    fputcsv($output, $headers);
    
    // Sample Data
    $sample = [
        'John Doe', '', '2025-2026', '2002-05-20', 'Male', '9876543210', 'john@test.com', 
        'David Doe', 'Sarah Doe', '9876500000', '150000', 
        'BC', 'Vanniyar', '123 Main St, Salem', 
        'Vinayaka Mission Kirupananda Variyar Engineering College', 'B.E. Computer Science and Engineering', '1', '1', 'Merit Scholarship', 
        'HSC', '123456', 'State Board', 'First', '85%', 
        '', '', '', 
        'District', 'No', 'No', '', 'No', ''
    ];
    fputcsv($output, $sample);
    
    fclose($output);
    exit;
}

$pageTitle = "Bulk Application Upload";
$currentPage = 'bulk_upload';
include('header.php'); 
?>

<style>
    .upload-area { border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 12px; padding: 40px; text-align: center; margin-bottom: 25px; transition: 0.2s; }
    .upload-area:hover { border-color: #3b82f6; background: #eff6ff; }
    .btn-download { display: inline-flex; align-items: center; gap: 8px; color: #3b82f6; border: 1px solid #3b82f6; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
    .btn-download:hover { background: #eff6ff; }
    .error-log { background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin-top: 20px; color: #991b1b; font-size: 0.9rem; max-height: 200px; overflow-y: auto; }
</style>

<div class="dashboard-container">
    <div class="page-header">
        <div>
            <h2>Bulk Upload Applications</h2>
            <p>Directly import full application data into the system.</p>
        </div>
        <a href="bulk_upload_applications.php?download_template=1" class="btn-download">
            <i class="fa-solid fa-download"></i> Download Template
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div style="padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; 
            background: <?= $messageType == 'success' ? '#dcfce7' : '#fee2e2' ?>; 
            color: <?= $messageType == 'success' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="box">
        <form method="post" enctype="multipart/form-data">
            <div class="upload-area">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 3rem; color: #94a3b8; margin-bottom: 15px;"></i>
                <h3 style="margin:0 0 10px 0; color:#334155;">Upload CSV File</h3>
                <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">Use the template to ensure correct format.</p>
                
                <input type="file" name="file" id="file" accept=".csv" required style="display: none;" onchange="document.getElementById('fileName').textContent = this.files[0].name">
                <button type="button" class="button button-primary" onclick="document.getElementById('file').click()">Select File</button>
                <div id="fileName" style="margin-top: 10px; font-weight: 600; color: #3b82f6;"></div>
            </div>

            <div style="text-align: right;">
                <button type="submit" name="upload" class="button button-success">
                    <i class="fa-solid fa-check"></i> Import Applications
                </button>
            </div>
        </form>

        <?php if (!empty($errors)): ?>
            <div class="error-log">
                <strong><i class="fa-solid fa-triangle-exclamation"></i> Errors found:</strong>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="box" style="margin-top: 20px;">
        <h3>Instructions</h3>
        <ul style="color: #64748b; font-size: 0.9rem; line-height: 1.6;">
            <li><strong>Mandatory:</strong> Name, Academic Year, DOB, Gender, Mobile, Email, Father Name, Mother Name, Income, Community, Address, College, Course, Year, Scholarship Name.</li>
            <li><strong>Exact Match:</strong> College Name, Course Name, and Scholarship Name must match the system spelling exactly.</li>
            <li><strong>Dates:</strong> Use <code>YYYY-MM-DD</code> format.</li>
            <li><strong>Lateral Entry:</strong> Leave lateral exam columns blank if not applicable.</li>
        </ul>
    </div>
</div>

<?php include('footer.php'); ?>
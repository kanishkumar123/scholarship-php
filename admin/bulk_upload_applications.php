<?php
// --- 1. ALL PHP LOGIC GOES FIRST ---
session_start();
include("../config.php");

// --- 2. Security & Login Checks ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Admin') {
    die("Access Denied: You do not have permission to view this page.");
}

// --- 3. Handle File Upload on POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['applications_csv'])) {
    // --- Collect and Validate Context ---
    $scholarship_id = isset($_POST['scholarship_id']) ? intval($_POST['scholarship_id']) : 0;
    $academic_year = isset($_POST['academic_year']) ? mysqli_real_escape_string($conn, $_POST['academic_year']) : '';

    if ($scholarship_id <= 0 || empty($academic_year)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a valid Scholarship and Academic Year before uploading.'];
        header("Location: bulk_upload_applications.php");
        exit;
    }

    $file = $_FILES['applications_csv'];
    // --- Basic File Validation ---
    if ($file['error'] !== UPLOAD_ERR_OK || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid file. Please upload a valid .csv file.'];
        header("Location: bulk_upload_applications.php");
        exit;
    }

    // --- 4. Process the CSV File within a Transaction ---
    $csv_file = fopen($file['tmp_name'], 'r');
    
    // --- Loop to find the actual header row and skip instructions ---
    $header = null;
    while (($row_data = fgetcsv($csv_file)) !== FALSE) {
        if (empty($row_data) || !isset($row_data[0]) || trim($row_data[0]) === '' || strpos(trim($row_data[0]), '#') === 0) {
            continue; // Skip this line
        } else {
            $header = array_map(function($h) { return str_replace(' (*)', '', $h); }, $row_data); 
            break; 
        }
    }

    if ($header === null) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Could not find a valid header row in the CSV file.'];
        header("Location: bulk_upload_applications.php");
        exit;
    }
        
    $applications_to_insert = [];
    $errors = [];
    $row_number = 1; // Header row

    mysqli_begin_transaction($conn);
    try {
        while (($row = fgetcsv($csv_file)) !== FALSE) {
            $row_number++;
            
            // --- A. Look up student_id from reg_no ---
            $reg_no = trim($row[0] ?? '');
            if (empty($reg_no)) {
                $errors[] = "Row $row_number: Registration Number (column 1) is required.";
                continue;
            }
            $student_res = mysqli_query($conn, "SELECT id FROM scholarship_students WHERE application_no = '" . mysqli_real_escape_string($conn, $reg_no) . "'");
            if (mysqli_num_rows($student_res) === 0) {
                $errors[] = "Row $row_number: Student with Registration Number '$reg_no' not found. Please run 'Fresh Applications Import' first.";
                continue;
            }
            $student = mysqli_fetch_assoc($student_res);
            $student_id = $student['id'];

            // --- B. Map CSV columns to variables (matching the full list) ---
            $data = [
                'student_id' => $student_id,
                'scholarship_id' => $scholarship_id,
                'academic_year' => $academic_year,
                'institution_name' => trim($row[1] ?? ''), // This should be the College ID
                'course' => trim($row[2] ?? ''), // This should be the Program ID
                'year_of_study' => intval($row[3] ?? 0),
                'semester' => intval($row[4] ?? 0),
                'gender' => trim($row[5] ?? ''),
                'father_name' => trim($row[6] ?? ''),
                'mother_name' => trim($row[7] ?? ''),
                'community' => trim($row[8] ?? ''),
                'caste' => trim($row[9] ?? ''),
                'family_income' => str_replace(',', '', trim($row[10] ?? '0')),
                'address' => trim($row[11] ?? ''),
                'phone_std' => trim($row[12] ?? ''),
                'mobile' => trim($row[13] ?? ''),
                'email' => trim($row[14] ?? ''),
                'exam_name_1' => trim($row[15] ?? ''),
                'exam_year_reg_1' => trim($row[16] ?? ''),
                'exam_board_1' => trim($row[17] ?? ''),
                'exam_class_1' => trim($row[18] ?? ''),
                'exam_marks_1' => trim($row[19] ?? ''),
                'exam_name_2' => trim($row[20] ?? ''),
                'exam_year_reg_2' => trim($row[21] ?? ''),
                'exam_board_2' => trim($row[22] ?? ''),
                'exam_class_2' => trim($row[23] ?? ''),
                'exam_marks_2' => trim($row[24] ?? ''),
                'lateral_exam_name' => trim($row[25] ?? ''),
                'lateral_exam_year_reg' => trim($row[26] ?? ''),
                'lateral_percentage' => trim($row[27] ?? ''),
                'sports_level' => trim($row[28] ?? ''),
                'ex_servicemen' => trim($row[29] ?? 'No'),
                'disabled' => trim($row[30] ?? 'No'),
                'disability_category' => trim($row[31] ?? ''),
                'parent_vmrf' => trim($row[32] ?? 'No'),
                'parent_vmrf_details' => trim($row[33] ?? '')
            ];

            // --- C. Validate required fields ---
            if (empty($data['institution_name']) || empty($data['course']) || empty($data['gender'])) {
                $errors[] = "Row $row_number: Institution, Course, and Gender are required fields.";
                continue;
            }

            // --- D. Sanitize and prepare for bulk insert ---
            $sanitized_values = array_map(function($value) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $value) . "'";
            }, $data);
            $applications_to_insert[] = "(" . implode(',', $sanitized_values) . ")";
        }

        if (!empty($errors)) {
            throw new Exception("Validation failed.");
        }

        if (!empty($applications_to_insert)) {
            // Explicitly list all 36 columns in the correct order
            $columns = "
                (student_id, scholarship_id, academic_year, institution_name, course, year_of_study, semester, 
                gender, father_name, mother_name, community, caste, family_income, address, 
                phone_std, mobile, email, exam_name_1, exam_year_reg_1, exam_board_1, exam_class_1, 
                exam_marks_1, exam_name_2, exam_year_reg_2, exam_board_2, exam_class_2, exam_marks_2, 
                lateral_exam_name, lateral_exam_year_reg, lateral_percentage, sports_level, 
                ex_servicemen, disabled, disability_category, parent_vmrf, parent_vmrf_details)
            ";
            $query = "INSERT INTO applications $columns VALUES " . implode(',', $applications_to_insert);

            if (!mysqli_query($conn, $query)) {
                throw new Exception("Database insert failed: " . mysqli_error($conn));
            }
            mysqli_commit($conn);
            $_SESSION['message'] = ['type' => 'success', 'text' => count($applications_to_insert) . " applications were successfully imported!"];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => "No valid application data found in the file."];
        }

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_list = implode("<br>", $errors);
        $_SESSION['message'] = ['type' => 'error', 'text' => "<strong>Import Failed!</strong> No data was saved.<br><br><strong>Errors:</strong><br>" . $error_list . "<br><br><strong>MySQL Error:</strong><br>" . $e->getMessage()];
    }
    
    fclose($csv_file);
    header("Location: bulk_upload_applications.php");
    exit;
}

// --- 5. Fetch Data for Page Display ---
$scholarships = mysqli_query($conn, "SELECT id, name FROM scholarships ORDER BY name ASC");
$academic_years = mysqli_query($conn, "SELECT year_range FROM academic_years ORDER BY year_range DESC");

// --- 6. Set Page Variables ---
$currentPage = 'bulk_upload';
$pageTitle = "Bulk Upload Applications";
$pageSubtitle = "Import multiple applications from a single CSV file";

// --- 7. Include Header (HTML starts here) ---
include('header.php'); 
?>

<?php
// Display success/error messages
if (isset($_SESSION['message'])) {
    // Use nl2br to respect newlines in error messages
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . nl2br($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']);
}
?>

<form method="post" enctype="multipart/form-data" class="box">
    <div class="form-grid">
        <div class="filter-group">
            <label for="scholarship_id">1. Select Scholarship</label>
            <select id="scholarship_id" name="scholarship_id" required>
                <option value="">-- Choose a Scholarship --</option>
                <?php while($row = mysqli_fetch_assoc($scholarships)): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="academic_year">2. Select Academic Year</label>
            <select id="academic_year" name="academic_year" required>
                <option value="">-- Choose a Year --</option>
                 <?php while($row = mysqli_fetch_assoc($academic_years)): ?>
                    <option value="<?= $row['year_range'] ?>"><?= htmlspecialchars($row['year_range']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div  style="margin-top: 30px;">
        <label for="applications_csv">3. Upload CSV File</label>
        <div class="file-upload-wrapper">
            <input type="file" id="applications_csv" name="applications_csv" class="file-upload-input" accept=".csv" required>
            <label for="applications_csv" class="file-upload-label">
                <span class="file-upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                <span class="file-upload-text">Choose file... (See instructions below)</span>
            </label>
        </div>
    </div>

    <div class="form-actions" style="justify-content: flex-end;">
        <button type="submit" class="button button-primary">Upload and Import Applications</button>
    </div>
</form>

<div class="box">
    <h3>CSV File Instructions</h3>
    
    <ul class="instruction-list">
        <li>The first row of your file must be the headers. <a href="download_template.php" style="font-weight: 600;">Download the template</a> to ensure the correct order.</li>
        <li>The student's <strong>reg_no (*)</strong> must already exist in the system (from 'Fresh Applications Import').</li>
        <li><strong>institution_name (*)</strong> must be the **ID** from the `colleges` table.</li>
        <li><strong>course (*)</strong> must be the **ID** from the `programs` table.</li>
        <li><strong>family_income (*)</strong> should be a number without commas (e.g., `150000`).</li>
        <li>For fields like <strong>ex_servicemen</strong>, use the text 'Yes' or 'No'.</li>
    </ul>

    <p>
        <a href="download_template.php" class="button" style="background-color: var(--text-secondary); color: white;">
            <i class="fa-solid fa-download"></i> Download CSV Template
        </a>
    </p>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script>
    document.addEventListener('DOMContentLoaded', () => {
        const fileInput = document.getElementById('applications_csv');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'Choose file...';
                // Find the label's text span and update it
                const textSpan = this.closest('.file-upload-wrapper').querySelector('.file-upload-text');
                if (textSpan) {
                    textSpan.textContent = fileName;
                }
            });
        }
    });
</script>
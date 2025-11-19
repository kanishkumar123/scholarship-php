<?php
// --- 1. ALL PHP LOGIC GOES FIRST ---
session_start();
include("../config.php");

// --- 2. Security & Login Checks ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 3. Initialize Variables & Fetch Data ---
$message = ""; 
$upload_summary = null; 

// Fetch colleges for dropdown
$colleges = mysqli_query($conn, "SELECT * FROM colleges ORDER BY name ASC");

// Fetch academic years for dropdown
$academic_years = mysqli_query($conn, "SELECT * FROM academic_years ORDER BY id DESC");

// Fetch scholarship codes map
$scholarship_map = [];
$scholarship_code_query = mysqli_query($conn, "SELECT id, scholarshipcode FROM scholarships");
while ($row = mysqli_fetch_assoc($scholarship_code_query)) {
    $scholarship_map[trim($row['scholarshipcode'])] = $row['id'];
}

// --- 4. Handle POST Upload Logic ---
if (isset($_POST['upload'])) {
    // Get selected institution and year
    $institution_name = mysqli_real_escape_string($conn, $_POST['institution_name']);
    $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);

    if (!empty($_FILES['file']['name'])) {
        $filename = $_FILES['file']['tmp_name'];
        $handle = fopen($filename, "r");

        $successful_imports = [];
        $duplicate_entries = [];
        $failed_validations = [];
        $row_number = 0; 

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_number++;
            if ($row_number == 1) continue; // Skip header

            // CSV Columns: 0=app_no, 1=dob, 2=name, 3=scholarship_code
            $app_no = mysqli_real_escape_string($conn, $data[0] ?? null);
            $dob_raw = trim($data[1] ?? null); 
            $name = mysqli_real_escape_string($conn, $data[2] ?? null);
            $scholarship_code_csv = trim($data[3] ?? null);

            $row_identifier = "Row $row_number: $name ($app_no)";

            // --- Validation 1: Integrity ---
            $dob_obj = DateTime::createFromFormat('d-m-Y', $dob_raw);
            $dob = $dob_obj ? $dob_obj->format('Y-m-d') : null;
            $scholarship_id = $scholarship_map[$scholarship_code_csv] ?? null;

            if (!$dob || empty($app_no) || empty($name) || !$scholarship_id) {
                $reason = "Invalid data";
                if (empty($app_no)) $reason = "Missing App No";
                elseif (empty($name)) $reason = "Missing Name";
                elseif (!$dob) $reason = "Bad DOB (use d-m-Y)";
                elseif (!$scholarship_id) $reason = "Unknown Scholarship Code";
                $failed_validations[] = "$row_identifier - $reason";
                continue; 
            }

            // --- Validation 2: Duplicate Check ---
            $check_sql = "SELECT id FROM scholarship_students 
                          WHERE application_no = '$app_no' AND academic_year = '$academic_year'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $duplicate_entries[] = $row_identifier;
                continue;
            }

            // --- Success: Insert ---
            // ★ FIXED: Added institution_name to the INSERT statement
            $query = "INSERT INTO scholarship_students 
                      (scholarship_id, academic_year, application_no, dob, name, institution_name) 
                      VALUES ('$scholarship_id','$academic_year','$app_no','$dob','$name', '$institution_name')";
            
            if (mysqli_query($conn, $query)) {
                // Note: We do NOT update 'applications' table here. 
                // The student will fill that table when they log in and apply.
                $successful_imports[] = $row_identifier;
            } else {
                $failed_validations[] = "$row_identifier - SQL Error: " . mysqli_error($conn);
            }
        }
        fclose($handle);
        
        // Summary Generation
        $import_count = count($successful_imports);
        $duplicate_count = count($duplicate_entries);
        $failure_count = count($failed_validations);
        $message = "Upload Complete. Imported: $import_count | Duplicates: $duplicate_count | Failures: $failure_count.";
        $upload_summary = ['success' => $successful_imports, 'duplicates' => $duplicate_entries, 'failures' => $failed_validations];

    } else {
        $message = "Error: No file selected.";
    }
}

$currentPage = 'fresh_upload';
$pageTitle = "Upload Student Data";
$pageSubtitle = "Import student records via CSV";
include('header.php'); 
?>

<div class="box">
    <?php if (!empty($message)) { ?>
        <p class="alert <?= strpos($message, 'Error') !== false || (isset($failure_count) && $failure_count > 0) ? 'alert-error' : 'alert-success' ?>">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php } ?>

    <?php if (!empty($upload_summary)): ?>
        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd;">
            <strong>Summary:</strong><br>
            ✅ Success: <?= count($upload_summary['success']) ?><br>
            ⚠️ Duplicates: <?= count($upload_summary['duplicates']) ?><br>
            ❌ Failures: <?= count($upload_summary['failures']) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="filter-group">
            <label for="institution_name">Select Institution:</label>
            <select id="institution_name" name="institution_name" required>
                <option value="">-- Select --</option>
                <?php 
                mysqli_data_seek($colleges, 0);
                while ($row = mysqli_fetch_assoc($colleges)) { 
                ?>
                    <option value="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="filter-group" style="margin-top: 20px;">
            <label for="academic_year">Select Academic Year:</label>
            <select id="academic_year" name="academic_year" required>
                <option value="">-- Select --</option>
                <?php 
                mysqli_data_seek($academic_years, 0);
                while ($row = mysqli_fetch_assoc($academic_years)) { ?>
                    <option value="<?= htmlspecialchars($row['year_range']) ?>"><?= htmlspecialchars($row['year_range']) ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="filter-group" style="margin-top: 20px;">
            <label for="file">Upload CSV:</label>
            <input type="file" id="file" name="file" accept=".csv" required style="padding: 10px;">
        </div>

        <button type="submit" name="upload" class="button button-primary" style="width: 100%; margin-top: 25px;">Upload</button>
    </form>
    
    <div style="margin-top: 20px;">
         <a href="download_sample.php" class="back-link">⬇️ Download Sample CSV</a> | 
         <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</div>
<?php include('footer.php'); ?>
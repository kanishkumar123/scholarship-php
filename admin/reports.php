<?php
session_start();
include("../config.php");

// --- Security Check ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- CSV Export Logic ---
// THIS BLOCK MUST RUN *BEFORE* header.php IS INCLUDED
if (isset($_POST['export_csv'])) {
    $sid = $_POST['scholarship_id'];

    // Fetch all student application data for the selected scholarship
    $query = "SELECT 
                s.application_no, s.name, s.dob, a.gender, a.father_name, a.mother_name, a.community, a.caste,
                a.institution_name, a.course, a.year_of_study, a.semester, a.family_income, a.address,
                a.phone_std, a.mobile, a.email,
                a.exam_name_1, a.exam_year_reg_1, a.exam_board_1, a.exam_class_1, a.exam_marks_1,
                a.exam_name_2, a.exam_year_reg_2, a.exam_board_2, a.exam_class_2, a.exam_marks_2,
                a.lateral_exam_name, a.lateral_exam_year_reg, a.lateral_percentage,
                a.sports_level, a.ex_servicemen, a.disabled, a.disability_category,
                a.parent_vmrf, a.parent_vmrf_details
              FROM applications a
              JOIN scholarship_students s ON a.student_id = s.id
              WHERE a.scholarship_id = '$sid'";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=scholarship_report.csv');
    $output = fopen("php://output", "w");

    // CSV headers
    fputcsv($output, [
        'Application No', 'Name', 'DOB', 'Gender', 'Father Name', 'Mother Name', 'Community', 'Caste',
        'Institution Name', 'Course', 'Year of Study', 'Semester', 'Family Income', 'Address',
        'Phone', 'Mobile', 'Email',
        'Exam 1 Name', 'Exam 1 Year/Reg', 'Exam 1 Board', 'Exam 1 Class', 'Exam 1 Marks',
        'Exam 2 Name', 'Exam 2 Year/Reg', 'Exam 2 Board', 'Exam 2 Class', 'Exam 2 Marks',
        'Lateral Exam Name', 'Lateral Exam Year/Reg', 'Lateral Percentage',
        'Sports Level', 'Ex-Servicemen', 'Disabled', 'Disability Category',
        'Parent in VMRF-DU', 'Parent Details'
    ]);

    while ($row = mysqli_fetch_assoc($result)) {
        $row['sports_level'] = is_array($row['sports_level']) ? implode(', ', $row['sports_level']) : $row['sports_level'];
        fputcsv($output, $row);
    }

    fclose($output);
    exit; // Stop script execution after file download
}

// --- If NOT exporting, set variables and include the HTML header ---
$currentPage = 'reports';
$pageTitle = "Export Reports";
$pageSubtitle = "Download application data as CSV files";

include('header.php'); // Includes <head>, <body>, <sidebar>, and <header>

// --- Page-Specific Data Fetching (for the form) ---
$scholarships = mysqli_query($conn, "SELECT * FROM scholarships");
?>

<div class="box">
    <h3>Export Scholarship Report</h3>
    <p style="color: var(--text-secondary); margin-top: -10px; margin-bottom: 25px;">
        Select a scholarship to download all associated application data in a single CSV file.
    </p>
    
    <form method="post">
        <div class="filter-group">
            <label for="scholarship_id">Select Scholarship:</label>
            <select id="scholarship_id" name="scholarship_id" required>
                <option value="">-- Select --</option>
                <?php 
                // Reset query pointer just in case
                mysqli_data_seek($scholarships, 0); 
                while ($row = mysqli_fetch_assoc($scholarships)) { ?>
                    <option value="<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php } ?>
            </select>
        </div>
        
        <button type="submit" name="export_csv" class="button button-primary" style="margin-top: 20px; width: 100%;">
            Export as CSV
        </button>
    </form>
</div>

<p><a href="dashboard.php" class="back-link">← Back to Dashboard</a></p>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>
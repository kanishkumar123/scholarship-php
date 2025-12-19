<?php
session_start();
// ⭐️ FIX: Go up one level to find config.php
include("../config.php");

// --- 1. SECURITY ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. CONFIGURATION ---
// Define available columns with ALIASES to prevent name collisions
$available_columns = [
    'a.application_no' => 'Application No',
    'a.name'           => 'Student Name',
    'a.academic_year'  => 'Academic Year',
    'c.name AS college_name' => 'Institution',
    'p.name AS program_name' => 'Program / Course',
    's.name AS scholarship_name' => 'Scholarship Scheme',
    'a.year_of_study'  => 'Year of Study',
    'a.mobile'         => 'Mobile Number',
    'a.parent_mobile'  => 'Parent Mobile',
    'a.email'          => 'Email ID',
    'a.gender'         => 'Gender',
    'a.community'      => 'Community',
    'a.family_income'  => 'Annual Income',
    'a.status'         => 'App Status',
    'a.submitted_at'   => 'Submission Date'
];

// Map Aliases back to clean keys for data retrieval in the loop
$column_keys = [
    'a.application_no' => 'application_no',
    'a.name'           => 'name',
    'a.academic_year'  => 'academic_year',
    'c.name AS college_name' => 'college_name',
    'p.name AS program_name' => 'program_name',
    's.name AS scholarship_name' => 'scholarship_name',
    'a.year_of_study'  => 'year_of_study',
    'a.mobile'         => 'mobile',
    'a.parent_mobile'  => 'parent_mobile',
    'a.email'          => 'email',
    'a.gender'         => 'gender',
    'a.community'      => 'community',
    'a.family_income'  => 'family_income',
    'a.status'         => 'status',
    'a.submitted_at'   => 'submitted_at'
];

// Fetch Dropdown Data
$colleges_list = mysqli_query($conn, "SELECT id, name FROM colleges ORDER BY name");
$scholarships_list = mysqli_query($conn, "SELECT id, name FROM scholarships ORDER BY name");
$years_list = mysqli_query($conn, "SELECT DISTINCT academic_year FROM applications ORDER BY academic_year DESC");

// --- 3. FILTER & QUERY BUILDER LOGIC ---
$results = [];
$is_export = false;

// Initialize inputs
$selected_cols = array_keys($available_columns);
$f_college = $_GET['college'] ?? '';
$f_scholarship = $_GET['scholarship'] ?? '';
$f_year = $_GET['academic_year'] ?? '';
$f_status = $_GET['status'] ?? '';
$f_gender = $_GET['gender'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['generate'])) {
    
    // 1. Get Selected Columns
    if (isset($_GET['cols']) && is_array($_GET['cols'])) {
        $selected_cols = $_GET['cols'];
    }

    // 2. Build SELECT Clause (Use the aliased definitions)
    $select_clause = implode(", ", $selected_cols);

    // 3. Build WHERE Clause
    $conditions = ["1=1"];
    
    if (!empty($f_college)) {
        $safe_col = mysqli_real_escape_string($conn, $f_college);
        $conditions[] = "a.institution_name = '$safe_col'";
    }
    if (!empty($f_scholarship)) {
        $safe_sch = mysqli_real_escape_string($conn, $f_scholarship);
        $conditions[] = "a.scholarship_id = '$safe_sch'";
    }
    if (!empty($f_year)) {
        $safe_year = mysqli_real_escape_string($conn, $f_year);
        $conditions[] = "a.academic_year = '$safe_year'";
    }
    if (!empty($f_status)) {
        $safe_stat = mysqli_real_escape_string($conn, $f_status);
        $conditions[] = "a.status = '$safe_stat'";
    }
    if (!empty($f_gender)) {
        $safe_gen = mysqli_real_escape_string($conn, $f_gender);
        $conditions[] = "a.gender = '$safe_gen'";
    }

    $where_sql = implode(' AND ', $conditions);

    // 4. Construct Query
    $query = "SELECT $select_clause 
              FROM applications a
              LEFT JOIN colleges c ON a.institution_name = c.id
              LEFT JOIN programs p ON a.course = p.id
              LEFT JOIN scholarships s ON a.scholarship_id = s.id
              WHERE $where_sql
              ORDER BY a.id DESC";

    // 5. Handle Export
    if (isset($_GET['export']) && $_GET['export'] == 'true') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Scholarship_Report_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        
        // Write CSV Headers
        $csv_headers = [];
        foreach ($selected_cols as $col_def) {
            $csv_headers[] = $available_columns[$col_def];
        }
        fputcsv($output, $csv_headers);

        $export_result = mysqli_query($conn, $query);
        while ($row = mysqli_fetch_assoc($export_result)) {

            fputcsv($output, $row);
            
        }
        fclose($output);
        exit;
    }

    // 6. Handle Preview
    $query .= " LIMIT 50";
    $preview_result = mysqli_query($conn, $query);
}

$pageTitle = "Advanced Reports";
$currentPage = 'reports';
include('header.php'); 
?>

<style>
    /* Variables */
    :root {
        --primary: #2563eb;
        --surface: #ffffff;
        --border: #e2e8f0;
        --text: #1e293b;
    }

    /* Layout */
    .report-container { max-width: 1400px; padding-bottom: 50px; }
    
    .panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        padding: 25px;
        margin-bottom: 25px;
    }

    .panel-header {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Grid Layouts */
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .column-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 12px;
    }

    /* Inputs */
    .form-label { display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 6px; }
    .form-select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; background: #f8fafc; }
    
    /* Custom Checkboxes */
    .col-checkbox { display: none; }
    .col-label {
        display: block;
        padding: 10px 15px;
        background: #f1f5f9;
        border: 1px solid transparent;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        color: #475569;
        transition: 0.2s;
        text-align: center;
        user-select: none;
    }
    .col-checkbox:checked + .col-label {
        background: #eff6ff;
        border-color: var(--primary);
        color: var(--primary);
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(37,99,235,0.1);
    }
    .col-checkbox:checked + .col-label::before {
        content: "✓ ";
    }

    /* Action Buttons */
    .action-bar { display: flex; gap: 15px; margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px; }
    .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 0.95rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .btn-preview { background: #3b82f6; color: white; }
    .btn-preview:hover { background: #2563eb; }
    .btn-export { background: #10b981; color: white; }
    .btn-export:hover { background: #059669; }
    .btn-reset { background: #f1f5f9; color: #64748b; border: 1px solid var(--border); text-decoration: none; }
    .btn-reset:hover { background: #e2e8f0; }

    /* Preview Table */
    .table-responsive { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border); }
    .data-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
    .data-table th { background: #f8fafc; padding: 15px; text-align: left; border-bottom: 2px solid var(--border); color: #475569; font-size: 0.85rem; text-transform: uppercase; }
    .data-table td { padding: 12px 15px; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: #1e293b; }
    .data-table tr:hover { background: #f8fafc; }
</style>

<div class="report-container">
    
    <div style="margin-bottom: 20px;">
        <h2 style="margin:0; color:#1e293b;">Advanced Report Builder</h2>
        <p style="margin:5px 0 0; color:#64748b;">Filter data and choose specific columns to export.</p>
    </div>

    <form method="GET" id="reportForm">
        <input type="hidden" name="generate" value="true">
        <input type="hidden" name="export" id="exportFlag" value="false">

        <div class="panel">
            <div class="panel-header">
                <span><i class="fa-solid fa-filter"></i> Filters</span>
            </div>
            <div class="filter-grid">
                <div>
                    <label class="form-label">Academic Year</label>
                    <select name="academic_year" class="form-select">
                        <option value="">All Years</option>
                        <?php 
                        mysqli_data_seek($years_list, 0);
                        while($y = mysqli_fetch_assoc($years_list)): ?>
                            <option value="<?= $y['academic_year'] ?>" <?= $f_year == $y['academic_year'] ? 'selected' : '' ?>><?= $y['academic_year'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Institution</label>
                    <select name="college" class="form-select">
                        <option value="">All Colleges</option>
                        <?php 
                        mysqli_data_seek($colleges_list, 0);
                        while($c = mysqli_fetch_assoc($colleges_list)): ?>
                            <option value="<?= $c['id'] ?>" <?= $f_college == $c['id'] ? 'selected' : '' ?>><?= $c['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Scholarship Scheme</label>
                    <select name="scholarship" class="form-select">
                        <option value="">All Schemes</option>
                        <?php 
                        mysqli_data_seek($scholarships_list, 0);
                        while($s = mysqli_fetch_assoc($scholarships_list)): ?>
                            <option value="<?= $s['id'] ?>" <?= $f_scholarship == $s['id'] ? 'selected' : '' ?>><?= $s['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All</option>
                        <option value="Male" <?= $f_gender == 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $f_gender == 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <span><i class="fa-solid fa-table-columns"></i> Select Columns to Export</span>
                <button type="button" onclick="toggleAllCols()" style="font-size:0.8rem; background:none; border:none; color:var(--primary); cursor:pointer; text-decoration:underline;">Select / Deselect All</button>
            </div>
            <div class="column-grid">
                <?php foreach($available_columns as $db_col => $human_label): ?>
                    <label>
                        <input type="checkbox" name="cols[]" value="<?= $db_col ?>" class="col-checkbox" 
                            <?= in_array($db_col, $selected_cols) ? 'checked' : '' ?>>
                        <span class="col-label"><?= $human_label ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="action-bar">
                <button type="button" onclick="submitPreview()" class="btn btn-preview">
                    <i class="fa-solid fa-eye"></i> Preview Data
                </button>
                <button type="button" onclick="submitExport()" class="btn btn-export">
                    <i class="fa-solid fa-file-csv"></i> Export to Excel/CSV
                </button>
                <a href="reports.php" class="btn btn-reset">Reset</a>
            </div>
        </div>
    </form>

    <?php if (isset($preview_result)): ?>
    <div class="panel">
        <div class="panel-header">
            <span><i class="fa-solid fa-list"></i> Data Preview (First 50 Rows)</span>
        </div>
        
        <?php if (mysqli_num_rows($preview_result) > 0): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php foreach ($selected_cols as $col_def): ?>
                                <th><?= htmlspecialchars($available_columns[$col_def]) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($preview_result)): ?>
                            <tr>
                                <?php foreach ($selected_cols as $col_def): 
                                    // Use the Mapping array to find the correct key in the row
                                    $key = $column_keys[$col_def];
                                ?>
                                    <td><?= htmlspecialchars($row[$key] ?? '-') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:15px; color:#64748b; font-size:0.9rem; font-style:italic;">
                * This is a preview. Click "Export" to download the full dataset.
            </p>
        <?php else: ?>
            <div style="text-align:center; padding:40px; color:#64748b;">
                <i class="fa-regular fa-folder-open" style="font-size:2rem; margin-bottom:10px;"></i><br>
                No records found matching your filters.
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php include('footer.php'); ?>

<script>
    function submitPreview() {
        document.getElementById('exportFlag').value = 'false';
        document.getElementById('reportForm').submit();
    }

    function submitExport() {
        document.getElementById('exportFlag').value = 'true';
        document.getElementById('reportForm').submit();
    }

    function toggleAllCols() {
        const checkboxes = document.querySelectorAll('.col-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
    }
</script>
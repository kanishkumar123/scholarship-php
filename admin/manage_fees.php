<?php
session_start();
$currentPage = 'manage_fees';
include("../config.php");

// --- 1. SECURITY ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_fees'])) {
        $selected_items = explode(',', $_POST['selected_ids']);
        $tuition_fee = floatval($_POST['tuition_fee']);
        $sanctioned_amount = floatval($_POST['sanctioned_amount']);

        if (!empty($selected_items)) {
            $stmt = $conn->prepare("INSERT INTO application_financials (application_id, renewal_id, tuition_fee, sanctioned_amount) 
                                    VALUES (?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE tuition_fee = VALUES(tuition_fee), sanctioned_amount = VALUES(sanctioned_amount)");

            foreach ($selected_items as $item) {
                list($app_id, $ren_id) = explode('_', $item);
                $ren_val = ($ren_id == '0') ? NULL : intval($ren_id);
                $app_val = intval($app_id);

                $stmt->bind_param("iidd", $app_val, $ren_val, $tuition_fee, $sanctioned_amount);
                $stmt->execute();
            }
            $stmt->close();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Updated ' . count($selected_items) . ' records successfully.'];
        }
    }
    $redirect_url = "manage_fees.php";
    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirect_url .= "?" . $_SERVER['QUERY_STRING'];
    }
    header("Location: " . $redirect_url); 
    exit;
}

// --- 3. FILTER & SEARCH LOGIC ---
$f_search = $_GET['search'] ?? '';
$f_academic_year = $_GET['academic_year'] ?? '';
$f_college = $_GET['college'] ?? '';
$f_course = $_GET['course'] ?? '';
$f_year_study = $_GET['year_of_study'] ?? '';

// Pagination Vars
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20; 
$offset = ($page - 1) * $records_per_page;

// Build SQL Conditions
$conditions = ["1=1"];

if (!empty($f_search)) {
    $safe_search = mysqli_real_escape_string($conn, $f_search);
    $conditions[] = "(T.application_no LIKE '%$safe_search%' OR T.name LIKE '%$safe_search%')";
}
if (!empty($f_academic_year)) {
    $safe_year = mysqli_real_escape_string($conn, $f_academic_year);
    $conditions[] = "T.academic_year = '$safe_year'";
}
if (!empty($f_college)) {
    $safe_college = mysqli_real_escape_string($conn, $f_college);
    $conditions[] = "T.institution_id = '$safe_college'";
}
if (!empty($f_course)) {
    $safe_course = mysqli_real_escape_string($conn, $f_course);
    $conditions[] = "T.course_id = '$safe_course'";
}
if (!empty($f_year_study)) {
    $safe_year_study = mysqli_real_escape_string($conn, $f_year_study);
    $conditions[] = "T.current_year = '$safe_year_study'";
}
$where_sql = implode(' AND ', $conditions);

// --- 4. DATA FETCHING ---
$union_subquery = "
    -- 1. FRESH APPLICATIONS
    SELECT 
        a.id AS app_id, 
        0 AS ren_id, 
        'Fresh' AS type,
        a.application_no, 
        a.name, 
        a.academic_year, 
        a.year_of_study AS current_year, 
        a.community,
        a.institution_name AS institution_id,
        a.course AS course_id,
        c.name AS college_name, 
        p.name AS program_name,
        s.name AS scholarship_name,
        af.tuition_fee, 
        af.sanctioned_amount
    FROM applications a
    LEFT JOIN colleges c ON a.institution_name = c.id
    LEFT JOIN programs p ON a.course = p.id
    LEFT JOIN scholarships s ON a.scholarship_id = s.id
    LEFT JOIN application_financials af ON (a.id = af.application_id AND af.renewal_id IS NULL)

    UNION ALL

    -- 2. RENEWAL APPLICATIONS
    SELECT 
        a.id AS app_id, 
        r.id AS ren_id, 
        'Renewal' AS type,
        a.application_no, 
        a.name, 
        a.academic_year, 
        r.year_of_study AS current_year, 
        a.community,
        r.institution_name AS institution_id,
        r.course AS course_id,
        c.name AS college_name, 
        p.name AS program_name,
        s.name AS scholarship_name,
        af.tuition_fee, 
        af.sanctioned_amount
    FROM renewals r
    JOIN applications a ON r.application_id = a.id
    LEFT JOIN colleges c ON r.institution_name = c.id
    LEFT JOIN programs p ON r.course = p.id
    LEFT JOIN scholarships s ON a.scholarship_id = s.id
    LEFT JOIN application_financials af ON (a.id = af.application_id AND r.id = af.renewal_id)
";

// Count
$count_query = "SELECT COUNT(*) as total FROM ($union_subquery) AS T WHERE $where_sql";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $records_per_page);

// Data
$data_query = "SELECT * FROM ($union_subquery) AS T 
               WHERE $where_sql 
               ORDER BY T.app_id DESC, T.current_year ASC 
               LIMIT $records_per_page OFFSET $offset";

$result = mysqli_query($conn, $data_query);

// Grouping
$grouped_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $app_id = $row['app_id'];
    if (!isset($grouped_data[$app_id])) {
        $grouped_data[$app_id] = [
            'details' => $row,
            'records' => []
        ];
    }
    $grouped_data[$app_id]['records'][] = $row;
}

// Dropdowns
$colleges_list = mysqli_query($conn, "SELECT id, name FROM colleges");
$courses_list = mysqli_query($conn, "SELECT id, name FROM programs");
$years_list = mysqli_query($conn, "SELECT DISTINCT academic_year FROM applications ORDER BY academic_year DESC");

$pageTitle = "Fee Management";
include('header.php'); 
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* VARIABLES */
    :root {
        --primary: #2563eb;
        --primary-dark: #1e40af;
        --bg-main: #f1f5f9;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --success: #10b981;
        --danger: #ef4444;
        --fresh-bg: #e0f2fe; --fresh-text: #0369a1;
        --renew-bg: #fef3c7; --renew-text: #b45309;
    }

    body { font-family: 'Inter', sans-serif; background-color: var(--bg-main); color: var(--text-main); }
    .dashboard-container { max-width: 1400px; margin: 0 auto; padding-bottom: 80px; }
    
    /* HEADER */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0; }
    .page-header p { color: var(--text-muted); margin: 4px 0 0 0; font-size: 0.95rem; }

    /* FILTERS CONTAINER */
    .filters-wrapper {
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        overflow: hidden; /* Contains the search bar */
    }

    /* SEARCH BAR (Redesigned) */
    .search-row {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        background: #f8fafc;
    }
    .search-container {
        position: relative;
        max-width: 100%;
    }
    .search-icon-fixed {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .search-input-clean {
        width: 100%;
        padding: 12px 12px 12px 38px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.2s;
    }
    .search-input-clean:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        outline: none;
        background: white;
    }

    /* FILTER DROPDOWNS ROW */
    .filters-row {
        padding: 20px;
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    .filter-item { flex: 1; min-width: 160px; }
    .filter-item label { display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; }
    .filter-select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; background: #fff; color: var(--text-main); }
    .btn-apply { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; height: 40px; }
    .btn-apply:hover { background: var(--primary-dark); }
    .btn-reset { background: white; color: var(--text-muted); border: 1px solid var(--border-color); padding: 0 15px; border-radius: 8px; cursor: pointer; transition: 0.2s; height: 40px; display: inline-flex; align-items: center; }
    .btn-reset:hover { background: #f1f5f9; }

    /* STUDENT CARDS */
    .student-grid { display: flex; flex-direction: column; gap: 20px; }
    
    .student-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--primary); /* Accent Border */
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        display: grid;
        grid-template-columns: 280px 1fr;
        overflow: hidden;
    }

    /* Sidebar Design */
    .student-info-sidebar {
        background: #fff;
        padding: 24px;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .st-header { margin-bottom: 8px; }
    .st-name { font-size: 1.15rem; font-weight: 700; color: var(--text-main); line-height: 1.3; margin-bottom: 6px; }
    
    .st-badges { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .st-app-badge { font-size: 0.75rem; color: var(--text-muted); background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-family: monospace; }
    .st-year-badge { font-size: 0.75rem; color: #0f172a; background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: 600; }

    .st-meta-list { display: flex; flex-direction: column; gap: 8px; }
    .st-meta-item { display: flex; gap: 10px; align-items: flex-start; font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; }
    .st-meta-item i { margin-top: 3px; width: 16px; color: #94a3b8; }
    .st-meta-item span { color: var(--text-main); font-weight: 500; }

    .st-scheme-box { margin-top: auto; padding-top: 15px; border-top: 1px dashed var(--border-color); }
    .st-scheme-label { font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; display: block; margin-bottom: 2px; }
    .st-scheme-val { font-size: 0.85rem; font-weight: 600; color: var(--primary); }

    /* Fee Grid */
    .fee-years-container {
        background: #fdfdfd;
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
        align-items: start;
    }

    .year-block {
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 16px;
        position: relative;
        background: #fff;
        transition: 0.2s;
    }
    .year-block:hover { border-color: var(--primary); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transform: translateY(-2px); }
    
    .yb-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
    .yb-title { font-size: 0.95rem; font-weight: 700; color: var(--text-main); }
    .yb-badge { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; letter-spacing: 0.05em; }
    .yb-fresh { background: var(--fresh-bg); color: var(--fresh-text); }
    .yb-renew { background: var(--renew-bg); color: var(--renew-text); }
    
    .yb-select { position: absolute; top: 16px; right: 16px; cursor: pointer; accent-color: var(--primary); width: 16px; height: 16px; }

    .yb-row { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 8px; color: var(--text-muted); }
    .yb-val { font-weight: 600; color: var(--text-main); font-family: 'Consolas', monospace; }
    
    .yb-total { 
        margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color); 
        display: flex; justify-content: space-between; align-items: center;
    }
    .yb-total-label { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
    .yb-total-val { font-weight: 700; font-size: 1rem; }
    .val-due { color: var(--danger); }
    .val-paid { color: var(--success); }

    .btn-block-edit { 
        margin-top: 15px; width: 100%; background: #fff; border: 1px solid var(--border-color); 
        color: var(--text-muted); padding: 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: 0.2s;
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .btn-block-edit:hover { background: #f8fafc; color: var(--primary); border-color: var(--primary); }

    /* Pagination */
    .pagination-bar {
        margin-top: 30px; display: flex; justify-content: space-between; align-items: center;
        background: white; padding: 15px 20px; border-radius: 12px; border: 1px solid var(--border-color);
    }
    .page-link { padding: 8px 12px; border: 1px solid var(--border-color); background: white; color: var(--text-main); text-decoration: none; border-radius: 6px; font-size: 0.9rem; margin-left: 4px; }
    .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }

    /* Modal & Floating Bar (Same as before) */
    .bulk-floating-bar { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(150%); background: #1e293b; color: white; padding: 12px 24px; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 1000; display: flex; align-items: center; gap: 20px; transition: 0.3s; }
    .bulk-floating-bar.active { transform: translateX(-50%) translateY(0); }
    
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.7); backdrop-filter: blur(4px); z-index: 2000; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; }
    .modal-overlay.open { opacity: 1; }
    .modal-card { background: white; width: 100%; max-width: 450px; border-radius: 16px; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transform: translateY(20px); transition: 0.3s; }
    .modal-overlay.open .modal-card { transform: translateY(0); }
    .mc-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
    .inp-field { width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 1rem; font-weight: 600; color: var(--text-main); }
    .calc-box { background: #f1f5f9; padding: 15px; border-radius: 8px; text-align: center; margin-top: 10px; border: 1px solid var(--border-color); }
    .mc-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }
    .btn-mc-save { background: var(--primary); border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; color: white; cursor: pointer; }

    @media (max-width: 900px) {
        .student-card { grid-template-columns: 1fr; }
        .student-info-sidebar { border-right: none; border-bottom: 1px solid var(--border-color); }
    }
</style>

<div class="dashboard-container">
    <div class="page-header">
        <div>
            <h2>Fee Management</h2>
            <p>Manage tuition and sanctioned amounts efficiently.</p>
        </div>
        <label style="font-weight: 600; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" id="masterCheckbox" style="accent-color: var(--primary); width: 18px; height: 18px;"> Select All Visible
        </label>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #bbf7d0; font-weight: 500;">
            <?= $_SESSION['message']['text'] ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <form method="get" class="filters-wrapper">
        <div class="search-row">
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass search-icon-fixed"></i>
                <input type="text" name="search" class="search-input-clean" placeholder="Search by Application No. or Student Name..." value="<?= htmlspecialchars($f_search) ?>">
            </div>
        </div>

        <div class="filters-row">
            <div class="filter-item">
                <label>Academic Year</label>
                <select name="academic_year" class="filter-select">
                    <option value="">All Years</option>
                    <?php while($y = mysqli_fetch_assoc($years_list)): ?>
                        <option value="<?= htmlspecialchars($y['academic_year']) ?>" <?= $f_academic_year == $y['academic_year'] ? 'selected' : '' ?>><?= htmlspecialchars($y['academic_year']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>College</label>
                <select name="college" class="filter-select">
                    <option value="">All Colleges</option>
                    <?php while($c = mysqli_fetch_assoc($colleges_list)): ?>
                        <option value="<?= $c['id'] ?>" <?= $f_college == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Course</label>
                <select name="course" class="filter-select">
                    <option value="">All Courses</option>
                    <?php while($p = mysqli_fetch_assoc($courses_list)): ?>
                        <option value="<?= $p['id'] ?>" <?= $f_course == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Year of Study</label>
                <select name="year_of_study" class="filter-select">
                    <option value="">All Years</option>
                    <option value="1" <?= $f_year_study == '1' ? 'selected' : '' ?>>Year 1</option>
                    <option value="2" <?= $f_year_study == '2' ? 'selected' : '' ?>>Year 2</option>
                    <option value="3" <?= $f_year_study == '3' ? 'selected' : '' ?>>Year 3</option>
                    <option value="4" <?= $f_year_study == '4' ? 'selected' : '' ?>>Year 4</option>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-apply">Apply</button>
                <a href="manage_fees.php" class="btn-reset" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <div class="student-grid">
        <?php if(!empty($grouped_data)): ?>
            <?php foreach($grouped_data as $student_app): 
                $student = $student_app['details'];
                $records = $student_app['records'];
            ?>
            <div class="student-card">
                <div class="student-info-sidebar">
                    <div class="st-header">
                        <div class="st-name"><?= htmlspecialchars($student['name']) ?></div>
                        <div class="st-badges">
                            <span class="st-app-badge">#<?= htmlspecialchars($student['application_no']) ?></span>
                            <span class="st-year-badge"><i class="fa-regular fa-calendar" style="margin-right:4px;"></i><?= htmlspecialchars($student['academic_year']) ?></span>
                        </div>
                    </div>

                    <div class="st-meta-list">
                        <div class="st-meta-item">
                            <i class="fa-solid fa-university"></i>
                            <span><?= htmlspecialchars($student['college_name'] ?? '-') ?></span>
                        </div>
                        <div class="st-meta-item">
                            <i class="fa-solid fa-graduation-cap"></i>
                            <span><?= htmlspecialchars($student['program_name'] ?? '-') ?></span>
                        </div>
                        <div class="st-meta-item">
                            <i class="fa-solid fa-users"></i>
                            <span><?= htmlspecialchars($student['community'] ?? '-') ?></span>
                        </div>
                    </div>

                    <div class="st-scheme-box">
                        <span class="st-scheme-label">Scholarship Scheme</span>
                        <span class="st-scheme-val"><?= htmlspecialchars($student['scholarship_name'] ?? 'None Assigned') ?></span>
                    </div>
                </div>

                <div class="fee-years-container">
                    <?php foreach($records as $rec): 
                        $t_fee = floatval($rec['tuition_fee'] ?? 0);
                        $s_amt = floatval($rec['sanctioned_amount'] ?? 0);
                        $balance = $t_fee - $s_amt;
                        $composite_id = $rec['app_id'] . '_' . $rec['ren_id'];
                        
                        $status_txt = "Not Set";
                        $status_cls = "color: #94a3b8;";
                        if($t_fee > 0) {
                            if($balance > 0) { $status_txt = "Net Pay: " . number_format($balance); $status_cls = "val-due"; }
                            else { $status_txt = "Settled"; $status_cls = "val-paid"; }
                        }
                    ?>
                    <div class="year-block">
                        <input type="checkbox" class="yb-select row-checkbox" value="<?= $composite_id ?>">
                        
                        <div class="yb-header">
                            <span class="yb-title">Year <?= $rec['current_year'] ?></span>
                            <span class="yb-badge <?= ($rec['ren_id'] == 0) ? 'yb-fresh' : 'yb-renew' ?>">
                                <?= ($rec['ren_id'] == 0) ? 'Fresh' : 'Renew' ?>
                            </span>
                        </div>

                        <div class="yb-row">
                            <span>Tuition Fee</span>
                            <span class="yb-val"><?= $t_fee > 0 ? number_format($t_fee) : '-' ?></span>
                        </div>
                        <div class="yb-row">
                            <span>Sanctioned</span>
                            <span class="yb-val" style="color:#10b981;"><?= $s_amt > 0 ? number_format($s_amt) : '-' ?></span>
                        </div>

                        <div class="yb-total">
                            <span class="yb-total-label">Payable</span>
                            <span class="yb-total-val <?= $status_cls ?>"><?= $status_txt ?></span>
                        </div>

                        <button type="button" class="btn-block-edit" 
                                onclick="openSingleModal('<?= $composite_id ?>', '<?= addslashes($student['name']) ?> (Year <?= $rec['current_year'] ?>)', <?= $t_fee ?>, <?= $s_amt ?>)">
                            <i class="fa-solid fa-pen"></i> Edit Details
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                <i class="fa-regular fa-folder-open" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No students found matching your search or filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-bar">
        <div style="color: var(--text-muted); font-size: 0.9rem;">Page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_rows ?> students)</div>
        <div style="display: flex; gap: 5px;">
            <?php 
            $queryParams = $_GET; 
            unset($queryParams['page']);
            
            // Prev
            if($page > 1) {
                $queryParams['page'] = $page - 1;
                echo '<a href="?'.http_build_query($queryParams).'" class="page-link"><i class="fa-solid fa-chevron-left"></i></a>';
            }

            // Numbers
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)) {
                    $queryParams['page'] = $i;
                    $active = ($i == $page) ? 'active' : '';
                    echo '<a href="?'.http_build_query($queryParams).'" class="page-link '.$active.'">'.$i.'</a>';
                } elseif ($i == $page - 3 || $i == $page + 3) {
                    echo '<span style="padding: 8px; color: #94a3b8;">...</span>';
                }
            }

            // Next
            if($page < $total_pages) {
                $queryParams['page'] = $page + 1;
                echo '<a href="?'.http_build_query($queryParams).'" class="page-link"><i class="fa-solid fa-chevron-right"></i></a>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="bulk-floating-bar" id="bulkFloatingBar">
    <span class="fb-count"><span id="selectedCount">0</span> Selected</span> 
    <button class="btn-fb-action" onclick="openBulkModal()">Update Fees for Selection</button>
</div>

<div id="feeModal" class="modal-overlay">
    <div class="modal-card">
        <form method="post">
            <div class="mc-header">
                <div>
                    <h3 class="mc-title" id="modalTitle">Update Fees</h3>
                    <div style="font-size: 0.9rem; color: #64748b;" id="modalSubTitle">...</div>
                </div>
                <div style="cursor: pointer; color: #94a3b8;" onclick="closeModal()"><i class="fa-solid fa-times fa-lg"></i></div>
            </div>

            <input type="hidden" name="selected_ids" id="modalIds">

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:0.75rem; font-weight:700; color:#64748b; margin-bottom:6px; text-transform:uppercase;">Tuition Fee (₹)</label>
                <input type="number" name="tuition_fee" id="modalFee" class="inp-field" placeholder="0" step="0.01" required oninput="calc()">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:0.75rem; font-weight:700; color:#64748b; margin-bottom:6px; text-transform:uppercase;">Sanctioned Amount (₹)</label>
                <input type="number" name="sanctioned_amount" id="modalSanction" class="inp-field" placeholder="0" step="0.01" required oninput="calc()">
            </div>

            <div class="calc-box" id="calcBox">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Net Payable</span>
                <span class="calc-val" id="calcVal">₹ 0</span>
            </div>

            <div class="mc-footer">
                <button type="button" class="btn-mc-cancel" style="background:white; border:1px solid #e2e8f0; padding:10px 20px; border-radius:8px; font-weight:600; color:#64748b; cursor:pointer;" onclick="closeModal()">Cancel</button>
                <button type="submit" name="update_fees" class="btn-mc-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
// BULK SELECT
const masterCb = document.getElementById('masterCheckbox');
const rowCbs = document.querySelectorAll('.row-checkbox');
const bulkBar = document.getElementById('bulkFloatingBar');
const countSpan = document.getElementById('selectedCount');

if(masterCb) {
    masterCb.addEventListener('change', function() {
        rowCbs.forEach(cb => cb.checked = this.checked);
        updateUI();
    });
}
rowCbs.forEach(cb => cb.addEventListener('change', updateUI));

function updateUI() {
    const total = document.querySelectorAll('.row-checkbox:checked').length;
    countSpan.textContent = total;
    bulkBar.classList.toggle('active', total > 0);
}

// MODAL
const modal = document.getElementById('feeModal');
const feeIn = document.getElementById('modalFee');
const sancIn = document.getElementById('modalSanction');
const cVal = document.getElementById('calcVal');
const cBox = document.getElementById('calcBox');

function openSingleModal(id, title, f, s) {
    document.getElementById('modalTitle').innerText = "Edit Details";
    document.getElementById('modalSubTitle').innerText = title;
    document.getElementById('modalIds').value = id;
    feeIn.value = f > 0 ? f : '';
    sancIn.value = s > 0 ? s : '';
    calc();
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('open'), 10);
}

function openBulkModal() {
    const ids = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    document.getElementById('modalTitle').innerText = "Bulk Update";
    document.getElementById('modalSubTitle').innerText = ids.length + " Items Selected";
    document.getElementById('modalIds').value = ids.join(',');
    feeIn.value = ''; sancIn.value = ''; calc();
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('open'), 10);
}

function closeModal() {
    modal.classList.remove('open');
    setTimeout(() => modal.style.display = 'none', 200);
}

function calc() {
    let f = parseFloat(feeIn.value) || 0;
    let s = parseFloat(sancIn.value) || 0;
    let b = f - s;
    cVal.innerText = "₹ " + b.toLocaleString('en-IN');
    
    if (b > 0) { cVal.style.color = "#ef4444"; cBox.style.background = "#fef2f2"; } // Red
    else { cVal.style.color = "#10b981"; cBox.style.background = "#f0fdf4"; } // Green
}

window.onclick = e => { if(e.target == modal) closeModal(); }
</script>
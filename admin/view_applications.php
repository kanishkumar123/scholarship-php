<?php
// --- 1. Start Session & Config (FIXES REDIRECT LOOP) ---
session_start();
include("../config.php");

// --- 2. Set Page Variables ---
$currentPage = 'applications';
$pageTitle = "View Applications";
$pageSubtitle = "Search, filter, and manage all submissions";

// --- 3. Include Header ---
include('header.php'); // Includes <head>, <body>, <sidebar>, and <header>

// --- 4. Main Page Logic ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 5. Page-Specific Data Fetching ---
$limit = 15; // Number of applications per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

// Fetch scholarships
$scholarships_query = mysqli_query($conn, "SELECT * FROM scholarships");

// Fetch college-program mappings
$mapping_query = mysqli_query($conn, "
    SELECT c.id AS college_id, c.name AS college_name, p.id AS program_id, p.name AS program_name
    FROM college_program_mapping m
    JOIN colleges c ON m.college_id = c.id
    JOIN programs p ON m.program_id = p.id
    ORDER BY c.name ASC, p.name ASC
");

$college_programs = [];
while ($row = mysqli_fetch_assoc($mapping_query)) {
    $college_programs[$row['college_id']]['name'] = $row['college_name'];
    $college_programs[$row['college_id']]['programs'][$row['program_id']] = $row['program_name'];
}

// Handle search & filter
$query_where = " WHERE 1";
$search_params = []; // For pagination links
if (isset($_REQUEST['search'])) {
    $scholarship_id = $_REQUEST['scholarship_id'] ?? '';
    $college_id = $_REQUEST['institution_id'] ?? '';
    $program_id = $_REQUEST['program_id'] ?? '';
    $keyword = $_REQUEST['keyword'] ?? '';

    if (!empty($scholarship_id)) {
        $query_where .= " AND a.scholarship_id='" . intval($scholarship_id) . "'";
        $search_params['scholarship_id'] = $scholarship_id;
    }
    if (!empty($college_id)) {
        $query_where .= " AND a.institution_name='" . intval($college_id) . "'";
        $search_params['institution_id'] = $college_id;
    }
    if (!empty($program_id)) {
        $query_where .= " AND a.course='" . intval($program_id) . "'";
        $search_params['program_id'] = $program_id;
    }
    if (!empty($keyword)) {
        $safe_keyword = mysqli_real_escape_string($conn, $keyword);
        $query_where .= " AND (a.application_no LIKE '%$safe_keyword%' OR s.name LIKE '%$safe_keyword%')";
        $search_params['keyword'] = $keyword;
    }
    $search_params['search'] = '1'; // Mark that a search is active
}

// Count total applications
$count_query = "SELECT COUNT(*) FROM applications a
                JOIN scholarship_students s ON a.student_id = s.id
                JOIN scholarships sc ON a.scholarship_id = sc.id" . $query_where;
$total_records_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_row($total_records_result)[0];
$total_pages = ceil($total_records / $limit);

// Fetch applications
$applications_query = "SELECT a.*, s.name as student_name, sc.name as scholarship_name, 
                           c.name as college_name, p.name as program_name
                       FROM applications a
                       JOIN scholarship_students s ON a.student_id = s.id
                       JOIN scholarships sc ON a.scholarship_id = sc.id
                       LEFT JOIN colleges c ON a.institution_name = c.id
                       LEFT JOIN programs p ON a.course = p.id
                       " . $query_where . " ORDER BY a.submitted_at DESC LIMIT $limit OFFSET $offset";
$applications = mysqli_query($conn, $applications_query);
?>

<form method="post" class="filter-form" action="view_applications.php">
    <div class="filter-group">
        <label for="keyword">Search:</label>
        <input type="text" id="keyword" name="keyword" placeholder="Application No or Student Name" value="<?= isset($_REQUEST['keyword']) ? htmlspecialchars($_REQUEST['keyword']) : '' ?>">
    </div>
    
    <div class="filter-group">
        <label for="scholarship_id">Scholarship:</label>
        <select id="scholarship_id" name="scholarship_id">
            <option value="">-- All --</option>
            <?php
            mysqli_data_seek($scholarships_query, 0);
            while ($row = mysqli_fetch_assoc($scholarships_query)) {
                $selected = (isset($_REQUEST['scholarship_id']) && $_REQUEST['scholarship_id'] == $row['id']) ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($row['id']) ?>" <?= $selected ?>><?= htmlspecialchars($row['name']) ?></option>
            <?php } ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="institution_id">Institution:</label>
        <select id="institution_id" name="institution_id">
            <option value="">-- All --</option>
            <?php 
            $all_colleges_query = mysqli_query($conn, "SELECT id, name FROM colleges ORDER BY name ASC");
            while ($row = mysqli_fetch_assoc($all_colleges_query)) {
                $selected = (isset($_REQUEST['institution_id']) && $_REQUEST['institution_id'] == $row['id']) ? 'selected' : '';
            ?>
                <option value="<?= $row['id'] ?>" <?= $selected ?>><?= htmlspecialchars($row['name']) ?></option>
            <?php } ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="program_id">Program:</label>
        <select id="program_id" name="program_id" disabled>
            <option value="">-- Select Institution First --</option>
        </select>
    </div>

    <button type="submit" name="search" class="button button-primary">Search</button>
</form>

<div class="table-container box">
    <?php if (mysqli_num_rows($applications) > 0) { ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;"></th> <th>Application No</th>
                    <th>Student Name</th>
                    <th>Scholarship Name</th>
                    <th style="width: 300px;">Action</th> </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($applications)) { 
                    
                    // --- Fetch renewals for this specific application ---
                    $renewals = [];
                    $current_application_id = $row['id']; 
                    
                    $renewal_query = mysqli_query($conn, "
                        SELECT id, year_of_study, semester FROM renewals 
                        WHERE application_id = $current_application_id
                        ORDER BY year_of_study DESC, semester DESC
                    ");
                    
                    if ($renewal_query) {
                        while ($renewal_row = mysqli_fetch_assoc($renewal_query)) {
                            $renewals[] = $renewal_row;
                        }
                    }
                ?>
                    <tr class="main-row">
                        <td>
                            <button type="button" class="toggle-details-btn" title="Show Details">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </td>
                        <td><?= htmlspecialchars($row['application_no']) ?></td>
                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                        <td><?= htmlspecialchars($row['scholarship_name']) ?></td>
                        <td>
                            <div class="action-buttons">
                                
                                <div class="action-dropdown">
                                    <button type="button" class="action-button view-button toggle-view-dropdown">
                                        View <i class="fa-solid fa-chevron-down" style="font-size: 0.8em; margin-left: 5px;"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a href="../confirmation.php?id=<?= $row['id'] ?>" target="_blank">
                                            <i class="fa-solid fa-file-invoice"></i> View Original App
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (empty($renewals)): ?>
                                            <span class="dropdown-item-disabled">No Renewals Found</span>
                                        <?php else: ?>
                                            <?php foreach ($renewals as $renewal): ?>
                                            <a href="view_renewal.php?id=<?= $renewal['id'] ?>" target="_blank">
                                                <i class="fa-solid fa-file-lines"></i> Renewal (Year <?= htmlspecialchars($renewal['year_of_study']) ?>, Sem <?= htmlspecialchars($renewal['semester']) ?>)
                                            </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="action-dropdown">
                                    <button type="button" class="action-button toggle-edit-dropdown" style="background-color:#ffc107; color: #000;">
                                        Edit <i class="fa-solid fa-chevron-down" style="font-size: 0.8em; margin-left: 5px;"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a href="edit_application.php?id=<?= $row['id'] ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit Original App
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (empty($renewals)): ?>
                                            <span class="dropdown-item-disabled">No Renewals</span>
                                        <?php else: ?>
                                            <?php foreach ($renewals as $renewal): ?>
                                            <a href="edit_renewal.php?id=<?= $renewal['id'] ?>">
                                                <i class="fa-solid fa-pen"></i> Renewal (Year <?= htmlspecialchars($renewal['year_of_study']) ?>)
                                            </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="../generate_pdf.php?id=<?= $row['id'] ?>" target="_blank" class="action-button download-button">Download</a>
                                <a href="renewal_form.php?id=<?= $row['id'] ?>" class="action-button" style="background-color:#6f42c1;">Add Renewal</a>
                            </div>
                        </td>
                    </tr>
                    <tr class="app-details-row">
                        <td colspan="5">
                            <div class="app-details-content">
                                <div>
                                    <strong>College Name:</strong>
                                    <span><?= htmlspecialchars($row['college_name'] ?? 'N/A') ?></span>
                                </div>
                                <div>
                                    <strong>Course Name:</strong>
                                    <span><?= htmlspecialchars($row['program_name'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php
            if ($total_pages > 1) {
                function get_page_link($page, $params) {
                    $params['page'] = $page;
                    return '?' . http_build_query($params);
                }
                $ellipsis = '<span class="ellipsis">...</span>';
                $range = 2;
                if ($current_page > 1) {
                    echo '<a href="' . get_page_link(1, $search_params) . '">First</a>';
                    echo '<a href="' . get_page_link($current_page - 1, $search_params) . '">Prev</a>';
                }
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                        $class = ($i == $current_page) ? 'active' : '';
                        echo '<a href="' . get_page_link($i, $search_params) . '" class="' . $class . '">' . $i . '</a>';
                    }
                    else if ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                        echo $ellipsis;
                    }
                }
                if ($current_page < $total_pages) {
                    echo '<a href="' . get_page_link($current_page + 1, $search_params) . '">Next</a>';
                    echo '<a href="' . get_page_link($total_pages, $search_params) . '">Last</a>';
                }
            }
            ?>
        </div>

    <?php } else { ?>
        <p class="no-applications">No applications found.</p>
    <?php } ?>
</div>

<p><a href="dashboard.php" class="back-link">← Back to Dashboard</a></p>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script src="js/view_applications.js"></script> 

<script>
// Pass the PHP array of college-program mappings to JavaScript
const collegePrograms = <?php echo json_encode($college_programs); ?>;

document.getElementById('institution_id').addEventListener('change', function() {
    const collegeId = this.value;
    const programSelect = document.getElementById('program_id');
    programSelect.innerHTML = '<option value="">-- All --</option>'; 

    if (collegeId && collegePrograms[collegeId]) {
        for (const [programId, programName] of Object.entries(collegePrograms[collegeId]['programs'])) {
            const opt = document.createElement('option');
            opt.value = programId;
            opt.textContent = programName;
            programSelect.appendChild(opt);
        }
        programSelect.disabled = false;
    } else {
        programSelect.disabled = true;
        programSelect.innerHTML = '<option value="">-- Select Institution First --</option>';
    }
});

// Trigger change on page load
document.addEventListener('DOMContentLoaded', () => {
    const institutionSelect = document.getElementById('institution_id');
    if (institutionSelect.value) {
        institutionSelect.dispatchEvent(new Event('change'));
        
        const selectedProgram = "<?php echo isset($_REQUEST['program_id']) ? htmlspecialchars($_REQUEST['program_id']) : '' ?>";
        if (selectedProgram) {
            setTimeout(() => {
                document.getElementById('program_id').value = selectedProgram;
            }, 100);
        }
    }
    
    // --- NEW/MODIFIED: Dropdown Menu Logic ---
    // Use event delegation for all dropdowns in the table
    // --- NEW/MODIFIED: Dropdown Menu Logic ---
    // Use event delegation for all dropdowns in the table
    document.body.addEventListener('click', function(e) {
        // Check if the clicked element is (or is inside) a view toggle OR an edit toggle
        const toggleBtn = e.target.closest('.toggle-view-dropdown, .toggle-edit-dropdown');
        
        // If the click is not on a toggle button, close all open dropdowns
        if (!toggleBtn) {
            document.querySelectorAll('.action-dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
            return;
        }

        // If the click *is* on a toggle button
        const dropdown = toggleBtn.closest('.action-dropdown');
        
        // Check if this dropdown is already active
        const wasActive = dropdown.classList.contains('active');

        // First, close all *other* dropdowns to ensure only one is open at a time
        document.querySelectorAll('.action-dropdown.active').forEach(d => {
            if (d !== dropdown) d.classList.remove('active');
        });
        
        // Finally, toggle the clicked dropdown
        if (!wasActive) {
            dropdown.classList.add('active');
        } else {
            dropdown.classList.remove('active');
        }
    });
    // --- END NEW/MODIFIED ---
});
</script>
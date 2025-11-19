<?php
// --- 1. ALL PHP LOGIC GOES FIRST ---
session_start();
include("../config.php");

// --- 2. Security & Login Checks ---
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Admin') {
    die("Access Denied: You do not have permission to view this page.");
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 3. Set Page Variables ---
$currentPage = 'mapping';
$pageTitle = "College-Program Mapping";
$pageSubtitle = "Assign programs to colleges instantly";

// --- 4. Include Header (HTML starts here) ---
include('header.php'); // This includes <head>, <body>, <sidebar>, and <header>

// --- 5. Page-Specific Data Fetching ---

// Fetch ALL programs (for the modal)
$programs_result = mysqli_query($conn, "SELECT id, name FROM programs ORDER BY name ASC");
$all_programs_js = []; // For JS
$all_programs_map = []; // For PHP
while($p = mysqli_fetch_assoc($programs_result)) {
    $all_programs_js[] = ['id' => $p['id'], 'name' => $p['name']];
    $all_programs_map[$p['id']] = $p['name']; 
}

// Fetch ALL existing mappings (for the modal)
$mappings = [];
$mapping_result = mysqli_query($conn, "SELECT * FROM college_program_mapping");
while ($row = mysqli_fetch_assoc($mapping_result)) {
    $mappings[$row['college_id']][$row['program_id']] = true;
}

// --- 6. NEW: Search & Pagination Logic for Colleges ---
$limit = 6; // 6 colleges per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

$search_term = '';
$search_query_sql = '';
$search_params = []; // For pagination links

if (!empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    // Search by name, code, or nodal officer
    $search_query_sql = " WHERE name LIKE '%$search_term%' OR collegecode LIKE '%$search_term%' OR nodalofficername LIKE '%$search_term%'";
    $search_params['search'] = $_GET['search']; // Add to params
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM colleges" . $search_query_sql;
$total_records_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_row($total_records_result)[0];
$total_pages = ceil($total_records / $limit);

// Fetch paginated colleges
$colleges_result = mysqli_query($conn, "SELECT * FROM colleges" . $search_query_sql . " ORDER BY name ASC LIMIT $limit OFFSET $offset");

?>

<?php
// Display success/error messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']);
}
?>

<div class="box college-search-box">
    <form method="get" class="list-search-form" action="map_programs.php">
        <div class="modal-search-bar">
            <i class="fa-solid fa-search"></i>
            <input type="text" name="search" id="college-search-bar" placeholder="Search by college name or code..." value="<?= htmlspecialchars($search_term) ?>">
        </div>
        <button type="submit" class="button button-primary">Search</button>
        <?php if (!empty($search_term)): ?>
            <a href="map_programs.php" class="button" style="background-color: var(--text-secondary); color: white;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="map-card-grid" id="college-card-grid">
    <?php 
    if (mysqli_num_rows($colleges_result) > 0) :
        mysqli_data_seek($colleges_result, 0); // Reset pointer
        while($c = mysqli_fetch_assoc($colleges_result)): 
            $college_id = $c['id'];
            $mapped_program_ids = $mappings[$college_id] ?? [];
            $mapped_count = count($mapped_program_ids);
    ?>
        <div class="map-card" 
             data-college-id="<?php echo $college_id; ?>"
             data-college-name="<?php echo htmlspecialchars($c['name']); ?>"
             data-search-term="<?php echo htmlspecialchars(strtolower($c['name'] . $c['collegecode'])); ?>">
            
            <div class="map-card-header">
                <h3 class="map-card-title"><?php echo htmlspecialchars($c['name']); ?></h3>
                <span class="map-card-code"><?php echo htmlspecialchars($c['collegecode']); ?></span>
            </div>

            <div class="map-card-body">
                <div class="map-card-info-item">
                    <i class="fa-solid fa-user-tie"></i>
                    <span><?php echo !empty($c['nodalofficername']) ? htmlspecialchars($c['nodalofficername']) : '<em>N/A</em>'; ?></span>
                </div>
                <div class="map-card-info-item">
                    <i class="fa-solid fa-phone"></i>
                    <span><?php echo !empty($c['contact']) ? htmlspecialchars($c['contact']) : '<em>N/A</em>'; ?></span>
                </div>
            </div>
            
            <div class="map-card-programs">
                <h5 class="map-card-programs-title">
                    <span id="count-<?php echo $college_id; ?>"><?php echo $mapped_count; ?></span> 
                    Program<?php echo $mapped_count != 1 ? 's' : ''; ?> Mapped
                </h5>
                <ul class="program-pill-list" id="pills-<?php echo $college_id; ?>">
                    <?php 
                    $pills_shown = 0;
                    if ($mapped_count > 0) {
                        foreach ($mapped_program_ids as $p_id => $val) {
                            if ($pills_shown >= 2) break; 
                            if (isset($all_programs_map[$p_id])) {
                                echo '<li class="program-pill">' . htmlspecialchars($all_programs_map[$p_id]) . '</li>';
                                $pills_shown++;
                            }
                        }
                        if ($mapped_count > $pills_shown) {
                            echo '<li class="program-pill-more">+' . ($mapped_count - $pills_shown) . ' more</li>';
                        }
                    } else {
                        echo '<li class="program-pill-none">No programs mapped.</li>';
                    }
                    ?>
                </ul>
            </div>
            
            <div class="map-card-footer">
                <button class="button button-primary manage-button">
                    <i class="fa-solid fa-pen-to-square"></i> Manage
                </button>
            </div>
        </div>
    <?php 
        endwhile; 
    else: // No colleges found
    ?>
        <div class="no-applications" style="display: block; grid-column: 1 / -1;">
            <?php if (!empty($search_term)): ?>
                No colleges found matching "<?= htmlspecialchars($search_term) ?>".
            <?php else: ?>
                No colleges found.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="pagination">
    <?php
    if ($total_pages > 1) {
        
        function get_page_link($page, $params) {
            $params['page'] = $page;
            return '?' . http_build_query($params);
        }
        
        $ellipsis = '<span class="ellipsis">...</span>';
        $range = 2; // Pages to show around current page
        
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

<div id="mapping-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content"> 
        <div class="modal-header">
            <h3 id="modal-title">Manage Programs</h3>
            <button type="button" class="modal-close" id="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="modal_college_id" value="">
            <p>Select programs to assign to <strong id="modal-college-name">this college</strong>.</p>
            <div class="program-list-box">
                <div class="modal-search-bar">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="program-search" placeholder="Search for programs...">
                </div>
                <div class="program-checkbox-list" id="modal-program-list">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-danger" id="modal-cancel-btn" style="background-color: var(--text-secondary);">Cancel</button>
            <button type="button" id="modal-save-btn" class="button button-success">
                <i class="fa-solid fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script>
    const allPrograms = <?php echo json_encode($all_programs_js); ?>; // Array of {id, name}
    let allMappings = <?php echo json_encode($mappings); ?>; // Object of { college_id: { program_id: true } }
    const allProgramsMap = <?php echo json_encode($all_programs_map); ?>; // Object of { id: "name" }
</script>

<script src="js/map_programs.js"></script>
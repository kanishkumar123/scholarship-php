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

// --- 3. Handle Add College ---
if (isset($_POST['add'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $collegecode = mysqli_real_escape_string($conn, $_POST['collegecode']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $nodalofficername = isset($_POST['nodalofficername']) ? mysqli_real_escape_string($conn, $_POST['nodalofficername']) : '';

    // Check for duplicate college code
    $check = mysqli_query($conn, "SELECT * FROM colleges WHERE collegecode='$collegecode'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: College code already exists!'];
    } else {
        $query = "INSERT INTO colleges (name, collegecode, description, contact, nodalofficername)
                  VALUES ('$name', '$collegecode', '$description', '$contact', '$nodalofficername')";
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'New college added successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not add college.'];
        }
    }
    header("Location: college.php");
    exit;
}

// --- 4. NEW: Search & Pagination Logic ---
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
$colleges = mysqli_query($conn, "SELECT * FROM colleges" . $search_query_sql . " ORDER BY name ASC LIMIT $limit OFFSET $offset");


// --- 5. Set Page Variables ---
$currentPage = 'colleges';
$pageTitle = "Manage Colleges";
$pageSubtitle = "Add, view, and manage college information";

// --- 6. Include Header ---
include('header.php'); 
?>

<?php
// Display success/error messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']);
}
?>

<div class="action-container" style="margin-bottom: 20px;">
    <button id="show-add-form" class="button button-success">
        <i class="fa-solid fa-plus"></i> Add New College
    </button>
</div>

<div id="add-form-section" class="form-section box" style="display: none;">
    <h3>Add New College</h3>
    <form method="post" action="college.php">
        <div class="form-grid">
            <div class="form-group">
                <label for="name">College Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="collegecode">College Code:</label>
                <input type="text" id="collegecode" name="collegecode" required>
            </div>
            <div class="form-group">
                <label for="nodalofficername">Nodal Officer Name:</label>
                <input type="text" id="nodalofficername" name="nodalofficername">
            </div>
            <div class="form-group">
                <label for="contact">Contact:</label>
                <input type="text" id="contact" name="contact">
            </div>
            <div class="form-group form-group-full">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="add" class="button button-success">Save College</button>
            <button type="button" id="cancel-add-form" class="button button-danger">Cancel</button>
        </div>
    </form>
</div>

<div class="box">
    <h3>Existing Colleges</h3>
    
    <form method="get" class="list-search-form" action="college.php">
        <div class="modal-search-bar"> <i class="fa-solid fa-search"></i>
            <input type="text" name="search" placeholder="Search by name, code, or officer..." value="<?= htmlspecialchars($search_term) ?>">
        </div>
        <button type="submit" class="button button-primary">Search</button>
        <?php if (!empty($search_term)): ?>
            <a href="college.php" class="button" style="background-color: var(--text-secondary); color: white;">Clear</a>
        <?php endif; ?>
    </form>

    <div class="college-card-grid" style="margin-top: 25px;">
        <?php if (mysqli_num_rows($colleges) > 0) : ?>
            <?php while ($row = mysqli_fetch_assoc($colleges)) : ?>
                <div class="college-card">
                    <div class="card-header">
                        <h4 class="card-title"><?= htmlspecialchars($row['name']) ?></h4>
                        <span class="card-code"><?= htmlspecialchars($row['collegecode']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="card-info-item">
                            <i class="fa-solid fa-user-tie"></i>
                            <span><?= !empty($row['nodalofficername']) ? htmlspecialchars($row['nodalofficername']) : '<em>Nodal Officer not set</em>' ?></span>
                        </div>
                        <div class="card-info-item">
                            <i class="fa-solid fa-phone"></i>
                            <span><?= !empty($row['contact']) ? htmlspecialchars($row['contact']) : '<em>No contact info</em>' ?></span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="edit_college.php?id=<?= $row['id'] ?>" class="action-button" style="background-color:#ffc107;">Edit</a>
                        <a href="delete_college.php?id=<?= $row['id'] ?>&search=<?= urlencode($search_term) ?>" class="action-button" style="background-color: var(--danger-color);" onclick="return confirm('Are you sure you want to delete this college? This action cannot be undone.')">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-applications" style="grid-column: 1 / -1;">
                <?php if (!empty($search_term)): ?>
                    No colleges found matching "<?= htmlspecialchars($search_term) ?>".
                <?php else: ?>
                    No colleges found. Add one to get started!
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    
    <div class="pagination">
        <?php
        if ($total_pages > 1) {
            
            // Function to build the link
            function get_page_link($page, $params) {
                $params['page'] = $page;
                return '?' . http_build_query($params);
            }
            
            $ellipsis = '<span class="ellipsis">...</span>';
            $range = 2; // Pages to show around current page
            
            // "First" and "Previous" links
            if ($current_page > 1) {
                echo '<a href="' . get_page_link(1, $search_params) . '">First</a>';
                echo '<a href="' . get_page_link($current_page - 1, $search_params) . '">Prev</a>';
            }

            // Page number links
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                    $class = ($i == $current_page) ? 'active' : '';
                    echo '<a href="' . get_page_link($i, $search_params) . '" class="' . $class . '">' . $i . '</a>';
                }
                // Add ellipsis (...)
                else if ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                    echo $ellipsis;
                }
            }
            
            // "Next" and "Last" links
            if ($current_page < $total_pages) {
                echo '<a href="' . get_page_link($current_page + 1, $search_params) . '">Next</a>';
                echo '<a href="' . get_page_link($total_pages, $search_params) . '">Last</a>';
            }
        }
        ?>
    </div>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script src="js/college.js"></script>
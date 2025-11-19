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

// --- 3. Handle Add Scholarship ---
if (isset($_POST['add'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $last_date = $_POST['last_date'];
    $amount = $_POST['amount'];
    $scholarshipcode = mysqli_real_escape_string($conn, $_POST['scholarshipcode']);

    $query = "INSERT INTO scholarships (name, scholarshipcode, description, last_date, amount)
              VALUES ('$name', '$scholarshipcode', '$desc', '$last_date', '$amount')";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'New scholarship added successfully!'];
    } else {
         $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not add scholarship.'];
    }
    
    header("Location: scholarships.php");
    exit;
}

// --- 4. NEW: Search & Pagination Logic ---
$limit = 6; // 6 scholarships per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

$search_term = '';
$search_query_sql = '';
$search_params = []; // For pagination links

if (!empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    // Search by name or code
    $search_query_sql = " WHERE name LIKE '%$search_term%' OR scholarshipcode LIKE '%$search_term%'";
    $search_params['search'] = $_GET['search']; // Add to params
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM scholarships" . $search_query_sql;
$total_records_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_row($total_records_result)[0];
$total_pages = ceil($total_records / $limit);

// Fetch paginated scholarships
$scholarships = mysqli_query($conn, "SELECT * FROM scholarships" . $search_query_sql . " ORDER BY name ASC LIMIT $limit OFFSET $offset");


// --- 5. Set Page Variables ---
$currentPage = 'scholarships';
$pageTitle = "Manage Scholarships";
$pageSubtitle = "Add, edit, or view scholarship details";

// --- 6. Include Header (HTML starts here) ---
include('header.php'); 
?>

<?php
// Display success/error messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']); // Clear the message after displaying
}
?>

<div class="action-container" style="margin-bottom: 20px; text-align: left;">
    <button id="show-add-form" class="button button-success">
        <i class="fa-solid fa-plus"></i> Add New Scholarship
    </button>
</div>

<div id="add-form-section" class="box" style="display: none;">
    <h3>Add New Scholarship</h3>
    <form method="post" action="scholarships.php">
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="scholarshipcode">Scholarship Code:</label>
                <input type="text" id="scholarshipcode" name="scholarshipcode" required>
            </div>
            <div class="form-group form-group-full">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="last_date">Last Date:</label>
                <input type="date" id="last_date" name="last_date">
            </div>
            <div class="form-group">
                <label for="amount">Amount:</label>
                <input type="number" id="amount" step="0.01" name="amount">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="add" class="button button-success">
                Add Scholarship
            </button>
            <button type="button" id="cancel-add-form" class="button button-danger">
                Cancel
            </button>
        </div>
    </form>
</div>

<div class="box">
    <h3>Existing Scholarships</h3>
    
    <form method="get" class="list-search-form" action="scholarships.php">
        <div class="modal-search-bar"> <i class="fa-solid fa-search"></i>
            <input type="text" name="search" placeholder="Search by name or code..." value="<?= htmlspecialchars($search_term) ?>">
        </div>
        <button type="submit" class="button button-primary">Search</button>
        <?php if (!empty($search_term)): ?>
            <a href="scholarships.php" class="button" style="background-color: var(--text-secondary); color: white;">Clear</a>
        <?php endif; ?>
    </form>

    <div class="scholarship-card-grid">
        <?php 
        if ($scholarships && mysqli_num_rows($scholarships) > 0) :
            while ($row = mysqli_fetch_assoc($scholarships)) { ?>
                
                <div class="scholarship-card">
                    <div class="scholarship-card-header">
                        
                        <div class="scholarship-card-toggle">
                            <i class="fa-solid fa-chevron-down accordion-icon"></i>
                            <div class="scholarship-card-title-group">
                                <h4><?= htmlspecialchars($row['name']) ?></h4>
                                <span class="card-code"><?= htmlspecialchars($row['scholarshipcode']) ?></span>
                            </div>
                        </div>

                        <div class="scholarship-card-actions">
                            <a href="edit_scholarship.php?id=<?= $row['id'] ?>&search=<?= urlencode($search_term) ?>" class="action-button" style="background-color:#ffc107;">Edit</a>
                            <a href="delete_scholarship.php?id=<?= $row['id'] ?>&search=<?= urlencode($search_term) ?>" class="action-button" style="background-color: var(--danger-color);" onclick="return confirm('Are you sure you want to delete this scholarship? This action cannot be undone.')">Delete</a>
                        </div>
                    </div>
                    
                    <div class="scholarship-card-body">
                        <div class="scholarship-card-content">
                            <strong>Description:</strong>
                            <p><?= !empty($row['description']) ? nl2br(htmlspecialchars($row['description'])) : '<em>No description provided.</em>' ?></p>
                            <hr>
                            <div class="accordion-footer-details">
                                <span><strong>Last Date:</strong> <?= !empty($row['last_date']) ? date("d M, Y", strtotime($row['last_date'])) : 'N/A' ?></span>
                                <span><strong>Amount:</strong> <?= !empty($row['amount']) ? '₹' . htmlspecialchars($row['amount']) : 'N/A' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            <?php }
        else: ?>
            <p class="no-applications" style="padding: 20px; text-align: center; grid-column: 1 / -1;">
                <?php if (!empty($search_term)): ?>
                    No scholarships found matching "<?= htmlspecialchars($search_term) ?>".
                <?php else: ?>
                    No scholarships found. Add one to get started!
                <?php endif; ?>
            </p>
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
</div> <p><a href="dashboard.php" class="back-link">← Back to Dashboard</a></p>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script src="js/scholarships.js"></script>
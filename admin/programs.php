<?php
session_start();
include("../config.php");

// --- Security & Login Checks ---
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Admin') {
    die("Access Denied: You do not have permission to view this page.");
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- Handle form submission (MUST be before any HTML) ---
if (isset($_POST['add_program'])) {
    $program_name = mysqli_real_escape_string($conn, $_POST['program_name']);
    
    // Check for duplicate program name
    $check = mysqli_query($conn, "SELECT * FROM programs WHERE name='$program_name'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: This program already exists!'];
    } else {
        $query = "INSERT INTO programs (name) VALUES ('$program_name')";
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'New program added successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not add the program.'];
        }
    }
    header("Location: programs.php");
    exit;
}

// --- Page-Specific Data Fetching with SEARCH ---
$search_term = '';
$search_query_sql = '';
$search_params = []; // For pagination links

if (!empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    $search_query_sql = " WHERE name LIKE '%$search_term%'";
    $search_params['search'] = $_GET['search']; // Add to params
}

// --- NEW PAGINATION LOGIC ---
$limit = 10; // Set programs per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM programs" . $search_query_sql;
$total_records_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_row($total_records_result)[0];
$total_pages = ceil($total_records / $limit);

// --- MODIFIED programs query with LIMIT and OFFSET ---
$programs = mysqli_query($conn, "SELECT * FROM programs" . $search_query_sql . " ORDER BY name ASC LIMIT $limit OFFSET $offset");

// --- Set Page Variables (for header.php) ---
$currentPage = 'programs';
$pageTitle = "Manage Programs";
$pageSubtitle = "Add, search, and manage academic programs";

// --- Include Header ---
include('header.php'); 
?>

<?php
// Display success/error messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']);
}
?>

<div class="content-grid">
    <div class="box add-program-box">
        <h3>Add New Program</h3>
        <form method="post" action="programs.php">
            <div class="form-group">
                <label for="program_name">Program Name:</label>
                <input type="text" id="program_name" name="program_name" placeholder="e.g., Bachelor of Computer Applications" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_program" class="button button-success">Add Program</button>
            </div>
        </form>
    </div>

    <div class="box programs-list-box">
        <h3>Existing Programs</h3>

        <form method="get" class="list-search-form" action="programs.php">
            <div class="modal-search-bar"> <i class="fa-solid fa-search"></i>
                <input type="text" name="search" placeholder="Search for programs..." value="<?= htmlspecialchars($search_term) ?>">
            </div>
            <button type="submit" class="button button-primary">Search</button>
            <?php if (!empty($search_term)): ?>
                <a href="programs.php" class="button" style="background-color: var(--text-secondary); color: white;">Clear</a>
            <?php endif; ?>
        </form>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Program Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($programs && mysqli_num_rows($programs) > 0) : ?>
                        <?php while ($row = mysqli_fetch_assoc($programs)) : ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="delete_program.php?id=<?= $row['id'] ?>&search=<?= urlencode($search_term) ?>" 
                                           class="action-button" 
                                           style="background-color: var(--danger-color);" 
                                           onclick="return confirm('Are you sure you want to delete this program? This cannot be undone.')">
                                           Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">
                                <?php if (!empty($search_term)): ?>
                                    No programs found matching "<?= htmlspecialchars($search_term) ?>".
                                <?php else: ?>
                                    No programs found. Add one to get started!
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div> <div class="pagination">
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
        </div> </div> <?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>
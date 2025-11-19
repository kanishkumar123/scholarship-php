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

// Get search term for redirect
$search_term = $_GET['search'] ?? '';
$search_query = !empty($search_term) ? '?search=' . urlencode($search_term) : '';

// --- 3. Handle Update on POST Request ---
if (isset($_POST['update_scholarship'])) {
    $scholarship_id = intval($_POST['scholarship_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $last_date = $_POST['last_date'];
    $amount = $_POST['amount'];
    $scholarshipcode = mysqli_real_escape_string($conn, $_POST['scholarshipcode']);

    $sql = "UPDATE scholarships SET 
                name = '$name', 
                scholarshipcode = '$scholarshipcode', 
                description = '$desc', 
                last_date = '$last_date', 
                amount = '$amount'
            WHERE id = $scholarship_id";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Scholarship updated successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not update scholarship.'];
    }
    
    header("Location: scholarships.php" . $search_query); // Redirect with search term
    exit;
}

// --- 4. Fetch Data for Page Display (GET Request) ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: scholarships.php");
    exit;
}
$scholarship_id = intval($_GET['id']);

$result = mysqli_query($conn, "SELECT * FROM scholarships WHERE id = $scholarship_id");
if (mysqli_num_rows($result) == 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Scholarship not found.'];
    header("Location: scholarships.php");
    exit;
}
$scholarship = mysqli_fetch_assoc($result);

// --- 5. Set Page Variables ---
$currentPage = 'scholarships';
$pageTitle = "Edit Scholarship";
$pageSubtitle = "Editing: " . htmlspecialchars($scholarship['name']);

// --- 6. Include Header ---
include('header.php'); 
?>

<div class="box" style="max-width: 900px; margin: 0 auto;">
    <h3>Edit Scholarship</h3>
    <form method="post" action="edit_scholarship.php?search=<?= urlencode($search_term) ?>">
        <input type="hidden" name="scholarship_id" value="<?= $scholarship['id'] ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($scholarship['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="scholarshipcode">Scholarship Code:</label>
                <input type="text" id="scholarshipcode" name="scholarshipcode" value="<?= htmlspecialchars($scholarship['scholarshipcode']) ?>" required>
            </div>
            <div class="form-group form-group-full">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($scholarship['description']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="last_date">Last Date:</label>
                <input type="date" id="last_date" name="last_date" value="<?= htmlspecialchars($scholarship['last_date']) ?>">
            </div>
            <div class="form-group">
                <label for="amount">Amount:</label>
                <input type="number" id="amount" step="0.01" name="amount" value="<?= htmlspecialchars($scholarship['amount']) ?>">
            </div>
        </div>
        
        <div class="form-actions" style="justify-content: space-between;">
            <a href="scholarships.php<?= $search_query ?>" class="button" style="background-color: var(--text-secondary); color: white;">Cancel</a>
            <button type="submit" name="update_scholarship" class="button button-success">Save Changes</button>
        </div>
    </form>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>
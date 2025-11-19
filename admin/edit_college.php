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

// --- 3. Handle Update on POST Request ---
if (isset($_POST['update_college'])) {
    $college_id = intval($_POST['college_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $collegecode = mysqli_real_escape_string($conn, $_POST['collegecode']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $nodalofficername = isset($_POST['nodalofficername']) ? mysqli_real_escape_string($conn, $_POST['nodalofficername']) : '';

    // Check for duplicate college code (but exclude self)
    $check = mysqli_query($conn, "SELECT * FROM colleges WHERE collegecode='$collegecode' AND id != $college_id");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: That college code already exists for another college.'];
    } else {
        $sql = "UPDATE colleges SET 
                    name = '$name', 
                    collegecode = '$collegecode', 
                    description = '$description', 
                    contact = '$contact', 
                    nodalofficername = '$nodalofficername' 
                WHERE id = $college_id";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'College updated successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not update college.'];
        }
    }
    header("Location: college.php");
    exit;
}

// --- 4. Fetch Data for Page Display (GET Request) ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: college.php");
    exit;
}
$college_id = intval($_GET['id']);

$college_result = mysqli_query($conn, "SELECT * FROM colleges WHERE id = $college_id");
if (mysqli_num_rows($college_result) == 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: College not found.'];
    header("Location: college.php");
    exit;
}
$college = mysqli_fetch_assoc($college_result);

// --- 5. Set Page Variables ---
$currentPage = 'colleges';
$pageTitle = "Edit College";
$pageSubtitle = "Editing: " . htmlspecialchars($college['name']);

// --- 6. Include Header ---
include('header.php'); 
?>

<div class="box" style="max-width: 900px; margin: 0 auto;">
    <h3>Edit College</h3>
    <form method="post">
        <input type="hidden" name="college_id" value="<?= $college['id'] ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="name">College Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($college['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="collegecode">College Code:</label>
                <input type="text" id="collegecode" name="collegecode" value="<?= htmlspecialchars($college['collegecode']) ?>" required>
            </div>
            <div class="form-group">
                <label for="nodalofficername">Nodal Officer Name:</label>
                <input type="text" id="nodalofficername" name="nodalofficername" value="<?= htmlspecialchars($college['nodalofficername']) ?>">
            </div>
            <div class="form-group">
                <label for="contact">Contact:</label>
                <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($college['contact']) ?>">
            </div>
            <div class="form-group form-group-full">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($college['description']) ?></textarea>
            </div>
        </div>
        
        <div class="form-actions" style="justify-content: space-between;">
            <a href="college.php" class="button" style="background-color: var(--text-secondary); color: white;">Cancel</a>
            <button type="submit" name="update_college" class="button button-success">Save Changes</button>
        </div>
    </form>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>
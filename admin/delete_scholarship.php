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

// --- 3. Handle Delete Action ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid scholarship ID.'];
    header("Location: scholarships.php");
    exit;
}

$scholarship_id_to_delete = intval($_GET['id']);
$search_term = $_GET['search'] ?? ''; // Get search term to pass back

// --- 4. CRITICAL SAFETY CHECK ---
// Check if this scholarship is in use by students or applications
$check_students = mysqli_query($conn, "SELECT id FROM scholarship_students WHERE scholarship_id = $scholarship_id_to_delete");
$check_apps = mysqli_query($conn, "SELECT id FROM applications WHERE scholarship_id = $scholarship_id_to_delete");

if (mysqli_num_rows($check_students) > 0 || mysqli_num_rows($check_apps) > 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Cannot delete. This scholarship is linked to existing students or applications.'];
} else {
    // --- 5. Perform Delete (If all checks pass) ---
    $sql = "DELETE FROM scholarships WHERE id = $scholarship_id_to_delete";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Scholarship deleted successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not delete scholarship.'];
    }
}

// --- 6. Redirect back (with search term) ---
$search_query = !empty($search_term) ? '?search=' . urlencode($search_term) : '';
header("Location: scholarships.php" . $search_query);
exit;
?>
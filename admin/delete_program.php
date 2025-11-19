<?php
// --- 1. ALL PHP LOGIC GOES FIRST ---
session_start();
include("../config.php");

// --- 2. Security & Login Checks ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
// Only allow Admin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Admin') {
    die("Access Denied: You do not have permission to view this page.");
}

// --- 3. Handle Delete Action ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid program ID.'];
    header("Location: programs.php");
    exit;
}

$program_id_to_delete = intval($_GET['id']);

// --- 4. CRITICAL SAFETY CHECK ---
// Check if this program is being used in mappings
$check_map = mysqli_query($conn, "SELECT college_id FROM college_program_mapping WHERE program_id = $program_id_to_delete");

// Check if this program is being used in applications (assumes 'course' stores the program ID)
$check_app = mysqli_query($conn, "SELECT id FROM applications WHERE course = $program_id_to_delete");

if (mysqli_num_rows($check_map) > 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Cannot delete. This program is mapped to one or more colleges.'];
} elseif (mysqli_num_rows($check_app) > 0) {
     $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Cannot delete. This program is linked to existing applications.'];
} else {
    // --- 5. Perform Delete (If all checks pass) ---
    $sql = "DELETE FROM programs WHERE id = $program_id_to_delete";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Program deleted successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not delete program.'];
    }
}

// --- 6. Redirect back ---
// (Redirect back with any search terms that were active)
$search_query = isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : '';
header("Location: programs.php" . $search_query);
exit;
?>
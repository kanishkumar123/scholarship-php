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
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid college ID.'];
    header("Location: college.php");
    exit;
}

$college_id_to_delete = intval($_GET['id']);

// --- 4. CRITICAL SAFETY CHECK ---
// Check if this college is being used in other tables.
$check_users = mysqli_query($conn, "SELECT id FROM admin_users WHERE college_id = $college_id_to_delete");
$check_mappings = mysqli_query($conn, "SELECT college_id FROM college_program_mapping WHERE college_id = $college_id_to_delete");
$check_applications = mysqli_query($conn, "SELECT id FROM applications WHERE institution_name = $college_id_to_delete"); // Assumes institution_name stores the ID

if (mysqli_num_rows($check_users) > 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Cannot delete college. It is assigned to one or more users.'];
} elseif (mysqli_num_rows($check_mappings) > 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Cannot delete college. It has one or more programs mapped to it.'];
} elseif (mysqli_num_rows($check_applications) > 0) {
     $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Cannot delete college. It is linked to existing applications.'];
} else {
    // --- 5. Perform Delete (If all checks pass) ---
    $sql = "DELETE FROM colleges WHERE id = $college_id_to_delete";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'College deleted successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not delete college.'];
    }
}

// --- 6. Redirect back ---
header("Location: college.php");
exit;
?>
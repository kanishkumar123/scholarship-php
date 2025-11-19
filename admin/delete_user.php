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
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid user ID.'];
    header("Location: user_management.php");
    exit;
}

$user_id_to_delete = intval($_GET['id']);

// --- 4. CRITICAL: Prevent admin from deleting themselves! ---
if ($user_id_to_delete == $_SESSION['admin_id']) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: You cannot delete your own account.'];
    header("Location: user_management.php");
    exit;
}

// --- 5. Perform Delete ---
$sql = "DELETE FROM admin_users WHERE id = $user_id_to_delete";

if (mysqli_query($conn, $sql)) {
    $_SESSION['message'] = ['type' => 'success', 'text' => 'User deleted successfully!'];
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not delete user.'];
}

header("Location: user_management.php");
exit;

?>
<?php
session_start();
include("../config.php");

// Only run if an admin is logged in
if (isset($_SESSION['admin_id'])) {
    
    // Update all unread applications to be read
    mysqli_query($conn, "UPDATE applications SET admin_read = 1 WHERE admin_read = 0");
    
    // Send back a success response
    echo json_encode(['status' => 'success']);
    exit;
}

// Send an error if not logged in
echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
?>
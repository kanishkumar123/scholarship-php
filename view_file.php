<?php
session_start();
// Include your database connection config
include("config.php"); 

if (!isset($conn) || mysqli_connect_errno()) {
    // For security, don't reveal database errors publicly in production
    die("Error: Could not connect to database.");
}

// 1. Basic Security and Input Validation
if (!isset($_GET['app_id']) || !isset($_GET['type'])) {
    die("Missing file parameters.");
}

$app_id = intval($_GET['app_id']);
$file_type = $_GET['type'];

// Check user session/permissions (CRITICAL SECURITY STEP)
// Only allow viewing if the current user owns this application or is an admin.
if (!isset($_SESSION['application_id']) || $app_id !== intval($_SESSION['application_id'])) {
    // You should check if the user is an admin here if necessary.
    // For this basic example, we restrict to the session holder.
    // Replace this with your actual permission logic.
    die("Access denied. You do not have permission to view this file.");
}

// 2. Database Lookup using Prepared Statement (Recommended over simple mysqli_query)
$stmt = $conn->prepare("SELECT file_path FROM application_files WHERE application_id = ? AND file_type = ? LIMIT 1");
$stmt->bind_param("is", $app_id, $file_type);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Document not found for this application type.");
}

$file_row = $result->fetch_assoc();
$relative_file_path = $file_row['file_path'];

// 3. Absolute Path Resolution (CRITICAL SECURITY STEP)
// Ensure the path is within the allowed 'uploads' directory
$base_dir = __DIR__ . '/'; // Current directory of view_file.php
$file_path = realpath($base_dir . $relative_file_path);

// Double check that the resolved path starts with the allowed directory
// This prevents directory traversal attacks (e.g., ../../etc/passwd)
if (!$file_path || strpos($file_path, realpath($base_dir . 'uploads/')) !== 0) {
    die("Error: Invalid file path.");
}

if (!file_exists($file_path)) {
    die("Error: File physically not found.");
}

// 4. Serve the File
$mime = mime_content_type($file_path);
$file_size = filesize($file_path);
$filename = basename($file_path);

// Set headers for viewing the file inline (in the browser)
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=86400'); // Cache for a day

// Clear output buffer before reading file
if (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;

?>
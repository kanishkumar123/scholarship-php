<?php
session_start();
include("../config.php"); // For $conn

// Security: Must be a logged-in admin
if (!isset($_SESSION['admin_id'])) {
    die("Access Denied.");
}

// Get the application ID and file type from the URL
$application_id = intval($_GET['app_id'] ?? 0);
$file_type = mysqli_real_escape_string($conn, $_GET['type'] ?? '');

if ($application_id <= 0 || empty($file_type)) {
    die("Invalid file request.");
}

// --- Fetch the file path from the database ---
$query = "SELECT file_path, file_name FROM application_files 
          WHERE application_id = ? AND file_type = ? 
          LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "is", $application_id, $file_type);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$file_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$file_data) {
    die("File not found in database or you do not have permission.");
}

// --- ❗️ IMPORTANT: This is the fix for "File Not Found" ---
// We assume the path stored in the DB is RELATIVE, e.g., "uploads/file.pdf"
// We must build the FULL server path to read the file.

// `__DIR__` is the directory of *this* file (e.g., /var/www/scholarship/admin)
// `dirname(__DIR__)` goes one level up to the project root (e.g., /var/www/scholarship)
$project_root = dirname(__DIR__); 

$relative_path = $file_data['file_path'];
$original_filename = $file_data['file_name'];

// Combine the root path with the relative path from the DB
$full_file_path = $project_root . '/' . $relative_path;

// Check if the file *actually* exists on the server
if (!file_exists($full_file_path)) {
    die("File Not Found on server. Path is broken: " . htmlspecialchars($full_file_path));
}

// --- Serve the file securely ---
$file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
$content_type = 'application/octet-stream'; // Default download

// Set a more specific content type if known
switch ($file_extension) {
    case 'pdf':
        $content_type = 'application/pdf';
        break;
    case 'jpg':
    case 'jpeg':
        $content_type = 'image/jpeg';
        break;
    case 'png':
        $content_type = 'image/png';
        break;
}

// Send headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($full_file_path));

// If it's not an image, force "Save As" dialog
if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'pdf'])) {
    header('Content-Disposition: attachment; filename="' . basename($original_filename) . '"');
} else {
    // For images/PDFs, show "inline" in the browser
    header('Content-Disposition: inline; filename="' . basename($original_filename) . '"');
}

// Clear any output buffer (like ob_start() in config.php)
while (ob_get_level()) {
    ob_end_clean();
}

// Read the file and output its contents
readfile($full_file_path);
exit;
?>
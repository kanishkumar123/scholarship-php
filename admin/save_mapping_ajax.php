<?php
ob_start(); // Start an output buffer

session_start();
include("../config.php");

// Default response
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

// ❗️❗️CRITICAL FIX: Check if the database connection exists and is valid
if (!$conn || mysqli_connect_errno()) {
    $response['message'] = 'Database connection failed. Check config.php.';
    ob_end_clean(); // Clean the buffer
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- Security Check ---
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Admin') {
    $response['message'] = 'Access Denied';
    ob_end_clean(); // Clean the buffer
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- Get Data from JS (sent as JSON) ---
$data = json_decode(file_get_contents('php://input'), true);

$college_id = intval($data['college_id'] ?? 0);
$program_ids = $data['program_ids'] ?? []; // This is now an array of IDs

if ($college_id > 0) {
    // --- Safer Transaction ---
    mysqli_begin_transaction($conn);
    try {
        // 1. Delete *only* the old mappings for this specific college
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM college_program_mapping WHERE college_id = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $college_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);

        // 2. Insert the new mappings (if any were selected)
        if (!empty($program_ids)) {
            $insert_stmt = mysqli_prepare($conn, "INSERT INTO college_program_mapping (college_id, program_id) VALUES (?, ?)");
            foreach ($program_ids as $program_id) {
                $p_id = intval($program_id);
                if ($p_id > 0) {
                    mysqli_stmt_bind_param($insert_stmt, "ii", $college_id, $p_id);
                    mysqli_stmt_execute($insert_stmt);
                }
            }
            mysqli_stmt_close($insert_stmt);
        }
        
        // 3. Commit the changes
        mysqli_commit($conn);
        $response = [
            'status' => 'success', 
            'message' => 'Mappings updated successfully!',
            'new_count' => count($program_ids) // Send back the new count
        ];

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($conn);
        $response['message'] = 'Database error: ' . $exception->getMessage();
    }

} else {
    $response['message'] = 'Invalid College ID.';
}

// --- Send JSON response back to JavaScript ---
ob_end_clean(); // Discard the buffer (with all warnings/spaces)
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
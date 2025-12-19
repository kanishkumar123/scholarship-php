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

    // A. Check for duplicate college code (excluding self)
    $check = mysqli_query($conn, "SELECT * FROM colleges WHERE collegecode='$collegecode' AND id != $college_id");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: That college code already exists for another college.'];
        header("Location: edit_college.php?id=$college_id");
        exit;
    }

    // B. Handle Signature Logic
    
    // 1. Fetch current data to know existing file path
    $current_query = mysqli_query($conn, "SELECT signature_path FROM colleges WHERE id = $college_id");
    $current_data = mysqli_fetch_assoc($current_query);
    $final_signature_path = $current_data['signature_path']; // Default to keeping existing
    $upload_dir = '../uploads/signatures/';

    // 2. Check if user wants to DELETE existing signature
    if (isset($_POST['delete_signature']) && $_POST['delete_signature'] == '1') {
        if (!empty($final_signature_path) && file_exists('../' . $final_signature_path)) {
            unlink('../' . $final_signature_path); // Delete file from server
        }
        $final_signature_path = 'NULL';
    }

    // 3. Check if user Uploaded a NEW File
    if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['signature_image']['name'];
        $filetype = $_FILES['signature_image']['type'];
        $filesize = $_FILES['signature_image']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
             $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Only JPG and PNG files are allowed.'];
             header("Location: edit_college.php?id=$college_id");
             exit;
        }

        if ($filesize > 2 * 1024 * 1024) { // 2MB Limit
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: File size must be less than 2MB.'];
            header("Location: edit_college.php?id=$college_id");
            exit;
        }

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate new name
        $new_filename = $collegecode . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['signature_image']['tmp_name'], $destination)) {
            // Delete OLD file if it exists and wasn't already deleted above
            if ($final_signature_path !== 'NULL' && !empty($current_data['signature_path']) && file_exists('../' . $current_data['signature_path'])) {
                unlink('../' . $current_data['signature_path']);
            }
            
            // Set new path
            $final_signature_path = 'uploads/signatures/' . $new_filename;
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Failed to upload new image.'];
            header("Location: edit_college.php?id=$college_id");
            exit;
        }
    }

    // C. Update Database
    // We handle the NULL value for SQL string
    $sql_sig = ($final_signature_path === 'NULL') ? "NULL" : "'$final_signature_path'";

    $sql = "UPDATE colleges SET 
                name = '$name', 
                collegecode = '$collegecode', 
                description = '$description', 
                contact = '$contact', 
                nodalofficername = '$nodalofficername',
                signature_path = $sql_sig
            WHERE id = $college_id";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'College updated successfully!'];
        header("Location: college.php"); // Redirect to main list on success
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not update college.'];
        header("Location: edit_college.php?id=$college_id");
    }
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

<?php
// Display error messages inside the page if redirecting back to edit
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '" style="max-width: 900px; margin: 0 auto 20px auto;">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']);
}
?>

<div class="box" style="max-width: 900px; margin: 0 auto;">
    <h3>Edit College</h3>
    <form method="post" enctype="multipart/form-data">
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

            <div class="form-group">
                <label>Head of Institution Signature:</label>
                
                <?php if (!empty($college['signature_path']) && file_exists('../' . $college['signature_path'])): ?>
                    <div style="margin-bottom: 10px; padding: 10px; border: 1px solid #eee; border-radius: 4px; background: #f9f9f9;">
                        <p style="margin: 0 0 5px 0; font-size: 0.9em; color: #666;">Current Signature:</p>
                        <img src="../<?= htmlspecialchars($college['signature_path']) ?>" alt="Signature" style="max-height: 60px; border: 1px solid #ddd;">
                        <div style="margin-top: 5px;">
                            <label style="font-weight: normal; cursor: pointer; color: #dc3545;">
                                <input type="checkbox" name="delete_signature" value="1"> Delete this signature
                            </label>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 10px; color: #888; font-style: italic;">No signature uploaded.</div>
                <?php endif; ?>

                <label for="signature_image" style="font-size: 0.9em;">Upload New (Overwrites existing):</label>
                <input type="file" id="signature_image" name="signature_image" accept=".png, .jpg, .jpeg">
                <small style="color: #666;">Format: PNG/JPG. Max 2MB.</small>
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
include('footer.php'); 
?>
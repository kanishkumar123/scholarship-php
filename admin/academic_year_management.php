<?php
// --- 1. ALL PHP LOGIC GOES FIRST ---
session_start();
include("../config.php");

// --- 2. Security & Login Checks ---
// Only admin/staff can access
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
// (Optional) You can also add an Admin role check if needed
// if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'Admin') {
//     die("Access Denied: You do not have permission to view this page.");
// }

// --- 3. Handle Add new academic year ---
if (isset($_POST['add_year'])) {
    $new_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
    
    // Check if already exists
    $check = mysqli_query($conn, "SELECT id FROM academic_years WHERE year_range='$new_year'");
    if (mysqli_num_rows($check) == 0) {
        // Simple created_at, assumes your column is TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        if(mysqli_query($conn, "INSERT INTO academic_years (year_range) VALUES ('$new_year')")) {
             $_SESSION['message'] = ['type' => 'success', 'text' => "Academic year ($new_year) added successfully!"];
        } else {
             $_SESSION['message'] = ['type' => 'error', 'text' => "Database error: Could not add year."];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => "This academic year ($new_year) already exists."];
    }
    header("Location: academic_year_management.php"); // Redirect back to this same page
    exit;
}

// --- 4. Fetch Page Data ---
$years_result = mysqli_query($conn, "SELECT * FROM academic_years ORDER BY id DESC");

// --- 5. Set Page Variables ---
$currentPage = 'academic_year_management'; // Matches the link in your sidebar
$pageTitle = "Academic Year Management";
$pageSubtitle = "Add and view all available academic years";

// --- 6. Include Header (HTML starts here) ---
include('header.php'); 
?>

<?php
// Display success/error messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']);
}
?>

<div class="content-grid">
    <div class="box add-program-box"> <h3>Add Academic Year</h3>
        <form method="post" class="add-year-form">
            <div class="form-group">
                 <label for="academic_year" class="sr-only">Academic Year</label>
                 <input type="text" id="academic_year" name="academic_year" placeholder="e.g., 2025-2026" required>
            </div>
            <button type="submit" name="add_year" class="button button-primary">Add Year</button>
        </form>
    </div>

    <div class="box programs-list-box"> <h3>Existing Academic Years</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Academic Year</th>
                        <th>Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($years_result) > 0) : ?>
                        <?php while($row = mysqli_fetch_assoc($years_result)) : ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><strong><?= htmlspecialchars($row['year_range']) ?></strong></td>
                            <td>
                                <?php 
                                // Format date, check for NULL or invalid dates
                                if (!empty($row['created_at']) && $row['created_at'] !== '0000-00-00 00:00:00') {
                                    echo date("M d, Y, h:i A", strtotime($row['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">No academic years found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>
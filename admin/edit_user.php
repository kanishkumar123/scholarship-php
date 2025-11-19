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

// --- 3. Handle Update on POST Request ---
if (isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $college_id = ($role === 'Admin' || empty($_POST['college_id'])) ? 'NULL' : intval($_POST['college_id']);

    // Check for duplicate username (but exclude self)
    $check = mysqli_query($conn, "SELECT id FROM admin_users WHERE username='$username' AND id != $user_id");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Username already exists.'];
    } else {
        // Check if password needs to be updated
        if (!empty($_POST['password'])) {
            // New password was entered, hash it
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE admin_users SET 
                        username = '$username', 
                        password = '$password', 
                        role = '$role', 
                        college_id = $college_id 
                    WHERE id = $user_id";
        } else {
            // No new password, keep the old one
            $sql = "UPDATE admin_users SET 
                        username = '$username', 
                        role = '$role', 
                        college_id = $college_id 
                    WHERE id = $user_id";
        }
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'User updated successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not update user.'];
        }
    }
    // Redirect back to the main user list
    header("Location: user_management.php");
    exit;
}

// --- 4. Fetch Data for Page Display (GET Request) ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user_management.php");
    exit;
}
$user_id = intval($_GET['id']);

$user_result = mysqli_query($conn, "SELECT * FROM admin_users WHERE id = $user_id");
if (mysqli_num_rows($user_result) == 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: User not found.'];
    header("Location: user_management.php");
    exit;
}
$user = mysqli_fetch_assoc($user_result);

// Fetch Colleges for the dropdown
$colleges = mysqli_query($conn, "SELECT id, name FROM colleges ORDER BY name ASC");

// --- 5. Set Page Variables ---
$currentPage = 'users';
$pageTitle = "Edit User";
$pageSubtitle = "Editing user: " . htmlspecialchars($user['username']);

// --- 6. Include Header ---
include('header.php'); 
?>

<div class="box" style="max-width: 700px; margin: 0 auto;">
    <h3>Edit User</h3>
    <form method="post">
        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
        
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
        </div>
        
        <div class="form-group">
            <label for="role">Role</label>
            <select name="role" id="role" required>
                <option value="Admin" <?= ($user['role'] === 'Admin') ? 'selected' : '' ?>>Admin</option>
                <option value="Principal" <?= ($user['role'] === 'Principal') ? 'selected' : '' ?>>Principal</option>
                <option value="Nodal Officer" <?= ($user['role'] === 'Nodal Officer') ? 'selected' : '' ?>>Nodal Officer</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="college_id">College (for Principal/Nodal Officer)</label>
            <select name="college_id" id="college_id">
                <option value="">-- Select College --</option>
                <?php while($col = mysqli_fetch_assoc($colleges)) : ?>
                    <option value="<?= $col['id'] ?>" <?= ($user['college_id'] == $col['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($col['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-actions" style="justify-content: space-between;">
            <a href="user_management.php" class="button" style="background-color: var(--text-secondary); color: white;">Cancel</a>
            <button type="submit" name="update_user" class="button button-success">Save Changes</button>
        </div>
    </form>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script src="js/user_management.js"></script>
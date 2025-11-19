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

// --- 3. Handle Add User ---
if (isset($_POST['add_user'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $college_id = ($role === 'Admin' || empty($_POST['college_id'])) ? 'NULL' : intval($_POST['college_id']);

    // Check for duplicate username
    $check = mysqli_query($conn, "SELECT id FROM admin_users WHERE username='$username'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Username already exists.'];
    } else {
        $sql = "INSERT INTO admin_users (username, password, role, college_id) 
                VALUES ('$username', '$password', '$role', $college_id)";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'User added successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not add user.'];
        }
    }
    header("Location: user_management.php");
    exit;
}

// --- 4. Fetch Page Data ---
// Fetch Users with college names
$users = mysqli_query($conn, "
    SELECT a.*, c.name AS college_name 
    FROM admin_users a
    LEFT JOIN colleges c ON a.college_id = c.id
    ORDER BY a.id DESC
");
// Fetch Colleges for the dropdown
$colleges = mysqli_query($conn, "SELECT id, name FROM colleges ORDER BY name ASC");

// --- 5. Set Page Variables ---
$currentPage = 'users';
$pageTitle = "User Management";
$pageSubtitle = "Add, view, and manage system users";

// --- 6. Include Header ---
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
    <div class="box add-user-box">
        <h3>Add New User</h3>
        <form method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="filter-group">
                <label for="role">Role</label>
                <select name="role" id="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="Admin">Admin</option>
                    <option value="Principal">Principal</option>
                    <option value="Nodal Officer">Nodal Officer</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="college_id">College (for Principal/Nodal Officer)</label>
                <select name="college_id" id="college_id" disabled>
                    <option value="">-- Select Role First --</option>
                    <?php while($col = mysqli_fetch_assoc($colleges)) : ?>
                        <option value="<?= $col['id'] ?>"><?= htmlspecialchars($col['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_user" class="button button-success">Add User</button>
            </div>
        </form>
    </div>

    <div class="box users-list-box">
        <h3>Existing Users</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Assigned College</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($users)) : ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td><?= htmlspecialchars($user['college_name'] ?? 'N/A') ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="action-button" style="background-color:#ffc107;">Edit</a>
                                    <a href="delete_user.php?id=<?= $user['id'] ?>" class="action-button" style="background-color: var(--danger-color);" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>


<script src="js/user_management.js"></script>
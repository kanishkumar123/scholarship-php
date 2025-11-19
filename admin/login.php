<?php
// --- 1. SETUP ---
session_start();
include("../config.php");

// If the user is already logged in, redirect them to the dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = ""; // Initialize error message variable

// --- 2. PROCESS FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {

        $error = "Username and password are required.";

    } else {
        // --- 3. SECURE DATABASE QUERY ---
        $stmt = $conn->prepare("SELECT id, username, password, role FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // --- 4. VERIFY THE HASHED PASSWORD ---
            if (password_verify($password, $user['password'])) {
                // --- 5. SUCCESS: SET SESSIONS AND REDIRECT ---
                session_regenerate_id(true); // Security measure
                
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $user['role']; 

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/login_style.css">
</head>
<body>

    <canvas id="particle-canvas"></canvas>
    <ul class="bg-slideshow">
        <li></li> <li></li> <li></li> <li></li> <li></li>
    </ul>

    <div class="login-box"> 
        <form action="login.php" method="post" class="login-form">
            
            <h3 class="university-title">
                <span>VINAYAKA MISSIONS RESEARCH FOUNDATION</span>
            </h3>

            <h2>Admin Portal</h2>
            <p>Welcome back, please log in.</p>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <input type="text" id="username" name="username" placeholder="" required autocomplete="off"
                       readonly onfocus="this.removeAttribute('readonly');">
                <label for="username">Username</label>
                <div class="input-bg"></div>
            </div>

            <div class="form-group">
                <input type="password" id="password" name="password" placeholder="" required
                       readonly onfocus="this.removeAttribute('readonly');">
                <label for="password">Password</label>
                <div class="input-bg"></div>
            </div>
            
            <button type="submit" name="login" class="login-btn">Login</button>

            <div class="social-icons">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter (X)"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
            
            <div class="form-footer">
                <a href="../index.php">
                    <i class="fa-solid fa-user-graduate"></i> Are you a Student?
                </a>
            </div>
        </form>
    </div>

    <script src="js/particles.js"></script>
    
    </body>
</html>
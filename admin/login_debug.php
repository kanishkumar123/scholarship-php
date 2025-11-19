<?php
echo "<h1>Login Debugging...</h1>";

// --- 1. SETUP ---
session_start();
include("../config.php");

// --- Check Database Connection ---
if ($conn->connect_error) {
    die("<h2>DEBUG: DATABASE CONNECTION FAILED!</h2><p>Error: " . $conn->connect_error . "</p><p><b>Fix:</b> Please check your database credentials (host, username, password, db name) in your `config.php` file.</p>");
}
echo "<p><strong>DEBUG:</strong> Database connection successful.</p>";


// --- 2. PROCESS FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    echo "<p><strong>DEBUG:</strong> Form was submitted.</p>";
    $username = $_POST['username'];
    $password = $_POST['password'];

    echo "<p><strong>DEBUG:</strong> Username entered: '" . htmlspecialchars($username) . "'</p>";
    echo "<p><strong>DEBUG:</strong> Password entered: '********'</p>";

    if (empty($username) || empty($password)) {
        die("<p><strong>DEBUG: ERROR:</strong> Username or password was empty.</p>");
    }

    // --- 3. SECURE DATABASE QUERY ---
    $stmt = $conn->prepare("SELECT id, username, password, role FROM admin_users WHERE username = ?");
    if ($stmt === false) {
        die("<h2>DEBUG: PREPARED STATEMENT FAILED!</h2><p>Error: " . $conn->error . "</p><p><b>Fix:</b> Check if your table is named `admin_users` and has a `username` column.</p>");
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<p><strong>DEBUG:</strong> Query executed. Number of users found with that username: " . $result->num_rows . "</p>";

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        echo "<p><strong>DEBUG:</strong> User '" . htmlspecialchars($user['username']) . "' found in the database.</p>";
        echo "<p><strong>DEBUG:</strong> Stored password hash is: <br><strong style='font-family: monospace;'>" . htmlspecialchars($user['password']) . "</strong></p>";

        // --- 4. VERIFY THE HASHED PASSWORD ---
        echo "<p><strong>DEBUG:</strong> Now checking if the password you entered matches the hash...</p>";
        
        if (password_verify($password, $user['password'])) {
            echo "<h2>DEBUG: SUCCESS!</h2>";
            echo "<p>The password is correct. Login would have been successful.</p>";
            // The redirect is commented out so you can see this message.
            // header("Location: dashboard.php");
            // exit;
        } else {
            echo "<h2>DEBUG: FAILED!</h2>";
            echo "<p><strong>Reason:</strong> The password you entered does NOT match the stored hash.</p>";
            echo "<p><b>Fix:</b> You must generate a new hash for your password and update the database. Use the `create_hash.php` tool from our previous conversation.</p>";
        }
    } else {
        echo "<h2>DEBUG: FAILED!</h2>";
        echo "<p><strong>Reason:</strong> No user was found with that username in the `admin_users` table.</p>";
        echo "<p><b>Fix:</b> Double-check the spelling of the username. It is case-sensitive.</p>";
    }
    $stmt->close();
    
} else {
    echo "<p><strong>DEBUG:</strong> Form has not been submitted yet. Please go to login.php and try to log in.</p>";
}
?>
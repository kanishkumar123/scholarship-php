<?php
// Database Configuration
$host = "localhost";   // Database host
$user = "root";        // Database username
$pass = "";            // Database password
$dbname = "ssvmrf"; // Database name

// Create connection
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Optional: Set UTF8 encoding
mysqli_set_charset($conn, "utf8");
?>
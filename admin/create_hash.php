<?php
// --- IMPORTANT: CHANGE THIS TO THE PASSWORD YOU WANT TO USE ---
$myPassword = '123';

// Generate the secure hash
$hashedPassword = password_hash($myPassword, PASSWORD_DEFAULT);

// Display the hash
echo "<h3>Your New Secure Password Hash</h3>";
echo "<p>Copy this entire hash and paste it into the 'password' column in your database.</p>";
echo "<hr>";
echo "<p style='font-family: monospace; font-size: 1.1rem; background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 8px; word-wrap: break-word;'>" . htmlspecialchars($hashedPassword) . "</p>";
echo "<hr>";
echo "<p>After updating the database, log in with the password: <strong>" . htmlspecialchars($myPassword) . "</strong></p>";
?>
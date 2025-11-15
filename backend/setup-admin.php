<?php
/**
 * Setup Admin Script
 * Run this file ONCE after importing the database schema
 * Access: http://localhost/london-park-ticketing/backend/setup_admin.php
 */

require_once 'config/database.php';

// Generate password hash for 'admin123'
$password = 'admin123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Admin Setup Script</h2>";
echo "<p>Generating password hash for: <strong>admin123</strong></p>";
echo "<p>Hash: <code>$password_hash</code></p>";

$conn = getDBConnection();

// Check if admin exists
$stmt = $conn->prepare("SELECT id, username FROM users WHERE username = 'admin'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin password
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->bind_param("s", $password_hash);

    if ($stmt->execute()) {
        echo "<p style='color: green;'><strong>✓ Admin password updated successfully!</strong></p>";
        echo "<p>You can now login with:</p>";
        echo "<ul>";
        echo "<li><strong>Username:</strong> admin</li>";
        echo "<li><strong>Password:</strong> admin123</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'><strong>✗ Failed to update admin password</strong></p>";
    }
} else {
    // Create new admin user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES ('admin', 'admin@londonpark.com', ?, 'System Administrator', 'admin')");
    $stmt->bind_param("s", $password_hash);

    if ($stmt->execute()) {
        echo "<p style='color: green;'><strong>✓ Admin user created successfully!</strong></p>";
        echo "<p>You can now login with:</p>";
        echo "<ul>";
        echo "<li><strong>Username:</strong> admin</li>";
        echo "<li><strong>Password:</strong> admin123</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'><strong>✗ Failed to create admin user</strong></p>";
    }
}

$stmt->close();
$conn->close();

echo "<hr>";
echo "<p><strong>IMPORTANT:</strong> Delete this file after setup for security!</p>";
echo "<p><a href='../frontend/admin-login.html'>Go to Admin Login</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}
h2 {
    color: #2c5f2d;
}
code {
    background: #e0e0e0;
    padding: 5px 10px;
    border-radius: 3px;
    display: block;
    margin: 10px 0;
    word-break: break-all;
}
ul {
    background: white;
    padding: 20px 40px;
    border-radius: 5px;
}
a {
    display: inline-block;
    background: #2c5f2d;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    margin-top: 10px;
}
a:hover {
    background: #1a4314;
}
</style>

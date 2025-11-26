<?php
session_start();

// --- CONFIGURATION ---
$servername = "localhost";
$username = "root";
$password = ""; // IMPORTANT: Use your actual MySQL password here!
$dbname = "batcave_db";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize user input
    $input_username = trim($_POST['username'] ?? '');
    $input_password = $_POST['password'] ?? '';
    
    // 1. Connect to MySQL
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        $_SESSION['login_error'] = "Database error. Please try again later.";
        header('Location: loginPage.php');
        exit;
    }

    // 2. Query the Database for the user
    $stmt = $conn->prepare("SELECT username, password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 3. Verify Password (Using plain text as per initial setup. For production, use password_verify.)
        if ($input_password === $user['password']) {
            // SUCCESS: Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            
            // Redirect to the dashboard
            header('Location: admin_dashboard.php');
            exit;
        } 
    }
    
    // FAILURE: If we reach here, the login failed (username not found or password incorrect)
    $_SESSION['login_error'] = "Invalid username or password. Access denied.";
    header('Location: loginPage.php');
    exit;

    $stmt->close();
    $conn->close();

} else {
    // Prevent direct access to this script
    header('Location: loginPage.php');
    exit;
}
?>
<?php
// --- CONFIGURATION ---
$servername = "localhost";
$username = "root";
$password = ""; // Use your actual MySQL password
$dbname = "batcave_db";

// Ensure JSON response
header('Content-Type: application/json');

// Connect to Database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Fetch the count of pending bookings
$sql = "SELECT COUNT(id) AS count FROM bookings WHERE status = 'Pending'";
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['count'] ?? 0;
    
    // Return the pending count
    echo json_encode(["status" => "success", "count" => $count]);
} else {
    echo json_encode(["status" => "error", "message" => "Error querying database: " . $conn->error]);
}

$conn->close();
?>
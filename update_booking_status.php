<?php
session_start();
// Check if admin is logged in (security measure)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

// --- CONFIGURATION ---
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "batcave_db";

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

// Use filter_input for security
$booking_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

if (!$booking_id || !in_array($new_status, ['Approved', 'Rejected'])) {
    echo json_encode(["status" => "error", "message" => "Invalid ID or status provided."]);
    exit;
}

// 1. Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection error."]);
    exit;
}

// 2. Prepare the UPDATE statement
$sql = "UPDATE bookings SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_status, $booking_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Booking #$booking_id updated to $new_status."]);
} else {
    echo json_encode(["status" => "error", "message" => "Error updating booking: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
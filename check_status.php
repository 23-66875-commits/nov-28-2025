<?php
// --- CONFIGURATION ---
$servername = "localhost";
$username = "root";
$password = ""; // Use your actual MySQL password
$dbname = "batcave_db";

// Ensure JSON response
header('Content-Type: application/json');

// Get the Booking ID from the URL parameter
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Booking ID"]);
    exit;
}

// Connect to Database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Fetch the current status and details
$sql = "SELECT id, status, client_name, booking_date, start_time, end_time, total_fee, notes FROM bookings WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Return the booking data found in the DB
    echo json_encode(["status" => "success", "data" => $row]);
} else {
    echo json_encode(["status" => "error", "message" => "Booking not found"]);
}

$stmt->close();
$conn->close();
?>
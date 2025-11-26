<?php
// --- CONFIGURATION ---
$servername = "localhost";
$username = "root";
$password = ""; // IMPORTANT: Set your actual MySQL password here if you have one!
$dbname = "batcave_db";

// Set JSON header
header('Content-Type: application/json');

// Accept POST and parse JSON fallback
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Try to parse JSON payload first
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload || !is_array($payload)) {
    // Fallback to form data
    $payload = $_POST;
}

// --- 1. BASIC VALIDATION ---
if (!isset($payload['client_name']) || !isset($payload['booking_date']) || !isset($payload['total_fee'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

// 2. Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection error: " . $conn->connect_error]);
    exit;
}

// 3. Prepare the INSERT statement
$sql = "INSERT INTO bookings (client_name, client_email, booking_type, booking_date, start_time, end_time, total_fee, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

// Bind parameters and sanitize input
$client_name = trim($payload['client_name']);
$client_email = trim($payload['client_email'] ?? '');
$booking_type = trim($payload['booking_type'] ?? 'Study Room');
$booking_date = trim($payload['booking_date']);
$start_time = trim($payload['start_time'] ?? '00:00:00');
$end_time = trim($payload['end_time'] ?? '00:00:00');
$total_fee = floatval($payload['total_fee']);
$status = 'Pending'; // Always start as Pending
$notes = trim($payload['notes'] ?? '');

$stmt->bind_param("ssssssdss", $client_name, $client_email, $booking_type, $booking_date, $start_time, $end_time, $total_fee, $status, $notes);

// 4. Execute
if ($stmt->execute()) {
    $booking_id = $conn->insert_id;
    // Success response with the new ID and the 'Pending' status (for client confirmation)
    echo json_encode([
        "status" => "success",
        "message" => "Booking saved successfully. Awaiting admin approval.",
        "dbStatus" => $status,
        "bookingId" => $booking_id
    ]);
} else {
    // Error during execution
    echo json_encode(["status" => "error", "message" => "Error executing statement: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
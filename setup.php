<?php
// --- CONFIGURATION ---
$servername = "localhost";
$username = "root";        // Default XAMPP user
$password = "";            // Default XAMPP password is empty
$dbname = "batcave_db";

// 1. Connect to MySQL Server
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "🔌 Connected to MySQL Server...<br>";

// 2. Create Database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "🦇 Database '$dbname' checked/created successfully.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// 3. Create Tables

// Table: Menu Items
$sql = "CREATE TABLE IF NOT EXISTS menu_items (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    is_featured BOOLEAN DEFAULT 0,
    in_stock BOOLEAN DEFAULT 1
)";
$conn->query($sql);

// Table: Bookings - UPDATED TO INCLUDE submission_timestamp
$sql = "CREATE TABLE IF NOT EXISTS bookings (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    client_email VARCHAR(255),
    booking_type VARCHAR(100),
    booking_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    total_fee DECIMAL(10, 2),
    status VARCHAR(50) DEFAULT 'Pending',
    notes TEXT,
    submission_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === FALSE) {
    // Attempt to alter the table if it already exists (needed for older XAMPP versions or existing tables)
    $conn->query("ALTER TABLE bookings ADD COLUMN submission_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Table: Admins
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
)";
$conn->query($sql);

// 4. Insert Sample Data (Resetting tables first to avoid duplicates)
$conn->query("TRUNCATE TABLE menu_items");
$conn->query("TRUNCATE TABLE bookings");
$conn->query("TRUNCATE TABLE admins");

// Prepare Menu Data
$stmt = $conn->prepare("INSERT INTO menu_items (name, category, price, description, image_path, is_featured, in_stock) VALUES (?, ?, ?, ?, ?, ?, ?)");

$menu_items = [
    ['The Bat Brew', 'Signature', 120.00, 'Signature blend, dark roast.', 'media/Bat_Cave_Cafe__The_Bat_Brew.png', 1, 1],
    ['Midnight Mocha', 'Signature', 135.00, 'Dark Chocolate Espresso.', 'media/Bat_Cave_Cafe__Midnight_Mocha.png', 1, 1],
    ['Red Velvet Muffin', 'Pastry', 65.00, 'Freshly baked daily.', 'media/Bat_Cave_Cafe__Red_Velvet_Muffin.png', 1, 0],
    ['Joker\'s Frappe', 'Signature', 145.00, 'Chaotic Sweet Blend.', 'media/Bat_Cave_Cafe__Jokers_Frappe.png', 1, 1],
    ['Chilled Bat Latte', 'Signature', 135.00, 'Cold brew with vanilla.', 'media/Bat_Cave_Cafe__Chilled_Bat_Latte.png', 0, 1],
    ['Cave Dweller', 'Signature', 110.00, 'Strong hot americano.', 'media/Bat_Cave_Cafe__Cave_Dweller.png', 0, 1],
    ['Vigilante Shot', 'Signature', 90.00, 'Double espresso shot.', 'media/Bat_Cave_Cafe__Vigilante_Espresso.png', 0, 1],
    ['Choco Croissant', 'Pastry', 95.00, 'Buttery pastry with dark chocolate.', 'media/Bat_Cave_Cafe__Guano_Croissant.png', 0, 1],
    ['Garlic Batwings', 'Snack', 110.00, 'Cheesy garlic breadsticks.', 'media/Bat_Cave_Cafe__Garlic_Breadsticks.png', 0, 1]
];

foreach ($menu_items as $item) {
    $stmt->bind_param("ssdssii", $item[0], $item[1], $item[2], $item[3], $item[4], $item[5], $item[6]);
    $stmt->execute();
}

// Prepare Booking Data - Sample data will use NOW() for the new column
$stmt = $conn->prepare("INSERT INTO bookings (client_name, client_email, booking_type, booking_date, start_time, end_time, total_fee, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

$bookings = [
    ['Juan Dela Cruz', 'juan@student.edu', 'Study Room', '2025-11-24', '18:00:00', '21:00:00', 600.00, 'Pending', 'Projector needed'],
    ['Maria Clara', 'maria@student.edu', 'Study Room', '2025-11-24', '16:00:00', '17:00:00', 75.00, 'Paid', ''],
    ['Engr. Department', 'dept@univ.edu', 'Event', '2025-11-24', '13:00:00', '16:00:00', 1200.00, 'Completed', 'Speaker, Mic, Projector']
];

foreach ($bookings as $b) {
    $stmt->bind_param("ssssssdss", $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $b[8]);
    $stmt->execute();
}

// Admin User
$admin_user = "admin";
$admin_pass = "alfred123"; // In a real app, use password_hash()
$stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $admin_user, $admin_pass);
$stmt->execute();

echo "✅ Success! Tables created and data imported.";

$stmt->close();
$conn->close();
?>
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "batcave_db";

// Connect
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Database Test</title>
    <style>
        body { background: #070606; color: #F6EDD9; font-family: sans-serif; padding: 2rem; }
        h1, h2 { color: #D4AF37; }
        .item { border: 1px solid #333; padding: 10px; margin-bottom: 10px; border-radius: 8px; }
        .sold-out { opacity: 0.5; color: red; }
    </style>
</head>
<body>

    <h1>📜 Menu from MySQL</h1>
    
    <?php
    $sql = "SELECT * FROM menu_items";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $status = $row["in_stock"] ? "✅ In Stock" : "<span class='sold-out'>❌ Sold Out</span>";
            echo "<div class='item'>";
            echo "<h3>" . $row["name"] . " (₱" . $row["price"] . ")</h3>";
            echo "<p>" . $row["description"] . "</p>";
            echo "<small>$status</small>";
            echo "</div>";
        }
    } else {
        echo "0 results";
    }
    ?>

    <hr style="border-color: #333; margin: 2rem 0;">

    <h2>📅 Pending Bookings (Admin View)</h2>
    
    <?php
    $sql = "SELECT * FROM bookings WHERE status='Pending'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo "<div class='item' style='border-color: #D4AF37'>";
            echo "<strong>Booking #" . $row["id"] . "</strong>: " . $row["client_name"];
            echo "<br>Date: " . $row["booking_date"] . " (" . $row["start_time"] . ")";
            echo "</div>";
        }
    }
    ?>

</body>
</html>

<?php $conn->close(); ?>
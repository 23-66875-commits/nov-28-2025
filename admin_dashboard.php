
<?php
session_start();
// Check if admin is logged in (security measure)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // If not, redirect them to the login page
    header('Location: loginPage.php');
    exit;
}

// --- CONFIGURATION ---
// Ensure these credentials match your XAMPP/MySQL setup
$servername = "localhost";
$username = "root";
$password = ""; // Use your actual MySQL password if you set one
$dbname = "batcave_db";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

// --- 1. FETCH DASHBOARD DATA ---

// Count Pending Requests (Used in the stat card and JS polling)
$pending_count_result = $conn->query("SELECT COUNT(id) AS count FROM bookings WHERE status = 'Pending'");
$pending_count = $pending_count_result->fetch_assoc()['count'];

// Count Booking Types for Pie Chart
$study_count_result = $conn->query("SELECT COUNT(id) AS count FROM bookings WHERE booking_type = 'Study Room' AND status != 'Rejected'");
$study_count = $study_count_result->fetch_assoc()['count'];

$event_count_result = $conn->query("SELECT COUNT(id) AS count FROM bookings WHERE booking_type = 'Event' AND status != 'Rejected'");
$event_count = $event_count_result->fetch_assoc()['count'];

// --- 2. CALCULATE WEEKLY REVENUE (For Line Graph) ---
$revenue_labels = [];
$revenue_data = [];

for ($i = 6; $i >= 0; $i--) {
    $check_date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime("-$i days"));
    
    $sql = "SELECT SUM(total_fee) as daily_total FROM bookings 
            WHERE booking_date = '$check_date' 
            AND status IN ('Approved', 'Paid', 'Completed')";
            
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    $total = $row['daily_total'] ? $row['daily_total'] : 0;
    
    $revenue_labels[] = $day_name;
    $revenue_data[] = $total;
}

// Calculate Total Revenue (All Time) for Stat Card
$total_rev_result = $conn->query("SELECT SUM(total_fee) as total FROM bookings WHERE status IN ('Approved', 'Paid', 'Completed')");
$total_revenue_all_time = $total_rev_result->fetch_assoc()['total'] ?? 0;


// --- 3. FETCH LISTS ---
// Fetch Current Bookings (Active & Pending)
$current_bookings_sql = "SELECT id, client_name, client_email, booking_date, start_time, end_time, total_fee, notes, status, submission_timestamp FROM bookings WHERE status IN ('Pending', 'Approved') ORDER BY id DESC";
$current_bookings_result = $conn->query($current_bookings_sql);
$current_bookings = $current_bookings_result->fetch_all(MYSQLI_ASSOC);

// Fetch ALL Bookings for the "Bookings" tab (Historical view)
$all_bookings_sql = "SELECT id, client_name, client_email, booking_type, booking_date, start_time, end_time, total_fee, notes, status, submission_timestamp FROM bookings ORDER BY status ASC, booking_date DESC, start_time DESC";
$all_bookings_result = $conn->query($all_bookings_sql);
$all_bookings = $all_bookings_result->fetch_all(MYSQLI_ASSOC);


// --- RENDER AJAX CONTENT ONLY IF REQUESTED ---
if (isset($_GET['render']) && $_GET['render'] == 'bookings_table') {
    ob_start();
    // Re-fetch all bookings just in case the data changed since page load
    $re_fetch_result = $conn->query($all_bookings_sql);
    $all_bookings_reloaded = $re_fetch_result->fetch_all(MYSQLI_ASSOC);
    
    foreach ($all_bookings_reloaded as $booking): 
        $badge_class = '';
        if ($booking['status'] == 'Pending') $badge_class = 'badge-gold';
        elseif ($booking['status'] == 'Approved' || $booking['status'] == 'Completed') $badge_class = 'badge-green';
        else $badge_class = 'badge-red';
    ?>
    <tr class="booking-row" data-id="<?php echo $booking['id']; ?>" data-date="<?php echo $booking['booking_date']; ?>">
        <td style="color: var(--gold-accent);">#BK-<?php echo $booking['id']; ?></td>
        <td>
            <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?><br>
            <span style="font-size:0.8rem; color:#666;"><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
        </td>
        <td>
            <strong><?php echo $booking['client_name']; ?></strong><br>
            <span style="font-size:0.8rem; color:#666;"><?php echo $booking['client_email']; ?></span>
        </td>
        <td><?php echo $booking['booking_type']; ?></td>
        <td>₱<?php echo number_format($booking['total_fee'], 2); ?></td>
        <td><span class="badge <?php echo $badge_class; ?>" id="status-<?php echo $booking['id']; ?>"><?php echo $booking['status']; ?></span></td>
        <td>
            <span style="font-size:0.8rem; color:#666;"><?php echo date('M j, Y', strtotime($booking['submission_timestamp'])); ?></span><br>
            <span style="font-size:0.8rem; color:#888;"><?php echo date('g:i A', strtotime($booking['submission_timestamp'])); ?></span>
        </td>
        <td id="actions-<?php echo $booking['id']; ?>" style="min-width: 160px;">
            <?php if ($booking['status'] == 'Pending'): ?>
                <div class="action-buttons-group">
                    <button class="btn btn-gold" style="padding: 5px 10px; font-size: 0.8rem;" onclick="updateStatus(<?php echo $booking['id']; ?>, 'Approved', this)">Accept</button>
                    <button class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;" onclick="updateStatus(<?php echo $booking['id']; ?>, 'Rejected', this)">Reject</button>
                </div>
            <?php else: ?>
                <span class="text-xs text-gray-500">Processed</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; 
    if (empty($all_bookings_reloaded)): ?>
        <tr>
            <td colspan="8" style="text-align: center; color: var(--text-muted);">No bookings found.</td>
        </tr>
    <?php endif;

    $conn->close();
    echo ob_get_clean();
    exit;
}

$conn->close();
// --- END AJAX CONTENT SECTION ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command Center | The Bat Cave</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
        :root {
            /* Retaining the Bat Cave Color Scheme */
            --bg-dark: #070606;
            --bg-card: #121212;
            --bg-hover: #1E1E1E;
            --text-main: #F6EDD9;
            --text-muted: #888888;
            --gold-accent: #D4AF37;
            --gold-glow: rgba(212, 175, 55, 0.4);
            --border-color: rgba(255, 255, 255, 0.1);
            --nav-height: 70px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            padding-top: var(--nav-height); /* Space for fixed header */
            position: relative; /* Added for pseudo-elements */
        }

        /* Background Effects (similar to index.html) */
        body::before {
            content: "";
            position: fixed; 
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -2; 
            /* Using the local background image for consistent styling */
            background-image: url('../media/bg-image.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(10px) brightness(0.7); 
            transform: scale(1.1); 
        }

        body::after {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1; 
            background: rgba(7, 6, 6, 0.4); 
        }
        
        /* Ensure content is above the background/overlay */
        .navbar, .container {
            position: relative;
            z-index: 10;
        }

        /* ================= TOP NAVIGATION ================= */
        .navbar {
            position: fixed;
            top: 0; left: 0; width: 100%;
            height: var(--nav-height);
            background: rgba(7, 6, 6, 0.85); /* Semi-transparent */
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            z-index: 1000;
        }

        .brand-logo {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--gold-accent);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            gap: 5px;
            background: rgba(255,255,255,0.05);
            padding: 5px;
            border-radius: 50px;
            border: 1px solid var(--border-color);
        }

        .nav-item {
            padding: 8px 24px;
            border-radius: 40px;
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .nav-item:hover { color: #fff; }

        .nav-item.active {
            background: var(--gold-accent);
            color: #000;
            font-weight: 700;
            box-shadow: 0 0 15px var(--gold-glow);
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* ================= MAIN CONTAINER ================= */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 20px;
        }

        .page-title h2 { font-family: 'Montserrat', sans-serif; font-size: 2rem; color: #fff; }
        .page-title p { color: var(--text-muted); margin-top: 5px; }

        /* ================= STATS ROW ================= */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
            background: var(--gold-accent);
            opacity: 0.5;
        }

        .stat-label { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
        .stat-number { font-size: 2.2rem; font-weight: 700; font-family: 'Montserrat', sans-serif; margin: 10px 0; }
        .stat-sub { font-size: 0.85rem; color: #10b981; display: flex; align-items: center; gap: 5px; }

        /* ================= CONTENT TABLES ================= */
        .content-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        /* New Styles for Charts */
        .charts-row {
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 20px; 
            margin-bottom: 40px;
        }
        /* Make chart panels consistent */
        .charts-row .content-panel {
            padding: 20px;
            height: 350px; /* Fixed height for consistent chart rendering */
        }
        
        /* Responsive Chart Layout */
        @media (max-width: 900px) {
            .charts-row { grid-template-columns: 1fr; }
        }


        .panel-header {
            padding: 20px 30px;
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-responsive { 
            width: 100%; 
            overflow-x: auto; 
            max-height: 500px; 
            /* Hide vertical scrollbar for the table body (All Reservations) */
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        } 
        
        /* HIDE SCROLLBAR TRACKS (FOR ALL RESERVATIONS TABLE) */
        #bookings .table-responsive::-webkit-scrollbar {
            display: none;
        }


        table { width: 100%; border-collapse: collapse; }
        
        th {
            text-align: left;
            padding: 18px 30px;
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border-color);
            color: #eee;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background: var(--bg-hover); }

        /* Badges & Buttons */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; white-space: nowrap;}
        .badge-gold { background: rgba(212, 175, 55, 0.15); color: var(--gold-accent); border: 1px solid rgba(212, 175, 55, 0.3); }
        .badge-green { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-red { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }


        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            text-align: center; /* Ensure text is centered inside the button */
            justify-content: center;
        }
        .btn-gold { background: var(--gold-accent); color: #000; }
        .btn-gold:hover { background: #e6c245; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); }
        .btn-outline:hover { border-color: var(--gold-accent); color: var(--gold-accent); }

        /* FIX: New style for button grouping */
        .action-buttons-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 5px; /* Space between buttons */
        }
        .action-buttons-group .btn {
            flex-grow: 1; 
            flex-basis: 48%; 
            min-width: 0; 
        }

        /* Form Inputs */
        .input-dark {
            background: #000;
            border: 1px solid var(--border-color);
            color: white;
            padding: 8px 15px;
            border-radius: 6px;
        }

        /* Section Visibility Logic */
        .view-section { display: none; animation: fadeIn 0.4s ease; }
        .view-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 1000px) {
            .navbar { flex-direction: column; height: auto; padding: 20px; gap: 15px; }
            .nav-links { width: 100%; justify-content: center; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            body { padding-top: 140px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand-logo">
            <i class="fas fa-bat"></i> THE BAT CAVE
        </div>

        <div class="nav-links">
            <div class="nav-item active" onclick="switchView('dashboard', this)">Dashboard</div>
            <div class="nav-item" onclick="switchView('bookings', this)">Bookings</div>
        </div>
    </nav>

    <main class="container">
        
        <section id="dashboard" class="view-section active">
            <div class="page-header">
                <div class="page-title">
                    <h2>Command Center</h2>
                    <p>Welcome back, Nina. Here is today's overview.</p>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-number">₱<?php echo number_format($total_revenue_all_time); ?></div>
                    <div class="stat-sub" style="color: var(--gold-accent);">All Time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Bookings</div>
                    <div class="stat-number"><?php echo count($current_bookings); ?></div>
                    <div class="stat-sub" style="color: #888;">Current sessions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Bookings</div>
                    <div class="stat-number"><?php echo count($all_bookings); ?></div>
                    <div class="stat-sub" style="color: #10b981;">Lifetime Volume</div>
                </div>
                <div class="stat-card" id="pendingCard">
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-number" id="pendingCountDisplay"><?php echo $pending_count; ?></div>
                    <div class="stat-sub" style="color: #ef4444;" id="pendingStatusText">Action Required</div>
                </div>
            </div>
            
            <div class="charts-row">
                <div class="content-panel">
                    <h3 style="margin-bottom: 15px; color: #fff;"><i class="fas fa-chart-line"></i> Last 7 Days Revenue</h3>
                    <canvas id="weeklyRevenueChart" style="height: 280px;"></canvas>
                </div>
                <div class="content-panel">
                    <h3 style="margin-bottom: 15px; color: #fff;"><i class="fas fa-chart-pie"></i> Booking Type Distribution</h3>
                    <canvas id="bookingTypeChart" style="height: 280px;"></canvas>
                </div>
            </div>

            <div class="content-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-clock"></i> Current Bookings</h3>
                    <button class="btn btn-outline" style="font-size: 0.8rem;" onclick="switchView('bookings', document.querySelector('.nav-links .nav-item:nth-child(2)'))">View History</button>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Student / Client</th>
                                <th>Time Slot</th>
                                <th>Notes</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_bookings as $booking): 
                                // Determine badge class based on status
                                $badge_class = '';
                                if ($booking['status'] == 'Pending') $badge_class = 'badge-gold';
                                elseif ($booking['status'] == 'Approved' || $booking['status'] == 'Completed') $badge_class = 'badge-green';
                                else $badge_class = 'badge-red';
                            ?>
                            <tr class="booking-row" data-id="<?php echo $booking['id']; ?>" data-date="<?php echo $booking['booking_date']; ?>">
                                <td style="color: var(--gold-accent);">#BK-<?php echo $booking['id']; ?></td>
                                <td>
                                    <strong><?php echo $booking['client_name']; ?></strong><br>
                                    <span style="font-size:0.8rem; color:#666;"><?php echo $booking['client_email']; ?></span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?><br>
                                    <span style="font-size:0.8rem; color:#888;"><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                                </td>
                                <td><?php echo empty($booking['notes']) ? '-' : htmlspecialchars($booking['notes']); ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>" id="dash-status-<?php echo $booking['id']; ?>"><?php echo $booking['status']; ?></span></td>
                                <td>₱<?php echo number_format($booking['total_fee'], 2); ?></td>
                                <td>
                                    <span style="font-size:0.8rem; color:#666;"><?php echo date('M j, Y', strtotime($booking['submission_timestamp'])); ?></span><br>
                                    <span style="font-size:0.8rem; color:#888;"><?php echo date('g:i A', strtotime($booking['submission_timestamp'])); ?></span>
                                </td>
                                <td id="dash-actions-<?php echo $booking['id']; ?>" style="min-width: 160px;">
                                    <?php if ($booking['status'] == 'Pending'): ?>
                                        <div class="action-buttons-group">
                                            <button class="btn btn-gold" style="padding: 5px 10px; font-size: 0.7rem;" onclick="updateStatus(<?php echo $booking['id']; ?>, 'Approved', this)">Accept</button>
                                            <button class="btn btn-outline" style="padding: 5px 10px; font-size: 0.7rem;" onclick="updateStatus(<?php echo $booking['id']; ?>, 'Rejected', this)">Reject</button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($current_bookings)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">No active bookings found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="bookings" class="view-section">
            <div class="page-header">
                <div class="page-title">
                    <h2>Reservation Management</h2>
                    <p>Manage room allocation and equipment schedules.</p>
                </div>
                <div style="display:flex; gap: 10px;">
                    <input type="text" id="searchId" placeholder="Search ID..." class="input-dark" onkeyup="filterBookings()">
                    <input type="date" id="searchDate" class="input-dark" onchange="filterBookings()">
                </div>
            </div>
            
            <div class="content-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-list-alt"></i> All Reservations</h3>
                    <button class="btn btn-outline" style="font-size: 0.8rem;" onclick="refreshBookingsTable(this)">Refresh Data</button>
                </div>
                <div class="table-responsive">
                    <table id="allBookingsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date / Time</th>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="allBookingsTbody">
                            <?php foreach ($all_bookings as $booking): 
                                // Determine badge class based on status
                                $badge_class = '';
                                if ($booking['status'] == 'Pending') $badge_class = 'badge-gold';
                                elseif ($booking['status'] == 'Approved' || $booking['status'] == 'Completed') $badge_class = 'badge-green';
                                else $badge_class = 'badge-red';
                            ?>
                            <tr class="booking-row" data-id="<?php echo $booking['id']; ?>" data-date="<?php echo $booking['booking_date']; ?>">
                                <td style="color: var(--gold-accent);">#BK-<?php echo $booking['id']; ?></td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?><br>
                                    <span style="font-size:0.8rem; color:#888;"><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo $booking['client_name']; ?></strong><br>
                                    <span style="font-size:0.8rem; color:#666;"><?php echo $booking['client_email']; ?></span>
                                </td>
                                <td><?php echo $booking['booking_type']; ?></td>
                                <td>₱<?php echo number_format($booking['total_fee'], 2); ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>" id="status-<?php echo $booking['id']; ?>"><?php echo $booking['status']; ?></span></td>
                                <td>
                                    <span style="font-size:0.8rem; color:#666;"><?php echo date('M j, Y', strtotime($booking['submission_timestamp'])); ?></span><br>
                                    <span style="font-size:0.8rem; color:#888;"><?php echo date('g:i A', strtotime($booking['submission_timestamp'])); ?></span>
                                </td>
                                <td id="actions-<?php echo $booking['id']; ?>" style="min-width: 160px;">
                                    <?php if ($booking['status'] == 'Pending'): ?>
                                        <div class="action-buttons-group">
                                            <button class="btn btn-gold" style="padding: 5px 10px; font-size: 0.8rem;" onclick="updateStatus(<?php echo $booking['id']; ?>, 'Approved', this)">Accept</button>
                                            <button class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;" onclick="updateStatus(<?php echo $booking['id']; ?>, 'Rejected', this)">Reject</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($all_bookings)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-muted);">No bookings found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        </main>

    <script>
        // Store initial pending count for polling logic
        const INITIAL_PENDING_COUNT = <?php echo $pending_count; ?>;

        let currentView = 'dashboard';
        
        function switchView(viewId, navElement) {
            // Remove active class from all nav items
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            // Add active to clicked
            navElement.classList.add('active');

            // Hide all sections
            document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
            // Show target
            document.getElementById(viewId).classList.add('active');
            
            currentView = viewId;

            // If switching to dashboard, initialize charts
            if (viewId === 'dashboard') {
                // Charts need a moment after the section becomes visible to calculate dimensions
                setTimeout(initializeCharts, 100);
            }
        }
        
        // --- FEATURE 3: DYNAMIC FILTERING (Search ID and Date) ---
        function filterBookings() {
            const searchIdInput = document.getElementById('searchId');
            const searchDateInput = document.getElementById('searchDate');
            const filterId = searchIdInput ? searchIdInput.value.toLowerCase().trim() : '';
            const filterDate = searchDateInput ? searchDateInput.value : '';

            const rows = document.querySelectorAll('#allBookingsTable tbody .booking-row');

            rows.forEach(row => {
                const bookingId = row.getAttribute('data-id');
                const bookingDate = row.getAttribute('data-date');
                
                let matchesId = true;
                let matchesDate = true;

                // 1. ID Filter: Must match the start of the ID string
                if (filterId) {
                    matchesId = bookingId.startsWith(filterId);
                }

                // 2. Date Filter
                if (filterDate) {
                    matchesDate = (bookingDate === filterDate);
                }

                if (matchesId && matchesDate) {
                    row.style.display = ''; // Show row
                } else {
                    row.style.display = 'none'; // Hide row
                }
            });
        }
        
        // --- FEATURE: AJAX REFRESH FOR BOOKINGS TABLE ---
        function refreshBookingsTable(buttonElement) {
            const originalText = buttonElement.textContent;
            buttonElement.textContent = 'Loading...';
            buttonElement.disabled = true;

            // Use fetch targeting the current page with a parameter to trigger AJAX rendering
            fetch('admin_dashboard.php?render=bookings_table')
            .then(response => response.text())
            .then(html => {
                const tbody = document.getElementById('allBookingsTbody');
                if (tbody) {
                    tbody.innerHTML = html;
                    filterBookings(); // Re-apply any active filters after new data is loaded
                } else {
                    console.error("Target tbody element not found.");
                }
            })
            .catch(error => {
                console.error('Error refreshing table via AJAX:', error);
                alert('Failed to load new data.');
            })
            .finally(() => {
                buttonElement.textContent = originalText;
                buttonElement.disabled = false;
            });
        }


        // --- FEATURE 1: AUTOMATIC REFRESH / POLLING ---
        /* Polls the server every 15 seconds to check for new pending submissions */
        function pollForNewBookings() {
            // Fetch the count of pending bookings
            fetch('../php/get_pending_count.php')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const latestCount = parseInt(data.count);
                    const pendingDisplay = document.getElementById('pendingCountDisplay');
                    const currentDisplayedCount = parseInt(pendingDisplay.textContent);

                    pendingDisplay.textContent = latestCount;

                    // Only reload if a *new* booking has arrived (count increased)
                    if (latestCount > currentDisplayedCount) {
                        document.getElementById('pendingStatusText').innerHTML = '<i class="fas fa-bell animate-pulse"></i> New Request Received! Reloading...';
                        document.getElementById('pendingCard').style.backgroundColor = 'rgba(212, 175, 55, 0.2)'; // Subtle visual hint
                        
                        // Reload after a short delay so the user notices the change
                        setTimeout(() => window.location.reload(), 2000); 
                    } else if (latestCount === 0) {
                         document.getElementById('pendingStatusText').innerHTML = 'All Clear';
                         document.getElementById('pendingCard').style.backgroundColor = 'var(--bg-card)';
                    } else {
                         document.getElementById('pendingStatusText').innerHTML = 'Action Required';
                         document.getElementById('pendingCard').style.backgroundColor = 'var(--bg-card)';
                    }
                }
            })
            .catch(error => {
                console.error("Polling error:", error);
            });
        }

        // --- CHARTS (Remains the same) ---
        function initializeCharts() {
            // Clear previous charts if they exist
            const revenueCtx = document.getElementById('weeklyRevenueChart');
            const bookingCtx = document.getElementById('bookingTypeChart');
            
            if (revenueCtx.chart) revenueCtx.chart.destroy();
            if (bookingCtx.chart) bookingCtx.chart.destroy();


            // --- DYNAMIC REVENUE CHART ---
            revenueCtx.chart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($revenue_labels); ?>, // PHP Labels (Mon, Tue, etc.)
                    datasets: [{
                        label: 'Revenue (PHP)',
                        data: <?php echo json_encode($revenue_data); ?>, // PHP Data
                        borderColor: 'rgba(212, 175, 55, 1)', // Gold
                        backgroundColor: 'rgba(212, 175, 55, 0.2)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#aaa' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#aaa' }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: false }
                    }
                }
            });

            // --- DYNAMIC PIE CHART ---
            bookingCtx.chart = new Chart(bookingCtx, {
                type: 'pie',
                data: {
                    labels: ['Study Room', 'Event Reservation'],
                    datasets: [{
                        data: [<?php echo $study_count; ?>, <?php echo $event_count; ?>],
                        backgroundColor: [
                            'rgba(212, 175, 55, 0.8)', // Gold
                            'rgba(16, 185, 129, 0.8)' // Green
                        ],
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#aaa' }
                        },
                        title: { display: false }
                    }
                }
            });
        }
        
        // --- Function to handle Admin Accept/Reject with live AJAX (Remains the same) ---
        function updateStatus(bookingId, newStatus, buttonElement) {
            if (!confirm(`Are you sure you want to change booking #${bookingId} status to ${newStatus}?`)) {
                return;
            }

            // Find elements in both dashboard and list views
            const actionCells = document.querySelectorAll(`[id^='dash-actions-${bookingId}'], [id^='actions-${bookingId}']`);
            actionCells.forEach(cell => cell.innerHTML = '<i class="fas fa-spinner fa-spin text-gold-accent"></i> Processing...');
            
            // AJAX call to update_booking_status.php
            fetch('../php/update_booking_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${bookingId}&status=${newStatus}`
            })
            .then(response => {
                if (!response.ok || response.headers.get("content-type")?.indexOf("application/json") === -1) {
                    throw new Error("Server returned non-JSON response or error status.");
                }
                return response.json();
            })
            .then(data => {
                if(data.status === 'success') {
                    // Refresh the page to update everything (pending counts, lists, user polling)
                    alert(`Booking #${bookingId} successfully set to ${newStatus}.`);
                    window.location.reload(); 
                } else {
                    alert(`Failed to update status: ${data.message}`);
                    actionCells.forEach(cell => cell.innerHTML = `<span class="text-xs text-red-500">Error</span>`); 
                }
            })
            .catch(error => {
                alert(`Network or Server error during update. Details: ${error.message}`);
                actionCells.forEach(cell => cell.innerHTML = `<span class="text-xs text-red-500">Error</span>`);
            });
        }


        // Call initializeCharts when the DOM is fully loaded and on the dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Check if the initial PHP count showed pending requests
            if (INITIAL_PENDING_COUNT > 0) {
                 document.getElementById('pendingCard').style.backgroundColor = 'rgba(212, 175, 55, 0.2)';
            }
            
            if (document.getElementById('dashboard').classList.contains('active')) {
                initializeCharts();
                // Start polling every 15 seconds to check for new submissions
                setInterval(pollForNewBookings, 15000); 
            }
        });
    </script>
</body>
</html>

<style>
    /* ... [Previous CSS] ... */

    /* Responsive Chart Layout */
    @media (max-width: 900px) {
        .charts-row { grid-template-columns: 1fr; height: auto; }
        .content-panel { height: auto; padding: 15px; }
        /* Fix table overflow on mobile */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    }

    /* Mobile Phone Adjustments */
    @media (max-width: 600px) {
        /* Force 1 column for stats */
        .stats-row { grid-template-columns: 1fr; gap: 15px; }
        
        /* Adjust navbar for mobile */
        .navbar { flex-direction: column; padding: 10px; height: auto; }
        .nav-links { width: 100%; overflow-x: auto; justify-content: flex-start; }
        
        /* Reduce padding */
        .container { padding: 20px 10px; }
        
        /* Ensure charts don't squash */
        canvas { min-height: 250px; }
        
        /* Hide less important table columns on very small screens if needed, 
           or rely on the .table-responsive scroll wrapper (recommended) */
    }
</style>
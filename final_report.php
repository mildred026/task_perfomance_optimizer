<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_lecturer = false;
$is_leader = false;
$user_group_id = null;

// Get user role and name
$user_sql = "SELECT name, role FROM users WHERE id = '$user_id'";
$user_result = $conn->query($user_sql);
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'];
$user_role = $user_data['role'];

$is_lecturer = ($user_role == 'lecturer');

// Check if user is a group leader
$leader_sql = "SELECT group_id FROM groups WHERE leader_id = '$user_id'";
$leader_result = $conn->query($leader_sql);
if ($leader_result && $leader_result->num_rows > 0) {
    $is_leader = true;
    $leader_row = $leader_result->fetch_assoc();
    $user_group_id = $leader_row['group_id'];
}

// Get user's group membership if not leader
if (!$user_group_id) {
    $member_sql = "SELECT group_id FROM group_members WHERE user_id = '$user_id'";
    $member_result = $conn->query($member_sql);
    if ($member_result && $member_result->num_rows > 0) {
        $member_row = $member_result->fetch_assoc();
        $user_group_id = $member_row['group_id'];
    }
}

// Redirect non-lecturer users
if (!$is_lecturer) {
    $_SESSION['error_message'] = "Access denied. Only lecturers can view final reports.";
    header('Location: dashboard.php');
    exit();
}

// Get unread message count for inbox badge
$unread_count = 0;
$table_check = $conn->query("SHOW TABLES LIKE 'inbox'");
if ($table_check && $table_check->num_rows > 0) {
    $unread_sql = "SELECT COUNT(*) as unread FROM inbox WHERE user_id = $user_id AND is_read = 0";
    $unread_result = $conn->query($unread_sql);
    if ($unread_result && $unread_result->num_rows > 0) {
        $unread_count = $unread_result->fetch_assoc()['unread'];
    }
}

// Handle file download (only for lecturers)
if (isset($_GET['download'])) {
    $report_id = intval($_GET['download']);
    $file_sql = "SELECT file_path, original_filename FROM final_reports WHERE id = $report_id";
    $file_result = $conn->query($file_sql);
    if ($file_result && $file_result->num_rows > 0) {
        $file = $file_result->fetch_assoc();
        $file_path = __DIR__ . "/uploads/final_reports/" . $file['file_path'];
        if (file_exists($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
            readfile($file_path);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Final Reports - Task Performance Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: #1a2a4f;
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar-logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-logo h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
        }
        
        .sidebar-logo p {
            font-size: 0.75rem;
            opacity: 0.7;
            color: white;
        }
        
        .user-info-sidebar {
            padding: 16px 24px;
            background: #0f1e3a;
            margin: 0 16px 20px 16px;
            border-radius: 12px;
        }
        
        .user-info-sidebar .name {
            font-weight: 700;
            font-size: 1rem;
            color: white;
        }
        
        .user-info-sidebar .role {
            font-size: 0.7rem;
            color: #a8b8d8;
            margin-top: 4px;
        }
        
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 0 16px;
        }
        
        .sidebar-nav a {
            color: #e0e6f0;
            text-decoration: none;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.95rem;
            border-radius: 10px;
            transition: all 0.2s;
        }
        
        .sidebar-nav a:hover {
            background: #2a3a60;
            color: white;
        }
        
        .sidebar-nav a.active {
            background: #2a3a60;
            color: white;
        }
        
        .sidebar-nav a i {
            width: 24px;
            font-size: 1.1rem;
        }
        
        .logout-item {
            margin-top: auto;
            margin-bottom: 30px;
            border-top: 1px solid #2a3a5f;
            padding-top: 16px;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
        }
        
        .container {
            max-width: 1200px;
            margin: auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #1a2a4f 0%, #2a3a5f 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h2 {
            margin: 0;
            font-size: 2em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .page-header p {
            margin-top: 10px;
            opacity: 0.9;
        }
        
        .report-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .report-title {
            background: #1a2a4f;
            color: white;
            padding: 15px 20px;
        }
        
        .report-title h3 {
            margin: 0;
            font-size: 1.3em;
        }
        
        .report-content {
            padding: 20px;
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .data-table thead tr {
            background: #1a2a4f;
            color: white;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .data-table tbody tr:hover {
            background-color: #e8f0fe;
        }
        
        .download-btn {
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .no-data {
            text-align: center;
            color: #999;
            padding: 40px;
            font-size: 1.1em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card i {
            font-size: 2.5em;
            color: #1a2a4f;
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            font-size: 2em;
            color: #1a2a4f;
            margin: 10px 0;
        }
        
        .stat-card p {
            color: #666;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-nav span { display: none; }
            .sidebar-logo h2, .sidebar-logo p { display: none; }
            .user-info-sidebar .name, .user-info-sidebar .role { display: none; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { margin-left: 80px; padding: 20px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>TPT</h2>
            <p>Task Performance Tracker</p>
        </div>
        <div class="user-info-sidebar">
            <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="role">Lecturer</div>
        </div>
        <div class="sidebar-nav">
            <!-- Dashboard -->
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
            
            <!-- Analytics -->
            <a href="analytics.php">
                <i class="fas fa-chart-pie"></i><span>Analytics</span>
            </a>
            
            <!-- Video Reviews -->
            <a href="video_reviews.php">
                <i class="fas fa-video"></i><span>Video Reviews</span>
            </a>
            
            <!-- Reports -->
            <a href="report.php">
                <i class="fas fa-chart-line"></i><span>Reports</span>
            </a>
            
            <!-- Final Reports - Active -->
            <a href="final_report.php" class="active">
                <i class="fas fa-file-pdf"></i><span>Final Reports</span>
            </a>
            
            <!-- Inbox -->
            <a href="inbox.php">
                <i class="fas fa-envelope"></i><span>Inbox</span>
                <?php if ($unread_count > 0): ?>
                    <span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            
            <!-- Logout -->
            <a href="logout.php" class="logout-item">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h2><i class="fas fa-file-pdf"></i> Final Reports Management</h2>
                <p><i class="fas fa-chalkboard-teacher"></i> Lecturer View - All submitted final reports</p>
            </div>
            
            <?php
            // Get statistics for lecturer
            $total_sql = "SELECT COUNT(*) as total FROM final_reports";
            $total_result = $conn->query($total_sql);
            $total_reports = $total_result->fetch_assoc()['total'];
            
            $recent_sql = "SELECT COUNT(*) as recent FROM final_reports WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $recent_result = $conn->query($recent_sql);
            $recent_reports = $recent_result->fetch_assoc()['recent'];
            
            $groups_sql = "SELECT COUNT(DISTINCT group_id) as total_groups FROM final_reports";
            $groups_result = $conn->query($groups_sql);
            $groups_submitted = $groups_result->fetch_assoc()['total_groups'];
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-file-alt"></i>
                    <h3><?php echo $total_reports; ?></h3>
                    <p>Total Reports Submitted</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-week"></i>
                    <h3><?php echo $recent_reports; ?></h3>
                    <p>Submitted This Week</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $groups_submitted; ?></h3>
                    <p>Groups Submitted</p>
                </div>
            </div>
            
            <?php
            // Lecturer view - see all submitted final reports
            $reports_sql = "SELECT fr.*, g.group_name, u.name as leader_name, 
                                   COUNT(DISTINCT gm.user_id) as member_count
                           FROM final_reports fr 
                           JOIN groups g ON fr.group_id = g.id 
                           JOIN users u ON fr.uploaded_by = u.id 
                           LEFT JOIN group_members gm ON g.id = gm.group_id
                           GROUP BY fr.id
                           ORDER BY fr.uploaded_at DESC";
            $reports = $conn->query($reports_sql);
            
            echo "<div class='report-card'>";
            echo "<div class='report-title'><h3><i class='fas fa-inbox'></i> All Submitted Final Reports</h3></div>";
            echo "<div class='report-content'>";
            echo "<table class='data-table'>";
            echo "<thead>";
            echo "<tr>";
            echo "<th>#</th>";
            echo "<th><i class='fas fa-users'></i> Group Name</th>";
            echo "<th><i class='fas fa-user-tie'></i> Submitted By</th>";
            echo "<th><i class='fas fa-calendar'></i> Submitted At</th>";
            echo "<th><i class='fas fa-download'></i> File</th>";
            echo "<th><i class='fas fa-info-circle'></i> Details</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            
            if ($reports && $reports->num_rows > 0) {
                $counter = 1;
                while ($report = $reports->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $counter++ . "</td>";
                    echo "<td><strong>" . htmlspecialchars($report['group_name']) . "</strong></td>";
                    echo "<td><i class='fas fa-user'></i> " . htmlspecialchars($report['leader_name']) . "</td>";
                    echo "<td><i class='fas fa-calendar-alt'></i> " . date('Y-m-d H:i', strtotime($report['uploaded_at'])) . "</td>";
                    echo "<td><a href='?download=" . $report['id'] . "' class='download-btn'><i class='fas fa-download'></i> Download Report</a></td>";
                    echo "<td><small>Report ID: " . $report['id'] . "<br>Members: " . $report['member_count'] . "</small></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='no-data'><i class='fas fa-folder-open'></i> No final reports have been submitted yet</td赡";
            }
            
            echo "</tbody>";
            echo "</table>";
            echo "</div></div>";
            ?>
        </div>
    </div>
</div>
</body>
</html>
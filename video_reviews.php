<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Define variables first
$is_lecturer = false;
$is_admin = false;

// Check user role
$role_sql = "SELECT role FROM users WHERE id = '$user_id'";
$role_result = $conn->query($role_sql);
if ($role_result && $role_result->num_rows > 0) {
    $role_row = $role_result->fetch_assoc();
    if ($role_row['role'] == 'lecturer') {
        $is_lecturer = true;
    }
    if ($role_row['role'] == 'admin') {
        $is_admin = true;
    }
}

// Get unread count
$unread_count = 0;
$table_check = $conn->query("SHOW TABLES LIKE 'inbox'");
if ($table_check && $table_check->num_rows > 0) {
    $unread_sql = "SELECT COUNT(*) as unread FROM inbox WHERE user_id = $user_id AND is_read = 0";
    $unread_result = $conn->query($unread_sql);
    if ($unread_result && $unread_result->num_rows > 0) {
        $unread_count = $unread_result->fetch_assoc()['unread'];
    }
}

// Get pending videos count
$pending_count = 0;
$count_sql = "SELECT COUNT(*) as count FROM video_verifications WHERE verified = 0";
$count_result = $conn->query($count_sql);
if ($count_result && $count_result->num_rows > 0) {
    $pending_count = $count_result->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Reviews | Task Performance Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px;
            background: #1a2a4f;
            color: white;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: sticky;
            top: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header { 
            padding: 30px 24px; 
            border-bottom: 1px solid #2a3a5f; 
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 { 
            font-size: 1.8rem; 
            font-weight: 700; 
            color: white;
        }
        
        .sidebar-header p { 
            font-size: 0.75rem; 
            color: #a8b8d8; 
            margin-top: 5px;
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
        
        .nav-menu {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 0 16px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            color: #e0e6f0;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .nav-item:hover, .nav-item.active { 
            background: #2a3a60; 
            color: white;
        }
        
        .nav-item i { 
            width: 24px; 
            font-size: 1.1rem;
        }
        
        .inbox-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            margin-left: auto;
        }
        
        .logout-item { 
            margin-top: auto; 
            margin-bottom: 30px; 
            border-top: 1px solid #2a3a5f; 
            padding-top: 16px;
        }
        
        .main-content { 
            flex: 1; 
            margin-left: 0px;
            padding: 30px; 
            overflow-y: auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #1a2a4f 0%, #2a3a6f 100%);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .video-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .video-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .video-card video {
            max-width: 400px;
            border-radius: 10px;
            margin: 10px 0;
        }
        
        .btn-verify {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .no-data {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .main-content { margin-left: 80px; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>TPT</h2>
        <p>Task Performance Tracker</p>
    </div>
    <div class="user-info-sidebar">
        <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="role">Lecturer</div>
    </div>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="lecturer_courses.php" class="nav-item"><i class="fas fa-book"></i> <span>My Courses</span></a>
        <a href="analytics.php" class="nav-item"><i class="fas fa-chart-pie"></i> <span>Analytics</span></a>
        <a href="report.php" class="nav-item"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
        <a href="final_report.php" class="nav-item"><i class="fas fa-file-pdf"></i> <span>Final Reports</span></a>
        <a href="video_reviews.php" class="nav-item active"><i class="fas fa-video"></i> <span>Video Reviews</span>
            <?php if ($pending_count > 0): ?>
                <span class="inbox-badge"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="inbox.php" class="nav-item"><i class="fas fa-envelope"></i> <span>Inbox</span>
            <?php if ($unread_count > 0): ?>
                <span class="inbox-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="logout.php" class="nav-item logout-item"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-video"></i> Video Verifications</h1>
        <p>Review videos submitted by students</p>
    </div>
    
    <div class="video-container">
        <h3><i class="fas fa-clock"></i> Pending Video Verifications (<?php echo $pending_count; ?>)</h3>
        
        <?php
        $video_sql = "SELECT v.*, u.name as student_name 
                      FROM video_verifications v
                      JOIN users u ON v.user_id = u.id
                      WHERE v.verified = 0
                      ORDER BY v.submitted_at DESC";
        $videos = $conn->query($video_sql);
        
        if ($videos && $videos->num_rows > 0):
            while ($video = $videos->fetch_assoc()):
                // Fix video path
                $video_path = $video['video_path'];
                if (!file_exists($video_path) && file_exists("video_checks/" . basename($video_path))) {
                    $video_path = "video_checks/" . basename($video_path);
                }
        ?>
            <div class="video-card">
                <p><strong><i class="fas fa-user"></i> Student:</strong> <?php echo htmlspecialchars($video['student_name']); ?></p>
                <p><strong><i class="fas fa-calendar"></i> Submitted:</strong> <?php echo date('M d, Y g:i A', strtotime($video['submitted_at'])); ?></p>
                <?php if (file_exists($video_path)): ?>
                    <video controls>
                        <source src="<?php echo $video_path; ?>" type="video/webm">
                        Your browser does not support the video tag.
                    </video>
                <?php else: ?>
                    <p style="color: #dc3545;">⚠️ Video file not found at: <?php echo htmlspecialchars($video_path); ?></p>
                <?php endif; ?>
                <div style="margin-top: 15px;">
                    <button class="btn-verify" onclick="verifyVideo(<?php echo $video['id']; ?>, 1)"><i class="fas fa-check"></i> Verify</button>
                    <button class="btn-reject" onclick="verifyVideo(<?php echo $video['id']; ?>, 2)"><i class="fas fa-flag"></i> Flag as Suspicious</button>
                </div>
            </div>
        <?php 
            endwhile;
        else:
            echo '<div class="no-data"><i class="fas fa-video-slash" style="font-size: 48px; color: #ccc;"></i><p>No pending video verifications.</p></div>';
        endif;
        ?>
    </div>
</div>

<script>
function verifyVideo(videoId, status) {
    if (confirm(status == 1 ? 'Verify this video?' : 'Flag this video as suspicious?')) {
        fetch('verify_video.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'video_id=' + videoId + '&status=' + status
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error updating video status');
        });
    }
}
</script>

</body>
</html>

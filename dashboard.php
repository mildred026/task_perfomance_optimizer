<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// ============ ADMIN REDIRECT FIX ============
// Check if user is admin - redirect to admin dashboard
$role_check = "SELECT role FROM users WHERE id = $user_id";
$role_result = $conn->query($role_check);
if ($role_result && $row = $role_result->fetch_assoc()) {
    if ($row['role'] == 'admin') {
        header('Location: admin_dashboard.php');
        exit();
    }
}
// ============ END ADMIN REDIRECT FIX ============

// Get user role, name, and is_leader from database
$user_sql = "SELECT role, name, is_leader FROM users WHERE id = '$user_id'";
$user_result = $conn->query($user_sql);
$user_data = $user_result->fetch_assoc();
$user_role = $user_data['role'];
$user_name = htmlspecialchars($user_data['name']);
$is_leader = $user_data['is_leader'];

$is_lecturer = ($user_role == 'lecturer');

// Get group_id for statistics
$group_id = null;
if ($is_leader) {
    $group_sql = "SELECT id FROM project_groups WHERE leader_id = '$user_id'";
    $group_result = $conn->query($group_sql);
    if ($group_result && $group_result->num_rows > 0) {
        $group_id = $group_result->fetch_assoc()['id'];
        $_SESSION['group_id'] = $group_id;
    }
} else if ($user_role == 'student') {
    $member_sql = "SELECT group_id FROM group_members WHERE user_id = '$user_id'";
    $member_result = $conn->query($member_sql);
    if ($member_result && $member_result->num_rows > 0) {
        $group_id = $member_result->fetch_assoc()['group_id'];
    }
}

// Get statistics for dashboard
$task_stats = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'pending' => 0];
$member_count = 0;
$recent_activity = null;

if ($group_id && !$is_lecturer) {
    $task_stats_query = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
        FROM tasks WHERE group_id = $group_id");
    if ($task_stats_query && $task_stats_query->num_rows > 0) {
        $task_stats = $task_stats_query->fetch_assoc();
    }
    
    $member_query = $conn->query("SELECT COUNT(*) as count FROM group_members WHERE group_id = $group_id");
    if ($member_query && $member_query->num_rows > 0) {
        $member_count = $member_query->fetch_assoc()['count'];
    }
    
    $recent_activity = $conn->query("SELECT pu.title, u.name, pu.created_at 
        FROM progress_updates pu
        JOIN users u ON pu.user_id = u.id
        WHERE pu.group_id = $group_id
        ORDER BY pu.created_at DESC LIMIT 3");
}

// FOR LECTURERS: Get their courses
$lecturer_courses = [];
if ($is_lecturer) {
    $courses_sql = "SELECT * FROM lecturer_courses WHERE lecturer_id = $user_id ORDER BY course_code";
    $courses_result = $conn->query($courses_sql);
    while ($course = $courses_result->fetch_assoc()) {
        $group_count_sql = "SELECT COUNT(*) as count FROM project_groups WHERE course_id = {$course['id']}";
        $group_count_result = $conn->query($group_count_sql);
        $course['group_count'] = $group_count_result->fetch_assoc()['count'];
        $lecturer_courses[] = $course;
    }
}

// Get unread message count for inbox badge
$unread_count = 0;
$table_check = $conn->query("SHOW TABLES LIKE 'messages'");
if ($table_check && $table_check->num_rows > 0) {
    $unread_sql = "SELECT COUNT(*) as unread FROM messages WHERE to_user_id = $user_id AND is_read = 0";
    $unread_result = $conn->query($unread_sql);
    if ($unread_result && $unread_result->num_rows > 0) {
        $unread_count = $unread_result->fetch_assoc()['unread'];
    }
}

// Get pending video count for badge
$pending_videos = 0;
if ($is_lecturer) {
    $video_count_sql = "SELECT COUNT(*) as count FROM video_verifications WHERE verified = 0";
    $video_count_result = $conn->query($video_count_sql);
    if ($video_count_result && $video_count_result->num_rows > 0) {
        $pending_videos = $video_count_result->fetch_assoc()['count'];
    }
}

// Update session variables
$_SESSION['isLeader'] = $is_leader;
$_SESSION['user_name'] = $user_name;
$_SESSION['group_id'] = $group_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Task Performance Tracker</title>
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
            display: flex;
            min-height: 100vh;
        }

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
            transition: all 0.2s;
            text-decoration: none;
        }

        .nav-item i {
            width: 24px;
            font-size: 1.1rem;
            color: #8a9bc0;
        }

        .nav-item:hover {
            background: #2a3a60;
            color: white;
        }

        .nav-item:hover i {
            color: white;
        }

        .nav-item.active {
            background: #2a3a60;
            color: white;
        }

        .nav-item.active i {
            color: white;
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
            padding: 40px 50px;
            overflow-y: auto;
        }

        .welcome-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            padding: 30px 35px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            flex: 1;
            z-index: 2;
        }

        .greeting {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .wave-emoji {
            font-size: 2rem;
            animation: wave 2s infinite;
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(15deg); }
            75% { transform: rotate(-10deg); }
        }

        .greeting-text {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.9);
        }

        .hero-name {
            font-size: 2.5rem;
            color: white;
            margin-bottom: 10px;
        }

        .hero-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            background: rgba(255,255,255,0.15);
            padding: 15px 25px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .hero-stat {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }

        .stat-label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.7);
        }

        .hero-decoration {
            position: relative;
            width: 150px;
            height: 150px;
        }

        .floating-icon {
            position: absolute;
            font-size: 2rem;
            animation: float 3s ease-in-out infinite;
        }

        .icon-1 { top: 0; left: 20px; animation-delay: 0s; }
        .icon-2 { top: 50px; right: 0; animation-delay: 0.5s; }
        .icon-3 { bottom: 0; left: 0; animation-delay: 1s; }
        .icon-4 { bottom: 30px; right: 30px; animation-delay: 1.5s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        .section-title {
            font-size: 1.4rem;
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .title-underline {
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
        }

        .quick-actions {
            margin-bottom: 35px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            border-radius: 18px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .action-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #667eea15, #764ba215);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-icon i {
            font-size: 1.8rem;
            color: #667eea;
        }

        .action-info {
            flex: 1;
        }

        .action-info h3 {
            font-size: 1rem;
            color: #1a2a4f;
            margin-bottom: 5px;
        }

        .action-info p {
            font-size: 0.8rem;
            color: #666;
        }

        .action-arrow {
            color: #ccc;
            transition: all 0.3s;
        }

        .action-card:hover .action-arrow {
            transform: translateX(5px);
            color: #667eea;
        }

        .stats-dashboard {
            margin-bottom: 35px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            position: relative;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1a2a4f;
        }

        .stat-icon {
            font-size: 2rem;
            margin: 10px 0;
        }

        .progress-bar-bg {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .activity-feed {
            margin-bottom: 35px;
        }

        .activity-timeline {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content p {
            margin-bottom: 5px;
            color: #666;
        }

        .activity-title {
            font-weight: 500;
            color: #1a2a4f;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 0.7rem;
            color: #999;
        }

        .courses-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .courses-card h3 {
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .course-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .course-code { font-weight: bold; color: #667eea; font-size: 1rem; }
        .course-name { font-size: 0.95rem; color: #333; }
        .course-meta { font-size: 0.75rem; color: #666; margin-top: 5px; }
        .course-stats { font-size: 0.85rem; color: #28a745; }
        .btn-view-course {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-view-course:hover { background: #5a67d8; }

        .quote-card {
            background: linear-gradient(135deg, #1a2a4f, #2a3a6f);
            border-radius: 20px;
            padding: 25px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
            position: relative;
        }

        .quote-icon {
            font-size: 3rem;
        }

        .quote-text {
            flex: 1;
        }

        .quote-text p {
            font-size: 1.1rem;
            font-style: italic;
            margin-bottom: 8px;
        }

        .quote-text span {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .quote-refresh {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quote-refresh:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(180deg);
        }

        .role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .role-student {
            background: #28a745;
            color: white;
        }

        .role-leader {
            background: #ffc107;
            color: #1a2a4f;
        }

        .role-lecturer {
            background: #dc3545;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
            .hero-stats { gap: 15px; padding: 10px 15px; }
            .stat-value { font-size: 1.2rem; }
            .hero-name { font-size: 1.5rem; }
            .actions-grid { grid-template-columns: 1fr; }
            .course-item { flex-direction: column; gap: 10px; }
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
        <div class="name"><?php echo $user_name; ?></div>
        <div class="role">
            <?php 
            if ($is_lecturer) {
                echo 'Lecturer';
            } elseif ($is_leader) {
                echo 'Group Leader 👑';
            } else {
                echo 'Member';
            }
            ?>
        </div>
    </div>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item active">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        
        <?php if ($is_lecturer): ?>
            <a href="lecturer_courses.php" class="nav-item">
                <i class="fas fa-book"></i> <span>My Courses</span>
                <?php if (count($lecturer_courses) > 0): ?>
                    <span class="inbox-badge"><?php echo count($lecturer_courses); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        
        <?php if ($is_lecturer): ?>
            <a href="analytics.php" class="nav-item">
                <i class="fas fa-chart-pie"></i> <span>Analytics</span>
            </a>
        <?php endif; ?>
        
        <?php if ($is_leader || $is_lecturer): ?>
            <a href="report.php" class="nav-item">
                <i class="fas fa-chart-line"></i> <span>Reports</span>
            </a>
        <?php endif; ?>
        
        <?php if ($is_lecturer): ?>
            <a href="final_report.php" class="nav-item">
                <i class="fas fa-file-pdf"></i> <span>Final Reports</span>
            </a>
        <?php endif; ?>
        
        <?php if ($is_lecturer): ?>
            <a href="video_reviews.php" class="nav-item">
                <i class="fas fa-video"></i> <span>Video Reviews</span>
                <?php if ($pending_videos > 0): ?>
                    <span class="inbox-badge"><?php echo $pending_videos; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        
        <?php if (!$is_lecturer): ?>
            <a href="progress_board.php" class="nav-item">
                <i class="fas fa-images"></i> <span>Progress Board</span>
            </a>
        <?php endif; ?>
        
        <?php if ($is_leader && !$is_lecturer): ?>
            <a href="create_group.php" class="nav-item">
                <i class="fas fa-users"></i> <span>Create Group</span>
            </a>
        <?php endif; ?>
        
        <?php if (!$is_lecturer): ?>
            <a href="join_group.php" class="nav-item">
                <i class="fas fa-layer-group"></i> <span>Available Groups</span>
            </a>
        <?php endif; ?>
        
        <?php if (!$is_lecturer): ?>
            <a href="inbox.php" class="nav-item">
                <i class="fas fa-envelope"></i> <span>Inbox</span>
                <?php if ($unread_count > 0): ?>
                    <span class="inbox-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        
       <?php if ($is_leader && !$is_lecturer): ?>
    <a href="assign_tasks.php" class="nav-item">
        <i class="fas fa-tasks"></i> <span>Assign Tasks</span>
    </a>
<?php endif; ?>
        
        <?php if (!$is_lecturer): ?>
            <a href="my_tasks.php" class="nav-item">
                <i class="fas fa-list-check"></i> <span>My Tasks</span>
            </a>
        <?php endif; ?>
        
        <?php if (!$is_lecturer): ?>
            <a href="upload_contribution.php" class="nav-item">
                <i class="fas fa-upload"></i> <span>Upload Contribution</span>
            </a>
        <?php endif; ?>
        
        <a href="logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="welcome-hero">
        <div class="hero-content">
            <div class="greeting">
                <span class="wave-emoji">👋</span>
                <span class="greeting-text">
                    <?php 
                    $hour = date('H');
                    if ($hour < 12) echo "Good Morning";
                    elseif ($hour < 17) echo "Good Afternoon";
                    else echo "Good Evening";
                    ?>
                </span>
            </div>
            <h1 class="hero-name"><?php echo $user_name; ?>!</h1>
            <p class="hero-subtitle">
                <?php 
                if ($is_lecturer) echo "Welcome to the Lecturer Dashboard 📚";
                elseif ($is_leader) echo "Ready to lead your team to success? 🚀";
                else echo "Keep up the great work! 🌟";
                ?>
            </p>
        </div>
        <?php if (!$is_lecturer): ?>
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="stat-value"><?php echo $task_stats['in_progress'] + $task_stats['pending']; ?></div>
                <div class="stat-label">Active Tasks</div>
            </div>
            <div class="hero-stat">
                <div class="stat-value"><?php echo $member_count; ?></div>
                <div class="stat-label">Team Members</div>
            </div>
            <div class="hero-stat">
                <div class="stat-value"><?php echo $task_stats['total'] > 0 ? round(($task_stats['completed'] / $task_stats['total']) * 100) : 0; ?>%</div>
                <div class="stat-label">Completion Rate</div>
            </div>
        </div>
        <?php endif; ?>
        <div class="hero-decoration">
            <div class="floating-icon icon-1">🎯</div>
            <div class="floating-icon icon-2">📊</div>
            <div class="floating-icon icon-3">🏆</div>
            <div class="floating-icon icon-4">💡</div>
        </div>
    </div>

    <div style="margin-bottom: 25px;">
        <?php
        $role_display = '';
        $role_class = '';
        if ($is_lecturer) {
            $role_display = 'Lecturer';
            $role_class = 'role-lecturer';
        } elseif ($is_leader) {
            $role_display = 'Group Leader';
            $role_class = 'role-leader';
        } else {
            $role_display = 'Student Member';
            $role_class = 'role-student';
        }
        ?>
        
        <div class="role-badge <?php echo $role_class; ?>" style="margin-bottom: 15px;">
            <i class="fas fa-user-tag"></i> Role: <?php echo $role_display; ?>
        </div>
        
        <?php if ($is_lecturer): ?>
            <div style="background: #e8f0fe; border-left: 4px solid #17a2b8; padding: 12px 20px; border-radius: 10px;">
                <i class="fas fa-chalkboard-teacher" style="color: #17a2b8;"></i> 
                <strong>Lecturer Mode:</strong> You can view all group reports and final submissions.
            </div>
        <?php elseif ($is_leader && !$is_lecturer): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 20px; border-radius: 10px;">
                <i class="fas fa-crown" style="color: #ffc107;"></i> 
                <strong>Group Leader:</strong> You can assign tasks to your group members and review their progress!
            </div>
        <?php elseif (!$is_lecturer && !$is_leader && !$group_id): ?>
            <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px 20px; border-radius: 10px;">
                <i class="fas fa-users" style="color: #28a745;"></i> 
                <strong>Not in a group yet!</strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($is_lecturer && count($lecturer_courses) > 0): ?>
    <div class="courses-card">
        <h3><i class="fas fa-book"></i> My Courses / Assignments</h3>
        <?php foreach ($lecturer_courses as $course): ?>
        <div class="course-item">
            <div>
                <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                <div class="course-meta">
                    <i class="fas fa-calendar"></i> <?php echo $course['semester']; ?> | 
                    <i class="fas fa-clock"></i> <?php echo $course['academic_year']; ?>
                </div>
            </div>
            <div>
                <span class="course-stats"><i class="fas fa-users"></i> <?php echo $course['group_count']; ?> groups</span>
                <a href="report.php?course_id=<?php echo $course['id']; ?>" class="btn-view-course">
                    <i class="fas fa-eye"></i> View Groups
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php elseif ($is_lecturer && count($lecturer_courses) == 0): ?>
    <div class="courses-card">
        <h3><i class="fas fa-book"></i> My Courses / Assignments</h3>
        <div style="text-align: center; padding: 30px; color: #999;">
            <i class="fas fa-plus-circle" style="font-size: 48px;"></i>
            <p style="margin-top: 15px;">You haven't added any courses yet.</p>
            <a href="lecturer_courses.php" class="btn-view-course" style="display: inline-block; margin-top: 10px;">
                <i class="fas fa-plus"></i> Add Your First Course
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$is_lecturer): ?>
    <div class="quick-actions">
        <h2 class="section-title">
            <i class="fas fa-bolt"></i> Quick Actions
            <span class="title-underline"></span>
        </h2>
        <div class="actions-grid">
            <a href="my_tasks.php" class="action-card">
                <div class="action-icon"><i class="fas fa-list-check"></i></div>
                <div class="action-info">
                    <h3>My Tasks</h3>
                    <p>View and manage your assignments</p>
                </div>
                <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
            </a>
            
            <a href="progress_board.php" class="action-card">
                <div class="action-icon"><i class="fas fa-images"></i></div>
                <div class="action-info">
                    <h3>Progress Board</h3>
                    <p>Share screenshots of your work</p>
                </div>
                <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
            </a>
            
            <?php if ($is_leader && $group_id): ?>
                <a href="view_group.php?group_id=<?php echo $group_id; ?>" class="action-card">
                    <div class="action-icon"><i class="fas fa-users"></i></div>
                    <div class="action-info">
                        <h3>Team Members</h3>
                        <p>Manage your group members</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
                </a>
            <?php endif; ?>
            
            <?php if (!$group_id): ?>
                <a href="join_group.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-link"></i></div>
                    <div class="action-info">
                        <h3>Join a Group</h3>
                        <p>Find and join existing groups</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
                </a>
            <?php endif; ?>
            
            <?php if ($is_leader): ?>
                <a href="create_group.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-users"></i></div>
                    <div class="action-info">
                        <h3>Create Group</h3>
                        <p>Start a new team</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($group_id && !$is_lecturer): ?>
    <div class="stats-dashboard">
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i> Project Overview
            <span class="title-underline"></span>
        </h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $task_stats['completed']; ?>/<?php echo $task_stats['total']; ?></div>
                <div class="stat-label">Tasks Completed</div>
                <div class="stat-icon">✅</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $member_count; ?></div>
                <div class="stat-label">Team Members</div>
                <div class="stat-icon">👥</div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?php echo $member_count > 0 ? '100' : '0'; ?>%"></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $task_stats['total'] > 0 ? round(($task_stats['completed'] / $task_stats['total']) * 100) : 0; ?>%</div>
                <div class="stat-label">Completion Rate</div>
                <div class="stat-icon">📈</div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?php echo $task_stats['total'] > 0 ? ($task_stats['completed'] / $task_stats['total']) * 100 : 0; ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($recent_activity && $recent_activity->num_rows > 0 && !$is_lecturer): ?>
    <div class="activity-feed">
        <h2 class="section-title">
            <i class="fas fa-clock"></i> Recent Activity
            <span class="title-underline"></span>
        </h2>
        <div class="activity-timeline">
            <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-avatar">
                        <?php echo strtoupper(substr($activity['name'], 0, 1)); ?>
                    </div>
                    <div class="activity-content">
                        <p><strong><?php echo htmlspecialchars($activity['name']); ?></strong> updated progress on</p>
                        <p class="activity-title">"<?php echo htmlspecialchars($activity['title']); ?>"</p>
                        <span class="activity-time">
                            <?php 
                            $seconds = time() - strtotime($activity['created_at']);
                            if ($seconds < 60) echo 'just now';
                            elseif ($seconds < 3600) echo floor($seconds / 60) . ' minutes ago';
                            elseif ($seconds < 86400) echo floor($seconds / 3600) . ' hours ago';
                            else echo floor($seconds / 86400) . ' days ago';
                            ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="quote-card">
        <div class="quote-icon">💪</div>
        <div class="quote-text">
            <p id="dailyQuote">"The secret of getting ahead is getting started."</p>
            <span>- Mark Twain</span>
        </div>
        <button class="quote-refresh" onclick="changeQuote()">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
</div>

<script>
const quotes = [
    { text: "The secret of getting ahead is getting started.", author: "Mark Twain" },
    { text: "Teamwork makes the dream work.", author: "John C. Maxwell" },
    { text: "Success is not final, failure is not fatal.", author: "Winston Churchill" },
    { text: "The only way to do great work is to love what you do.", author: "Steve Jobs" },
    { text: "Alone we can do so little; together we can do so much.", author: "Helen Keller" },
    { text: "Progress is impossible without change.", author: "George Bernard Shaw" },
    { text: "Don't watch the clock; do what it does. Keep going.", author: "Sam Levenson" },
    { text: "The future depends on what you do today.", author: "Mahatma Gandhi" },
    { text: "Small progress is still progress.", author: "Unknown" },
    { text: "Believe you can and you're halfway there.", author: "Theodore Roosevelt" }
];

function changeQuote() {
    const randomIndex = Math.floor(Math.random() * quotes.length);
    const quote = quotes[randomIndex];
    document.querySelector('#dailyQuote').innerText = '"' + quote.text + '"';
    document.querySelector('.quote-text span').innerText = '- ' + quote.author;
}

const minTime = 30 * 1000;
const maxTime = 60 * 1000;

let lastVideoCheck = localStorage.getItem('lastVideoCheck') || 0;
let currentTime = Date.now();

<?php if (!$is_lecturer): ?>
if (currentTime - lastVideoCheck > minTime) {
    let randomDelay = Math.floor(Math.random() * (maxTime - minTime + 1) + minTime);
    
    setTimeout(function() {
        let videoWindow = window.open('video_check.php', '_blank', 'width=550,height=700');
        
        let checkInterval = setInterval(function() {
            if (videoWindow.closed) {
                clearInterval(checkInterval);
                localStorage.setItem('lastVideoCheck', Date.now());
            }
        }, 1000);
    }, randomDelay);
}
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>
</body>
</html>
<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// FIX: Get user data DIRECTLY FROM DATABASE (not from session)
$user_sql = "SELECT name, role, is_leader FROM users WHERE id = '$user_id'";
$user_result = $conn->query($user_sql);
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'];
$user_role = $user_data['role'];
$is_leader = $user_data['is_leader'];  // Get from database, NOT session

$is_lecturer = ($user_role == 'lecturer');
$error_message = "";
$success_message = "";

// Update session to be consistent
$_SESSION['is_leader'] = $is_leader;
$_SESSION['isLeader'] = $is_leader;

// ONLY leaders can create groups - RESTRICT regular students
if ($is_leader != 1) {
    $error_message = "🔒 Only group leaders can create new groups.";
}

// Handle group creation (ONLY for leaders)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_group']) && empty($error_message) && $is_leader == 1) {
    $group_name = $conn->real_escape_string($_POST['group_name']);
    $course_id = intval($_POST['course_id']);
    
    // Check if group name already exists
    $check_sql = "SELECT id FROM project_groups WHERE group_name = '$group_name'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->num_rows > 0) {
        $error_message = "❌ Group name already exists. Please choose another name.";
    } else {
        // Insert group with course_id
        $insert_sql = "INSERT INTO project_groups (group_name, leader_id, course_id, created_at) 
                       VALUES ('$group_name', '$user_id', '$course_id', NOW())";
        
        if ($conn->query($insert_sql)) {
            $group_id = $conn->insert_id;
            
            // Add leader as member of the group
            $add_member = "INSERT INTO group_members (group_id, user_id, joined_at) 
                           VALUES ($group_id, $user_id, NOW())";
            $conn->query($add_member);
            
            // Update session with group_id
            $_SESSION['group_id'] = $group_id;
            $_SESSION['is_leader'] = 1;
            $_SESSION['isLeader'] = 1;
            
            $success_message = "🎉 Group '$group_name' created successfully!";
            
            // Redirect to leader dashboard after 2 seconds
            echo '<script>
                    setTimeout(function() {
                        window.location.href = "leader_dashboard.php";
                    }, 2000);
                  </script>';
        } else {
            $error_message = "❌ Failed to create group: " . $conn->error;
        }
    }
}

// Get all groups created by this leader
$my_groups_sql = "SELECT g.*, 
                  (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                  lc.course_code, lc.course_name
                  FROM project_groups g
                  LEFT JOIN lecturer_courses lc ON g.course_id = lc.id
                  WHERE g.leader_id = $user_id
                  ORDER BY g.created_at DESC";
$my_groups = $conn->query($my_groups_sql);

// Get all available courses for the dropdown
$courses_sql = "SELECT lc.id, lc.course_code, lc.course_name, u.name as lecturer_name 
                FROM lecturer_courses lc
                JOIN users u ON lc.lecturer_id = u.id
                ORDER BY lc.course_code";
$courses_result = $conn->query($courses_sql);

// Pre-fetch courses for the dropdown
$all_courses = [];
while ($course = $courses_result->fetch_assoc()) {
    $all_courses[] = $course;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Group - Task Performance Tracker</title>
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
            padding: 40px;
            overflow-y: auto;
        }

        /* Two Column Layout */
        .two-columns {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .create-column {
            flex: 1;
            min-width: 350px;
        }

        .groups-column {
            flex: 1;
            min-width: 350px;
        }

        /* Create Group Card */
        .create-card {
            width: 100%;
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .card-header .emoji {
            font-size: 4rem;
            margin-bottom: 10px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .card-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .floating-stars {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 1.2rem;
            opacity: 0.6;
            animation: twinkle 3s infinite;
        }

        .floating-stars.right {
            left: auto;
            right: 20px;
            animation-delay: 1.5s;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        .card-body {
            padding: 35px 30px;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .alert i {
            font-size: 1.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1a2a4f;
            font-size: 1rem;
        }

        .form-group label i {
            color: #667eea;
            margin-right: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i.input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 1.1rem;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn-create {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-create:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #28a745, #20c997);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* My Groups List */
        .groups-list-card {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .groups-list-header {
            background: linear-gradient(135deg, #1a2a4f 0%, #2a3a6f 100%);
            padding: 25px 30px;
            color: white;
        }

        .groups-list-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .groups-list-header p {
            opacity: 0.8;
            font-size: 0.85rem;
        }

        .groups-list-body {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }

        .my-group-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s;
            border-left: 4px solid #667eea;
        }

        .my-group-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .my-group-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a2a4f;
            margin-bottom: 8px;
        }

        .my-group-info {
            display: flex;
            gap: 20px;
            font-size: 0.8rem;
            color: #666;
            flex-wrap: wrap;
        }

        .my-group-info i {
            margin-right: 5px;
            color: #667eea;
        }

        .course-badge {
            background: #e8f0fe;
            color: #1a2a4f;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        .no-groups-msg {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .leader-badge {
            display: inline-block;
            background: #ffc107;
            color: #1a2a4f;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 10px;
        }

        @media (max-width: 900px) {
            .two-columns {
                flex-direction: column;
            }
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
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
        <div class="role">
            <?php 
            if ($is_lecturer) {
                echo 'Lecturer';
            } elseif ($is_leader == 1) {
                echo 'Group Leader';
            } else {
                echo 'Member';
            }
            ?>
        </div>
    </div>
    <div class="nav-menu">
        <a href="leader_dashboard.php" class="nav-item">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <?php if (!$is_lecturer && $is_leader == 1): ?>
            <a href="create_group.php" class="nav-item active">
                <i class="fas fa-users"></i> <span>Create Group</span>
            </a>
        <?php endif; ?>
        <?php if (!$is_lecturer): ?>
            <a href="join_group.php" class="nav-item">
                <i class="fas fa-layer-group"></i> <span>Available Groups</span>
            </a>
        <?php endif; ?>
        <a href="inbox.php" class="nav-item">
            <i class="fas fa-envelope"></i> <span>Inbox</span>
            <?php if ($unread_count > 0): ?>
                <span class="inbox-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <?php if ($is_leader == 1 && !$is_lecturer): ?>
            <a href="assign_tasks.php" class="nav-item">
                <i class="fas fa-tasks"></i> <span>Assign Tasks</span>
            </a>
        <?php endif; ?>
        <?php if (!$is_lecturer): ?>
            <a href="my_tasks.php" class="nav-item">
                <i class="fas fa-list-check"></i> <span>My Tasks</span>
            </a>
            <a href="upload_contribution.php" class="nav-item">
                <i class="fas fa-upload"></i> <span>Upload Contribution</span>
            </a>
        <?php endif; ?>
        <a href="progress_board.php" class="nav-item">
            <i class="fas fa-images"></i> <span>Progress Board</span>
        </a>
        <?php if ($is_leader == 1 || $is_lecturer): ?>
            <a href="report.php" class="nav-item">
                <i class="fas fa-chart-line"></i> <span>Reports</span>
            </a>
        <?php endif; ?>
        <a href="logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="two-columns">
        <!-- Left Column: Create Group Form -->
        <div class="create-column">
            <div class="create-card">
                <div class="card-header">
                    <div class="floating-stars">✨</div>
                    <div class="floating-stars right">⭐</div>
                    <div class="emoji">🚀</div>
                    <h1>Create a New Group</h1>
                    <p>Start your journey with a team! <span class="leader-badge"><i class="fas fa-crown"></i> Leaders Only</span></p>
                </div>
                
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div><?php echo $success_message; ?> Redirecting to Leader Dashboard...</div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div><?php echo $error_message; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is_leader == 1): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Group Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-users input-icon"></i>
                                <input type="text" name="group_name" placeholder="Enter your group name" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-book"></i> Select Course / Assignment</label>
                            <div class="input-wrapper">
                                <i class="fas fa-graduation-cap input-icon"></i>
                                <select name="course_id" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($all_courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'] . ' (' . $course['lecturer_name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small>Select which course/assignment this group is for</small>
                        </div>
                        
                        <button type="submit" name="create_group" class="btn-create">
                            <i class="fas fa-magic"></i> Create Group
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="join_group.php" class="btn-create btn-secondary" style="text-decoration: none;">
                            <i class="fas fa-sign-in-alt"></i> Browse Available Groups
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: My Groups List -->
        <div class="groups-column">
            <div class="groups-list-card">
                <div class="groups-list-header">
                    <h2><i class="fas fa-chalkboard"></i> My Groups</h2>
                    <p>Groups you have created as a leader</p>
                </div>
                <div class="groups-list-body">
                    <?php if ($my_groups && $my_groups->num_rows > 0): ?>
                        <?php while ($group = $my_groups->fetch_assoc()): ?>
                            <div class="my-group-item">
                                <div class="my-group-name">
                                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($group['group_name']); ?>
                                </div>
                                <div class="my-group-info">
                                    <span><i class="fas fa-calendar-alt"></i> Created: <?php echo date('M d, Y', strtotime($group['created_at'])); ?></span>
                                    <span><i class="fas fa-user-friends"></i> <?php echo $group['member_count']; ?> members</span>
                                    <?php if (!empty($group['course_code'])): ?>
                                        <span class="course-badge"><i class="fas fa-book"></i> <?php echo htmlspecialchars($group['course_code']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-groups-msg">
                            <i class="fas fa-plus-circle" style="font-size: 48px; color: #ccc;"></i>
                            <p style="margin-top: 15px;">You haven't created any groups yet.</p>
                            <p>Use the form to create your first group!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

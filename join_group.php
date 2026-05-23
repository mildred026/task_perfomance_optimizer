<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = $_SESSION['group_id'] ?? null;
$is_leader = $_SESSION['isLeader'] ?? false;

// Get user role and name
$user_sql = "SELECT name, role FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'];
$user_role = $user_data['role'];

$is_lecturer = ($user_role == 'lecturer');

// Handle joining a group - REMOVED the check that prevents multiple memberships
if (isset($_GET['join'])) {
    $join_group_id = intval($_GET['join']);
    
    // Check if user is already a member of THIS SPECIFIC group
    $check_member = "SELECT * FROM group_members WHERE group_id = $join_group_id AND user_id = $user_id";
    $member_result = $conn->query($check_member);
    
    if ($member_result && $member_result->num_rows > 0) {
        $_SESSION['error'] = "You are already a member of this group.";
    } else {
        // Check if group exists
        $group_check = "SELECT id FROM project_groups WHERE id = $join_group_id";
        $group_result = $conn->query($group_check);
        
        if ($group_result && $group_result->num_rows > 0) {
            $insert_sql = "INSERT INTO group_members (group_id, user_id, joined_at) VALUES ($join_group_id, $user_id, NOW())";
            if ($conn->query($insert_sql)) {
                $_SESSION['success'] = "You have successfully joined the group!";
            } else {
                $_SESSION['error'] = "Failed to join group: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Group not found.";
        }
    }
    header('Location: join_group.php');
    exit();
}

// Handle leaving a group
if (isset($_GET['leave'])) {
    $leave_group_id = intval($_GET['leave']);
    
    $delete_sql = "DELETE FROM group_members WHERE group_id = $leave_group_id AND user_id = $user_id";
    if ($conn->query($delete_sql)) {
        $_SESSION['success'] = "You have left the group.";
    } else {
        $_SESSION['error'] = "Failed to leave group.";
    }
    header('Location: join_group.php');
    exit();
}

// Handle deleting a group (only for group leader)
if (isset($_GET['delete_group'])) {
    $delete_group_id = intval($_GET['delete_group']);
    
    // Verify the user is the leader of this group
    $check_leader = "SELECT leader_id FROM project_groups WHERE id = $delete_group_id";
    $leader_result = $conn->query($check_leader);
    
    if ($leader_result && $leader_result->num_rows > 0) {
        $leader_id = $leader_result->fetch_assoc()['leader_id'];
        
        if ($user_id == $leader_id) {
            // Delete group members first
            $conn->query("DELETE FROM group_members WHERE group_id = $delete_group_id");
            // Delete tasks
            $conn->query("DELETE FROM tasks WHERE group_id = $delete_group_id");
            // Delete progress updates and screenshots
            $progress = $conn->query("SELECT id FROM progress_updates WHERE group_id = $delete_group_id");
            while ($p = $progress->fetch_assoc()) {
                $conn->query("DELETE FROM progress_screenshots WHERE progress_id = {$p['id']}");
            }
            $conn->query("DELETE FROM progress_updates WHERE group_id = $delete_group_id");
            // Delete messages
            $conn->query("DELETE FROM messages WHERE group_id = $delete_group_id");
            // Finally delete the group
            $conn->query("DELETE FROM project_groups WHERE id = $delete_group_id");
            
            $_SESSION['success'] = "Group deleted successfully!";
        } else {
            $_SESSION['error'] = "You are not authorized to delete this group.";
        }
    } else {
        $_SESSION['error'] = "Group not found.";
    }
    header('Location: join_group.php');
    exit();
}

// Handle viewing members
if (isset($_GET['view_members'])) {
    $view_group_id = intval($_GET['view_members']);
    header('Location: view_group.php?group_id=' . $view_group_id);
    exit();
}

// Get ALL groups the user is a member of (for displaying current groups)
$user_groups_sql = "SELECT g.id, g.group_name, u.name as leader_name, g.leader_id,
                    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                    FROM project_groups g
                    JOIN group_members gm ON g.id = gm.group_id
                    JOIN users u ON g.leader_id = u.id
                    WHERE gm.user_id = $user_id
                    ORDER BY gm.joined_at DESC";
$user_groups = $conn->query($user_groups_sql);
$has_groups = ($user_groups && $user_groups->num_rows > 0);
$user_groups_count = $user_groups ? $user_groups->num_rows : 0;

// Get ALL groups available (excluding groups user is already in)
$available_groups_sql = "SELECT g.*, u.name as leader_name,
                         (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                         (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND user_id = $user_id) as is_member
                         FROM project_groups g
                         JOIN users u ON g.leader_id = u.id
                         WHERE g.id NOT IN (SELECT group_id FROM group_members WHERE user_id = $user_id)
                         ORDER BY g.created_at DESC";
$available_groups = $conn->query($available_groups_sql);
$available_groups_count = $available_groups ? $available_groups->num_rows : 0;

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
    <title>Groups - Task Performance Tracker</title>
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

        /* Sidebar */
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
        }

        .logout-item {
            margin-top: auto;
            margin-bottom: 30px;
            border-top: 1px solid #2a3a5f;
            padding-top: 16px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .page-header h1 {
            color: #1a2a4f;
            margin-bottom: 10px;
        }

        /* My Groups Section */
        .my-groups-section {
            margin-bottom: 35px;
        }

        .section-title {
            font-size: 1.4rem;
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .group-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .group-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .group-header {
            background: #1a2a4f;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .group-name {
            font-size: 1.2em;
            font-weight: 600;
        }

        .member-count {
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .group-body {
            padding: 20px;
        }

        .leader-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .leader-info i {
            font-size: 1.2em;
            color: #1a2a4f;
        }

        .group-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-join {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            flex: 1;
            text-align: center;
        }

        .btn-join:hover {
            background: #218838;
        }

        .btn-leave {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            flex: 1;
            text-align: center;
        }

        .btn-leave:hover {
            background: #c82333;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            flex: 1;
            text-align: center;
        }

        .btn-view:hover {
            background: #138496;
        }

        .btn-delete-group {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            flex: 1;
            text-align: center;
        }

        .btn-delete-group:hover {
            background: #c82333;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-groups {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 15px;
            color: #999;
        }

        .leader-badge {
            background: #ffc107;
            color: #1a2a4f;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
            .groups-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>TPT</h2>
    </div>
    <div class="user-info-sidebar">
        <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="role">
            <?php 
            if ($is_lecturer) {
                echo 'Lecturer';
            } elseif ($is_leader) {
                echo 'Group Leader';
            } else {
                echo 'Member';
            }
            ?>
        </div>
    </div>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <?php if (!$is_lecturer && $is_leader): ?>
            <a href="create_group.php" class="nav-item">
                <i class="fas fa-users"></i> <span>Create Group</span>
            </a>
        <?php endif; ?>
        <?php if (!$is_lecturer): ?>
            <a href="join_group.php" class="nav-item active">
                <i class="fas fa-link"></i> <span>Available Groups</span>
            </a>
        <?php endif; ?>
        <a href="inbox.php" class="nav-item">
            <i class="fas fa-envelope"></i> <span>Inbox</span>
            <?php if ($unread_count > 0): ?>
                <span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <?php if ($is_leader && !$is_lecturer): ?>
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
        <?php if ($is_leader || $is_lecturer): ?>
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
    <div class="page-header">
        <h1><i class="fas fa-users"></i>Available Groups</h1>
        <p>Manage your groups - join new ones or leave existing groups</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Groups I'm Currently In -->
    <div class="my-groups-section">
        <h2 class="section-title">
            <i class="fas fa-user-check"></i> Groups I'm In (<?php echo $user_groups->num_rows; ?>)
        </h2>
        <div class="groups-grid">
            <?php if ($user_groups && $user_groups->num_rows > 0): ?>
                <?php while ($group = $user_groups->fetch_assoc()): 
                    $is_group_leader = ($user_id == $group['leader_id']);
                ?>
                    <div class="group-card">
                        <div class="group-header">
                            <span class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></span>
                            <span class="member-count">
                                <i class="fas fa-users"></i> <?php echo $group['member_count']; ?> members
                            </span>
                        </div>
                        <div class="group-body">
                            <div class="leader-info">
                                <i class="fas fa-crown"></i>
                                <span>Leader: <strong><?php echo htmlspecialchars($group['leader_name']); ?></strong></span>
                                <?php if ($is_group_leader): ?>
                                    <span class="leader-badge"><i class="fas fa-crown"></i> You are leader</span>
                                <?php endif; ?>
                            </div>
                            <div class="group-actions">
                                <a href="?leave=<?php echo $group['id']; ?>" class="btn-leave" onclick="return confirm('Leave this group?')">
                                    <i class="fas fa-sign-out-alt"></i> Leave
                                </a>
                                <a href="view_group.php?group_id=<?php echo $group['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Members
                                </a>
                                <?php if ($is_group_leader): ?>
                                    <a href="?delete_group=<?php echo $group['id']; ?>" class="btn-delete-group" onclick="return confirm('⚠️ WARNING: This will permanently delete this group! Are you sure?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-groups" style="grid-column: 1/-1;">
                    <i class="fas fa-users-slash" style="font-size: 48px; color: #ccc;"></i>
                    <p style="margin-top: 15px;">You are not in any groups yet.</p>
                    <p>Join a group below to get started!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available Groups to Join -->
    <div class="my-groups-section">
        <h2 class="section-title">
            <i class="fas fa-globe"></i> Other Groups (<?php echo $available_groups->num_rows; ?>)
        </h2>
        <div class="groups-grid">
            <?php if ($available_groups && $available_groups->num_rows > 0): ?>
                <?php while ($group = $available_groups->fetch_assoc()): ?>
                    <div class="group-card">
                        <div class="group-header">
                            <span class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></span>
                            <span class="member-count">
                                <i class="fas fa-users"></i> <?php echo $group['member_count']; ?> members
                            </span>
                        </div>
                        <div class="group-body">
                            <div class="leader-info">
                                <i class="fas fa-crown"></i>
                                <span>Leader: <strong><?php echo htmlspecialchars($group['leader_name']); ?></strong></span>
                            </div>
                            <div class="group-actions">
                                <a href="?join=<?php echo $group['id']; ?>" class="btn-join" onclick="return confirm('Join this group?')">
                                    <i class="fas fa-sign-in-alt"></i> Join
                                </a>
                                <a href="view_group.php?group_id=<?php echo $group['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Members
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-groups" style="grid-column: 1/-1;">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745;"></i>
                    <p style="margin-top: 15px;">No more groups to join!</p>
                    <p>You're in all available groups or no new groups exist.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>

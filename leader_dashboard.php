<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if user is actually a leader from database (not from session)
$check_sql = "SELECT is_leader, name FROM users WHERE id = '$user_id'";
$check_result = $conn->query($check_sql);
$user_data = $check_result->fetch_assoc();
$is_leader = $user_data['is_leader'];
$user_name = $user_data['name'];

// Update session to be consistent
$_SESSION['is_leader'] = $is_leader;
$_SESSION['isLeader'] = $is_leader;

if (!$is_leader) {
    header('Location: dashboard.php');
    exit();
}

// Get group_id from session or database
$group_id = $_SESSION['group_id'] ?? null;

if (!$group_id) {
    $group_sql = "SELECT id FROM project_groups WHERE leader_id = '$user_id'";
    $group_result = $conn->query($group_sql);
    if ($group_result && $group_result->num_rows > 0) {
        $group_id = $group_result->fetch_assoc()['id'];
        $_SESSION['group_id'] = $group_id;
    }
}

// If still no group, redirect to create group
if (!$group_id) {
    header('Location: create_group.php');
    exit();
}

// Get group name
$group_sql = "SELECT group_name FROM project_groups WHERE id = $group_id";
$group_result = $conn->query($group_sql);
$group_name = $group_result->fetch_assoc()['group_name'];

// Get statistics
$stats_sql = "SELECT 
               COUNT(*) as total_updates,
               SUM(CASE WHEN leader_status = 'pending' THEN 1 ELSE 0 END) as pending_review,
               SUM(CASE WHEN leader_status = 'approved' THEN 1 ELSE 0 END) as approved,
               SUM(CASE WHEN leader_status = 'rejected' THEN 1 ELSE 0 END) as rejected
               FROM progress_updates WHERE group_id = $group_id";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get member performance
$member_sql = "SELECT u.name, u.id,
               COUNT(pu.id) as total_submissions,
               SUM(CASE WHEN pu.leader_status = 'approved' THEN 1 ELSE 0 END) as approved_submissions,
               SUM(CASE WHEN pu.leader_status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions,
               MAX(pu.created_at) as last_activity
               FROM users u
               LEFT JOIN progress_updates pu ON u.id = pu.user_id AND pu.group_id = $group_id
               WHERE u.id IN (SELECT user_id FROM group_members WHERE group_id = $group_id)
               GROUP BY u.id";
$members = $conn->query($member_sql);

// Get unread count for inbox badge
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
    <title>Leader Dashboard - <?php echo htmlspecialchars($group_name); ?></title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #1a2a4f;
        }

        .stat-label {
            color: #666;
            margin-top: 5px;
        }

        .member-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .member-table th, .member-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .member-table th {
            background: #1a2a4f;
            color: white;
            font-weight: 600;
        }

        .member-table tr:hover {
            background: #f8f9fa;
        }

        .btn-view {
            background: #1a2a4f;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 12px;
        }

        .btn-view:hover {
            background: #2a3a6f;
        }

        .progress-bar {
            width: 100px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #28a745;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .main-content { padding: 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .member-table { font-size: 12px; }
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
        <div class="role">Group Leader 👑</div>
    </div>
    <div class="nav-menu">
        <!-- Dashboard - STAYS on leader dashboard -->
        <a href="leader_dashboard.php" class="nav-item active">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        
        <!-- Group Management -->
        <a href="view_group.php?group_id=<?php echo $group_id; ?>" class="nav-item">
            <i class="fas fa-users"></i> <span>My Group</span>
        </a>
        
        <!-- Task Management - ALL stay in leader context -->
        <a href="assign_tasks.php" class="nav-item">
            <i class="fas fa-tasks"></i> <span>Assign Tasks</span>
        </a>
        <a href="my_tasks.php" class="nav-item">
            <i class="fas fa-list-check"></i> <span>My Tasks</span>
        </a>
        
        <!-- Progress Tracking -->
        <a href="progress_board.php" class="nav-item">
            <i class="fas fa-images"></i> <span>Progress Board</span>
        </a>
        <a href="upload_contribution.php" class="nav-item">
            <i class="fas fa-upload"></i> <span>Upload Contribution</span>
        </a>
        
        <!-- Reports -->
        <a href="report.php" class="nav-item">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
        </a>
        
        <!-- Communication -->
        <a href="inbox.php" class="nav-item">
            <i class="fas fa-envelope"></i> <span>Inbox</span>
            <?php if ($unread_count > 0): ?>
                <span class="inbox-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Group Actions -->
        <a href="join_group.php" class="nav-item">
            <i class="fas fa-layer-group"></i> <span>Available Groups</span>
        </a>
        <a href="create_group.php" class="nav-item">
            <i class="fas fa-plus-circle"></i> <span>Create New Group</span>
        </a>
        
        <!-- Account -->
        <a href="logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-crown"></i> Leader Dashboard</h1>
        <p>Manage and monitor your group: <strong><?php echo htmlspecialchars($group_name); ?></strong></p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_updates'] ?? 0; ?></div>
            <div class="stat-label">Total Submissions</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #ffc107;"><?php echo $stats['pending_review'] ?? 0; ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #28a745;"><?php echo $stats['approved'] ?? 0; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #dc3545;"><?php echo $stats['rejected'] ?? 0; ?></div>
            <div class="stat-label">Needs Changes</div>
        </div>
    </div>

    <!-- Member Performance Table -->
    <h2 style="color: #1a2a4f; margin-bottom: 20px;">
        <i class="fas fa-users"></i> Team Member Performance
    </h2>
    
    <div style="overflow-x: auto;">
        <table class="member-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Submissions</th>
                    <th>Approved</th>
                    <th>Rejected</th>
                    <th>Approval Rate</th>
                    <th>Last Activity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members && $members->num_rows > 0): ?>
                    <?php while ($member = $members->fetch_assoc()): 
                        $rate = $member['total_submissions'] > 0 ? round(($member['approved_submissions'] / $member['total_submissions']) * 100) : 0;
                        $rate_color = $rate >= 70 ? '#28a745' : ($rate >= 40 ? '#ffc107' : '#dc3545');
                    ?>
                        <tr>
                            <td>
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($member['name']); ?>
                            </td>
                            <td><?php echo $member['total_submissions']; ?></td>
                            <td style="color: #28a745;"><?php echo $member['approved_submissions']; ?></td>
                            <td style="color: #dc3545;"><?php echo $member['rejected_submissions']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="color: <?php echo $rate_color; ?>; font-weight: bold;"><?php echo $rate; ?>%</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $rate; ?>%; background: <?php echo $rate_color; ?>;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                if ($member['last_activity']) {
                                    echo date('M d, Y', strtotime($member['last_activity']));
                                } else {
                                    echo 'No activity';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="view_member_progress.php?user_id=<?php echo $member['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <i class="fas fa-users-slash" style="font-size: 48px; color: #ccc;"></i>
                            <p style="margin-top: 15px;">No members found in your group yet.</p>
                            <p>Share the group with students to get them started!</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
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

// Get courses for lecturer (for sidebar display)
$lecturer_courses_count = 0;
if ($is_lecturer) {
    $courses_sql = "SELECT COUNT(*) as count FROM lecturer_courses WHERE lecturer_id = $user_id";
    $courses_result = $conn->query($courses_sql);
    $lecturer_courses_count = $courses_result->fetch_assoc()['count'];
}

// Create messages table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    group_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table);

// Mark message as read
if (isset($_GET['read'])) {
    $message_id = intval($_GET['read']);
    $update_sql = "UPDATE messages SET is_read = 1 WHERE id = $message_id AND to_user_id = $user_id";
    $conn->query($update_sql);
    header('Location: inbox.php');
    exit();
}

// Get all messages for this user (SIMPLIFIED - only messages table)
$messages_sql = "SELECT m.*, 
                 u_from.name as from_name,
                 g.group_name
                 FROM messages m
                 JOIN users u_from ON m.from_user_id = u_from.id
                 JOIN project_groups g ON m.group_id = g.id
                 WHERE m.to_user_id = $user_id
                 ORDER BY m.created_at DESC";
$messages = $conn->query($messages_sql);

// Get unread count
$unread_sql = "SELECT COUNT(*) as unread FROM messages WHERE to_user_id = $user_id AND is_read = 0";
$unread_result = $conn->query($unread_sql);
$unread_count = $unread_result->fetch_assoc()['unread'];

// Get unread message count for inbox badge
$unread_badge = $unread_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - Messages</title>
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

        .messages-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .message-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #1a2a4f;
            transition: all 0.2s;
            cursor: pointer;
        }

        .message-card:hover {
            background: #e9ecef;
        }

        .message-card.unread {
            background: #e8f0fe;
            border-left-color: #28a745;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .message-from {
            font-weight: bold;
            color: #1a2a4f;
        }

        .message-date {
            font-size: 12px;
            color: #666;
        }

        .message-subject {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .message-body {
            color: #666;
            margin-bottom: 10px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .message-group {
            font-size: 12px;
            color: #17a2b8;
        }

        .no-messages {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        @media (max-width: 768px) {
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
        
        <?php if ($is_lecturer): ?>
            <a href="lecturer_courses.php" class="nav-item">
                <i class="fas fa-book"></i> <span>My Courses</span>
                <?php if ($lecturer_courses_count > 0): ?>
                    <span class="inbox-badge"><?php echo $lecturer_courses_count; ?></span>
                <?php endif; ?>
            </a>
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
            <a href="inbox.php" class="nav-item active">
                <i class="fas fa-envelope"></i> <span>Inbox</span>
                <?php if ($unread_badge > 0): ?>
                    <span class="inbox-badge"><?php echo $unread_badge; ?></span>
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
    <div class="page-header">
        <h1><i class="fas fa-envelope"></i> My Inbox</h1>
        <p><?php echo $unread_count; ?> unread message(s)</p>
    </div>

    <div class="messages-container">
        <h2><i class="fas fa-inbox"></i> Messages</h2>
        
        <?php if ($messages && $messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): 
                $is_unread = ($msg['is_read'] == 0);
            ?>
                <div class="message-card <?php echo $is_unread ? 'unread' : ''; ?>" onclick="window.location.href='?read=<?php echo $msg['id']; ?>'">
                    <div class="message-header">
                        <span class="message-from">
                            <i class="fas fa-user"></i> From: <?php echo htmlspecialchars($msg['from_name']); ?>
                        </span>
                        <span class="message-date">
                            <i class="far fa-clock"></i> <?php echo date('F j, Y g:i A', strtotime($msg['created_at'])); ?>
                        </span>
                    </div>
                    <div class="message-subject">
                        <strong>Subject:</strong> <?php echo htmlspecialchars($msg['subject']); ?>
                    </div>
                    <div class="message-body">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>
                    <div class="message-group">
                        <i class="fas fa-users"></i> Group: <?php echo htmlspecialchars($msg['group_name']); ?>
                    </div>
                    <?php if ($is_unread): ?>
                        <div style="margin-top: 8px;">
                            <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 20px; font-size: 10px;">New</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-messages">
                <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                <p style="margin-top: 15px;">No messages yet.</p>
                <p>When your leader sends you a message, it will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
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

// Only leaders can access
if (!$is_leader || $is_lecturer) {
    header("Location: dashboard.php");
    exit();
}

$error_message = "";
$success_message = "";

// Get all groups where user is leader
$all_groups_sql = "SELECT id, group_name FROM groups WHERE leader_id = $user_id ORDER BY group_name";
$all_groups_result = $conn->query($all_groups_sql);
$all_groups = [];
while ($row = $all_groups_result->fetch_assoc()) {
    $all_groups[] = $row;
}

// Get selected group from URL, session, or default to first group
$selected_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : ($_SESSION['active_group_id'] ?? null);

// If no group selected and user has groups, select the first one
if (!$selected_group_id && count($all_groups) > 0) {
    $selected_group_id = $all_groups[0]['id'];
    $_SESSION['active_group_id'] = $selected_group_id;
}

// If still no group, show error
if (!$selected_group_id && count($all_groups) == 0) {
    $error_message = "You haven't created any groups yet. Please create a group first.";
}

// Get current group name
$current_group_name = "";
if ($selected_group_id) {
    $group_name_sql = "SELECT group_name FROM groups WHERE id = $selected_group_id";
    $group_name_result = $conn->query($group_name_sql);
    if ($group_name_result && $group_name_result->num_rows > 0) {
        $current_group_name = $group_name_result->fetch_assoc()['group_name'];
    }
}

// Handle deleting a task
if (isset($_GET['delete_task'])) {
    $task_id = intval($_GET['delete_task']);
    
    $check_leader = "SELECT t.id, g.leader_id 
                     FROM tasks t 
                     JOIN groups g ON t.group_id = g.id 
                     WHERE t.id = $task_id";
    $task_result = $conn->query($check_leader);
    if ($task_result && $task_result->num_rows > 0) {
        $task_data = $task_result->fetch_assoc();
        if ($user_id == $task_data['leader_id']) {
            $conn->query("DELETE FROM tasks WHERE id = $task_id");
            $success_message = "Task deleted successfully!";
        } else {
            $error_message = "You are not authorized to delete this task.";
        }
    } else {
        $error_message = "Task not found.";
    }
    header('Location: assign_tasks.php?group_id=' . $selected_group_id);
    exit();
}

// Handle task assignment
if (isset($_POST['assign'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $assigned_to = intval($_POST['assigned_to']);
    $due_date = $_POST['due_date'];
    $task_group_id = intval($_POST['group_id']);
    $today = date('Y-m-d');
    
    $verify_leader = "SELECT id FROM groups WHERE id = $task_group_id AND leader_id = $user_id";
    $verify_result = $conn->query($verify_leader);
    
    if ($verify_result && $verify_result->num_rows == 0) {
        $error_message = "❌ You are not the leader of this group.";
    } 
    // FIXED: Stronger past date validation
    elseif (empty($due_date)) {
        $error_message = "❌ Please select a due date.";
    }
    elseif (strtotime($due_date) < strtotime($today)) {
        $error_message = "❌ Error: Due date cannot be in the past! Please select today or a future date.";
    } 
    else {
        $sql = "INSERT INTO tasks (group_id, title, description, assigned_to, due_date, status) 
                VALUES ('$task_group_id', '$title', '$description', '$assigned_to', '$due_date', 'Pending')";
        
        if ($conn->query($sql) === TRUE) {
            // Store success message in session before redirect
            $_SESSION['task_success'] = "✅ Task assigned successfully to group: " . $current_group_name;
            
            // Get member name for notification
            $member_sql = "SELECT name FROM users WHERE id = $assigned_to";
            $member_result = $conn->query($member_sql);
            $member_name = $member_result->fetch_assoc()['name'];
            
            // Insert notification for the member
            $notify_sql = "INSERT INTO notifications (user_id, title, message, notification_type) 
                           VALUES ($assigned_to, 'New Task Assigned', 
                                   'You have been assigned a new task: \"$title\" for group: $current_group_name. Due date: $due_date', 
                                   'info')";
            $conn->query($notify_sql);
            
            // Redirect to prevent form resubmission
            header('Location: assign_tasks.php?group_id=' . $task_group_id);
            exit();
        } else {
            $error_message = "Error: " . $conn->error;
        }
    }
}

// FIXED: Check for session success message after redirect
if (isset($_SESSION['task_success'])) {
    $success_message = $_SESSION['task_success'];
    unset($_SESSION['task_success']);
}

// Fetch group members for selected group
$members = null;
if ($selected_group_id) {
    $members = $conn->query("SELECT u.id, u.name FROM users u 
                             JOIN group_members gm ON u.id = gm.user_id 
                             WHERE gm.group_id='$selected_group_id'");
}

// Fetch tasks for selected group
$tasks = null;
if ($selected_group_id) {
    $tasks = $conn->query("SELECT t.*, u.name AS member_name 
                           FROM tasks t 
                           JOIN users u ON t.assigned_to = u.id 
                           WHERE t.group_id='$selected_group_id'
                           ORDER BY 
                               CASE 
                                   WHEN due_date < CURDATE() AND status != 'Completed' THEN 0 
                                   ELSE 1 
                               END,
                               due_date ASC");
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
    <title>Assign Tasks - Task Performance Tracker</title>
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

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
            color: white;
        }

        .page-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .page-header p {
            opacity: 0.9;
        }

        .group-selector {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .group-selector label {
            font-weight: 600;
            color: #1a2a4f;
        }

        .group-selector select {
            padding: 10px 20px;
            border-radius: 10px;
            border: 1px solid #ddd;
            font-size: 1rem;
            min-width: 250px;
            cursor: pointer;
        }

        .group-badge {
            background: #e8f0fe;
            padding: 8px 15px;
            border-radius: 20px;
            color: #1a2a4f;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 35px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .form-card h2 {
            color: #1a2a4f;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group label i {
            color: #667eea;
            margin-right: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* FIXED: Style for disabled past dates */
        .form-group input[type="date"] {
            cursor: pointer;
        }
        
        .form-group input[type="date"]:disabled {
            background: #e9ecef;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .assign-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .assign-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .tasks-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .tasks-card h2 {
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tasks-table-container {
            overflow-x: auto;
        }

        .tasks-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tasks-table th {
            background: #1a2a4f;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }

        .tasks-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .tasks-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-Pending {
            background: #ffc107;
            color: #1a2a4f;
        }

        .status-In-Progress {
            background: #17a2b8;
            color: white;
        }

        .status-Completed {
            background: #28a745;
            color: white;
        }

        .overdue-badge {
            background: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .completed-text {
            color: #28a745;
            font-weight: 500;
        }

        .no-tasks {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        .no-groups-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            text-align: center;
        }

        /* FIXED: JavaScript validation warning style */
        .validation-warning {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .group-selector { flex-direction: column; align-items: stretch; }
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
        <div class="role">Group Leader</div>
    </div>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <?php if (!$is_lecturer): ?>
            <a href="create_group.php" class="nav-item">
                <i class="fas fa-users"></i> <span>Create Group</span>
            </a>
            <a href="join_group.php" class="nav-item">
                <i class="fas fa-layer-group"></i> <span>Available Groups</span>
            </a>
        <?php endif; ?>
        <a href="inbox.php" class="nav-item">
            <i class="fas fa-envelope"></i> <span>Inbox</span>
            <?php if ($unread_count > 0): ?>
                <span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <?php if ($is_leader && !$is_lecturer): ?>
            <a href="assign_tasks.php" class="nav-item active">
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
        <h1><i class="fas fa-tasks"></i> Assign Tasks</h1>
        <p>Create and assign new tasks to your group members</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if (count($all_groups) == 0): ?>
        <div class="no-groups-card">
            <i class="fas fa-users-slash" style="font-size: 64px; color: #ccc;"></i>
            <h3 style="margin-top: 20px; color: #1a2a4f;">No Groups Created Yet</h3>
            <p style="margin-top: 10px; color: #666;">You need to create a group before you can assign tasks.</p>
            <a href="create_group.php" class="assign-btn" style="display: inline-block; margin-top: 20px; text-decoration: none;">
                <i class="fas fa-plus"></i> Create Your First Group
            </a>
        </div>
    <?php else: ?>

    <!-- Group Selector -->
    <div class="group-selector">
        <div>
            <i class="fas fa-layer-group"></i> <strong>Select Group to Manage:</strong>
        </div>
        <select id="groupSelect" onchange="window.location.href='assign_tasks.php?group_id=' + this.value">
            <?php foreach ($all_groups as $group): ?>
                <option value="<?php echo $group['id']; ?>" <?php echo ($selected_group_id == $group['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($group['group_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($selected_group_id): ?>
            <div class="group-badge">
                <i class="fas fa-check-circle" style="color: #28a745;"></i> Currently working on: <strong><?php echo htmlspecialchars($current_group_name); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assign Task Form -->
    <div class="form-card">
        <h2><i class="fas fa-plus-circle"></i> Create New Task for "<?php echo htmlspecialchars($current_group_name); ?>"</h2>
        <form method="POST" id="taskForm">
            <input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Task Title *</label>
                    <input type="text" name="title" id="taskTitle" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Assign To *</label>
                    <select name="assigned_to" id="assignedTo" required>
                        <option value="">Select a member...</option>
                        <?php if ($members && $members->num_rows > 0): ?>
                            <?php while ($row = $members->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>No members in this group yet</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Due Date *</label>
                    <input type="date" name="due_date" id="dueDate" min="<?php echo date('Y-m-d'); ?>" required>
                    <div class="validation-warning" id="dateWarning">
                        <i class="fas fa-exclamation-triangle"></i> Due date cannot be in the past!
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="taskDescription" placeholder="Enter task description here..."></textarea>
                </div>
            </div>
            
            <button type="submit" name="assign" class="assign-btn">
                <i class="fas fa-paper-plane"></i> Assign Task
            </button>
        </form>
    </div>

    <!-- Tasks List -->
    <div class="tasks-card">
        <h2><i class="fas fa-list-check"></i> Tasks in "<?php echo htmlspecialchars($current_group_name); ?>"</h2>
        <div class="tasks-table-container">
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tasks && $tasks->num_rows > 0): ?>
                        <?php while ($task = $tasks->fetch_assoc()): 
                            $today = date('Y-m-d');
                            $is_overdue = ($task['due_date'] < $today && $task['status'] != 'Completed');
                        ?>
                            <tr style="<?php echo $is_overdue ? 'background: #fff3cd;' : ''; ?>">
                                <td><strong><?php echo htmlspecialchars($task['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?><?php echo strlen($task['description']) > 50 ? '...' : ''; ?></td>
                                <td><?php echo htmlspecialchars($task['member_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($task['due_date']); ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="overdue-badge"><i class="fas fa-exclamation-circle"></i> OVERDUE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '-', $task['status']); ?>">
                                        <?php echo $task['status']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($task['status'] == 'Completed'): ?>
                                        <span class="completed-text"><i class="fas fa-check-circle"></i> Completed</span>
                                    <?php endif; ?>
                                    <a href="?delete_task=<?php echo $task['id']; ?>&group_id=<?php echo $selected_group_id; ?>" class="btn-delete" 
                                       onclick="return confirm('Delete this task? This cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-tasks">
                                <i class="fas fa-tasks" style="font-size: 48px; color: #ccc;"></i>
                                <p style="margin-top: 15px;">No tasks assigned yet in this group.</p>
                                <p>Use the form above to assign your first task!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- FIXED: JavaScript for additional client-side validation -->
<script>
    // Prevent manual entry of past dates
    const dateInput = document.getElementById('dueDate');
    const dateWarning = document.getElementById('dateWarning');
    const taskForm = document.getElementById('taskForm');
    
    if (dateInput) {
        // Set min date attribute (already in HTML, but ensure it's enforced)
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
        
        // Additional validation on change
        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            if (selectedDate < today) {
                dateWarning.style.display = 'block';
                this.value = today;
                setTimeout(() => {
                    dateWarning.style.display = 'none';
                }, 3000);
            } else {
                dateWarning.style.display = 'none';
            }
        });
    }
    
    // Form submission validation
    if (taskForm) {
        taskForm.addEventListener('submit', function(e) {
            const title = document.getElementById('taskTitle');
            const assignedTo = document.getElementById('assignedTo');
            const dueDate = document.getElementById('dueDate');
            const today = new Date().toISOString().split('T')[0];
            
            if (!title.value.trim()) {
                e.preventDefault();
                alert('Please enter a task title');
                title.focus();
                return false;
            }
            
            if (!assignedTo.value) {
                e.preventDefault();
                alert('Please select a member to assign this task to');
                assignedTo.focus();
                return false;
            }
            
            if (!dueDate.value) {
                e.preventDefault();
                alert('Please select a due date');
                dueDate.focus();
                return false;
            }
            
            if (dueDate.value < today) {
                e.preventDefault();
                alert('Due date cannot be in the past! Please select today or a future date.');
                dueDate.value = today;
                return false;
            }
            
            return true;
        });
    }
</script>

</body>
</html>
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

// Add progress_percentage column if not exists
$check_column = $conn->query("SHOW COLUMNS FROM tasks LIKE 'progress_percentage'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE tasks ADD COLUMN progress_percentage INT DEFAULT 0");
}

// Get user's tasks
$tasks_sql = "SELECT t.*, g.group_name 
              FROM tasks t
              JOIN project_groups g ON t.group_id = g.id
              WHERE t.assigned_to = $user_id
              ORDER BY 
                  CASE 
                      WHEN t.status = 'Pending' THEN 1
                      WHEN t.status = 'In Progress' THEN 2
                      WHEN t.status = 'Completed' THEN 3
                      ELSE 4
                  END,
                  t.due_date ASC";
$tasks = $conn->query($tasks_sql);

// Handle Start Task (25%)
if (isset($_POST['start_task'])) {
    $task_id = $_POST['task_id'];
    $update_sql = "UPDATE tasks SET status = 'In Progress', progress_percentage = 25 WHERE id = $task_id AND assigned_to = $user_id";
    $conn->query($update_sql);
    
    // Notify leader
    $notify_sql = "INSERT INTO inbox (user_id, sender_id, message, created_at, is_read) 
                   VALUES ($group_id, $user_id, '📌 $user_name has started working on a task.', NOW(), 0)";
    $conn->query($notify_sql);
    
    header('Location: my_tasks.php');
    exit();
}

// Handle In Progress Update (50%)
if (isset($_POST['in_progress_task'])) {
    $task_id = $_POST['task_id'];
    $update_sql = "UPDATE tasks SET status = 'In Progress', progress_percentage = 50 WHERE id = $task_id AND assigned_to = $user_id";
    $conn->query($update_sql);
    
    // Notify leader
    $notify_sql = "INSERT INTO inbox (user_id, sender_id, message, created_at, is_read) 
                   VALUES ($group_id, $user_id, '🔄 $user_name is halfway through the task.', NOW(), 0)";
    $conn->query($notify_sql);
    
    header('Location: my_tasks.php');
    exit();
}

// Handle Complete Task (100%)
if (isset($_POST['complete_task'])) {
    $task_id = $_POST['task_id'];
    $update_sql = "UPDATE tasks SET status = 'Completed', progress_percentage = 100 WHERE id = $task_id AND assigned_to = $user_id";
    $conn->query($update_sql);
    
    // Notify leader
    $notify_sql = "INSERT INTO inbox (user_id, sender_id, message, created_at, is_read) 
                   VALUES ($group_id, $user_id, '✅ $user_name has completed the task!', NOW(), 0)";
    $conn->query($notify_sql);
    
    header('Location: my_tasks.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Task Performance Tracker</title>
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

        /* Tasks Table */
        .tasks-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .tasks-container h2 {
            color: #1a2a4f;
            margin-bottom: 20px;
        }

        .tasks-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }

        .tasks-table th, .tasks-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .tasks-table th {
            background: #1a2a4f;
            color: white;
            font-weight: 600;
        }

        .tasks-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
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

        .btn-start {
            background: #ffc107;
            color: #1a2a4f;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }

        .btn-start:hover {
            background: #e0a800;
        }

        .btn-progress {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }

        .btn-progress:hover {
            background: #138496;
        }

        .btn-complete {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-complete:hover {
            background: #218838;
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

        .progress-cell {
            min-width: 120px;
        }

        .progress-bar-mini {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .progress-fill-mini {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            color: white;
            font-size: 10px;
            line-height: 20px;
            padding-left: 5px;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
            .tasks-table { font-size: 12px; }
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
        <?php if (!$is_lecturer): ?>
            <a href="create_group.php" class="nav-item">
                <i class="fas fa-users"></i> <span>Create Group</span>
            </a>
            <a href="join_group.php" class="nav-item">
                <i class="fas fa-link"></i> <span>Available Groups</span>
            </a>
        <?php endif; ?>
        <?php if ($is_leader && !$is_lecturer): ?>
            <a href="assign_tasks.php" class="nav-item">
                <i class="fas fa-tasks"></i> <span>Assign Tasks</span>
            </a>
        <?php endif; ?>
        <?php if (!$is_lecturer): ?>
            <a href="my_tasks.php" class="nav-item active">
                <i class="fas fa-list-check"></i> <span>My Tasks</span>
            </a>
            <a href="upload_contribution.php" class="nav-item">
                <i class="fas fa-upload"></i> <span>Upload Contribution</span>
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
        <a href="progress_board.php" class="nav-item">
            <i class="fas fa-images"></i> <span>Progress Board</span>
        </a>
        <a href="logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-list-check"></i> My Assigned Tasks</h1>
        <p>Update your progress: Start → In Progress → Complete</p>
    </div>

    <div class="tasks-container">
        <h2><i class="fas fa-tasks"></i> Tasks</h2>
        
        <?php if ($tasks && $tasks->num_rows > 0): ?>
            <div style="overflow-x: auto;">
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Due Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($task = $tasks->fetch_assoc()): 
                            $progress = $task['progress_percentage'] ?? 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                <td><?php echo htmlspecialchars($task['description']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($task['due_date']); ?>
                                    <?php 
                                    $today = date('Y-m-d');
                                    if ($task['due_date'] < $today && $task['status'] != 'Completed'): 
                                    ?>
                                        <span style="color:#dc3545; font-size:11px;"> (OVERDUE)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="progress-cell">
                                    <div class="progress-bar-mini">
                                        <div class="progress-fill-mini" style="width: <?php echo $progress; ?>%;">
                                            <?php if ($progress > 15): ?>
                                                <?php echo $progress; ?>%
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small><?php echo $progress; ?>% complete</small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '-', $task['status']); ?>">
                                        <?php echo $task['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($task['status'] == 'Pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <button type="submit" name="start_task" class="btn-start" 
                                                    onclick="return confirm('Start working on this task? Progress will be set to 25%')">
                                                <i class="fas fa-play"></i> Start (25%)
                                            </button>
                                        </form>
                                    <?php elseif ($task['status'] == 'In Progress' && $progress < 100): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <button type="submit" name="in_progress_task" class="btn-progress" 
                                                    onclick="return confirm('Update progress to 50%?')">
                                                <i class="fas fa-spinner"></i> In Progress (50%)
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <button type="submit" name="complete_task" class="btn-complete" 
                                                    onclick="return confirm('Complete this task? Progress will be set to 100%')">
                                                <i class="fas fa-check"></i> Complete (100%)
                                            </button>
                                        </form>
                                    <?php elseif ($task['status'] == 'Completed'): ?>
                                        <span class="completed-text"><i class="fas fa-check-circle"></i> Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-tasks">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745;"></i>
                <p style="margin-top: 15px;">No tasks assigned to you yet.</p>
                <p>Your leader will assign tasks to you soon!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

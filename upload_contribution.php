<?php
session_start();
include 'db.php';

// Only logged-in members can upload
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

$success_message = "";
$error_message = "";

// Handle upload
if (isset($_POST['upload'])) {
    $task_id = $_POST['task_id'];
    $file_name = $_FILES['contribution']['name'];
    $file_tmp = $_FILES['contribution']['tmp_name'];
    $file_size = $_FILES['contribution']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Create uploads folder if not exists
    $upload_dir = "uploads/contributions/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename to avoid conflicts
    $new_filename = time() . "_" . $user_id . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
    $target_file = $upload_dir . $new_filename;
    
    // Validate file type (allow PDF, DOC, DOCX, JPG, PNG)
    $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
    
    if (!in_array($file_ext, $allowed_types)) {
        $error_message = "Only PDF, DOC, DOCX, JPG, PNG, and ZIP files are allowed.";
    } elseif ($file_size > 10 * 1024 * 1024) { // 10MB limit
        $error_message = "File size too large. Maximum 10MB allowed.";
    } else {
        if (move_uploaded_file($file_tmp, $target_file)) {
            // Save to database
            $sql = "INSERT INTO contributions (task_id, user_id, file_path, original_filename, submitted_at) 
                    VALUES ('$task_id', '$user_id', '$target_file', '$file_name', NOW())";
            
            if ($conn->query($sql) === TRUE) {
                $success_message = "Contribution uploaded successfully!";
                
                // Notify leader
                $task_sql = "SELECT group_id FROM tasks WHERE id = $task_id";
                $task_result = $conn->query($task_sql);
                if ($task_result && $task_result->num_rows > 0) {
                    $group_id = $task_result->fetch_assoc()['group_id'];
                    $leader_sql = "SELECT leader_id FROM project_groups WHERE id = $group_id";
                    $leader_result = $conn->query($leader_sql);
                    if ($leader_result && $leader_result->num_rows > 0) {
                        $leader_id = $leader_result->fetch_assoc()['leader_id'];
                        $notify_sql = "INSERT INTO notifications (user_id, title, message, notification_type) 
                                       VALUES ($leader_id, 'New Contribution', 
                                               '$user_name uploaded a contribution for task ID: $task_id', 'info')";
                        $conn->query($notify_sql);
                    }
                }
            } else {
                $error_message = "Database error: " . $conn->error;
            }
        } else {
            $error_message = "File upload failed. Please try again.";
        }
    }
}

// Get user's tasks for dropdown
$my_tasks = $conn->query("SELECT id, title, due_date, status FROM tasks WHERE assigned_to='$user_id' ORDER BY due_date ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Contribution - Task perfomance tracker</title>
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

        /* Upload Form */
        .upload-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 600px;
        }

        .upload-container h2 {
            color: #1a2a4f;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group select {
            background: white;
            cursor: pointer;
        }

        .btn-upload {
            background: #1a2a4f;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        .btn-upload:hover {
            background: #2a3a6f;
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

        .file-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
            .upload-container { max-width: 100%; }
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
            <a href="my_tasks.php" class="nav-item">
                <i class="fas fa-list-check"></i> <span>My Tasks</span>
            </a>
            <a href="upload_contribution.php" class="nav-item active">
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
        <h1><i class="fas fa-upload"></i> Upload Contribution</h1>
        <p>Submit your work for tasks assigned to you</p>
    </div>

    <div class="upload-container">
        <h2><i class="fas fa-file-alt"></i> Contribution Form</h2>
        
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
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label><i class="fas fa-tasks"></i> Select Task</label>
                <select name="task_id" required>
                    <option value="">-- Select a task --</option>
                    <?php 
                    if ($my_tasks && $my_tasks->num_rows > 0) {
                        while ($task = $my_tasks->fetch_assoc()) {
                            $overdue = ($task['due_date'] < date('Y-m-d') && $task['status'] != 'Completed');
                            $overdue_text = $overdue ? ' (OVERDUE)' : '';
                            echo "<option value='" . $task['id'] . "'>" . htmlspecialchars($task['title']) . " - Due: " . $task['due_date'] . $overdue_text . "</option>";
                        }
                    } else {
                        echo "<option value='' disabled>No tasks assigned to you yet</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-file"></i> Upload File</label>
                <input type="file" name="contribution" required>
            </div>
            
            <button type="submit" name="upload" class="btn-upload">
                <i class="fas fa-paper-plane"></i> Upload Contribution
            </button>
        </form>
    </div>
</div>

</body>
</html>
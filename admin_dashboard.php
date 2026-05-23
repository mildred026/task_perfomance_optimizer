<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if user is admin
$role_sql = "SELECT role, name FROM users WHERE id = '$user_id'";
$role_result = $conn->query($role_sql);
$user_data = $role_result->fetch_assoc();
$user_role = $user_data['role'];
$user_name = $user_data['name'];

if ($user_role != 'admin') {
    header('Location: dashboard.php');
    exit();
}

// ============ HANDLE ACTIONS ============

// Handle user role change
if (isset($_POST['change_role'])) {
    $target_user = intval($_POST['user_id']);
    $new_role = $conn->real_escape_string($_POST['new_role']);
    $conn->query("UPDATE users SET role = '$new_role' WHERE id = $target_user");
    $_SESSION['admin_success'] = "User role updated successfully!";
    header('Location: admin_dashboard.php');
    exit();
}

// Handle user deletion
if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    if ($delete_id != $user_id) {
        $conn->query("DELETE FROM users WHERE id = $delete_id");
        $_SESSION['admin_success'] = "User deleted successfully!";
    } else {
        $_SESSION['admin_error'] = "You cannot delete your own admin account!";
    }
    header('Location: admin_dashboard.php');
    exit();
}

// Handle group deletion
if (isset($_GET['delete_group'])) {
    $delete_group_id = intval($_GET['delete_group']);
    $conn->query("DELETE FROM group_members WHERE group_id = $delete_group_id");
    $conn->query("DELETE FROM tasks WHERE group_id = $delete_group_id");
    $conn->query("DELETE FROM project_groups WHERE id = $delete_group_id");
    $_SESSION['admin_success'] = "Group deleted successfully!";
    header('Location: admin_dashboard.php');
    exit();
}

// Handle course deletion
if (isset($_GET['delete_course'])) {
    $delete_course_id = intval($_GET['delete_course']);
    $conn->query("DELETE FROM lecturer_courses WHERE id = $delete_course_id");
    $_SESSION['admin_success'] = "Course deleted successfully!";
    header('Location: admin_dashboard.php');
    exit();
}

// Handle reset password
if (isset($_GET['reset_password'])) {
    $reset_user_id = intval($_GET['reset_password']);
    $new_password = password_hash('password123', PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password = '$new_password' WHERE id = $reset_user_id");
    $_SESSION['admin_success'] = "Password reset to 'password123' for user ID: $reset_user_id";
    header('Location: admin_dashboard.php');
    exit();
}

// Handle lock/unlock user
if (isset($_GET['toggle_lock'])) {
    $lock_user_id = intval($_GET['toggle_lock']);
    $conn->query("UPDATE users SET is_locked = NOT is_locked WHERE id = $lock_user_id");
    $_SESSION['admin_success'] = "User status toggled!";
    header('Location: admin_dashboard.php');
    exit();
}

// Handle system settings update
if (isset($_POST['update_settings'])) {
    $max_file_size = intval($_POST['max_file_size']);
    $video_check_min = intval($_POST['video_check_min']);
    $video_check_max = intval($_POST['video_check_max']);
    
    $conn->query("UPDATE system_settings SET setting_value = '$max_file_size' WHERE setting_key = 'max_file_size'");
    $conn->query("UPDATE system_settings SET setting_value = '$video_check_min' WHERE setting_key = 'video_check_min'");
    $conn->query("UPDATE system_settings SET setting_value = '$video_check_max' WHERE setting_key = 'video_check_max'");
    
    $_SESSION['admin_success'] = "System settings updated!";
    header('Location: admin_dashboard.php');
    exit();
}

// Get system statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$total_lecturers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer'")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$total_groups = $conn->query("SELECT COUNT(*) as count FROM project_groups")->fetch_assoc()['count'];
$total_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks")->fetch_assoc()['count'];
$total_contributions = $conn->query("SELECT COUNT(*) as count FROM contributions")->fetch_assoc()['count'];
$total_final_reports = $conn->query("SELECT COUNT(*) as count FROM final_reports")->fetch_assoc()['count'];
$total_progress_updates = $conn->query("SELECT COUNT(*) as count FROM progress_updates")->fetch_assoc()['count'];

// Calculate storage usage
$upload_dirs = ['uploads/progress/', 'uploads/contributions/', 'uploads/final_reports/', 'uploads/video_checks/'];
$total_size = 0;
foreach ($upload_dirs as $dir) {
    if (file_exists($dir)) {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $total_size += filesize($file);
            }
        }
    }
}
$storage_used = round($total_size / (1024 * 1024), 2);

// Get all users - FIXED: removed created_at column
$users = $conn->query("SELECT id, name, email, role FROM users ORDER BY id DESC");

// Get all groups
$groups = $conn->query("SELECT g.*, u.name as leader_name, 
                        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                        FROM project_groups g
                        LEFT JOIN users u ON g.leader_id = u.id
                        ORDER BY g.created_at DESC");

// Get all courses
$courses = $conn->query("SELECT lc.*, u.name as lecturer_name 
                         FROM lecturer_courses lc
                         LEFT JOIN users u ON lc.lecturer_id = u.id
                         ORDER BY lc.course_code");

// Get system settings
$settings = [];
$settings_result = $conn->query("SELECT * FROM system_settings");
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} else {
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT
    )");
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
                  ('max_file_size', '10'),
                  ('video_check_min', '15'),
                  ('video_check_max', '30')");
    $settings['max_file_size'] = '10';
    $settings['video_check_min'] = '15';
    $settings['video_check_max'] = '30';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Task Performance Tracker</title>
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

        .nav-item.active {
            background: #2a3a60;
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
            padding: 30px;
            overflow-y: auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 2rem;
            color: #1a2a4f;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1a2a4f;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.8rem;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section-card h2 {
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }

        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .data-table th {
            background: #1a2a4f;
            color: white;
            font-weight: 600;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .role-student { background: #28a745; color: white; }
        .role-leader { background: #ffc107; color: #1a2a4f; }
        .role-lecturer { background: #17a2b8; color: white; }
        .role-admin { background: #dc3545; color: white; }

        .btn-edit, .btn-delete, .btn-reset, .btn-lock, .btn-save {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            margin: 2px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit { background: #ffc107; color: #1a2a4f; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-reset { background: #17a2b8; color: white; }
        .btn-lock { background: #6c757d; color: white; }
        .btn-save { background: #28a745; color: white; }

        .settings-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
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

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .main-content { padding: 20px; }
            .data-table { font-size: 12px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>TPT</h2>
        <p>Admin Panel</p>
    </div>
    <div class="user-info-sidebar">
        <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="role">Administrator</div>
    </div>
    <div class="nav-menu">
        <a href="admin_dashboard.php" class="nav-item active">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <h1 style="color: #1a2a4f; margin-bottom: 20px;">
        <i class="fas fa-user-shield"></i> Admin Dashboard
    </h1>

    <?php if (isset($_SESSION['admin_success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['admin_success']; unset($_SESSION['admin_success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['admin_error']; unset($_SESSION['admin_error']); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="number"><?php echo $total_users; ?></div>
            <div class="label">Total Users</div>
            <small>(<?php echo $total_students; ?> Students, <?php echo $total_lecturers; ?> Lecturers, <?php echo $total_admins; ?> Admins)</small>
        </div>
        <div class="stat-card">
            <i class="fas fa-layer-group"></i>
            <div class="number"><?php echo $total_groups; ?></div>
            <div class="label">Total Groups</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-tasks"></i>
            <div class="number"><?php echo $total_tasks; ?></div>
            <div class="label">Total Tasks</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-upload"></i>
            <div class="number"><?php echo $total_contributions; ?></div>
            <div class="label">Contributions</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-chart-line"></i>
            <div class="number"><?php echo $total_progress_updates; ?></div>
            <div class="label">Progress Updates</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-database"></i>
            <div class="number"><?php echo $storage_used; ?> MB</div>
            <div class="label">Storage Used</div>
        </div>
    </div>

    <!-- User Management Section - FIXED: Removed Joined column -->
    <div class="section-card">
        <h2><i class="fas fa-user-cog"></i> User Management</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): 
                        $role_class = '';
                        if ($user['role'] == 'student') $role_class = 'role-student';
                        elseif ($user['role'] == 'lecturer') $role_class = 'role-lecturer';
                        elseif ($user['role'] == 'admin') $role_class = 'role-admin';
                        else $role_class = 'role-leader';
                    ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="new_role" onchange="this.form.submit()">
                                        <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="lecturer" <?php echo $user['role'] == 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <input type="hidden" name="change_role" value="1">
                                </form>
                            </td>
                            <td>
                                <a href="?reset_password=<?php echo $user['id']; ?>" class="btn-reset" onclick="return confirm('Reset password for this user? New password will be: password123')">
                                    <i class="fas fa-key"></i> Reset Pwd
                                </a>
                                <?php if ($user['id'] != $user_id): ?>
                                    <a href="?delete_user=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Delete this user? This cannot be undone!')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Group Management Section -->
    <div class="section-card">
        <h2><i class="fas fa-layer-group"></i> Group Management</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Group Name</th>
                        <th>Leader</th>
                        <th>Members</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($group = $groups->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $group['id']; ?></td>
                            <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                            <td><?php echo htmlspecialchars($group['leader_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo $group['member_count']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($group['created_at'])); ?></td>
                            <td>
                                <a href="?delete_group=<?php echo $group['id']; ?>" class="btn-delete" onclick="return confirm('Delete this group? All tasks and members will be removed!')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($groups->num_rows == 0): ?>
                        <tr><td colspan="6" style="text-align: center;">No groups found</td赡
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Course Management Section -->
    <div class="section-card">
        <h2><i class="fas fa-book"></i> Course Management</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Lecturer</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $course['id']; ?></td>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($course['lecturer_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo $course['semester']; ?></td>
                            <td><?php echo $course['academic_year']; ?></td>
                            <td>
                                <a href="?delete_course=<?php echo $course['id']; ?>" class="btn-delete" onclick="return confirm('Delete this course? Groups under this course will be affected!')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($courses->num_rows == 0): ?>
                        <tr><td colspan="7" style="text-align: center;">No courses found</td赡
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System Settings Section -->
    <div class="section-card">
        <h2><i class="fas fa-cog"></i> System Settings</h2>
        <form method="POST" class="settings-form">
            <div class="form-group">
                <label><i class="fas fa-file"></i> Max File Size (MB)</label>
                <input type="number" name="max_file_size" value="<?php echo $settings['max_file_size']; ?>" min="1" max="100">
                <small>Maximum size for uploaded files</small>
            </div>
            <div class="form-group">
                <label><i class="fas fa-video"></i> Video Check Min (minutes)</label>
                <input type="number" name="video_check_min" value="<?php echo $settings['video_check_min']; ?>" min="5" max="60">
                <small>Minimum minutes between video checks</small>
            </div>
            <div class="form-group">
                <label><i class="fas fa-video"></i> Video Check Max (minutes)</label>
                <input type="number" name="video_check_max" value="<?php echo $settings['video_check_max']; ?>" min="10" max="120">
                <small>Maximum minutes between video checks</small>
            </div>
            <div class="form-group">
                <button type="submit" name="update_settings" class="btn-save" style="margin-top: 25px;">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
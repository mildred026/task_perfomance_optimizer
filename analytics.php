<?php
session_start();
include 'db.php';

// Protect access - only lecturers can access analytics
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Check if user is lecturer
$role_sql = "SELECT role FROM users WHERE id = '$user_id'";
$role_result = $conn->query($role_sql);
if ($role_result && $role_result->num_rows > 0) {
    $role_row = $role_result->fetch_assoc();
    $is_lecturer = ($role_row['role'] == 'lecturer');
}

// If not lecturer, redirect to dashboard
if (!$is_lecturer) {
    header('Location: dashboard.php');
    exit();
}

// Get selected course from URL
$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Get lecturer's courses for dropdown
$courses_sql = "SELECT * FROM lecturer_courses WHERE lecturer_id = $user_id ORDER BY course_code";
$courses_result = $conn->query($courses_sql);
$lecturer_courses = [];
while ($course = $courses_result->fetch_assoc()) {
    $lecturer_courses[] = $course;
}

// Build SQL conditions based on selected course
$course_condition = "";
if ($selected_course_id > 0) {
    $course_condition = " AND g.course_id = $selected_course_id";
}

// Get all students with TASK COMPLETION (from status) and PROGRESS PERCENTAGE (from the buttons)
$students_sql = "SELECT DISTINCT u.id, u.name, 
                 COUNT(DISTINCT t.id) as total_tasks,
                 SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                 ROUND(AVG(t.progress_percentage), 1) as avg_progress
                 FROM users u
                 LEFT JOIN group_members gm ON u.id = gm.user_id
                 LEFT JOIN groups g ON gm.group_id = g.id
                 LEFT JOIN tasks t ON t.assigned_to = u.id
                 WHERE u.role = 'student'
                 AND g.lecturer_id = $user_id
                 $course_condition
                 GROUP BY u.id
                 ORDER BY u.name";

$students_result = $conn->query($students_sql);

$students_data = [];
$student_names = [];
$student_completion = [];  // Red bar - Completed tasks
$student_progress = [];    // Green bar - Progress percentage from Start/In Progress buttons
$student_tasks_total = 0;
$student_tasks_completed = 0;

while ($student = $students_result->fetch_assoc()) {
    $student_names[] = $student['name'];
    $completion_rate = $student['total_tasks'] > 0 ? round(($student['completed_tasks'] / $student['total_tasks']) * 100) : 0;
    $progress_rate = round($student['avg_progress'] ?? 0, 1);
    
    $student_completion[] = $completion_rate;
    $student_progress[] = $progress_rate;
    $students_data[] = $student;
    $student_tasks_total += $student['total_tasks'];
    $student_tasks_completed += $student['completed_tasks'];
}

// Get progress board data (visual submissions with approval rates)
$progress_sql = "SELECT u.id, u.name,
                 COUNT(DISTINCT pu.id) as total_submissions,
                 SUM(CASE WHEN pu.leader_status = 'approved' THEN 1 ELSE 0 END) as approved_submissions,
                 SUM(CASE WHEN pu.leader_status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions,
                 SUM(CASE WHEN pu.leader_status = 'pending' THEN 1 ELSE 0 END) as pending_submissions
                 FROM users u
                 LEFT JOIN group_members gm ON u.id = gm.user_id
                 LEFT JOIN groups g ON gm.group_id = g.id
                 LEFT JOIN progress_updates pu ON u.id = pu.user_id
                 WHERE u.role = 'student'
                 AND g.lecturer_id = $user_id
                 $course_condition
                 GROUP BY u.id
                 ORDER BY u.name";

$progress_result = $conn->query($progress_sql);
$progress_data = [];
$approval_rates = [];
$student_submissions = [];

while ($progress = $progress_result->fetch_assoc()) {
    $approval_rate = $progress['total_submissions'] > 0 ? round(($progress['approved_submissions'] / $progress['total_submissions']) * 100) : 0;
    $approval_rates[] = $approval_rate;
    $student_submissions[$progress['id']] = $progress;
    $progress_data[] = $progress;
}

// Get group completion statistics
$groups_sql = "SELECT g.group_name, g.id,
               COUNT(DISTINCT t.id) as total_tasks,
               SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
               ROUND(AVG(t.progress_percentage), 1) as avg_progress,
               lc.course_code, lc.course_name,
               COUNT(DISTINCT pu.id) as total_submissions,
               SUM(CASE WHEN pu.leader_status = 'approved' THEN 1 ELSE 0 END) as approved_submissions
               FROM groups g
               LEFT JOIN tasks t ON t.group_id = g.id
               LEFT JOIN lecturer_courses lc ON g.course_id = lc.id
               LEFT JOIN progress_updates pu ON pu.group_id = g.id
               WHERE g.lecturer_id = $user_id
               $course_condition
               GROUP BY g.id
               ORDER BY g.group_name";
$groups_result = $conn->query($groups_sql);

$group_names = [];
$group_completion = [];
$group_progress = [];
$group_approval = [];
$groups_data = [];

while ($group = $groups_result->fetch_assoc()) {
    $group_names[] = $group['group_name'];
    $completion_rate = $group['total_tasks'] > 0 ? round(($group['completed_tasks'] / $group['total_tasks']) * 100) : 0;
    $progress_rate = round($group['avg_progress'] ?? 0, 1);
    $approval_rate = $group['total_submissions'] > 0 ? round(($group['approved_submissions'] / $group['total_submissions']) * 100) : 0;
    
    $group_completion[] = $completion_rate;
    $group_progress[] = $progress_rate;
    $group_approval[] = $approval_rate;
    $groups_data[] = $group;
}

// Overall statistics
$overall_rate = $student_tasks_total > 0 ? round(($student_tasks_completed / $student_tasks_total) * 100) : 0;
$total_students = count($students_data);
$total_groups = count($groups_data);
$total_submissions = array_sum(array_column($progress_data, 'total_submissions'));
$total_approved = array_sum(array_column($progress_data, 'approved_submissions'));
$overall_approval_rate = $total_submissions > 0 ? round(($total_approved / $total_submissions) * 100) : 0;
$overall_progress = $student_tasks_total > 0 ? round(array_sum($student_progress) / count($student_progress)) : 0;

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

// Get course name for display
$selected_course_name = "All My Courses";
if ($selected_course_id > 0) {
    $course_name_sql = "SELECT course_code, course_name FROM lecturer_courses WHERE id = $selected_course_id";
    $course_name_result = $conn->query($course_name_sql);
    if ($course_name_result && $course_name_result->num_rows > 0) {
        $course = $course_name_result->fetch_assoc();
        $selected_course_name = $course['course_code'] . " - " . $course['course_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Task Performance Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

        .course-selector {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .course-selector select {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #ddd;
            font-size: 16px;
            cursor: pointer;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-card p {
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .chart-container h3 {
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .progress-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .progress-container h3 {
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-progress {
            margin-bottom: 20px;
        }

        .student-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .progress-bar-bg {
            background: #e9ecef;
            border-radius: 10px;
            height: 30px;
            overflow: hidden;
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }

        .completion-bar-fill {
            background: linear-gradient(90deg, #ff6384, #dc3545);
        }

        .page-header {
            background: linear-gradient(135deg, #1a2a4f 0%, #2a3a6f 100%);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            color: white;
        }

        .page-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .page-header p {
            opacity: 0.9;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        .filter-info {
            background: #e8f0fe;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .legend-note {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 12px;
            color: #666;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
            .chart-wrapper { height: 300px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
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
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="lecturer_courses.php" class="nav-item">
            <i class="fas fa-book"></i> <span>My Courses</span>
        </a>
        <a href="analytics.php" class="nav-item active">
            <i class="fas fa-chart-pie"></i> <span>Analytics</span>
        </a>
        <a href="video_reviews.php" class="nav-item">
            <i class="fas fa-video"></i> <span>Video Reviews</span>
        </a>
        <a href="report.php" class="nav-item">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
        </a>
        <a href="final_report.php" class="nav-item">
            <i class="fas fa-file-pdf"></i> <span>Final Reports</span>
        </a>
        <a href="inbox.php" class="nav-item">
            <i class="fas fa-envelope"></i> <span>Inbox</span>
            <?php if ($unread_count > 0): ?>
                <span class="inbox-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> Analytics Dashboard</h1>
        <p>Visual overview of student progress - Green = Current Progress, Red = Completed Tasks</p>
    </div>

    <!-- Course Selector Dropdown -->
    <div class="course-selector">
        <select id="courseSelect" onchange="window.location.href='?course_id=' + this.value">
            <option value="0">-- All My Courses --</option>
            <?php foreach ($lecturer_courses as $course): ?>
                <option value="<?php echo $course['id']; ?>" <?php echo ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'] . ' (' . $course['semester'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Display current filter info -->
    <div class="filter-info">
        <i class="fas fa-info-circle"></i> Showing analytics for: 
        <strong><?php echo $selected_course_name; ?></strong>
        <?php if ($selected_course_id > 0): ?>
            <a href="analytics.php" style="margin-left: 10px; color: #667eea;">Clear Filter</a>
        <?php endif; ?>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <h3><?php echo $total_students; ?></h3>
            <p>Total Students</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-layer-group"></i>
            <h3><?php echo $total_groups; ?></h3>
            <p>Total Groups</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-tasks"></i>
            <h3><?php echo $student_tasks_total; ?></h3>
            <p>Tasks Assigned</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-chart-line" style="color: #28a745;"></i>
            <h3><?php echo $overall_progress; ?>%</h3>
            <p>Average Progress</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <h3><?php echo $overall_rate; ?>%</h3>
            <p>Task Completion Rate</p>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
            <i class="fas fa-images"></i>
            <h3><?php echo $total_submissions; ?></h3>
            <p>Progress Submissions</p>
        </div>
    </div>

    <!-- Bar Chart: Student Performance - 3 BARS -->
    <?php if (count($student_names) > 0): ?>
    <div class="chart-container">
        <h3><i class="fas fa-chart-bar"></i> Student Performance Overview</h3>
        <div class="chart-wrapper">
            <canvas id="studentChart"></canvas>
        </div>
        <div class="legend-note">
            <i class="fas fa-info-circle"></i> 
            <span style="color: #28a745;">🟢 Green bars = Current Progress (%)</span> | 
            <span style="color: #ff6384;">🔴 Red bars = Tasks Completed (%)</span> |
            <span style="color: #36a2eb;">🔵 Blue bars = Progress Board Approval (%)</span>
        </div>
    </div>
    <?php else: ?>
    <div class="chart-container">
        <div class="no-data">
            <i class="fas fa-users-slash" style="font-size: 48px; color: #ccc;"></i>
            <p style="margin-top: 15px;">No students found for <?php echo strtolower($selected_course_name); ?>.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bar Chart: Group Performance -->
    <?php if (count($group_names) > 0): ?>
    <div class="chart-container">
        <h3><i class="fas fa-chart-bar"></i> Group Performance Overview</h3>
        <div class="chart-wrapper">
            <canvas id="groupChart"></canvas>
        </div>
        <div class="legend-note">
            <i class="fas fa-info-circle"></i> 
            <span style="color: #28a745;">🟢 Green bars = Group Progress (%)</span> | 
            <span style="color: #ff6384;">🔴 Red bars = Tasks Completed (%)</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Progress Bars for Each Student -->
    <?php if (count($students_data) > 0): ?>
    <div class="progress-container">
        <h3><i class="fas fa-user-graduate"></i> Individual Student Detailed Progress</h3>
        <?php foreach ($students_data as $index => $student): 
            $completion = $student_completion[$index] ?? 0;
            $progress = $student_progress[$index] ?? 0;
            $approval = isset($student_submissions[$student['id']]) ? 
                ($student_submissions[$student['id']]['total_submissions'] > 0 ? 
                    round(($student_submissions[$student['id']]['approved_submissions'] / $student_submissions[$student['id']]['total_submissions']) * 100) : 0) : 0;
            $submissions_count = isset($student_submissions[$student['id']]) ? $student_submissions[$student['id']]['total_submissions'] : 0;
            $pending_count = isset($student_submissions[$student['id']]) ? $student_submissions[$student['id']]['pending_submissions'] : 0;
        ?>
        <div class="student-progress">
            <div class="student-name">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($student['name']); ?></span>
                <span>
                    🟢 Progress: <?php echo $progress; ?>% | 
                    🔴 Completed: <?php echo $completion; ?>% | 
                    📸 Submissions: <?php echo $submissions_count; ?> (<?php echo $approval; ?>% approved)
                    <?php if ($pending_count > 0): ?>
                        <span style="color: #ffc107;"> (<?php echo $pending_count; ?> pending)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div style="margin-bottom: 5px;">
                <small style="color: #28a745;">Current Progress (from Start/In Progress buttons)</small>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%;">
                        <?php if ($progress > 15): ?><?php echo $progress; ?>%<?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="margin-bottom: 5px;">
                <small style="color: #ff6384;">Tasks Completed</small>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill completion-bar-fill" style="width: <?php echo $completion; ?>%;">
                        <?php if ($completion > 15): ?><?php echo $completion; ?>%<?php endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <small style="color: #17a2b8;">Progress Board Approval Rate</small>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?php echo $approval; ?>%; background: linear-gradient(90deg, #17a2b8, #6f42c1);">
                        <?php if ($approval > 15): ?><?php echo $approval; ?>%<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Combined Bar Chart for Students - 3 datasets
<?php if (count($student_names) > 0): ?>
const studentCtx = document.getElementById('studentChart').getContext('2d');
new Chart(studentCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($student_names); ?>,
        datasets: [
            {
                label: 'Current Progress (%) - From Start/In Progress Buttons',
                data: <?php echo json_encode($student_progress); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1,
                borderRadius: 10
            },
            {
                label: 'Tasks Completed (%)',
                data: <?php echo json_encode($student_completion); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1,
                borderRadius: 10
            },
            {
                label: 'Progress Board Approval Rate (%)',
                data: <?php echo json_encode($approval_rates); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                borderRadius: 10
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Percentage (%)'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Students'
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw + '%';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Combined Bar Chart for Groups
<?php if (count($group_names) > 0): ?>
const groupCtx = document.getElementById('groupChart').getContext('2d');
new Chart(groupCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($group_names); ?>,
        datasets: [
            {
                label: 'Group Progress (%)',
                data: <?php echo json_encode($group_progress); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1,
                borderRadius: 10
            },
            {
                label: 'Task Completion Rate (%)',
                data: <?php echo json_encode($group_completion); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1,
                borderRadius: 10
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Percentage (%)'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Groups'
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw + '%';
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>
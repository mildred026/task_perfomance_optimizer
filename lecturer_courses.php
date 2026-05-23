<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Check if user is lecturer
$role_sql = "SELECT role FROM users WHERE id = '$user_id'";
$role_result = $conn->query($role_sql);
$user_role = $role_result->fetch_assoc()['role'];

if ($user_role != 'lecturer') {
    header('Location: dashboard.php');
    exit();
}

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

// Handle course creation
if (isset($_POST['create_course'])) {
    $course_code = strtoupper($conn->real_escape_string($_POST['course_code']));
    $course_name = $conn->real_escape_string($_POST['course_name']);
    $semester = $conn->real_escape_string($_POST['semester']);
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    
    $insert_sql = "INSERT INTO lecturer_courses (lecturer_id, course_code, course_name, semester, academic_year) 
                   VALUES ('$user_id', '$course_code', '$course_name', '$semester', '$academic_year')";
    
    if ($conn->query($insert_sql)) {
        $_SESSION['success'] = "Course added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add course: " . $conn->error;
    }
    header('Location: lecturer_courses.php');
    exit();
}

// Handle course deletion
if (isset($_GET['delete_course'])) {
    $course_id = intval($_GET['delete_course']);
    $conn->query("DELETE FROM lecturer_courses WHERE id = $course_id AND lecturer_id = $user_id");
    header('Location: lecturer_courses.php');
    exit();
}

// Get lecturer's courses
$courses_sql = "SELECT * FROM lecturer_courses WHERE lecturer_id = $user_id ORDER BY course_code";
$courses = $conn->query($courses_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Lecturer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }
        
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
            margin-left: 0px; 
            padding: 30px; 
        }
        .container { 
            max-width: 1000px; 
            margin: auto; 
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            color: white;
        }
        .page-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        .form-card, .courses-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .form-card h2, .courses-card h2 {
            color: #1a2a4f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .btn-create {
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
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .course-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .course-code { 
            font-weight: bold; 
            color: #667eea; 
            font-size: 1.1rem; 
        }
        .course-name { 
            font-size: 1rem; 
            color: #333; 
        }
        .course-meta { 
            font-size: 0.8rem; 
            color: #666; 
            margin-top: 5px; 
        }
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
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
        
        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .sidebar-header p, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { margin-left: 80px; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
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
        <a href="lecturer_courses.php" class="nav-item active">
            <i class="fas fa-book"></i> <span>My Courses</span>
        </a>
        <a href="analytics.php" class="nav-item">
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
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book"></i> My Courses / Assignments</h1>
            <p>Add the courses you are teaching. Groups will select which course they belong to.</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Add New Course Form -->
        <div class="form-card">
            <h2><i class="fas fa-plus-circle"></i> Add New Course</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course Code *</label>
                        <input type="text" name="course_code" required>
                    </div>
                    <div class="form-group">
                        <label>Academic Year *</label>
                        <input type="text" name="academic_year" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Course Name *</label>
                        <input type="text" name="course_name" required>
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester">
                            <option value="Semester 1">Semester 1</option>
                            <option value="Semester 2">Semester 2</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="create_course" class="btn-create">
                    <i class="fas fa-plus"></i> Add Course
                </button>
            </form>
        </div>
        
        <!-- My Courses List -->
        <div class="courses-card">
            <h2><i class="fas fa-list"></i> My Courses (<?php echo $courses->num_rows; ?>)</h2>
            <?php if ($courses && $courses->num_rows > 0): ?>
                <?php while ($course = $courses->fetch_assoc()): ?>
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
                        <a href="?delete_course=<?php echo $course['id']; ?>" class="btn-delete" onclick="return confirm('Delete this course? This will also delete associated groups and tasks!')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-book" style="font-size: 48px;"></i>
                    <p style="margin-top: 15px;">You haven't added any courses yet.</p>
                    <p>Use the form above to add your first course!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
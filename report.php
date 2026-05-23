<?php
session_start();
include 'db.php';

// Protect access - only leaders and lecturers can access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_leader = isset($_SESSION['is_leader']) ? $_SESSION['is_leader'] : 0;
$user_name = $_SESSION['user_name'];

// Check if user is lecturer
$is_lecturer = false;
$role_sql = "SELECT role FROM users WHERE id = '$user_id'";
$role_result = $conn->query($role_sql);
if ($role_result && $role_result->num_rows > 0) {
    $role_row = $role_result->fetch_assoc();
    $is_lecturer = ($role_row['role'] == 'lecturer');
}

// If student (not leader and not lecturer), redirect to dashboard
if ($is_leader != 1 && !$is_lecturer) {
    header('Location: dashboard.php');
    exit();
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

// ============ FOR LECTURERS: GET SELECTED COURSE ============
$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Helper function to find file path
function findFilePath($stored_path) {
    $possible_paths = [
        $stored_path,
        "uploads/contributions/" . basename($stored_path),
        "uploads/" . basename($stored_path),
        __DIR__ . "/" . $stored_path,
        __DIR__ . "/uploads/contributions/" . basename($stored_path),
        __DIR__ . "/uploads/" . basename($stored_path)
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return null;
}

// Handle individual contribution DOWNLOAD (forces download)
if (isset($_GET['download_contribution'])) {
    $file_id = intval($_GET['download_contribution']);
    $file_sql = "SELECT c.file_path, c.task_id, t.group_id, t.title 
                 FROM contributions c 
                 JOIN tasks t ON c.task_id = t.id 
                 WHERE c.id = $file_id";
    $file_result = $conn->query($file_sql);
    
    if ($file_result && $file_result->num_rows > 0) {
        $file = $file_result->fetch_assoc();
        $group_id = $file['group_id'];
        
        $has_permission = false;
        if ($is_lecturer) {
            $has_permission = true;
        } else if ($is_leader == 1) {
            $check_leader = $conn->query("SELECT id FROM project_groups WHERE id = $group_id AND leader_id = $user_id");
            if ($check_leader && $check_leader->num_rows > 0) {
                $has_permission = true;
            }
        }
        
        if ($has_permission) {
            $file_path = findFilePath($file['file_path']);
            if ($file_path && file_exists($file_path)) {
                // FORCE DOWNLOAD
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
                header('Content-Length: ' . filesize($file_path));
                header('Cache-Control: no-cache, must-revalidate');
                readfile($file_path);
                exit();
            }
        }
    }
    exit();
}

// Handle VIEW file (displays in browser)
if (isset($_GET['view_file'])) {
    $file_id = intval($_GET['view_file']);
    $file_sql = "SELECT c.file_path, t.group_id FROM contributions c JOIN tasks t ON c.task_id = t.id WHERE c.id = $file_id";
    $file_result = $conn->query($file_sql);
    
    if ($file_result && $file_result->num_rows > 0) {
        $file = $file_result->fetch_assoc();
        $group_id = $file['group_id'];
        
        $has_permission = false;
        if ($is_lecturer) {
            $has_permission = true;
        } else if ($is_leader == 1) {
            $check_leader = $conn->query("SELECT id FROM project_groups WHERE id = $group_id AND leader_id = $user_id");
            if ($check_leader && $check_leader->num_rows > 0) {
                $has_permission = true;
            }
        }
        
        if ($has_permission) {
            $file_path = findFilePath($file['file_path']);
            if ($file_path && file_exists($file_path)) {
                // Get file extension
                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                
                // Set proper content type for viewing
                $mime_types = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'txt' => 'text/plain',
                    'html' => 'text/html',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ];
                
                $content_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';
                
                // DISPLAY IN BROWSER - NOT DOWNLOAD
                header('Content-Type: ' . $content_type);
                header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
                header('Cache-Control: public, max-age=3600');
                
                // For PDF files, also set these headers
                if ($ext == 'pdf') {
                    header('Accept-Ranges: bytes');
                }
                
                readfile($file_path);
                exit();
            } else {
                echo "File not found: " . htmlspecialchars($file['file_path']);
                exit();
            }
        }
    }
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports - Task Performance Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .app-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px;
            background: #1a2a4f;
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-logo h2 { font-size: 1.8rem; margin-bottom: 5px; color: white; }
        .sidebar-logo p { font-size: 0.75rem; opacity: 0.7; color: white; }
        .user-info-sidebar {
            padding: 16px 24px;
            background: #0f1e3a;
            margin: 0 16px 20px 16px;
            border-radius: 12px;
        }
        .user-info-sidebar .name { font-weight: 700; font-size: 1rem; color: white; }
        .user-info-sidebar .role { font-size: 0.7rem; color: #a8b8d8; margin-top: 4px; }
        .sidebar-nav { display: flex; flex-direction: column; gap: 4px; padding: 0 16px; }
        .sidebar-nav a {
            color: #e0e6f0;
            text-decoration: none;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.95rem;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover { background: #2a3a60; color: white; }
        .sidebar-nav a.active { background: #2a3a60; color: white; }
        .sidebar-nav a i { width: 24px; font-size: 1.1rem; }
        .logout-item { margin-top: auto; margin-bottom: 30px; border-top: 1px solid #2a3a5f; padding-top: 16px; }
        .main-content { flex: 1; margin-left: 280px; padding: 30px; }
        .container { max-width: 1200px; margin: auto; }
        h2 { text-align: center; color: #1a2a4f; margin-bottom: 30px; font-size: 2em; }
        
        .course-selector {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .course-selector h3 {
            color: #1a2a4f;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .course-selector select {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #ddd;
            font-size: 16px;
            cursor: pointer;
        }
        
        .group-card { background: white; border-radius: 15px; margin-bottom: 30px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .group-title { background: #1a2a4f; color: white; padding: 15px 20px; }
        .group-title h3 { margin: 0; font-size: 1.3em; }
        .section { padding: 20px; }
        .section h4 { color: #1a2a4f; margin-bottom: 15px; font-size: 1.1em; border-left: 4px solid #4a90e2; padding-left: 12px; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table thead tr { background: #1a2a4f; color: white; }
        .data-table th { padding: 12px 15px; text-align: left; font-size: 14px; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #e9ecef; }
        .data-table tbody tr:hover { background-color: #e8f0fe; }
        .download-btn { background: #28a745; color: white; padding: 6px 12px; border-radius: 20px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .download-btn:hover { background: #218838; }
        .view-link { background: #4a90e2; color: white; padding: 5px 12px; border-radius: 20px; text-decoration: none; font-size: 12px; }
        .view-link:hover { background: #357abd; }
        .no-data { text-align: center; color: #999; padding: 20px; font-style: italic; }
        .lecturer-badge { background: #17a2b8; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: inline-block; margin-left: 10px; }
        .btn-upload-pink {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff4d7a 100%);
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(255, 77, 122, 0.3);
        }
        .btn-upload-pink:hover {
            background: linear-gradient(135deg, #ff4d7a 0%, #e63e68 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 77, 122, 0.4);
        }
        @media (max-width: 768px) { .sidebar { width: 80px; } .sidebar-nav span { display: none; } .main-content { margin-left: 80px; } }
    </style>
</head>
<body>
<div class="app-container">
    <div class="sidebar">
        <div class="sidebar-logo">
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
        <div class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            
            <?php if ($is_lecturer): ?>
                <a href="analytics.php"><i class="fas fa-chart-pie"></i><span>Analytics</span></a>
                <a href="video_reviews.php"><i class="fas fa-video"></i><span>Video Reviews</span></a>
            <?php endif; ?>
            
            <?php if ($is_leader == 1 || $is_lecturer): ?>
                <a href="report.php" class="active"><i class="fas fa-chart-line"></i><span>Reports</span></a>
            <?php endif; ?>
            
            <?php if ($is_lecturer): ?>
                <a href="final_report.php"><i class="fas fa-file-pdf"></i><span>Final Reports</span></a>
            <?php endif; ?>
            
            <?php if (!$is_lecturer): ?>
                <a href="progress_board.php"><i class="fas fa-images"></i><span>Progress Board</span></a>
            <?php endif; ?>
            
            <?php if ($is_leader == 1 && !$is_lecturer): ?>
                <a href="create_group.php"><i class="fas fa-users"></i><span>Create Group</span></a>
            <?php endif; ?>
            
            <?php if (!$is_lecturer): ?>
                <a href="join_group.php"><i class="fas fa-layer-group"></i><span>Available Groups</span></a>
            <?php endif; ?>
            
            <?php if (!$is_lecturer): ?>
                <a href="inbox.php"><i class="fas fa-envelope"></i><span>Inbox</span>
                    <?php if ($unread_count > 0): ?>
                        <span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            
            <?php if ($is_leader == 1 && !$is_lecturer): ?>
                <a href="assign_tasks.php"><i class="fas fa-tasks"></i><span>Assign Tasks</span></a>
            <?php endif; ?>
            
            <?php if (!$is_lecturer): ?>
                <a href="my_tasks.php"><i class="fas fa-list-check"></i><span>My Tasks</span></a>
            <?php endif; ?>
            
            <?php if (!$is_lecturer): ?>
                <a href="upload_contribution.php"><i class="fas fa-upload"></i><span>Upload Contribution</span></a>
            <?php endif; ?>
            
            <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="container">
            <h2><i class="fas fa-chart-line"></i> Group Reports Dashboard</h2>
            
            <?php
            // ============ COURSE SELECTOR FOR LECTURERS ============
            if ($is_lecturer):
                $lecturer_courses = $conn->query("SELECT * FROM lecturer_courses WHERE lecturer_id = $user_id ORDER BY course_code");
            ?>
            <div class="course-selector">
                <h3><i class="fas fa-filter"></i> Select Course / Assignment</h3>
                <select id="courseSelect" onchange="window.location.href='?course_id=' + this.value">
                    <option value="0">-- All My Courses --</option>
                    <?php while ($course = $lecturer_courses->fetch_assoc()): 
                        $selected = ($selected_course_id == $course['id']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'] . ' (' . $course['semester'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php
            // Get groups based on role and selected course
            if ($is_lecturer) {
                if ($selected_course_id > 0) {
                    $groups_sql = "SELECT * FROM project_groups WHERE course_id = $selected_course_id ORDER BY group_name";
                } else {
                    $groups_sql = "SELECT * FROM project_groups WHERE course_id IN (SELECT id FROM lecturer_courses WHERE lecturer_id = $user_id) ORDER BY group_name";
                }
                $groups = $conn->query($groups_sql);
                
                if ($groups->num_rows > 0) {
                    echo '<div style="background: #17a2b8; color: white; padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                            <i class="fas fa-chalkboard-teacher"></i> Lecturer Mode - Viewing ' . ($selected_course_id > 0 ? 'Selected Course' : 'All My Courses') . '
                          </div>';
                } else {
                    echo '<div style="background: #fff3cd; color: #856404; padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                            <i class="fas fa-info-circle"></i> No groups found for ' . ($selected_course_id > 0 ? 'this course' : 'your courses') . '.
                          </div>';
                }
            } else {
                $groups_sql = "SELECT * FROM project_groups WHERE leader_id = $user_id ORDER BY id";
                $groups = $conn->query($groups_sql);
            }
            
            if ($groups && $groups->num_rows > 0) {
                while ($group = $groups->fetch_assoc()) {
                    echo "<div class='group-card'>";
                    echo "<div class='group-title'><h3><i class='fas fa-users'></i> " . htmlspecialchars($group['group_name']);
                    if ($is_lecturer) {
                        echo "<span class='lecturer-badge'><i class='fas fa-chalkboard-teacher'></i> Lecturer View</span>";
                    }
                    echo "</h3></div>";
                    echo "<div class='section'>";
                    
                    // ============ SECTION 1: GROUP MEMBERS ============
                    $members = $conn->query("SELECT u.name, u.email FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = " . $group['id']);
                    
                    echo "<h4><i class='fas fa-user-friends'></i> Group Members</h4>";
                    echo "<table class='data-table'>";
                    echo "<thead><tr><th>Name</th><th>Email</th></tr></thead>";
                    echo "<tbody>";
                    
                    if ($members && $members->num_rows > 0) {
                        while ($member = $members->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><i class='fas fa-user'></i> " . htmlspecialchars($member['name']) . "</td>";
                            echo "<td><i class='fas fa-envelope'></i> " . htmlspecialchars($member['email']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2' class='no-data'>No members found</td赡";
                    }
                    echo "</tbody></table>";
                    
                    // ============ SECTION 2: CONTRIBUTIONS ============
                    $contributions = $conn->query("SELECT c.id, t.title, u.name AS member_name, c.submitted_at, c.file_path
                                                   FROM contributions c
                                                   JOIN tasks t ON c.task_id = t.id
                                                   JOIN users u ON c.user_id = u.id
                                                   WHERE t.group_id = " . $group['id'] . "
                                                   ORDER BY c.submitted_at DESC");
                    
                    echo "<h4><i class='fas fa-file-alt'></i> Contributions</h4>";
                    echo "<table class='data-table'>";
                    echo "<thead><tr><th>Task</th><th>Member</th><th>Submitted</th><th>View</th><th>Download</th></thead>";
                    echo "<tbody>";
                    
                    if ($contributions && $contributions->num_rows > 0) {
                        while ($c = $contributions->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><i class='fas fa-tag'></i> " . htmlspecialchars($c['title']) . "</td>";
                            echo "<td><i class='fas fa-user'></i> " . htmlspecialchars($c['member_name']) . "</td>";
                            echo "<td><i class='fas fa-calendar'></i> " . htmlspecialchars($c['submitted_at']) . "</td>";
                            echo "<td><a href='?view_file=" . $c['id'] . "' target='_blank' class='view-link'><i class='fas fa-eye'></i> View</a></td>";
                            echo "<td><a href='?download_contribution=" . $c['id'] . "' class='download-btn'><i class='fas fa-download'></i> Download</a></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='no-data'>No contributions yet</td赡";
                    }
                    echo "</tbody></table>";
                    
                    // ============ SECTION 3: UPLOAD BUTTON (Only for leaders) ============
                    if ($is_leader == 1 && !$is_lecturer && $group['leader_id'] == $user_id) {
                        echo "<div style='margin-top: 30px; text-align: center;'>";
                        echo "<a href='upload_final_report.php?group_id=" . $group['id'] . "' class='btn-upload-pink'>";
                        echo "<i class='fas fa-cloud-upload-alt'></i> Upload Combined Report to Lecturer";
                        echo "</a>";
                        echo "</div>";
                    }
                    
                    echo "</div></div>";
                }
            } else if (!$is_lecturer) {
                echo "<div class='group-card'><div class='section'><p class='no-data'>You are not the leader of any group. <a href='create_group.php'>Create a group</a> first!</p></div></div>";
            }
            ?>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
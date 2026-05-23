<?php
session_start();
include 'db.php';

// Only leaders can access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_leader = isset($_SESSION['is_leader']) ? $_SESSION['is_leader'] : 0;
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

// Verify user is leader
if ($is_leader != 1) {
    header('Location: dashboard.php');
    exit();
}

// Get group info
$group_sql = "SELECT group_name FROM project_groups WHERE id = $group_id AND leader_id = $user_id";
$group_result = $conn->query($group_sql);
$group = $group_result->fetch_assoc();

if (!$group) {
    header('Location: report.php');
    exit();
}

$upload_success = false;
$upload_error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['final_report'])) {
    $file = $_FILES['final_report'];
    $original_filename = basename($file['name']);
    $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    
    // Allowed file types
    $allowed = ['pdf', 'doc', 'docx', 'zip'];
    
    if ($file['error'] == 0) {
        if (in_array($file_ext, $allowed)) {
            // Create upload directory if it doesn't exist
            $upload_dir = __DIR__ . '/uploads/final_reports/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Create unique filename
            $new_filename = 'final_report_group_' . $group_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Create table if not exists
                $create_table = "CREATE TABLE IF NOT EXISTS final_reports (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    original_filename VARCHAR(255) NOT NULL,
                    uploaded_by INT NOT NULL,
                    uploaded_at DATETIME NOT NULL,
                    status VARCHAR(50) DEFAULT 'submitted'
                )";
                $conn->query($create_table);
                
                $stmt = $conn->prepare("INSERT INTO final_reports (group_id, file_path, original_filename, uploaded_by, uploaded_at, status) VALUES (?, ?, ?, ?, NOW(), 'submitted')");
                $stmt->bind_param("issi", $group_id, $new_filename, $original_filename, $user_id);
                
                if ($stmt->execute()) {
                    $upload_success = true;
                } else {
                    $upload_error = "Database error: " . $conn->error;
                }
                $stmt->close();
            } else {
                $upload_error = "Failed to upload file. Please check folder permissions.";
            }
        } else {
            $upload_error = "Only PDF, DOC, DOCX, and ZIP files are allowed.";
        }
    } else {
        $upload_error = "Error uploading file. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Final Report</title>
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
            min-height: 100vh;
        }
        
        /* Layout with sidebar */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Navigation - Dark Blue */
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
        
        .sidebar-logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-logo h2 {
            font-size: 1.5em;
            margin-bottom: 5px;
            color: white;
        }
        
        .sidebar-logo p {
            font-size: 0.8em;
            opacity: 0.7;
            color: white;
        }
        
        .sidebar-nav {
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-nav a {
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #4a90e2;
            padding-left: 30px;
        }
        
        .sidebar-nav .nav-icon {
            font-size: 1.2em;
        }
        
        .sidebar-nav .nav-text {
            flex: 1;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .upload-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .upload-icon {
            font-size: 80px;
            color: #ff4d7a;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #1a2a4f;
            margin-bottom: 10px;
        }
        
        .group-name {
            color: #ff4d7a;
            font-size: 1.2em;
            margin-bottom: 30px;
            padding: 10px;
            background: #fff0f3;
            border-radius: 10px;
        }
        
        .upload-area {
            border: 2px dashed #ff4d7a;
            border-radius: 15px;
            padding: 40px;
            margin: 20px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fffafc;
        }
        
        .upload-area:hover {
            background: #fff0f3;
            border-color: #e63e68;
        }
        
        .upload-area i {
            font-size: 50px;
            color: #ff4d7a;
            margin-bottom: 15px;
        }
        
        .upload-area p {
            color: #666;
            margin-top: 10px;
        }
        
        .file-info {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .selected-file {
            color: #28a745;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff4d7a 100%);
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(255, 77, 122, 0.3);
        }
        
        .btn-submit:hover {
            background: linear-gradient(135deg, #ff4d7a 0%, #e63e68 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 77, 122, 0.4);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            .sidebar-nav .nav-text {
                display: none;
            }
            .sidebar-logo h2, .sidebar-logo p {
                display: none;
            }
            .main-content {
                margin-left: 80px;
                padding: 20px;
            }
            .upload-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Left Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>GCS</h2>
            <p>Group System</p>
        </div>
        
        <div class="sidebar-nav">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt nav-icon"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="create_group.php">
                <i class="fas fa-users nav-icon"></i>
                <span class="nav-text">Create Group</span>
            </a>
            <a href="join_group.php">
                <i class="fas fa-link nav-icon"></i>
                <span class="nav-text">Available Groups</span>
            </a>
            <a href="assign_tasks.php">
                <i class="fas fa-tasks nav-icon"></i>
                <span class="nav-text">Assign Tasks</span>
            </a>
            <a href="my_tasks.php">
                <i class="fas fa-check-circle nav-icon"></i>
                <span class="nav-text">My Tasks</span>
            </a>
            <a href="report.php">
                <i class="fas fa-chart-line nav-icon"></i>
                <span class="nav-text">Reports</span>
            </a>
            <a href="final_report.php">
                <i class="fas fa-file-pdf nav-icon"></i>
                <span class="nav-text">Final Reports</span>
            </a>
            <a href="upload_contribution.php">
                <i class="fas fa-upload nav-icon"></i>
                <span class="nav-text">Upload</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt nav-icon"></i>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="upload-card">
            <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            
            <h2>Upload Final Report</h2>
            <div class="group-name">
                <i class="fas fa-users"></i> <?php echo htmlspecialchars($group['group_name']); ?>
            </div>
            
            <?php if ($upload_success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> Final report uploaded successfully!
                    <br><br>
                    The lecturer can now view your group's final report.
                </div>
                <a href="report.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
            <?php else: ?>
                
                <?php if ($upload_error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $upload_error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-file-pdf"></i>
                        <p><strong>Click to select your combined report</strong></p>
                        <p class="file-info">Supported formats: PDF, DOC, DOCX, ZIP</p>
                        <div id="fileName" class="selected-file"></div>
                    </div>
                    <input type="file" name="final_report" id="fileInput" style="display: none;" accept=".pdf,.doc,.docx,.zip" required>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit to Lecturer
                    </button>
                </form>
                
                <a href="report.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Show selected filename
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        if (fileName) {
            document.getElementById('fileName').innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + fileName;
        }
    });
</script>
<?php include 'footer.php'; ?>
</body>
</html>
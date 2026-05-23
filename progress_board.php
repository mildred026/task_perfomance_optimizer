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

// Get user name
$user_sql = "SELECT name FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);
$user_name = $user_result->fetch_assoc()['name'];

if (!$group_id) {
    header('Location: dashboard.php');
    exit();
}

// Handle progress update submission (only for members)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_progress']) && !$is_leader) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $status = $conn->real_escape_string($_POST['status']);
    
    $insert_sql = "INSERT INTO progress_updates (user_id, group_id, title, description, status, leader_status) 
                   VALUES ($user_id, $group_id, '$title', '$description', '$status', 'pending')";
    
    if ($conn->query($insert_sql)) {
        $progress_id = $conn->insert_id;
        
        // Handle screenshot uploads with captions
        if (!empty($_FILES['screenshots']['name'][0])) {
            $upload_dir = "uploads/progress/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['screenshots']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['screenshots']['error'][$key] == 0) {
                    $file_ext = pathinfo($_FILES['screenshots']['name'][$key], PATHINFO_EXTENSION);
                    $file_name = time() . "_" . $user_id . "_" . $key . "." . $file_ext;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Get caption for this screenshot
                        $caption = isset($_POST['caption_' . $key]) ? $conn->real_escape_string($_POST['caption_' . $key]) : '';
                        
                        $save_file = "INSERT INTO progress_screenshots (progress_id, user_id, file_path, original_filename, caption) 
                                      VALUES ($progress_id, $user_id, '$file_path', '{$_FILES['screenshots']['name'][$key]}', '$caption')";
                        $conn->query($save_file);
                    }
                }
            }
        }
        
        // Notify leader
        $leader_sql = "SELECT leader_id FROM project_groups WHERE id = $group_id";
        $leader_result = $conn->query($leader_sql);
        $leader_id = $leader_result->fetch_assoc()['leader_id'];
        
        $notify_sql = "INSERT INTO notifications (user_id, title, message, notification_type) 
                       VALUES ($leader_id, 'New Progress Update', 
                               '$user_name submitted a progress update: $title', 'info')";
        $conn->query($notify_sql);
        
        $_SESSION['success'] = "Progress update submitted! Your leader will review it.";
    }
    
    header('Location: progress_board.php');
    exit();
}

// Handle leader approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['review_progress']) && $is_leader) {
    $progress_id = intval($_POST['progress_id']);
    $action = $_POST['review_progress'];
    $feedback = $conn->real_escape_string($_POST['feedback']);
    
    $new_status = ($action == 'approve') ? 'approved' : 'rejected';
    
    $update_sql = "UPDATE progress_updates SET 
                   leader_feedback = '$feedback',
                   leader_reviewed_at = NOW(),
                   leader_status = '$new_status'
                   WHERE id = $progress_id AND group_id = $group_id";
    $conn->query($update_sql);
    
    // Notify the member
    $get_member = "SELECT user_id FROM progress_updates WHERE id = $progress_id";
    $member_result = $conn->query($get_member);
    $member_id = $member_result->fetch_assoc()['user_id'];
    
    $notify_sql = "INSERT INTO notifications (user_id, title, message, notification_type) 
                   VALUES ($member_id, 'Progress Update Review', 
                           'Your progress update was ' . ($action == 'approve' ? 'approved! Great work!' : 'rejected. Please check leader feedback.'),
                           '" . ($action == 'approve' ? 'success' : 'warning') . "')";
    $conn->query($notify_sql);
    
    $_SESSION['success'] = "Progress update " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully!";
    header('Location: progress_board.php');
    exit();
}

// Get all progress updates for this group
$updates_sql = "SELECT pu.*, u.name as user_name 
                FROM progress_updates pu
                JOIN users u ON pu.user_id = u.id
                WHERE pu.group_id = $group_id
                ORDER BY pu.created_at DESC";
$updates = $conn->query($updates_sql);

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
    <title>Progress Board - Task Performance Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
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

        /* Submit Form */
        .submit-form {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .screenshot-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }

        .screenshot-preview {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .preview-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
        }

        .caption-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 13px;
        }

        .btn-submit {
            background: #1a2a4f;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-add-screenshot {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .progress-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        .progress-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .card-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .user-name {
            font-weight: 600;
            color: #1a2a4f;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-in_progress { background: #ffc107; color: #1a2a4f; }
        .status-completed { background: #28a745; color: white; }
        .status-blocked { background: #dc3545; color: white; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-pending { background: #ffc107; color: #1a2a4f; }

        .card-body {
            padding: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a2a4f;
            margin-bottom: 10px;
        }

        .card-description {
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .screenshots-gallery {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        .screenshot-with-caption {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
        }

        .screenshot-with-caption img {
            width: 100%;
            max-height: 250px;
            object-fit: cover;
            cursor: pointer;
        }

        .screenshot-caption {
            padding: 10px;
            font-size: 13px;
            color: #666;
            background: white;
            border-top: 1px solid #e9ecef;
            font-style: italic;
        }

        .feedback-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .feedback-section textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 10px 0;
            resize: vertical;
        }

        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            margin-right: 10px;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
        }

        .leader-feedback {
            background: #e8f0fe;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 3px solid #1a2a4f;
        }

        .card-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #999;
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

        .leader-badge {
            background: #ffc107;
            color: #1a2a4f;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .user-info-sidebar { padding: 12px; text-align: center; }
            .main-content { padding: 20px; }
            .progress-grid { grid-template-columns: 1fr; }
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
            if ($is_leader) {
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
        <?php if ($is_leader): ?>
            <a href="create_group.php" class="nav-item">
                <i class="fas fa-users"></i> <span>Create Group</span>
            </a>
        <?php endif; ?>
        <a href="join_group.php" class="nav-item">
            <i class="fas fa-layer-group"></i> <span>Available Groups</span>
        </a>
        <a href="inbox.php" class="nav-item">
            <i class="fas fa-envelope"></i> <span>Inbox</span>
            <?php if ($unread_count > 0): ?>
                <span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <?php if ($is_leader): ?>
            <a href="assign_tasks.php" class="nav-item">
                <i class="fas fa-tasks"></i> <span>Assign Tasks</span>
            </a>
        <?php endif; ?>
        <a href="my_tasks.php" class="nav-item">
            <i class="fas fa-list-check"></i> <span>My Tasks</span>
        </a>
        <a href="upload_contribution.php" class="nav-item">
            <i class="fas fa-upload"></i> <span>Upload Contribution</span>
        </a>
        <a href="progress_board.php" class="nav-item active">
            <i class="fas fa-images"></i> <span>Progress Board</span>
        </a>
        <?php if ($is_leader): ?>
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
        <h1><i class="fas fa-images"></i> Visual Progress Board</h1>
        <?php if ($is_leader): ?>
            <p><span class="leader-badge"><i class="fas fa-crown"></i> Leader View</span> - Review and approve member progress</p>
        <?php else: ?>
            <p>Share screenshots with captions to explain your work. Your leader will review and provide feedback!</p>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <!-- Submit Form - Only for members -->
    <?php if (!$is_leader): ?>
    <div class="submit-form">
        <h3><i class="fas fa-upload"></i> Share Your Progress</h3>
        <form method="POST" enctype="multipart/form-data" id="progressForm">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required>
            </div>
            
            <div class="form-group">
                <label>Description *</label>
                <textarea name="description" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Status *</label>
                <select name="status" required>
                    <option value="in_progress">🔄 In Progress</option>
                    <option value="completed">✅ Completed</option>
                    <option value="blocked">⚠️ Blocked - Need Help</option>
                </select>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-camera"></i> Screenshots with Captions *</label>
                <div id="screenshots-container">
                    <div class="screenshot-item" id="screenshot-0">
                        <div class="screenshot-preview" id="preview-0"></div>
                        <input type="file" name="screenshots[]" accept="image/*" onchange="previewImage(this, 0)" required>
                        <input type="text" name="caption_0" class="caption-input" placeholder="Add a caption for this screenshot...">
                    </div>
                </div>
                <button type="button" class="btn-add-screenshot" onclick="addScreenshotField()">
                    <i class="fas fa-plus"></i> Add Another Screenshot
                </button>
                <small>You can upload multiple screenshots. Add captions to explain each one!</small>
            </div>
            
            <button type="submit" name="submit_progress" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Submit for Review
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Progress Updates Feed -->
    <h2><i class="fas fa-history"></i> Progress Updates</h2>
    <div class="progress-grid">
        <?php if ($updates && $updates->num_rows > 0): ?>
            <?php while ($update = $updates->fetch_assoc()): 
                $screenshot_sql = "SELECT * FROM progress_screenshots WHERE progress_id = {$update['id']} ORDER BY id ASC";
                $screenshots = $conn->query($screenshot_sql);
                $is_pending_review = ($update['leader_status'] == 'pending');
            ?>
                <div class="progress-card">
                    <div class="card-header">
                        <span class="user-name">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($update['user_name']); ?>
                        </span>
                        <?php if ($update['leader_status'] == 'approved'): ?>
                            <span class="status-badge status-approved">✓ Approved by Leader</span>
                        <?php elseif ($update['leader_status'] == 'rejected'): ?>
                            <span class="status-badge status-rejected">✗ Needs Changes</span>
                        <?php elseif ($update['leader_status'] == 'pending'): ?>
                            <span class="status-badge status-pending">Pending Review</span>
                        <?php else: ?>
                            <span class="status-badge status-<?php echo $update['status']; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($update['status'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($update['title']); ?></div>
                        <div class="card-description"><?php echo nl2br(htmlspecialchars($update['description'])); ?></div>
                        
                        <?php if ($screenshots && $screenshots->num_rows > 0): ?>
                            <div class="screenshots-gallery">
                                <?php while ($screenshot = $screenshots->fetch_assoc()): ?>
                                    <div class="screenshot-with-caption">
                                        <a href="<?php echo $screenshot['file_path']; ?>" data-lightbox="progress-<?php echo $update['id']; ?>">
                                            <img src="<?php echo $screenshot['file_path']; ?>" alt="Screenshot">
                                        </a>
                                        <?php if (!empty($screenshot['caption'])): ?>
                                            <div class="screenshot-caption">
                                                <i class="fas fa-comment"></i> <?php echo htmlspecialchars($screenshot['caption']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Leader Feedback Display -->
                        <?php if (!empty($update['leader_feedback'])): ?>
                            <div class="leader-feedback">
                                <strong><i class="fas fa-comment-dots"></i> Leader Feedback:</strong>
                                <p><?php echo nl2br(htmlspecialchars($update['leader_feedback'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Review Form (Only for Leader) -->
                        <?php if ($is_leader && $is_pending_review): ?>
                            <form method="POST" class="feedback-section">
                                <input type="hidden" name="progress_id" value="<?php echo $update['id']; ?>">
                                <textarea name="feedback" rows="2" placeholder="Provide feedback to the member..."></textarea>
                                <button type="submit" name="review_progress" value="approve" class="btn-approve">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="submit" name="review_progress" value="reject" class="btn-reject" 
                                        onclick="return confirm('Reject this submission? The member will need to resubmit.');">
                                    <i class="fas fa-times"></i> Request Changes
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <i class="far fa-clock"></i> <?php echo date('F j, Y g:i A', strtotime($update['created_at'])); ?>
                        <?php if ($update['leader_reviewed_at']): ?>
                            &nbsp;| <i class="fas fa-check-circle"></i> Reviewed: <?php echo date('M j, g:i A', strtotime($update['leader_reviewed_at'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 15px;">
                <i class="fas fa-camera" style="font-size: 48px; color: #ccc;"></i>
                <p style="margin-top: 15px;">No progress updates yet.</p>
                <?php if (!$is_leader): ?>
                    <p>Be the first to share your work using the form above!</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
<script>
let screenshotCount = 1;

function previewImage(input, index) {
    const previewDiv = document.getElementById(`preview-${index}`);
    previewDiv.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-img';
            previewDiv.appendChild(img);
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function addScreenshotField() {
    const container = document.getElementById('screenshots-container');
    const newIndex = screenshotCount;
    
    const newDiv = document.createElement('div');
    newDiv.className = 'screenshot-item';
    newDiv.id = `screenshot-${newIndex}`;
    newDiv.innerHTML = `
        <div class="screenshot-preview" id="preview-${newIndex}"></div>
        <input type="file" name="screenshots[]" accept="image/*" onchange="previewImage(this, ${newIndex})">
        <input type="text" name="caption_${newIndex}" class="caption-input" placeholder="Add a caption for this screenshot...">
        <button type="button" class="btn-add-screenshot" style="background: #dc3545; margin-top: 8px;" onclick="removeScreenshotField(${newIndex})">
            <i class="fas fa-trash"></i> Remove
        </button>
    `;
    
    container.appendChild(newDiv);
    screenshotCount++;
}

function removeScreenshotField(index) {
    const element = document.getElementById(`screenshot-${index}`);
    element.remove();
}
</script>

</body>
</html>
<?php
session_start();
include 'db.php';

// Protect the session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_leader = $_SESSION['isLeader'] ?? false;

// Get user role and name
$user_sql = "SELECT name, role FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'];
$user_role = $user_data['role'];

$is_lecturer = ($user_role == 'lecturer');

// Check if group_id is provided
if (!isset($_GET['group_id'])) {
    header('Location: join_group.php');
    exit();
}

$group_id = intval($_GET['group_id']);

// Get group info including leader_id
$group_query = "SELECT g.*, u.name as leader_name 
                FROM groups g
                JOIN users u ON g.leader_id = u.id
                WHERE g.id='$group_id'";
$group_result = $conn->query($group_query);
$group = $group_result->fetch_assoc();

if (!$group) {
    header('Location: join_group.php');
    exit();
}

// Check if current user is the leader of THIS group
$is_group_leader = ($user_id == $group['leader_id']);

// Handle removing a member (only for group leader)
if (isset($_GET['remove_member']) && $is_group_leader) {
    $remove_user_id = intval($_GET['remove_member']);
    
    // Don't allow removing the leader
    if ($remove_user_id != $group['leader_id']) {
        $delete_sql = "DELETE FROM group_members WHERE group_id = $group_id AND user_id = $remove_user_id";
        if ($conn->query($delete_sql)) {
            $_SESSION['success'] = "Member removed from group successfully!";
        } else {
            $_SESSION['error'] = "Failed to remove member.";
        }
    } else {
        $_SESSION['error'] = "Cannot remove the group leader!";
    }
    header("Location: view_group.php?group_id=$group_id");
    exit();
}

// Get members
$sql = "SELECT u.id, u.name, u.email, 
        CASE WHEN u.id = {$group['leader_id']} THEN 1 ELSE 0 END as is_leader
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        WHERE gm.group_id = '$group_id'
        ORDER BY is_leader DESC, u.name ASC";
$members = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Members - <?php echo htmlspecialchars($group['group_name']); ?></title>
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

        .group-info-card {
            background: linear-gradient(135deg, #1a2a4f 0%, #2a3a6f 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
        }

        .group-info-card .group-name {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .members-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
        }

        .members-table th, .members-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .members-table th {
            background: #1a2a4f;
            color: white;
        }

        .members-table tr:hover {
            background: #f8f9fa;
        }

        .leader-badge {
            background: #ffc107;
            color: #1a2a4f;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
        }

        .btn-message {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }

        .btn-message:hover {
            background: #138496;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-remove:hover {
            background: #c82333;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h2, .user-info-sidebar .name, 
            .user-info-sidebar .role, .nav-item span { display: none; }
            .nav-item i { margin: 0 auto; }
            .main-content { padding: 20px; }
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
        <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <?php if (!$is_lecturer): ?>
            <a href="create_group.php" class="nav-item"><i class="fas fa-users"></i> <span>Create Group</span></a>
            <a href="join_group.php" class="nav-item"><i class="fas fa-link"></i> <span>Available Groups</span></a>
        <?php endif; ?>
        <?php if ($is_leader && !$is_lecturer): ?>
            <a href="assign_tasks.php" class="nav-item"><i class="fas fa-tasks"></i> <span>Assign Tasks</span></a>
        <?php endif; ?>
        <?php if (!$is_lecturer): ?>
            <a href="my_tasks.php" class="nav-item"><i class="fas fa-list-check"></i> <span>My Tasks</span></a>
            <a href="upload_contribution.php" class="nav-item"><i class="fas fa-upload"></i> <span>Upload Contribution</span></a>
        <?php endif; ?>
        <a href="progress_board.php" class="nav-item"><i class="fas fa-images"></i> <span>Progress Board</span></a>
        <a href="logout.php" class="nav-item logout-item"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Group Members</h1>
        <p>View all members in this group</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="group-info-card">
        <div class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></div>
        <div><i class="fas fa-crown"></i> Group Leader: <?php echo htmlspecialchars($group['leader_name']); ?></div>
    </div>

    <div class="members-container">
        <h2><i class="fas fa-user-friends"></i> Members List</h2>
        
        <table class="members-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members && $members->num_rows > 0): ?>
                    <?php $counter = 1; while ($member = $members->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td>
                                <?php echo htmlspecialchars($member['name']); ?>
                                <?php if ($member['is_leader']): ?>
                                    <span class="leader-badge"><i class="fas fa-crown"></i> Leader</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td>
                                <?php if ($member['is_leader']): ?>
                                    <span style="color: #ffc107;"><i class="fas fa-crown"></i> Group Leader</span>
                                <?php else: ?>
                                    <span style="color: #17a2b8;"><i class="fas fa-user"></i> Member</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$member['is_leader']): ?>
                                    <?php if ($is_group_leader): ?>
                                        <!-- Only the group leader sees these buttons -->
                                        <button class="btn-message" onclick="openMessageModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>')">
                                            <i class="fas fa-envelope"></i> Message
                                        </button>
                                        <a href="?remove_member=<?php echo $member['id']; ?>&group_id=<?php echo $group_id; ?>" 
                                           class="btn-remove" 
                                           onclick="return confirm('Remove <?php echo htmlspecialchars($member['name']); ?> from this group?')">
                                            <i class="fas fa-trash"></i> Remove
                                        </a>
                                    <?php else: ?>
                                        <!-- Regular members see no actions -->
                                        <span style="color: #999;">No actions available</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #28a745;"><i class="fas fa-crown"></i> Leader</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">No members found in this group.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="join_group.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Groups
        </a>
    </div>
</div>

<!-- Message Modal - Only shown for group leader -->
<?php if ($is_group_leader): ?>
<div id="messageModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-envelope"></i> Send Message</h3>
        <form method="POST" action="send_message.php">
            <input type="hidden" name="to_user_id" id="modal_to_user_id">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            
            <div class="form-group">
                <label>Sending to: <strong id="modal_user_name"></strong></label>
            </div>
            
            <div class="form-group">
                <label>Subject:</label>
                <input type="text" name="subject" required>
            </div>
            
            <div class="form-group">
                <label>Message:</label>
                <textarea name="message" rows="4" required></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;">Cancel</button>
                <button type="submit" style="background: #1a2a4f; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;">Send Message</button>
            </div>
        </form>
    </div>
</div>

<script>
function openMessageModal(userId, userName) {
    document.getElementById('modal_to_user_id').value = userId;
    document.getElementById('modal_user_name').innerHTML = userName;
    document.getElementById('messageModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('messageModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('messageModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
<?php endif; ?>

</body>
</html>
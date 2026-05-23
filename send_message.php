<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$from_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $to_user_id = intval($_POST['to_user_id']);
    $group_id = intval($_POST['group_id']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);
    
    // SECURITY CHECK: Verify that the sender is actually the leader of this group
    $leader_check = "SELECT leader_id FROM groups WHERE id = $group_id";
    $leader_result = $conn->query($leader_check);
    $leader_id = $leader_result->fetch_assoc()['leader_id'];
    
    if ($from_user_id != $leader_id) {
        $_SESSION['error'] = "Only the group leader can send messages to members.";
        header('Location: view_group.php?group_id=' . $group_id);
        exit();
    }
    
    // Create messages table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_user_id INT NOT NULL,
        to_user_id INT NOT NULL,
        group_id INT NOT NULL,
        subject VARCHAR(255),
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_table);
    
    // Insert the message
    $insert_sql = "INSERT INTO messages (from_user_id, to_user_id, group_id, subject, message, created_at) 
                   VALUES ($from_user_id, $to_user_id, $group_id, '$subject', '$message', NOW())";
    
    if ($conn->query($insert_sql)) {
        $_SESSION['success'] = "Message sent successfully!";
    } else {
        $_SESSION['error'] = "Failed to send message: " . $conn->error;
    }
    
    header('Location: view_group.php?group_id=' . $group_id);
    exit();
} else {
    header('Location: dashboard.php');
    exit();
}
?>
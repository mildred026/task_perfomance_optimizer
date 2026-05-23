<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$video_id = intval($_POST['video_id']);
$status = intval($_POST['status']);

$update_sql = "UPDATE video_verifications SET verified = $status, reviewed_by = $user_id, reviewed_at = NOW() 
               WHERE id = $video_id";
$conn->query($update_sql);

echo json_encode(['success' => true]);
?>
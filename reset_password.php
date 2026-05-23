<?php
include 'db.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    if (isset($_POST['reset'])) {
        $newPassword = $_POST['new_password'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $sql = "UPDATE users SET password='$hashedPassword', reset_token=NULL WHERE reset_token='$token'";
        if ($conn->query($sql) === TRUE) {
            // Redirect to login page after success
            header("Location: login.php?reset=success");
            exit();
        } else {
            echo "❌ Error: " . $conn->error;
        }
    }
} else {
    echo "Invalid or missing token.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<div class="login-container">
    <h2 class="login-title">Reset Password</h2>
    <form method="POST" class="login-form">
        <table class="login-table">
            <tr>
                <td><label>New Password:</label></td>
                <td><input type="password" name="new_password" required></td>
            </tr>
            <tr>
                <td colspan="2">
                    <button type="submit" name="reset" class="login-btn">Set New Password</button>
                </td>
            </tr>
        </table>
    </form>
</div>
</body>
</html>

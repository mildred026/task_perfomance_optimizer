<?php
include 'db.php';

if (isset($_POST['submit'])) {
    $email = $_POST['email'];

    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($result->num_rows > 0) {
        $token = bin2hex(random_bytes(50));
        $conn->query("UPDATE users SET reset_token='$token' WHERE email='$email'");

        // Show reset link directly (no email yet)
        echo "Password reset link: <a href='reset_password.php?token=$token'>Click here</a>";
    } else {
        echo "No account found with that email.";
    }
}
?>

<form method="POST">
    <label>Enter your email:</label>
    <input type="email" name="email" required>
    <button type="submit" name="submit">Reset Password</button>
</form>

<?php include 'footer.php'; ?>

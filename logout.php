<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect back to login page with a flag
header("Location: login.php?logout=success");
exit();
?>

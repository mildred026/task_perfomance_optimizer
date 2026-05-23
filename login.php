<?php
session_start();
include 'db.php';

$error = "";

// Show logout confirmation if redirected from logout.php
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = "You have successfully logged out.";
}

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];   
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_leader'] = $user['is_leader'];
            $_SESSION['isLeader'] = $user['is_leader']; // Both formats for compatibility
            
            // Get group_id for leaders
            if ($user['is_leader'] == 1) {
                $check_group = $conn->query("SELECT id FROM groups WHERE leader_id='{$user['id']}'");
                if ($check_group && $check_group->num_rows > 0) {
                    $group = $check_group->fetch_assoc();
                    $_SESSION['group_id'] = $group['id'];
                }
            }
            
            // REDIRECT ALL USERS TO ONE DASHBOARD
            // The dashboard will show different content based on role
            header("Location: dashboard.php");
            exit();
            
        } else {
            $error = "Invalid password. Please try again.";
        }
    } else {
        $error = "No account found with this email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Task Performance Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background Bubbles */
        .bubble {
            position: fixed;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            animation: float 10s infinite ease-in-out;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
                opacity: 0.4;
            }
            50% {
                transform: translateY(-60px) translateX(40px);
                opacity: 0.8;
            }
        }

        /* Floating Shapes */
        .shape {
            position: fixed;
            font-size: 2rem;
            opacity: 0.2;
            animation: floatShape 15s infinite ease-in-out;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes floatShape {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-50px) rotate(15deg);
            }
        }

        /* Main Container - Split Layout */
        .container {
            display: flex;
            max-width: 1100px;
            width: 90%;
            margin: 40px auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            z-index: 10;
            position: relative;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Left Side - Cloud with Image */
        .image-side {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        /* Cloud Container */
        .cloud-container {
            position: relative;
            width: 100%;
            max-width: 350px;
            animation: floatCloud 6s ease-in-out infinite;
        }

        @keyframes floatCloud {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-15px);
            }
        }

        /* Cloud Shape */
        .cloud {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 80px;
            padding: 25px;
            position: relative;
            box-shadow: 0 30px 50px rgba(0, 0, 0, 0.2);
        }

        /* Cloud bumps */
        .cloud::before,
        .cloud::after {
            content: '';
            position: absolute;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 50%;
        }

        .cloud::before {
            width: 70px;
            height: 70px;
            top: -35px;
            left: 25px;
        }

        .cloud::after {
            width: 100px;
            height: 100px;
            top: -50px;
            right: 35px;
        }

        /* Inner cloud bump */
        .cloud .cloud-inner {
            position: absolute;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            width: 55px;
            height: 55px;
            top: -28px;
            left: 105px;
            z-index: 2;
        }

        /* Image inside cloud */
        .cloud-image {
            position: relative;
            z-index: 5;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .cloud-image img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s ease;
        }

        .cloud-image:hover img {
            transform: scale(1.05);
        }

        .image-caption {
            text-align: center;
            margin-top: 20px;
            color: white;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .image-caption i {
            margin-right: 5px;
        }

        /* Right Side - Form Side */
        .form-side {
            flex: 1;
            padding: 45px 40px;
            background: white;
        }

        /* Logo */
        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .logo-icon i {
            font-size: 1.8rem;
            color: white;
        }

        .logo h2 {
            font-size: 1.5rem;
            color: #1a2a4f;
        }

        .logo p {
            font-size: 0.8rem;
            color: #999;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1a2a4f;
            font-size: 0.85rem;
        }

        .form-group label i {
            color: #667eea;
            margin-right: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i.input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Messages */
        .success-message, .error-message {
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        /* Links */
        .forgot-link {
            text-align: center;
            margin-top: 10px;
        }

        .forgot-link a {
            color: #999;
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.3s;
        }

        .forgot-link a:hover {
            color: #667eea;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .register-link p {
            color: #666;
            font-size: 0.85rem;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 850px) {
            .container {
                flex-direction: column;
                max-width: 450px;
            }
            .image-side {
                padding: 40px;
            }
            .cloud-container {
                max-width: 280px;
            }
            .cloud::before {
                width: 50px;
                height: 50px;
                top: -25px;
                left: 15px;
            }
            .cloud::after {
                width: 70px;
                height: 70px;
                top: -35px;
                right: 20px;
            }
            .cloud .cloud-inner {
                width: 40px;
                height: 40px;
                top: -20px;
                left: 70px;
            }
            .form-side {
                padding: 35px 30px;
            }
        }
    </style>
</head>
<body>

<!-- Animated Background Bubbles -->
<div class="bubble" style="width: 100px; height: 100px; top: 10%; left: 5%; animation-duration: 6s;"></div>
<div class="bubble" style="width: 150px; height: 150px; bottom: 15%; right: 8%; animation-duration: 8s;"></div>
<div class="bubble" style="width: 60px; height: 60px; top: 20%; right: 20%; animation-duration: 5s;"></div>
<div class="bubble" style="width: 80px; height: 80px; bottom: 30%; left: 15%; animation-duration: 7s;"></div>

<!-- Floating Shapes -->
<div class="shape" style="top: 15%; left: 10%;">✨</div>
<div class="shape" style="bottom: 20%; right: 12%; animation-delay: 2s;">☁️</div>
<div class="shape" style="top: 40%; right: 5%; animation-delay: 4s;">🌟</div>
<div class="shape" style="bottom: 40%; left: 8%; animation-delay: 1s;">💫</div>

<div class="container">
    <!-- Left Side - Cloud with Image -->
    <div class="image-side">
        <div class="cloud-container">
            <div class="cloud">
                <div class="cloud-inner"></div>
                <div class="cloud-image">
                    <img src="image.jpg" alt="Task Performance Tracker">
                </div>
            </div>
            <div class="image-caption">
                <i class="fas fa-chart-line"></i> Track • Perform • Excel
            </div>
        </div>
    </div>
    
    <!-- Right Side - Form -->
    <div class="form-side">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h2>Welcome Back!</h2>
            <p>Sign in to continue</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success_message; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" required>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" required>
                </div>
            </div>
            
            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
            
            <div class="forgot-link">
                <a href="forgot_password.php"><i class="fas fa-question-circle"></i> Forgot Password?</a>
            </div>
        </form>
        
        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
    </div>
</div>

</body>
</html>
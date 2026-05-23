<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Performance Optimizer | Welcome</title>
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
            position: relative;
            overflow-x: hidden;
        }

        /* Full Screen Background Image */
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -2;
        }

        /* Dark Overlay for better text readability */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            z-index: -1;
        }

        /* Animated Background Bubbles */
        .bubble {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s infinite ease-in-out;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
                opacity: 0.3;
            }
            50% {
                transform: translateY(-50px) translateX(30px);
                opacity: 0.6;
            }
        }

        /* Floating Shapes */
        .shape {
            position: absolute;
            font-size: 2rem;
            opacity: 0.15;
            animation: floatShape 12s infinite ease-in-out;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes floatShape {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-40px) rotate(10deg);
            }
        }

        .site-header {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 10;
            padding: 24px 36px;
        }

        .site-nav {
            max-width: 1180px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 14px 18px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            background: rgba(10, 16, 32, 0.28);
            backdrop-filter: blur(10px);
        }

        .site-brand {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .site-brand i {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .site-links {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .site-links a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .site-links a:hover {
            color: white;
            text-decoration: underline;
        }

        .site-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .site-action {
            color: white;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 999px;
            padding: 8px 14px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .site-action.primary {
            border-color: transparent;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        /* Main Content - Directly on image */
        .content {
            position: relative;
            z-index: 5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 120px 20px 60px;
        }

        /* Logo Animation */
        .logo-icon {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-12px);
            }
        }

        .logo-icon i {
            font-size: 2.8rem;
            color: white;
        }

        .logo h1 {
            font-size: 2.8rem;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            animation: fadeInDown 0.8s ease-out;
        }

        .logo p {
            font-size: 1rem;
            color: rgba(255,255,255,0.85);
            margin-top: 8px;
            letter-spacing: 3px;
            animation: fadeInUp 0.8s ease-out;
        }

        /* Welcome Text */
        .welcome-text {
            margin: 35px 0 25px;
            animation: fadeIn 1s ease-out;
        }

        .welcome-text h2 {
            font-size: 3.2rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            margin-bottom: 15px;
        }

        .welcome-text h2 span {
            background: linear-gradient(135deg, #ffd89b, #c7e9fb);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: none;
        }

        .welcome-text p {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            max-width: 550px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 25px;
            justify-content: center;
            margin: 35px 0 0;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out;
        }

        .btn {
            padding: 14px 42px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-3px);
        }

        /* Footer Info */
        .info-text {
            margin-top: 50px;
            text-align: center;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.7);
            animation: fadeIn 1.5s ease-out;
        }

        .info-text i {
            margin: 0 5px;
        }

        .info-text span {
            margin: 0 10px;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .site-header {
                padding: 14px;
            }
            .site-nav {
                align-items: flex-start;
                flex-direction: column;
            }
            .site-links,
            .site-actions {
                width: 100%;
            }
            .logo h1 {
                font-size: 1.8rem;
            }
            .welcome-text h2 {
                font-size: 2rem;
            }
            .welcome-text p {
                font-size: 0.95rem;
            }
            .btn {
                padding: 12px 28px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<!-- Full Screen Background Image -->
<img src="teamwork-2.jpg" alt="Background" class="bg-image">
<div class="overlay"></div>

<header class="site-header">
    <nav class="site-nav" aria-label="Primary navigation">
        <a href="index.php" class="site-brand">
            <i class="fas fa-chart-line"></i>
            <span>Task Performance Optimizer</span>
        </a>

        <div class="site-links">
            <a href="index.php">Home</a>
            <a href="login.php">Dashboard</a>
            <a href="register.php">Account</a>
            <a href="#tpoFooter">Contact</a>
        </div>

        <div class="site-actions">
            <a href="login.php" class="site-action">Sign In</a>
            <a href="register.php" class="site-action primary">Create Account</a>
        </div>
    </nav>
</header>

<!-- Animated Background Bubbles -->
<div class="bubble" style="width: 100px; height: 100px; top: 10%; left: 5%; animation-duration: 6s;"></div>
<div class="bubble" style="width: 150px; height: 150px; bottom: 15%; right: 8%; animation-duration: 8s;"></div>
<div class="bubble" style="width: 60px; height: 60px; top: 20%; right: 20%; animation-duration: 5s;"></div>
<div class="bubble" style="width: 80px; height: 80px; bottom: 30%; left: 15%; animation-duration: 7s;"></div>

<!-- Floating Shapes -->
<div class="shape" style="top: 15%; left: 10%;">✨</div>
<div class="shape" style="bottom: 20%; right: 12%; animation-delay: 2s;">⚡</div>
<div class="shape" style="top: 40%; right: 5%; animation-delay: 4s;">🎯</div>
<div class="shape" style="bottom: 40%; left: 8%; animation-delay: 1s;">🚀</div>

<div class="content">
    <!-- Logo -->
    <div class="logo">
        <div class="logo-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <h1>Task Performance Optimizer</h1>
        <p>TRACK • COLLABORATE • SUCCEED</p>
    </div>

    <!-- Welcome Text -->
    <div class="welcome-text">
        <h2>Welcome! <span>👋</span></h2>
        <p>>Manage group projects, track individual contributions and achieve better results together</p>
    </div>

    <!-- Buttons -->
    <div class="btn-group">
        <a href="login.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </a>
        <a href="register.php" class="btn btn-secondary">
            <i class="fas fa-user-plus"></i> Create Account
        </a>
    </div>

    <!-- Footer Info -->
    <div class="info-text">
        <i class="fas fa-shield-alt"></i> Secure
        <span>•</span>
        <i class="fas fa-chart-line"></i> Optimize
        <span>•</span>
        <i class="fas fa-tachometer-alt"></i> Performance
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>

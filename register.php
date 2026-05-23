<?php
include 'db.php';

if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $is_leader = isset($_POST['is_leader']) ? 1 : 0;
    
    $sql = "INSERT INTO users (name, email, password, role, is_leader)
            VALUES ('$name', '$email', '$password', '$role', '$is_leader')";

    if ($conn->query($sql) === TRUE) {
        $user_id = $conn->insert_id;
        
        echo '<div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div>
                    <span class="highlight">Welcome aboard, <b>' . htmlspecialchars($name) . '</b>!</span><br>
                    <span class="highlight">Your account has been created successfully.</span><br>
                    <span class="highlight">Redirecting you to the login page...</span>
                </div>
              </div>';
        echo '<script>
                setTimeout(function(){
                    window.location.href = "login.php";
                }, 3000);
              </script>';
        exit();
    } else {
        echo '<div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div>Error: ' . $conn->error . '</div>
              </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Task Performance Optimizer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }

        .bubble {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s infinite ease-in-out;
            pointer-events: none;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
            50% { transform: translateY(-50px) translateX(30px); opacity: 0.6; }
        }

        .shape {
            position: absolute;
            font-size: 3rem;
            opacity: 0.15;
            animation: floatShape 12s infinite ease-in-out;
            pointer-events: none;
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-40px) rotate(10deg); }
        }

        .container {
            max-width: 500px;
            width: 90%;
            margin: 20px;
            background: white;
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.6s ease-out;
            z-index: 10;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .card-header .emoji {
            font-size: 3.5rem;
            margin-bottom: 10px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .card-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .floating-stars {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 1.2rem;
            opacity: 0.6;
            animation: twinkle 3s infinite;
        }

        .floating-stars.right {
            left: auto;
            right: 20px;
            animation-delay: 1.5s;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        .card-body {
            padding: 35px 30px;
        }

        .success-message, .error-message {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .error-message {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .success-message i, .error-message i {
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1a2a4f;
            font-size: 0.9rem;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Highlighted Checkbox Group */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 12px;
            margin: 15px 0 5px 0;
            border-left: 4px solid #ffc107;
        }

        .checkbox-group input {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #ffc107;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            color: #856404;
            font-weight: 600;
        }

        .checkbox-group label i {
            color: #ffc107;
        }

        .checkbox-hint {
            font-size: 0.7rem;
            color: #856404;
            margin-left: 32px;
            margin-bottom: 15px;
            padding-left: 15px;
        }

        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .login-link p {
            color: #666;
            font-size: 0.9rem;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .card-header h1 { font-size: 1.4rem; }
            .card-body { padding: 25px 20px; }
        }
    </style>
</head>
<body>

<div class="bubble" style="width: 100px; height: 100px; top: 10%; left: 5%; animation-duration: 6s;"></div>
<div class="bubble" style="width: 150px; height: 150px; bottom: 15%; right: 8%; animation-duration: 8s;"></div>
<div class="bubble" style="width: 60px; height: 60px; top: 20%; right: 20%; animation-duration: 5s;"></div>
<div class="bubble" style="width: 80px; height: 80px; bottom: 30%; left: 15%; animation-duration: 7s;"></div>

<div class="shape" style="top: 15%; left: 10%;">🌟</div>
<div class="shape" style="bottom: 20%; right: 12%; animation-delay: 2s;">📚</div>
<div class="shape" style="top: 40%; right: 5%; animation-delay: 4s;">👥</div>
<div class="shape" style="bottom: 40%; left: 8%; animation-delay: 1s;">🎯</div>

<div class="container">
    <div class="card-header">
        <div class="floating-stars">✨</div>
        <div class="floating-stars right">⭐</div>
        <div class="emoji">📝</div>
        <h1>Create Account</h1>
        <p>Join Task Performance Optimizer</p>
    </div>
    
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="name" required>
                </div>
            </div>
            
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
            
            <div class="form-group">
                <label><i class="fas fa-user-tag"></i> Role</label>
                <div class="input-wrapper">
                    <i class="fas fa-graduation-cap input-icon"></i>
                    <select name="role" required>
                        <option value="Student">🎓 Student</option>
                        <option value="Lecturer">👨‍🏫 Lecturer</option>
                    </select>
                </div>
            </div>
            
            <!-- IMPORTANT: CHECKBOX IS NOW HIGHLIGHTED IN YELLOW -->
            <div class="checkbox-group">
                <input type="checkbox" name="is_leader" value="1" id="is_leader">
                <label for="is_leader">
                    <i class="fas fa-crown"></i> 👑 I am a Group Leader
                </label>
            </div>
            <div class="checkbox-hint">
                <i class="fas fa-info-circle"></i> ⚠️ TICK THIS BOX if you want to lead a group (Leaders can create groups and assign tasks)
            </div>
            
            <button type="submit" name="register" class="btn-register">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </form>
        
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Sign In</a></p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
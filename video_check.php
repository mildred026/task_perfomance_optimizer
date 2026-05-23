<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

// Get user name
$user_sql = "SELECT name FROM users WHERE id = $user_id";
$user_result = $conn->query($user_sql);
$user_name = $user_result->fetch_assoc()['name'];

// Handle video upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['video'])) {
    $upload_dir = "uploads/video_checks/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . "_" . $user_id . ".webm";
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['video']['tmp_name'], $file_path)) {
        // SAVE THE CORRECT RELATIVE PATH TO DATABASE
        $relative_path = "uploads/video_checks/" . $file_name;
        
        $insert_sql = "INSERT INTO video_verifications (user_id, task_id, video_path, verified, requested_at, submitted_at) 
                       VALUES ($user_id, $task_id, '$relative_path', 0, NOW(), NOW())";
        $conn->query($insert_sql);
        
        $_SESSION['video_check_pending'] = false;
        $_SESSION['last_video_check'] = time();
        
        // Update user record
        $conn->query("UPDATE users SET last_video_check = NOW(), video_check_count = video_check_count + 1 WHERE id = $user_id");
        
        echo "<script>alert('Video submitted successfully!'); window.close();</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Video Check-in</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .header .emoji {
            font-size: 3rem;
            margin-bottom: 10px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .header h2 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .body {
            padding: 30px;
            text-align: center;
        }
        
        .video-container {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        video {
            width: 100%;
            max-width: 400px;
            border-radius: 15px;
            background: #000;
            transform: scaleX(-1); /* Mirror effect like selfie */
        }
        
        .instruction {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .instruction p {
            margin: 5px 0;
            color: #856404;
        }
        
        .btn-record {
            background: #dc3545;
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin: 10px 0;
        }
        
        .btn-record.recording {
            background: #28a745;
            animation: pulse 1s infinite;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 15px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .timer {
            font-size: 1.2rem;
            font-weight: bold;
            color: #dc3545;
            margin: 10px 0;
        }
        
        .info {
            font-size: 0.8rem;
            color: #666;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .container { margin: 20px; }
            .header h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="emoji">🎥</div>
        <h2>Quick Video Check-in</h2>
        <p>Please verify you are the one working</p>
    </div>
    
    <div class="body">
        <div class="instruction">
            <i class="fas fa-microphone-alt"></i>
            <p><strong>Say the following:</strong></p>
            <p>"I am <?php echo htmlspecialchars($user_name); ?>, working on <?php echo date('F j, Y'); ?>"</p>
        </div>
        
        <div class="video-container">
            <video id="video" autoplay muted></video>
            <div id="timer" class="timer" style="display: none;">Recording: <span id="countdown">5</span> seconds</div>
            <button id="recordBtn" class="btn-record">
                <i class="fas fa-video"></i> Start Recording
            </button>
        </div>
        
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <input type="file" name="video" id="videoInput" accept="video/*" style="display: none;">
            <button type="button" id="submitBtn" class="btn-submit" style="display: none;">
                <i class="fas fa-upload"></i> Submit Video
            </button>
        </form>
        
        <div class="info">
            <i class="fas fa-info-circle"></i> This video will be sent to your lecturer for verification.
        </div>
    </div>
</div>

<script>
const video = document.getElementById('video');
const recordBtn = document.getElementById('recordBtn');
const submitBtn = document.getElementById('submitBtn');
const videoInput = document.getElementById('videoInput');
const timer = document.getElementById('timer');
const countdownSpan = document.getElementById('countdown');

let mediaRecorder;
let recordedChunks = [];
let isRecording = false;

// Request camera access
navigator.mediaDevices.getUserMedia({ video: true, audio: true })
    .then(stream => {
        video.srcObject = stream;
        mediaRecorder = new MediaRecorder(stream);
        
        mediaRecorder.ondataavailable = function(event) {
            if (event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };
        
        mediaRecorder.onstop = function() {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            const file = new File([blob], 'checkin.webm', { type: 'video/webm' });
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            videoInput.files = dataTransfer.files;
            
            // Preview recorded video
            const videoURL = URL.createObjectURL(blob);
            video.srcObject = null;
            video.src = videoURL;
            video.muted = false;
            video.controls = true;
            
            submitBtn.style.display = 'block';
            recordBtn.style.display = 'none';
        };
    })
    .catch(err => {
        alert('Camera access denied. Please allow camera to verify your identity.');
        console.error(err);
    });

// Recording with countdown
recordBtn.addEventListener('click', function() {
    if (!isRecording) {
        let countdown = 5;
        timer.style.display = 'block';
        countdownSpan.innerText = countdown;
        
        const countdownInterval = setInterval(() => {
            countdown--;
            countdownSpan.innerText = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                timer.style.display = 'none';
                
                // Start recording
                recordedChunks = [];
                mediaRecorder.start();
                isRecording = true;
                recordBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Recording';
                recordBtn.classList.add('recording');
                
                // Stop after 5 seconds
                setTimeout(() => {
                    if (isRecording) {
                        mediaRecorder.stop();
                        isRecording = false;
                        recordBtn.innerHTML = '<i class="fas fa-video"></i> Start Recording';
                        recordBtn.classList.remove('recording');
                    }
                }, 5000);
            }
        }, 1000);
    }
});

// Submit video
submitBtn.addEventListener('click', function() {
    if (videoInput.files.length > 0) {
        document.getElementById('uploadForm').submit();
    }
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
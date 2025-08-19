<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = $_SESSION['user_role'];
    switch ($role) {
        case 'admin':
            redirect('../admin/index.php');
            break;
        case 'teacher':
            redirect('../teacher/index.php');
            break;
        case 'student':
            redirect('../student/index.php');
            break;
        case 'parent':
            redirect('../parent/index.php');
            break;
        case 'guidance':
            redirect('../guidance/index.php');
            break;
        case 'registrar':
            redirect('../registrar/index.php');
            break;
        case 'accounting':
            redirect('../accounting/index.php');
            break;
        case 'principal':
            redirect('../principal/index.php');
            break;
        default:
            redirect('../student/index.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check login attempts (basic rate limiting)
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM activity_logs 
            WHERE ip_address = ? AND action = 'failed_login' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip]);
        $attempts = $stmt->fetch()['attempts'];
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $error = 'Too many failed login attempts. Please try again in 15 minutes.';
        } else {
            $user = $dataManager->getUserByCredentials($username, $password);
            
            if ($user) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['login_time'] = time();
                
                // Update last login
                $dataManager->updateUser($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
                
                // Log successful login
                log_activity($user['id'], 'successful_login', 'User logged in successfully');
                
                // Set remember me cookie if requested
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                    // Store token in database (implement remember_tokens table if needed)
                }
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        redirect('../admin/index.php');
                        break;
                    case 'teacher':
                        redirect('../teacher/index.php');
                        break;
                    case 'student':
                        redirect('../student/index.php');
                        break;
                    case 'parent':
                        redirect('../parent/index.php');
                        break;
                    case 'guidance':
                        redirect('../guidance/index.php');
                        break;
                    case 'registrar':
                        redirect('../registrar/index.php');
                        break;
                    case 'accounting':
                        redirect('../accounting/index.php');
                        break;
                    case 'principal':
                        redirect('../principal/index.php');
                        break;
                    default:
                        redirect('../student/index.php');
                }
            } else {
                // Failed login
                $error = 'Invalid username or password.';
                log_activity(null, 'failed_login', "Failed login attempt for username: $username");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduManage</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }

        .login-left {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="1" fill="white" opacity="0.1"/><circle cx="10" cy="90" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .login-left h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .login-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            position: relative;
            z-index: 2;
        }

        .login-right {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #64748b;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-group {
            position: relative;
        }

        .input-group .form-control {
            padding-left: 3rem;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .form-check input {
            width: 1.2rem;
            height: 1.2rem;
        }

        .form-check label {
            margin: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #3b82f6;
            color: #3b82f6;
        }

        .btn-outline:hover {
            background: #3b82f6;
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .login-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .demo-credentials {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .demo-credentials h4 {
            color: #475569;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .demo-credentials .credential-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }

            .login-left {
                padding: 2rem;
                min-height: 200px;
            }

            .login-left h2 {
                font-size: 1.5rem;
            }

            .login-right {
                padding: 2rem;
            }
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading.show {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h2>Welcome to EduManage</h2>
            <p>Your comprehensive school management system. Access your personalized dashboard and stay connected with your educational journey.</p>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h1>Sign In</h1>
                <p>Enter your credentials to access your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="demo-credentials">
                <h4><i class="fas fa-info-circle"></i> Demo Credentials</h4>
                <div class="credential-item">
                    <span>Admin:</span>
                    <span>admin / password</span>
                </div>
                <div class="credential-item">
                    <span>Student:</span>
                    <span>student / password</span>
                </div>
                <div class="credential-item">
                    <span>Teacher:</span>
                    <span>teacher / password</span>
                </div>
            </div>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Enter your username or email" 
                               value="<?= htmlspecialchars($username ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me for 30 days</label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="login-footer">
                <p>Don't have an account? <a href="../enrollment/index.php">Apply for Enrollment</a></p>
                <p><a href="../index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loading').classList.add('show');
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Quick login buttons for demo
        function quickLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.getElementById('loginForm').submit();
        }

        // Add click handlers to demo credentials
        document.querySelectorAll('.credential-item').forEach(item => {
            item.style.cursor = 'pointer';
            item.addEventListener('click', function() {
                const text = this.textContent;
                if (text.includes('admin')) {
                    quickLogin('admin', 'password');
                } else if (text.includes('student')) {
                    quickLogin('student', 'password');
                } else if (text.includes('teacher')) {
                    quickLogin('teacher', 'password');
                }
            });
        });

        // Add hover effect to demo credentials
        document.querySelectorAll('.credential-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#e2e8f0';
                this.style.borderRadius = '0.25rem';
                this.style.padding = '0.25rem';
            });
            item.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'transparent';
                this.style.padding = '0';
            });
        });
    </script>
</body>
</html>

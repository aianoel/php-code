<?php
session_start();
require_once '../includes/config.php';

$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - SchoolEnroll</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .container { 
            background: white; 
            border-radius: 1rem; 
            padding: 3rem; 
            text-align: center; 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); 
            max-width: 500px; 
            width: 90%; 
        }
        .error-icon { 
            font-size: 4rem; 
            color: #dc2626; 
            margin-bottom: 1rem; 
        }
        h1 { 
            color: #1f2937; 
            margin-bottom: 1rem; 
            font-size: 2rem; 
        }
        p { 
            color: #6b7280; 
            margin-bottom: 2rem; 
            line-height: 1.6; 
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            border-radius: 0.5rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            margin: 0.5rem; 
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .user-info { 
            background: #f3f4f6; 
            padding: 1rem; 
            border-radius: 0.5rem; 
            margin-bottom: 2rem; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1>Access Denied</h1>
        <p>You don't have permission to access this page. Your current role doesn't allow access to this resource.</p>
        
        <?php if ($user): ?>
            <div class="user-info">
                <strong>Current User:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?><br>
                <strong>Role:</strong> <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
            </div>
        <?php endif; ?>
        
        <div>
            <?php if ($user): ?>
                <?php 
                // Redirect to appropriate dashboard based on role
                $dashboard_url = match($user['role']) {
                    'admin' => '../admin/index.php',
                    'teacher' => '../teacher/index.php',
                    'student' => '../student/index.php',
                    'academic_coordinator' => '../academic_coordinator/index.php',
                    'parent' => '../parent/index.php',
                    'guidance' => '../guidance/index.php',
                    'registrar' => '../registrar/index.php',
                    'accounting' => '../accounting/index.php',
                    default => '../index.php'
                };
                ?>
                <a href="<?= $dashboard_url ?>" class="btn btn-primary">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
            
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
        </div>
    </div>
</body>
</html>

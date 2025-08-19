<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('principal');
$user = get_logged_in_user();

// Get teacher data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT c.id) as class_count,
               COUNT(DISTINCT s.id) as student_count
        FROM users u
        LEFT JOIN classes c ON u.id = c.teacher_id
        LEFT JOIN student_schedules ss ON c.id = ss.class_id
        LEFT JOIN students s ON ss.student_id = s.id
        WHERE u.role = 'teacher'
        GROUP BY u.id
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
} catch (Exception $e) {
    $teachers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers - Principal Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .teachers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .teacher-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; transition: all 0.3s; }
        .teacher-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        .teacher-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold; margin-bottom: 1rem; }
        .teacher-name { font-size: 1.25rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem; }
        .teacher-info { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .info-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .stats-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .stat { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #3b82f6; }
        .stat-label { font-size: 0.75rem; color: #6b7280; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; margin-right: 0.5rem; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-chalkboard-teacher"></i> Teaching Staff</h1>
                    <p>Overview of all teachers and their assignments</p>
                </div>

                <div class="card">
                    <?php if (empty($teachers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-tie" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No teachers found</h3>
                            <p>No teaching staff are currently registered.</p>
                        </div>
                    <?php else: ?>
                        <div class="teachers-grid">
                            <?php foreach ($teachers as $teacher): ?>
                                <div class="teacher-card">
                                    <div class="teacher-avatar">
                                        <?= strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)) ?>
                                    </div>
                                    
                                    <div class="teacher-name"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></div>
                                    
                                    <div class="teacher-info">
                                        <div class="info-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?= htmlspecialchars($teacher['email']) ?></span>
                                        </div>
                                        <?php if (!empty($teacher['phone'])): ?>
                                            <div class="info-item">
                                                <i class="fas fa-phone"></i>
                                                <span><?= htmlspecialchars($teacher['phone']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="info-item">
                                            <i class="fas fa-calendar"></i>
                                            <span>Joined <?= date('M Y', strtotime($teacher['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="stats-row">
                                        <div class="stat">
                                            <div class="stat-value"><?= $teacher['class_count'] ?></div>
                                            <div class="stat-label">Classes</div>
                                        </div>
                                        <div class="stat">
                                            <div class="stat-value"><?= $teacher['student_count'] ?></div>
                                            <div class="stat-label">Students</div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <a href="#" class="btn btn-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

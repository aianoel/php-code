<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('academic_coordinator');
$user = get_logged_in_user();

// Get academic coordinator statistics
$stats = [];

// Get total courses/subjects
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE status = 'active'");
$stmt->execute();
$stats['total_subjects'] = $stmt->fetchColumn();

// Get total classes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM classes");
$stmt->execute();
$stats['total_classes'] = $stmt->fetchColumn();

// Get total teachers
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'");
$stmt->execute();
$stats['total_teachers'] = $stmt->fetchColumn();

// Get total students
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'");
$stmt->execute();
$stats['total_students'] = $stmt->fetchColumn();

// Get recent academic activities
$stmt = $pdo->prepare("
    SELECT 'subject' as type, name as title, created_at as date, 'Subject created' as description
    FROM subjects 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    UNION ALL
    SELECT 'class' as type, CONCAT(section, ' - ', (SELECT name FROM subjects WHERE id = classes.subject_id)) as title, created_at as date, 'Class created' as description
    FROM classes 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute();
$recentActivities = $stmt->fetchAll();

// Get curriculum overview
$stmt = $pdo->prepare("
    SELECT s.*, COUNT(c.id) as class_count, COUNT(DISTINCT c.teacher_id) as teacher_count
    FROM subjects s
    LEFT JOIN classes c ON s.id = c.subject_id
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.name
");
$stmt->execute();
$subjects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Coordinator Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 2rem 0; }
        .logo { text-align: center; margin-bottom: 2rem; padding: 0 2rem; }
        .logo h2 { color: #1f2937; font-size: 1.5rem; }
        .logo p { color: #6b7280; font-size: 0.875rem; }
        .user-profile { display: flex; align-items: center; gap: 1rem; padding: 1rem 2rem; margin-bottom: 2rem; background: rgba(99, 102, 241, 0.1); }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .user-info h3 { color: #1f2937; font-size: 1rem; }
        .user-info p { color: #6b7280; font-size: 0.875rem; }
        .nav-menu { padding: 0 1rem; }
        .nav-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; margin-bottom: 0.5rem; border-radius: 0.75rem; color: #6b7280; text-decoration: none; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background: #dc2626; color: white; transform: translateX(5px); }
        .nav-item i { width: 20px; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .welcome { margin-bottom: 1rem; }
        .welcome h1 { color: #1f2937; font-size: 2rem; margin-bottom: 0.5rem; }
        .welcome p { color: #6b7280; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); text-align: center; }
        .stat-icon { width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #1f2937; margin-bottom: 0.5rem; }
        .stat-label { color: #6b7280; font-size: 0.875rem; }
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .card h3 { color: #1f2937; margin-bottom: 1rem; }
        .subject-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 0.5rem; }
        .subject-info h4 { color: #1f2937; margin-bottom: 0.25rem; }
        .subject-info p { color: #6b7280; font-size: 0.875rem; }
        .subject-stats { text-align: right; }
        .subject-stats span { display: block; font-size: 0.875rem; color: #6b7280; }
        .activity-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
        .activity-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; }
        .activity-content h4 { color: #1f2937; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .activity-content p { color: #6b7280; font-size: 0.75rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include_sidebar(); ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="welcome">
                    <h1>Welcome, <?= htmlspecialchars($user['first_name']) ?>! 📚</h1>
                    <p>Manage academic programs, curriculum, and coordinate educational activities</p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_subjects'] ?></div>
                    <div class="stat-label">Active Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_classes'] ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_teachers'] ?></div>
                    <div class="stat-label">Active Teachers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_students'] ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Curriculum Overview -->
                <div class="card">
                    <h3>Curriculum Overview</h3>
                    <?php if (empty($subjects)): ?>
                        <div style="text-align: center; padding: 2rem; color: #6b7280;">
                            <i class="fas fa-book" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No subjects available</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($subjects, 0, 8) as $subject): ?>
                            <div class="subject-item">
                                <div class="subject-info">
                                    <h4><?= htmlspecialchars($subject['name']) ?></h4>
                                    <p><?= htmlspecialchars($subject['code']) ?> • <?= htmlspecialchars($subject['units'] ?? 'N/A') ?> units</p>
                                </div>
                                <div class="subject-stats">
                                    <span><?= $subject['class_count'] ?> classes</span>
                                    <span><?= $subject['teacher_count'] ?> teachers</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($subjects) > 8): ?>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="subjects.php" class="btn btn-primary">View All Subjects</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <h3>Recent Academic Activities</h3>
                    <?php if (empty($recentActivities)): ?>
                        <div style="text-align: center; padding: 2rem; color: #6b7280;">
                            <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: <?= $activity['type'] === 'subject' ? '#3b82f6' : '#10b981' ?>; color: white;">
                                    <i class="fas fa-<?= $activity['type'] === 'subject' ? 'book' : 'chalkboard' ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?= htmlspecialchars($activity['title']) ?></h4>
                                    <p><?= htmlspecialchars($activity['description']) ?> • <?= date('M j, Y', strtotime($activity['date'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

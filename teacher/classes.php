<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('teacher');
$user = get_logged_in_user();

// Get teacher ID - create if doesn't exist
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    // Create teacher record
    $employeeId = 'T' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, specialization, hire_date, status) VALUES (?, ?, 'General', 'Teaching', CURDATE(), 'active')");
    $stmt->execute([$user['id'], $employeeId]);
    $teacherId = $pdo->lastInsertId();
    $teacher = ['id' => $teacherId];
}

// Get teacher's classes with detailed information
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.description as subject_description,
           c.schedule_days, c.schedule_time_start, c.schedule_time_end,
           COUNT(DISTINCT ce.student_id) as student_count,
           COUNT(DISTINCT a.id) as assignment_count
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    LEFT JOIN assignments a ON c.id = a.class_id
    WHERE c.teacher_id = ?
    GROUP BY c.id, s.id
    ORDER BY c.section, s.name
");
$stmt->execute([$user['id']]);
$classes = $stmt->fetchAll();

// Get recent activity for each class
$recentActivity = [];
foreach ($classes as $class) {
    $stmt = $pdo->prepare("
        SELECT 'assignment' as type, a.title as title, a.created_at as date, a.due_date
        FROM assignments a 
        WHERE a.class_id = ?
        UNION ALL
        SELECT 'enrollment' as type, CONCAT(u.first_name, ' ', u.last_name, ' enrolled') as title, ce.created_at as date, NULL as due_date
        FROM class_enrollments ce
        JOIN students st ON ce.student_id = st.id
        JOIN users u ON st.user_id = u.id
        WHERE ce.class_id = ? AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$class['id'], $class['id']]);
    $recentActivity[$class['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-info { background: #3b82f6; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .classes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 2rem; }
        .class-card { background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); transition: transform 0.3s; }
        .class-card:hover { transform: translateY(-4px); }
        .class-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .class-title { font-size: 1.25rem; font-weight: bold; color: #1f2937; }
        .class-code { background: #f3f4f6; color: #6b7280; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem; }
        .class-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0; }
        .stat-item { text-align: center; padding: 1rem; background: #f8fafc; border-radius: 0.5rem; }
        .stat-number { font-size: 1.5rem; font-weight: bold; color: #dc2626; }
        .stat-label { font-size: 0.875rem; color: #64748b; }
        .class-description { color: #64748b; margin-bottom: 1rem; font-size: 0.875rem; }
        .recent-activity { margin-top: 1rem; }
        .activity-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; }
        .activity-icon { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; }
        .activity-text { font-size: 0.875rem; color: #64748b; }
        .class-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .empty-state { text-align: center; padding: 4rem 2rem; background: white; border-radius: 1rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .schedule-info { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.875rem; color: #64748b; }
    </style>
    <link rel="stylesheet" href="../includes/sidebar.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>My Classes</h1>
                    <p>Manage your assigned classes and track student progress</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <?php if (empty($classes)): ?>
            <div class="empty-state">
                <i class="fas fa-chalkboard" style="font-size: 4rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <h3>No Classes Assigned</h3>
                <p style="color: #64748b; margin-top: 0.5rem;">You don't have any classes assigned yet. Contact the administration to get your class assignments.</p>
            </div>
        <?php else: ?>
            <div class="classes-grid">
                <?php foreach ($classes as $class): ?>
                    <div class="class-card">
                        <div class="class-header">
                            <div>
                                <div class="class-title"><?= htmlspecialchars($class['subject_name']) ?></div>
                                <div class="schedule-info">
                                    <i class="fas fa-users"></i>
                                    Section: <?= htmlspecialchars($class['section']) ?>
                                </div>
                                <?php if (isset($class['schedule_days']) && !empty($class['schedule_days']) && isset($class['schedule_time_start']) && !empty($class['schedule_time_start'])): ?>
                                    <div class="schedule-info">
                                        <i class="fas fa-clock"></i>
                                        <?= htmlspecialchars($class['schedule_days']) ?> 
                                        <?= date('g:i A', strtotime($class['schedule_time_start'])) ?> - 
                                        <?= date('g:i A', strtotime($class['schedule_time_end'])) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($class['room']): ?>
                                    <div class="schedule-info">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Room: <?= htmlspecialchars($class['room']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="class-code"><?= htmlspecialchars($class['subject_code']) ?></div>
                        </div>

                        <?php if ($class['subject_description']): ?>
                            <div class="class-description">
                                <?= htmlspecialchars($class['subject_description']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="class-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?= $class['student_count'] ?></div>
                                <div class="stat-label">Students</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $class['assignment_count'] ?></div>
                                <div class="stat-label">Assignments</div>
                            </div>
                        </div>

                        <?php if (!empty($recentActivity[$class['id']])): ?>
                            <div class="recent-activity">
                                <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Recent Activity</h4>
                                <?php foreach (array_slice($recentActivity[$class['id']], 0, 3) as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon"></div>
                                        <div class="activity-text">
                                            <?= htmlspecialchars($activity['title']) ?>
                                            <span style="color: #9ca3af;">
                                                • <?= date('M j', strtotime($activity['date'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="class-actions">
                            <a href="students.php?class_id=<?= $class['id'] ?>" class="btn btn-info" style="flex: 1; justify-content: center;">
                                <i class="fas fa-users"></i> View Students
                            </a>
                            <a href="assignments.php?class_id=<?= $class['id'] ?>" class="btn btn-success" style="flex: 1; justify-content: center;">
                                <i class="fas fa-tasks"></i> Assignments
                            </a>
                        </div>
                        
                        <div class="class-actions" style="margin-top: 0.5rem;">
                            <a href="gradebook.php?class_id=<?= $class['id'] ?>" class="btn" style="flex: 1; justify-content: center; background: #8b5cf6; color: white;">
                                <i class="fas fa-chart-line"></i> Gradebook
                            </a>
                            <a href="resources.php?class_id=<?= $class['id'] ?>" class="btn" style="flex: 1; justify-content: center; background: #f59e0b; color: white;">
                                <i class="fas fa-folder"></i> Resources
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

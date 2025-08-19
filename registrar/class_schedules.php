<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('registrar');
$user = get_logged_in_user();

// Get class schedules
try {
    $stmt = $pdo->prepare("
        SELECT c.*, sub.name as subject_name, sub.code as subject_code,
               CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
               COUNT(s.id) as student_count
        FROM classes c
        LEFT JOIN subjects sub ON c.subject_id = sub.id
        LEFT JOIN users t ON c.teacher_id = t.id
        LEFT JOIN student_schedules ss ON c.id = ss.class_id
        LEFT JOIN students s ON ss.student_id = s.id
        GROUP BY c.id
        ORDER BY c.day_of_week, c.start_time
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll();
} catch (Exception $e) {
    $schedules = [];
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedules - Registrar Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .schedule-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-top: 2rem; }
        .day-column { background: white; border-radius: 0.75rem; padding: 1rem; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .day-header { background: #3b82f6; color: white; padding: 0.75rem; border-radius: 0.5rem; text-align: center; font-weight: 600; margin-bottom: 1rem; }
        .class-item { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.5rem; }
        .class-subject { font-weight: 600; color: #1e40af; margin-bottom: 0.25rem; }
        .class-details { font-size: 0.875rem; color: #6b7280; }
        .empty-slot { color: #9ca3af; font-style: italic; text-align: center; padding: 1rem; }
        @media (max-width: 768px) {
            .schedule-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-calendar"></i> Class Schedules</h1>
                    <p>View and manage all class schedules</p>
                </div>

                <div class="card">
                    <div class="schedule-grid">
                        <?php foreach ($days as $day): ?>
                            <div class="day-column">
                                <div class="day-header"><?= $day ?></div>
                                <?php 
                                $day_classes = array_filter($schedules, function($item) use ($day) {
                                    return $item['day_of_week'] === $day;
                                });
                                
                                if (empty($day_classes)): ?>
                                    <div class="empty-slot">No classes scheduled</div>
                                <?php else: ?>
                                    <?php foreach ($day_classes as $class): ?>
                                        <div class="class-item">
                                            <div class="class-subject"><?= htmlspecialchars($class['subject_name']) ?></div>
                                            <div class="class-details">
                                                <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?><br>
                                                <i class="fas fa-door-open"></i> Room <?= htmlspecialchars($class['room']) ?><br>
                                                <i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($class['teacher_name']) ?><br>
                                                <i class="fas fa-users"></i> <?= $class['student_count'] ?> students
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

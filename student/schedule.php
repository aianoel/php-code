<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('student');
$user = get_logged_in_user();

// Get student schedule data
try {
    $stmt = $pdo->prepare("
        SELECT s.*, sub.name as subject_name, sub.code as subject_code, 
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
               c.room, c.capacity
        FROM student_schedules s
        LEFT JOIN subjects sub ON s.subject_id = sub.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE s.student_id = ?
        ORDER BY s.day_of_week, s.start_time
    ");
    $stmt->execute([$user['id']]);
    $schedule = $stmt->fetchAll();
} catch (Exception $e) {
    $schedule = [];
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Student Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .schedule-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-top: 2rem; }
        .day-column { background: white; border-radius: 0.75rem; padding: 1rem; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .day-header { background: #3b82f6; color: white; padding: 0.75rem; border-radius: 0.5rem; text-align: center; font-weight: 600; margin-bottom: 1rem; }
        .time-slot { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.5rem; }
        .subject { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 0.5rem; }
        .subject-name { font-weight: 600; color: #1e40af; }
        .subject-details { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }
        .empty-slot { color: #9ca3af; font-style: italic; text-align: center; }
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
                    <h1><i class="fas fa-calendar"></i> My Class Schedule</h1>
                    <p>View your weekly class schedule and room assignments</p>
                </div>

                <div class="schedule-grid">
                    <?php foreach ($days as $day): ?>
                        <div class="day-column">
                            <div class="day-header"><?= $day ?></div>
                            <?php 
                            $day_schedule = array_filter($schedule, function($item) use ($day) {
                                return $item['day_of_week'] === $day;
                            });
                            
                            if (empty($day_schedule)): ?>
                                <div class="time-slot empty-slot">No classes scheduled</div>
                            <?php else: ?>
                                <?php foreach ($day_schedule as $class): ?>
                                    <div class="subject">
                                        <div class="subject-name"><?= htmlspecialchars($class['subject_name']) ?></div>
                                        <div class="subject-details">
                                            <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?><br>
                                            <i class="fas fa-door-open"></i> Room <?= htmlspecialchars($class['room']) ?><br>
                                            <i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($class['teacher_name']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

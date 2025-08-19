<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('parent');
$user = get_logged_in_user();

// Get parent's children
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               CONCAT(s.first_name, ' ', s.last_name) as full_name,
               c.name as class_name, c.grade_level
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.parent_id = ?
        ORDER BY s.first_name
    ");
    $stmt->execute([$user['id']]);
    $children = $stmt->fetchAll();
} catch (Exception $e) {
    $children = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children - Parent Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .children-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .child-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; transition: all 0.3s; }
        .child-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        .child-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold; margin-bottom: 1rem; }
        .child-name { font-size: 1.25rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem; }
        .child-info { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .info-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; margin-right: 0.5rem; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-child"></i> My Children</h1>
                    <p>View and manage your children's academic information</p>
                </div>

                <div class="card">
                    <?php if (empty($children)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No children found</h3>
                            <p>No student records are linked to your account.</p>
                        </div>
                    <?php else: ?>
                        <div class="children-grid">
                            <?php foreach ($children as $child): ?>
                                <div class="child-card">
                                    <div class="child-avatar">
                                        <?= strtoupper(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1)) ?>
                                    </div>
                                    
                                    <div class="child-name"><?= htmlspecialchars($child['full_name']) ?></div>
                                    
                                    <div class="child-info">
                                        <div class="info-item">
                                            <i class="fas fa-id-card"></i>
                                            <span>Student ID: <?= htmlspecialchars($child['student_id']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-graduation-cap"></i>
                                            <span>Grade: <?= htmlspecialchars($child['grade_level']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-chalkboard"></i>
                                            <span>Class: <?= htmlspecialchars($child['class_name'] ?? 'Not assigned') ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-calendar"></i>
                                            <span>Enrolled: <?= date('M Y', strtotime($child['enrollment_date'])) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <a href="../parent/grades.php?student_id=<?= $child['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-chart-line"></i> View Grades
                                        </a>
                                        <a href="../parent/attendance.php?student_id=<?= $child['id'] ?>" class="btn btn-success">
                                            <i class="fas fa-calendar-check"></i> Attendance
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

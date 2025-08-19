<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('parent');
$user = get_logged_in_user();

$student_id = $_GET['student_id'] ?? null;

// Get child's grades
try {
    if ($student_id) {
        // Verify this child belongs to the parent
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND parent_id = ?");
        $stmt->execute([$student_id, $user['id']]);
        $child = $stmt->fetch();
        
        if ($child) {
            $stmt = $pdo->prepare("
                SELECT g.*, sub.name as subject_name, sub.code as subject_code,
                       CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM grades g
                LEFT JOIN subjects sub ON g.subject_id = sub.id
                LEFT JOIN users t ON g.teacher_id = t.id
                WHERE g.student_id = ?
                ORDER BY g.quarter, sub.name
            ");
            $stmt->execute([$student_id]);
            $grades = $stmt->fetchAll();
        } else {
            $grades = [];
        }
    } else {
        // Get all children's grades
        $stmt = $pdo->prepare("
            SELECT g.*, sub.name as subject_name, sub.code as subject_code,
                   CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                   CONCAT(s.first_name, ' ', s.last_name) as student_name
            FROM grades g
            LEFT JOIN subjects sub ON g.subject_id = sub.id
            LEFT JOIN users t ON g.teacher_id = t.id
            LEFT JOIN students s ON g.student_id = s.id
            WHERE s.parent_id = ?
            ORDER BY s.first_name, g.quarter, sub.name
        ");
        $stmt->execute([$user['id']]);
        $grades = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $grades = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades - Parent Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .grades-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .grades-table th, .grades-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .grades-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .grade-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .grade-a { background: #dcfce7; color: #16a34a; }
        .grade-b { background: #dbeafe; color: #2563eb; }
        .grade-c { background: #fef3c7; color: #d97706; }
        .grade-d { background: #fed7aa; color: #ea580c; }
        .grade-f { background: #fee2e2; color: #dc2626; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        .quarter-section { margin-bottom: 2rem; }
        .quarter-header { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-weight: 600; color: #374151; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-chart-line"></i> Academic Grades</h1>
                    <p><?= $student_id ? "View " . htmlspecialchars($child['first_name'] ?? '') . "'s grades" : "View all your children's grades" ?></p>
                </div>

                <div class="card">
                    <?php if (empty($grades)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No grades available</h3>
                            <p>No grades have been recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $quarters = array_unique(array_column($grades, 'quarter'));
                        sort($quarters);
                        ?>
                        
                        <?php foreach ($quarters as $quarter): ?>
                            <div class="quarter-section">
                                <div class="quarter-header">
                                    <i class="fas fa-calendar-alt"></i> Quarter <?= $quarter ?>
                                </div>
                                
                                <table class="grades-table">
                                    <thead>
                                        <tr>
                                            <?php if (!$student_id): ?>
                                                <th>Student</th>
                                            <?php endif; ?>
                                            <th>Subject</th>
                                            <th>Teacher</th>
                                            <th>Grade</th>
                                            <th>Percentage</th>
                                            <th>Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $quarter_grades = array_filter($grades, function($g) use ($quarter) {
                                            return $g['quarter'] == $quarter;
                                        });
                                        ?>
                                        <?php foreach ($quarter_grades as $grade): ?>
                                            <tr>
                                                <?php if (!$student_id): ?>
                                                    <td><?= htmlspecialchars($grade['student_name']) ?></td>
                                                <?php endif; ?>
                                                <td>
                                                    <strong><?= htmlspecialchars($grade['subject_name']) ?></strong><br>
                                                    <small><?= htmlspecialchars($grade['subject_code']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($grade['teacher_name']) ?></td>
                                                <td>
                                                    <?php
                                                    $grade_class = 'grade-c';
                                                    if ($grade['grade'] >= 90) $grade_class = 'grade-a';
                                                    elseif ($grade['grade'] >= 80) $grade_class = 'grade-b';
                                                    elseif ($grade['grade'] >= 70) $grade_class = 'grade-c';
                                                    elseif ($grade['grade'] >= 60) $grade_class = 'grade-d';
                                                    else $grade_class = 'grade-f';
                                                    ?>
                                                    <span class="grade-badge <?= $grade_class ?>">
                                                        <?= number_format($grade['grade'], 1) ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($grade['grade'], 1) ?>%</td>
                                                <td><?= htmlspecialchars($grade['comments'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

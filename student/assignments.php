<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('student');
$user = get_logged_in_user();

// Get student assignments
try {
    $stmt = $pdo->prepare("
        SELECT a.*, sub.name as subject_name, sub.code as subject_code,
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM assignments a
        LEFT JOIN subjects sub ON a.subject_id = sub.id
        LEFT JOIN users u ON a.teacher_id = u.id
        WHERE a.class_id IN (
            SELECT class_id FROM student_schedules WHERE student_id = ?
        )
        ORDER BY a.due_date ASC
    ");
    $stmt->execute([$user['id']]);
    $assignments = $stmt->fetchAll();
} catch (Exception $e) {
    $assignments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - Student Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .assignment-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; transition: all 0.3s; }
        .assignment-item:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .assignment-header { display: flex; justify-content: between; align-items: start; margin-bottom: 1rem; }
        .assignment-title { font-size: 1.25rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem; }
        .assignment-meta { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-submitted { background: #d1fae5; color: #065f46; }
        .assignment-description { color: #4b5563; margin-bottom: 1rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
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
                    <h1><i class="fas fa-tasks"></i> My Assignments</h1>
                    <p>View and manage your class assignments</p>
                </div>

                <div class="card">
                    <?php if (empty($assignments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No assignments found</h3>
                            <p>You don't have any assignments at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="assignment-item">
                                <div class="assignment-header">
                                    <div style="flex: 1;">
                                        <div class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></div>
                                        <div class="assignment-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-book"></i>
                                                <span><?= htmlspecialchars($assignment['subject_name']) ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-chalkboard-teacher"></i>
                                                <span><?= htmlspecialchars($assignment['teacher_name']) ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-calendar"></i>
                                                <span>Due: <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <?php
                                        $status = 'pending';
                                        $status_text = 'Pending';
                                        if (strtotime($assignment['due_date']) < time()) {
                                            $status = 'overdue';
                                            $status_text = 'Overdue';
                                        }
                                        ?>
                                        <span class="status-badge status-<?= $status ?>"><?= $status_text ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($assignment['description'])): ?>
                                    <div class="assignment-description">
                                        <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <a href="#" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

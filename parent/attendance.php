<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('parent');
$user = get_logged_in_user();

$student_id = $_GET['student_id'] ?? null;

// Get child's attendance
try {
    if ($student_id) {
        // Verify this child belongs to the parent
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND parent_id = ?");
        $stmt->execute([$student_id, $user['id']]);
        $child = $stmt->fetch();
        
        if ($child) {
            $stmt = $pdo->prepare("
                SELECT a.*, sub.name as subject_name, sub.code as subject_code
                FROM attendance a
                LEFT JOIN subjects sub ON a.subject_id = sub.id
                WHERE a.student_id = ?
                ORDER BY a.date DESC, sub.name
            ");
            $stmt->execute([$student_id]);
            $attendance = $stmt->fetchAll();
        } else {
            $attendance = [];
        }
    } else {
        // Get all children's attendance
        $stmt = $pdo->prepare("
            SELECT a.*, sub.name as subject_name, sub.code as subject_code,
                   CONCAT(s.first_name, ' ', s.last_name) as student_name
            FROM attendance a
            LEFT JOIN subjects sub ON a.subject_id = sub.id
            LEFT JOIN students s ON a.student_id = s.id
            WHERE s.parent_id = ?
            ORDER BY a.date DESC, s.first_name, sub.name
        ");
        $stmt->execute([$user['id']]);
        $attendance = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $attendance = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Parent Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .attendance-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .attendance-table th, .attendance-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .attendance-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .status-present { background: #dcfce7; color: #16a34a; }
        .status-absent { background: #fee2e2; color: #dc2626; }
        .status-late { background: #fef3c7; color: #d97706; }
        .status-excused { background: #dbeafe; color: #2563eb; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #f8fafc; border-radius: 0.75rem; padding: 1.5rem; text-align: center; }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { color: #64748b; }
        .present { color: #16a34a; }
        .absent { color: #dc2626; }
        .late { color: #d97706; }
        .excused { color: #2563eb; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-calendar-check"></i> Attendance Records</h1>
                    <p><?= $student_id ? "View " . htmlspecialchars($child['first_name'] ?? '') . "'s attendance" : "View all your children's attendance" ?></p>
                </div>

                <?php if (!empty($attendance)): ?>
                    <?php
                    $total = count($attendance);
                    $present = count(array_filter($attendance, function($a) { return $a['status'] === 'present'; }));
                    $absent = count(array_filter($attendance, function($a) { return $a['status'] === 'absent'; }));
                    $late = count(array_filter($attendance, function($a) { return $a['status'] === 'late'; }));
                    $excused = count(array_filter($attendance, function($a) { return $a['status'] === 'excused'; }));
                    ?>
                    
                    <div class="card">
                        <h3 style="margin-bottom: 1rem;">Attendance Summary</h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value present"><?= $present ?></div>
                                <div class="stat-label">Present</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value absent"><?= $absent ?></div>
                                <div class="stat-label">Absent</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value late"><?= $late ?></div>
                                <div class="stat-label">Late</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value excused"><?= $excused ?></div>
                                <div class="stat-label">Excused</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($attendance)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No attendance records</h3>
                            <p>No attendance records have been recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <?php if (!$student_id): ?>
                                        <th>Student</th>
                                    <?php endif; ?>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $record): ?>
                                    <tr>
                                        <?php if (!$student_id): ?>
                                            <td><?= htmlspecialchars($record['student_name']) ?></td>
                                        <?php endif; ?>
                                        <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($record['subject_name']) ?></strong><br>
                                            <small><?= htmlspecialchars($record['subject_code']) ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $record['status'] ?>">
                                                <?= ucfirst($record['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

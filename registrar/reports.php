<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('registrar');
$user = get_logged_in_user();

// Get enrollment statistics
try {
    $enrollment_stats = [
        'total_students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'new_enrollments' => $pdo->query("SELECT COUNT(*) FROM students WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
        'pending_applications' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'pending'")->fetchColumn(),
        'approved_applications' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'approved'")->fetchColumn()
    ];
    
    // Get grade level distribution
    $stmt = $pdo->prepare("SELECT grade_level, COUNT(*) as count FROM students GROUP BY grade_level ORDER BY grade_level");
    $stmt->execute();
    $grade_distribution = $stmt->fetchAll();
    
    // Get recent enrollments
    $stmt = $pdo->prepare("
        SELECT CONCAT(first_name, ' ', last_name) as full_name, 
               student_id, grade_level, enrollment_date
        FROM students 
        ORDER BY enrollment_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_enrollments = $stmt->fetchAll();
    
} catch (Exception $e) {
    $enrollment_stats = ['total_students' => 0, 'new_enrollments' => 0, 'pending_applications' => 0, 'approved_applications' => 0];
    $grade_distribution = [];
    $recent_enrollments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Registrar Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; }
        .stat-value { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { opacity: 0.9; }
        .chart-container { background: #f8fafc; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 2rem; }
        .chart-bar { display: flex; align-items: center; margin-bottom: 1rem; }
        .chart-label { width: 100px; font-weight: 600; }
        .chart-progress { flex: 1; background: #e5e7eb; border-radius: 0.5rem; height: 20px; margin: 0 1rem; position: relative; }
        .chart-fill { background: #3b82f6; height: 100%; border-radius: 0.5rem; }
        .chart-value { font-weight: 600; color: #1f2937; }
        .recent-table { width: 100%; border-collapse: collapse; }
        .recent-table th, .recent-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .recent-table th { background: #f8fafc; font-weight: 600; color: #374151; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-file-alt"></i> Enrollment Reports</h1>
                    <p>View enrollment statistics and reports</p>
                </div>

                <!-- Statistics Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $enrollment_stats['total_students'] ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #16a34a, #15803d);">
                        <div class="stat-value"><?= $enrollment_stats['new_enrollments'] ?></div>
                        <div class="stat-label">New Enrollments (30 days)</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #d97706, #b45309);">
                        <div class="stat-value"><?= $enrollment_stats['pending_applications'] ?></div>
                        <div class="stat-label">Pending Applications</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #7c3aed, #5b21b6);">
                        <div class="stat-value"><?= $enrollment_stats['approved_applications'] ?></div>
                        <div class="stat-label">Approved Applications</div>
                    </div>
                </div>

                <!-- Grade Level Distribution -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Student Distribution by Grade Level</h3>
                    <div class="chart-container">
                        <?php if (!empty($grade_distribution)): ?>
                            <?php 
                            $max_count = max(array_column($grade_distribution, 'count'));
                            foreach ($grade_distribution as $grade): 
                                $percentage = $max_count > 0 ? ($grade['count'] / $max_count) * 100 : 0;
                            ?>
                                <div class="chart-bar">
                                    <div class="chart-label">Grade <?= htmlspecialchars($grade['grade_level']) ?></div>
                                    <div class="chart-progress">
                                        <div class="chart-fill" style="width: <?= $percentage ?>%;"></div>
                                    </div>
                                    <div class="chart-value"><?= $grade['count'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #6b7280;">No enrollment data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Enrollments -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Recent Enrollments</h3>
                    <?php if (!empty($recent_enrollments)): ?>
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Grade Level</th>
                                    <th>Enrollment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_enrollments as $enrollment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($enrollment['full_name']) ?></td>
                                        <td><?= htmlspecialchars($enrollment['student_id']) ?></td>
                                        <td>Grade <?= htmlspecialchars($enrollment['grade_level']) ?></td>
                                        <td><?= date('M j, Y', strtotime($enrollment['enrollment_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No recent enrollments</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('principal');
$user = get_logged_in_user();

// Get comprehensive school statistics
try {
    $school_stats = [
        'total_students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'total_teachers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
        'total_classes' => $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
        'total_subjects' => $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
        'average_grade' => $pdo->query("SELECT AVG(grade) FROM grades")->fetchColumn(),
        'attendance_rate' => $pdo->query("SELECT (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*)) FROM attendance")->fetchColumn()
    ];
    
    // Monthly enrollment trends
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(enrollment_date, '%Y-%m') as month,
               COUNT(*) as enrollments
        FROM students 
        WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $enrollment_trends = $stmt->fetchAll();
    
    // Grade level performance
    $stmt = $pdo->prepare("
        SELECT s.grade_level,
               COUNT(DISTINCT s.id) as student_count,
               AVG(g.grade) as average_grade
        FROM students s
        LEFT JOIN grades g ON s.id = g.student_id
        GROUP BY s.grade_level
        ORDER BY s.grade_level
    ");
    $stmt->execute();
    $grade_performance = $stmt->fetchAll();
    
    // Teacher workload
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
               COUNT(DISTINCT c.id) as class_count,
               COUNT(DISTINCT ss.student_id) as student_count
        FROM users u
        LEFT JOIN classes c ON u.id = c.teacher_id
        LEFT JOIN student_schedules ss ON c.id = ss.class_id
        WHERE u.role = 'teacher'
        GROUP BY u.id
        ORDER BY class_count DESC
    ");
    $stmt->execute();
    $teacher_workload = $stmt->fetchAll();
    
} catch (Exception $e) {
    $school_stats = array_fill_keys(['total_students', 'total_teachers', 'total_classes', 'total_subjects', 'average_grade', 'attendance_rate'], 0);
    $enrollment_trends = [];
    $grade_performance = [];
    $teacher_workload = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Reports - Principal Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; }
        .stat-card.success { background: linear-gradient(135deg, #16a34a, #15803d); }
        .stat-card.warning { background: linear-gradient(135deg, #d97706, #b45309); }
        .stat-card.info { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { opacity: 0.9; }
        .chart-container { background: #f8fafc; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 2rem; }
        .chart-bar { display: flex; align-items: center; margin-bottom: 1rem; }
        .chart-label { width: 150px; font-weight: 600; }
        .chart-progress { flex: 1; background: #e5e7eb; border-radius: 0.5rem; height: 25px; margin: 0 1rem; position: relative; }
        .chart-fill { height: 100%; border-radius: 0.5rem; background: #3b82f6; }
        .chart-value { font-weight: 600; color: #1f2937; }
        .reports-table { width: 100%; border-collapse: collapse; }
        .reports-table th, .reports-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .reports-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .trend-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; }
        .trend-month { font-weight: 600; }
        .trend-value { color: #3b82f6; font-weight: 600; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-file-alt"></i> School Performance Reports</h1>
                    <p>Comprehensive overview of school statistics and performance metrics</p>
                </div>

                <!-- Key Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $school_stats['total_students'] ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= $school_stats['total_teachers'] ?></div>
                        <div class="stat-label">Teaching Staff</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value"><?= $school_stats['total_classes'] ?></div>
                        <div class="stat-label">Active Classes</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= number_format($school_stats['average_grade'], 1) ?>%</div>
                        <div class="stat-label">School Average</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= number_format($school_stats['attendance_rate'], 1) ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value"><?= $school_stats['total_subjects'] ?></div>
                        <div class="stat-label">Subjects Offered</div>
                    </div>
                </div>

                <!-- Enrollment Trends -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Monthly Enrollment Trends (Last 12 Months)</h3>
                    <?php if (!empty($enrollment_trends)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($enrollment_trends as $trend): ?>
                                <div class="trend-item">
                                    <div class="trend-month"><?= date('F Y', strtotime($trend['month'] . '-01')) ?></div>
                                    <div class="trend-value"><?= $trend['enrollments'] ?> new students</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No enrollment data available</p>
                    <?php endif; ?>
                </div>

                <!-- Grade Level Performance -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Academic Performance by Grade Level</h3>
                    <div class="chart-container">
                        <?php if (!empty($grade_performance)): ?>
                            <?php 
                            $max_students = max(array_column($grade_performance, 'student_count'));
                            foreach ($grade_performance as $grade): 
                                $percentage = $max_students > 0 ? ($grade['student_count'] / $max_students) * 100 : 0;
                            ?>
                                <div class="chart-bar">
                                    <div class="chart-label">Grade <?= htmlspecialchars($grade['grade_level']) ?></div>
                                    <div class="chart-progress">
                                        <div class="chart-fill" style="width: <?= $percentage ?>%;"></div>
                                    </div>
                                    <div class="chart-value">
                                        <?= $grade['student_count'] ?> students 
                                        (Avg: <?= number_format($grade['average_grade'], 1) ?>%)
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #6b7280;">No grade performance data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Teacher Workload -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Teacher Workload Distribution</h3>
                    <?php if (!empty($teacher_workload)): ?>
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Teacher Name</th>
                                    <th>Classes Assigned</th>
                                    <th>Total Students</th>
                                    <th>Workload Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teacher_workload as $teacher): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($teacher['teacher_name']) ?></td>
                                        <td><?= $teacher['class_count'] ?></td>
                                        <td><?= $teacher['student_count'] ?></td>
                                        <td>
                                            <?php
                                            $status = 'Normal';
                                            $color = '#16a34a';
                                            if ($teacher['class_count'] > 6) {
                                                $status = 'Heavy';
                                                $color = '#dc2626';
                                            } elseif ($teacher['class_count'] > 4) {
                                                $status = 'Moderate';
                                                $color = '#d97706';
                                            }
                                            ?>
                                            <span style="color: <?= $color ?>; font-weight: 600;"><?= $status ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No teacher workload data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

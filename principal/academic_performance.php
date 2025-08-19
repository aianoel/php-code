<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('principal');
$user = get_logged_in_user();

// Get academic performance data
try {
    // Overall performance statistics
    $performance_stats = [
        'total_students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'average_grade' => $pdo->query("SELECT AVG(grade) FROM grades")->fetchColumn(),
        'top_performers' => $pdo->query("SELECT COUNT(DISTINCT student_id) FROM grades WHERE grade >= 90")->fetchColumn(),
        'at_risk_students' => $pdo->query("SELECT COUNT(DISTINCT student_id) FROM grades WHERE grade < 70")->fetchColumn()
    ];
    
    // Grade distribution
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN grade >= 90 THEN 'A (90-100)'
                WHEN grade >= 80 THEN 'B (80-89)'
                WHEN grade >= 70 THEN 'C (70-79)'
                WHEN grade >= 60 THEN 'D (60-69)'
                ELSE 'F (Below 60)'
            END as grade_range,
            COUNT(*) as count
        FROM grades 
        GROUP BY 
            CASE 
                WHEN grade >= 90 THEN 'A (90-100)'
                WHEN grade >= 80 THEN 'B (80-89)'
                WHEN grade >= 70 THEN 'C (70-79)'
                WHEN grade >= 60 THEN 'D (60-69)'
                ELSE 'F (Below 60)'
            END
        ORDER BY MIN(grade) DESC
    ");
    $stmt->execute();
    $grade_distribution = $stmt->fetchAll();
    
    // Subject performance
    $stmt = $pdo->prepare("
        SELECT s.name as subject_name, 
               AVG(g.grade) as average_grade,
               COUNT(g.id) as total_grades
        FROM subjects s
        LEFT JOIN grades g ON s.id = g.subject_id
        GROUP BY s.id, s.name
        HAVING COUNT(g.id) > 0
        ORDER BY average_grade DESC
    ");
    $stmt->execute();
    $subject_performance = $stmt->fetchAll();
    
    // Top performing students
    $stmt = $pdo->prepare("
        SELECT CONCAT(st.first_name, ' ', st.last_name) as student_name,
               st.grade_level,
               AVG(g.grade) as average_grade
        FROM students st
        JOIN grades g ON st.id = g.student_id
        GROUP BY st.id
        ORDER BY average_grade DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_students = $stmt->fetchAll();
    
} catch (Exception $e) {
    $performance_stats = ['total_students' => 0, 'average_grade' => 0, 'top_performers' => 0, 'at_risk_students' => 0];
    $grade_distribution = [];
    $subject_performance = [];
    $top_students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Performance - Principal Portal</title>
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
        .stat-card.danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { opacity: 0.9; }
        .chart-container { background: #f8fafc; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 2rem; }
        .chart-bar { display: flex; align-items: center; margin-bottom: 1rem; }
        .chart-label { width: 120px; font-weight: 600; }
        .chart-progress { flex: 1; background: #e5e7eb; border-radius: 0.5rem; height: 25px; margin: 0 1rem; position: relative; }
        .chart-fill { height: 100%; border-radius: 0.5rem; }
        .chart-value { font-weight: 600; color: #1f2937; }
        .performance-table { width: 100%; border-collapse: collapse; }
        .performance-table th, .performance-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .performance-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .grade-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .grade-a { background: #dcfce7; color: #16a34a; }
        .grade-b { background: #dbeafe; color: #2563eb; }
        .grade-c { background: #fef3c7; color: #d97706; }
        .grade-d { background: #fed7aa; color: #ea580c; }
        .grade-f { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-chart-line"></i> Academic Performance Overview</h1>
                    <p>Comprehensive analysis of student academic performance</p>
                </div>

                <!-- Performance Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $performance_stats['total_students'] ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= number_format($performance_stats['average_grade'], 1) ?>%</div>
                        <div class="stat-label">School Average</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= $performance_stats['top_performers'] ?></div>
                        <div class="stat-label">Top Performers (90%+)</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value"><?= $performance_stats['at_risk_students'] ?></div>
                        <div class="stat-label">At-Risk Students (<70%)</div>
                    </div>
                </div>

                <!-- Grade Distribution -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Grade Distribution</h3>
                    <div class="chart-container">
                        <?php if (!empty($grade_distribution)): ?>
                            <?php 
                            $max_count = max(array_column($grade_distribution, 'count'));
                            $colors = ['#16a34a', '#2563eb', '#d97706', '#ea580c', '#dc2626'];
                            foreach ($grade_distribution as $index => $grade): 
                                $percentage = $max_count > 0 ? ($grade['count'] / $max_count) * 100 : 0;
                                $color = $colors[$index % count($colors)];
                            ?>
                                <div class="chart-bar">
                                    <div class="chart-label"><?= htmlspecialchars($grade['grade_range']) ?></div>
                                    <div class="chart-progress">
                                        <div class="chart-fill" style="width: <?= $percentage ?>%; background: <?= $color ?>;"></div>
                                    </div>
                                    <div class="chart-value"><?= $grade['count'] ?> students</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #6b7280;">No grade data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Subject Performance -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Subject Performance</h3>
                    <?php if (!empty($subject_performance)): ?>
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Average Grade</th>
                                    <th>Total Assessments</th>
                                    <th>Performance Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subject_performance as $subject): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                        <td><?= number_format($subject['average_grade'], 1) ?>%</td>
                                        <td><?= $subject['total_grades'] ?></td>
                                        <td>
                                            <?php
                                            $avg = $subject['average_grade'];
                                            $badge_class = 'grade-f';
                                            $level = 'Needs Improvement';
                                            if ($avg >= 90) { $badge_class = 'grade-a'; $level = 'Excellent'; }
                                            elseif ($avg >= 80) { $badge_class = 'grade-b'; $level = 'Good'; }
                                            elseif ($avg >= 70) { $badge_class = 'grade-c'; $level = 'Satisfactory'; }
                                            elseif ($avg >= 60) { $badge_class = 'grade-d'; $level = 'Below Average'; }
                                            ?>
                                            <span class="grade-badge <?= $badge_class ?>"><?= $level ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No subject performance data available</p>
                    <?php endif; ?>
                </div>

                <!-- Top Performing Students -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Top Performing Students</h3>
                    <?php if (!empty($top_students)): ?>
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student Name</th>
                                    <th>Grade Level</th>
                                    <th>Average Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_students as $index => $student): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index < 3): ?>
                                                <i class="fas fa-trophy" style="color: <?= $index === 0 ? '#ffd700' : ($index === 1 ? '#c0c0c0' : '#cd7f32') ?>;"></i>
                                            <?php endif; ?>
                                            #<?= $index + 1 ?>
                                        </td>
                                        <td><?= htmlspecialchars($student['student_name']) ?></td>
                                        <td>Grade <?= htmlspecialchars($student['grade_level']) ?></td>
                                        <td>
                                            <span class="grade-badge grade-a"><?= number_format($student['average_grade'], 1) ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No student performance data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

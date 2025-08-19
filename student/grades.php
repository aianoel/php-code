<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('student');
$user = get_logged_in_user();

// No need to define ALLOW_ACCESS here as include_sidebar() will handle it

// Get student record
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

if (!$student) {
    die("Student record not found.");
}

// Get current school year
$current_year = date('Y') . '-' . (date('Y') + 1);
$selected_year = $_GET['year'] ?? $current_year;

// Get student's enrolled classes with grades
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department, s.units,
           u.first_name as teacher_first, u.last_name as teacher_last,
           qg1.grade as q1_grade, qg1.remarks as q1_remarks,
           qg2.grade as q2_grade, qg2.remarks as q2_remarks,
           qg3.grade as q3_grade, qg3.remarks as q3_remarks,
           qg4.grade as q4_grade, qg4.remarks as q4_remarks
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    JOIN subjects s ON c.subject_id = s.id
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN quarterly_grades qg1 ON (ce.student_id = qg1.student_id AND c.id = qg1.class_id AND qg1.quarter = '1st' AND qg1.school_year = ?)
    LEFT JOIN quarterly_grades qg2 ON (ce.student_id = qg2.student_id AND c.id = qg2.class_id AND qg2.quarter = '2nd' AND qg2.school_year = ?)
    LEFT JOIN quarterly_grades qg3 ON (ce.student_id = qg3.student_id AND c.id = qg3.class_id AND qg3.quarter = '3rd' AND qg3.school_year = ?)
    LEFT JOIN quarterly_grades qg4 ON (ce.student_id = qg4.student_id AND c.id = qg4.class_id AND qg4.quarter = '4th' AND qg4.school_year = ?)
    WHERE ce.student_id = ? AND ce.status = 'active'
    ORDER BY s.department, s.name
");
$stmt->execute([$selected_year, $selected_year, $selected_year, $selected_year, $student['id']]);
$classes = $stmt->fetchAll();

// Calculate GPA and statistics
$total_units = 0;
$total_grade_points = 0;
$subjects_with_grades = 0;
$quarterly_stats = [
    '1st' => ['total' => 0, 'count' => 0],
    '2nd' => ['total' => 0, 'count' => 0],
    '3rd' => ['total' => 0, 'count' => 0],
    '4th' => ['total' => 0, 'count' => 0]
];

foreach ($classes as $class) {
    $units = $class['units'] ?? 3; // Default to 3 units if not specified
    $total_units += $units;
    
    // Calculate final grade (average of all quarters with grades)
    $grades = array_filter([
        $class['q1_grade'],
        $class['q2_grade'],
        $class['q3_grade'],
        $class['q4_grade']
    ]);
    
    if (!empty($grades)) {
        $final_grade = array_sum($grades) / count($grades);
        $total_grade_points += $final_grade * $units;
        $subjects_with_grades++;
    }
    
    // Calculate quarterly statistics
    foreach (['1st' => 'q1_grade', '2nd' => 'q2_grade', '3rd' => 'q3_grade', '4th' => 'q4_grade'] as $quarter => $grade_field) {
        if ($class[$grade_field]) {
            $quarterly_stats[$quarter]['total'] += $class[$grade_field];
            $quarterly_stats[$quarter]['count']++;
        }
    }
}

$current_gpa = $total_units > 0 ? $total_grade_points / $total_units : 0;

// Calculate quarterly averages
foreach ($quarterly_stats as $quarter => &$stats) {
    $stats['average'] = $stats['count'] > 0 ? $stats['total'] / $stats['count'] : 0;
}

// Get grade distribution
function getGradeLevel($grade) {
    if ($grade >= 97) return 'A+';
    if ($grade >= 94) return 'A';
    if ($grade >= 90) return 'A-';
    if ($grade >= 87) return 'B+';
    if ($grade >= 84) return 'B';
    if ($grade >= 80) return 'B-';
    if ($grade >= 77) return 'C+';
    if ($grade >= 74) return 'C';
    if ($grade >= 70) return 'C-';
    if ($grade >= 67) return 'D+';
    if ($grade >= 65) return 'D';
    return 'F';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - Student</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 1.5rem; border-radius: 1rem; text-align: center; }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { font-size: 0.875rem; opacity: 0.9; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f8fafc; font-weight: 600; }
        .grade-cell { text-align: center; font-weight: 600; }
        .grade-A { background: #dcfce7; color: #16a34a; }
        .grade-B { background: #dbeafe; color: #2563eb; }
        .grade-C { background: #fef3c7; color: #d97706; }
        .grade-D { background: #fee2e2; color: #dc2626; }
        .grade-F { background: #fecaca; color: #dc2626; }
        .quarter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .quarter-tab { padding: 0.75rem 1.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s; }
        .quarter-tab.active { background: #3b82f6; color: white; border-color: #3b82f6; }
        .gpa-indicator { font-size: 3rem; font-weight: bold; text-align: center; }
        .gpa-excellent { color: #16a34a; }
        .gpa-good { color: #2563eb; }
        .gpa-average { color: #d97706; }
        .gpa-poor { color: #dc2626; }
        .subject-row:hover { background: #f8fafc; }
        .no-grade { color: #6b7280; font-style: italic; }
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
                    <h1>My Grades</h1>
                    <p>View your academic performance by quarter</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value gpa-indicator <?= 
                    $current_gpa >= 90 ? 'gpa-excellent' : (
                        $current_gpa >= 80 ? 'gpa-good' : (
                            $current_gpa >= 70 ? 'gpa-average' : 'gpa-poor'
                        )
                    ) 
                ?>"><?= number_format($current_gpa, 2) ?></div>
                <div class="stat-label">Current GPA</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #16a34a, #15803d);">
                <div class="stat-value"><?= $subjects_with_grades ?></div>
                <div class="stat-label">Subjects with Grades</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #d97706, #b45309);">
                <div class="stat-value"><?= count($classes) ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #7c3aed, #5b21b6);">
                <div class="stat-value"><?= $total_units ?></div>
                <div class="stat-label">Total Units</div>
            </div>
        </div>

        <!-- Quarterly Performance -->
        <div class="card">
            <h3>Quarterly Performance</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1rem;">
                <?php foreach ($quarterly_stats as $quarter => $stats): ?>
                    <div style="text-align: center; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem;">
                        <div style="font-weight: 600; margin-bottom: 0.5rem;"><?= $quarter ?> Quarter</div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #3b82f6;">
                            <?= $stats['count'] > 0 ? number_format($stats['average'], 1) : 'N/A' ?>
                        </div>
                        <div style="font-size: 0.875rem; color: #6b7280;">
                            <?= $stats['count'] ?> subjects
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grades Table -->
        <div class="card">
            <h3>Subject Grades (<?= $selected_year ?>)</h3>
            
            <?php if (!empty($classes)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Code</th>
                            <th>Teacher</th>
                            <th>Units</th>
                            <th>1st Quarter</th>
                            <th>2nd Quarter</th>
                            <th>3rd Quarter</th>
                            <th>4th Quarter</th>
                            <th>Final Grade</th>
                            <th>Letter Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_department = '';
                        foreach ($classes as $class): 
                            if ($current_department !== $class['department']):
                                $current_department = $class['department'];
                        ?>
                            <tr style="background: #f1f5f9;">
                                <td colspan="10" style="font-weight: bold; color: #374151;">
                                    <?= htmlspecialchars($current_department) ?> Department
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr class="subject-row">
                            <td><?= htmlspecialchars($class['subject_name']) ?></td>
                            <td><?= htmlspecialchars($class['subject_code']) ?></td>
                            <td><?= htmlspecialchars($class['teacher_first'] . ' ' . $class['teacher_last']) ?></td>
                            <td><?= $class['units'] ?? 3 ?></td>
                            
                            <!-- Quarter Grades -->
                            <?php foreach (['q1_grade', 'q2_grade', 'q3_grade', 'q4_grade'] as $quarter_field): ?>
                                <td class="grade-cell">
                                    <?php if ($class[$quarter_field]): ?>
                                        <span class="<?= 
                                            $class[$quarter_field] >= 90 ? 'grade-A' : (
                                                $class[$quarter_field] >= 80 ? 'grade-B' : (
                                                    $class[$quarter_field] >= 70 ? 'grade-C' : (
                                                        $class[$quarter_field] >= 60 ? 'grade-D' : 'grade-F'
                                                    )
                                                )
                                            )
                                        ?>" style="padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
                                            <?= number_format($class[$quarter_field], 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-grade">Not graded</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            
                            <!-- Final Grade -->
                            <td class="grade-cell">
                                <?php 
                                $grades = array_filter([
                                    $class['q1_grade'],
                                    $class['q2_grade'],
                                    $class['q3_grade'],
                                    $class['q4_grade']
                                ]);
                                
                                if (!empty($grades)):
                                    $final_grade = array_sum($grades) / count($grades);
                                ?>
                                    <span class="<?= 
                                        $final_grade >= 90 ? 'grade-A' : (
                                            $final_grade >= 80 ? 'grade-B' : (
                                                $final_grade >= 70 ? 'grade-C' : (
                                                    $final_grade >= 60 ? 'grade-D' : 'grade-F'
                                                )
                                            )
                                        )
                                    ?>" style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-weight: bold;">
                                        <?= number_format($final_grade, 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="no-grade">Incomplete</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Letter Grade -->
                            <td class="grade-cell">
                                <?php if (!empty($grades)): ?>
                                    <strong><?= getGradeLevel($final_grade) ?></strong>
                                <?php else: ?>
                                    <span class="no-grade">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #6b7280; padding: 2rem;">No subjects enrolled for this school year.</p>
            <?php endif; ?>
        </div>
            </div>
        </main>
    </div>
</body>
</html>

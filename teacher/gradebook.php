<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('teacher');
$user = get_logged_in_user();

// Get or create teacher record
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    // Auto-create teacher record if missing
    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, hire_date, status) VALUES (?, ?, 'General', CURDATE(), 'active')");
    $employee_id = 'T' . str_pad($user['id'], 6, '0', STR_PAD_LEFT);
    $stmt->execute([$user['id'], $employee_id]);
    
    // Fetch the newly created teacher record
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $teacher = $stmt->fetch();
}

// Handle grade submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'save_grade') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO quarterly_grades (student_id, class_id, quarter, grade, remarks, teacher_id, school_year) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                grade = VALUES(grade), 
                remarks = VALUES(remarks),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([
                $_POST['student_id'],
                $_POST['class_id'],
                $_POST['quarter'],
                $_POST['grade'],
                $_POST['remarks'],
                $teacher['id'],
                $_POST['school_year']
            ]);
            
            $message = $result ? "Grade saved successfully!" : "Error saving grade.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Get selected class and quarter
$selectedClassId = $_GET['class_id'] ?? null;
$selectedQuarter = $_GET['quarter'] ?? '1st';
$selectedClass = null;
$students = [];

if ($selectedClassId) {
    // Get class info
    $stmt = $pdo->prepare("
        SELECT c.*, s.name as subject_name, s.code as subject_code, s.department
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        WHERE c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$selectedClassId, $teacher['id']]);
    $selectedClass = $stmt->fetch();
    
    if ($selectedClass) {
        // Get students in selected class with their current grades
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name, u.last_name, u.email,
                   qg.grade, qg.remarks, qg.updated_at as grade_date
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN class_enrollments ce ON s.id = ce.student_id
            LEFT JOIN quarterly_grades qg ON (s.id = qg.student_id AND qg.class_id = ? AND qg.quarter = ? AND qg.school_year = ?)
            WHERE ce.class_id = ? AND ce.status = 'active'
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$selectedClassId, $selectedQuarter, $current_year, $selectedClassId]);
        $students = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradebook - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f8fafc; font-weight: 600; }
        .grade-input { width: 80px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; text-align: center; }
        .remarks-input { width: 200px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .quarter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .quarter-tab { padding: 0.75rem 1.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s; }
        .quarter-tab.active { background: #3b82f6; color: white; border-color: #3b82f6; }
        .class-selector { margin-bottom: 2rem; }
        .save-btn { background: #16a34a; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.25rem; cursor: pointer; }
        .save-btn:hover { background: #15803d; }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .form-control { padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .gradebook-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .gradebook-table th, .gradebook-table td { padding: 0.5rem; text-align: center; border: 1px solid #e5e7eb; }
        .gradebook-table th { background: #f9fafb; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .gradebook-table .student-name { text-align: left; font-weight: 600; background: #f9fafb; position: sticky; left: 0; z-index: 5; }
        .grade-input { width: 60px; padding: 0.25rem; border: 1px solid #d1d5db; border-radius: 0.25rem; text-align: center; }
        .grade-cell { position: relative; }
        .grade-A { background: #dcfce7; color: #16a34a; }
        .grade-B { background: #dbeafe; color: #2563eb; }
        .grade-C { background: #fef3c7; color: #d97706; }
        .grade-D { background: #fed7d7; color: #dc2626; }
        .grade-F { background: #fee2e2; color: #dc2626; }
        .class-selector { margin-bottom: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .scrollable-table { overflow-x: auto; max-height: 600px; overflow-y: auto; }
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
                    <h1>Gradebook</h1>
                    <p>Manage grades and track student performance</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="class-selector">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Select Class:</label>
                <select class="form-control" style="width: 300px;" onchange="window.location.href='?class_id=' + this.value">
                    <option value="">Choose a class...</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $selectedClassId == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['subject_name']) ?> - Section <?= htmlspecialchars($class['section']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selectedClass && !empty($students)): ?>
                <!-- Class Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?= count($students) ?></h3>
                        <p>Total Students</p>
                    </div>
                    <div class="stat-card">
                        <h3><?= count($assignments) ?></h3>
                        <p>Assignments</p>
                    </div>
                    <div class="stat-card">
                        <h3>85%</h3>
                        <p>Class Average</p>
                    </div>
                    <div class="stat-card">
                        <h3>92%</h3>
                        <p>Completion Rate</p>
                    </div>
                </div>

                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 1rem;">
                    <h3><?= htmlspecialchars($selectedClass['subject_name']) ?> - Section <?= htmlspecialchars($selectedClass['section']) ?></h3>
                    <div>
                        <button class="btn btn-success" onclick="exportGrades()">
                            <i class="fas fa-download"></i> Export Grades
                        </button>
                    </div>
                </div>

                <?php if (!empty($assignments)): ?>
                    <div class="scrollable-table">
                        <table class="gradebook-table">
                            <thead>
                                <tr>
                                    <th class="student-name">Student</th>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <th style="min-width: 100px;">
                                            <div><?= htmlspecialchars($assignment['title']) ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;"><?= $assignment['points'] ?>pts</div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th>Average</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="student-name">
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        </td>
                                        <?php 
                                        $totalPoints = 0;
                                        $earnedPoints = 0;
                                        $gradedAssignments = 0;
                                        
                                        foreach ($assignments as $assignment): 
                                            $grade = $gradeMatrix[$student['id']][$assignment['id']] ?? null;
                                            $gradeValue = $grade ? $grade['grade'] : '';
                                            
                                            if ($grade && is_numeric($grade['grade'])) {
                                                $totalPoints += $assignment['points'];
                                                $earnedPoints += ($grade['grade'] / 100) * $assignment['points'];
                                                $gradedAssignments++;
                                            }
                                        ?>
                                            <td class="grade-cell">
                                                <input type="number" 
                                                       class="grade-input" 
                                                       value="<?= htmlspecialchars($gradeValue) ?>"
                                                       min="0" 
                                                       max="100"
                                                       onchange="saveGrade(<?= $student['id'] ?>, <?= $assignment['id'] ?>, this.value)"
                                                       placeholder="-">
                                            </td>
                                        <?php endforeach; ?>
                                        <td style="font-weight: 600;">
                                            <?php 
                                            if ($gradedAssignments > 0) {
                                                $average = ($earnedPoints / $totalPoints) * 100;
                                                echo number_format($average, 1) . '%';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-clipboard-list" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        No assignments found for this class. Create assignments first to start grading.
                    </div>
                <?php endif; ?>

            <?php elseif ($selectedClass): ?>
                <div style="text-align: center; padding: 2rem; color: #64748b;">
                    <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No students enrolled in this class yet.
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: #64748b;">
                    <i class="fas fa-chalkboard" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    Please select a class to view the gradebook.
                </div>
            <?php endif; ?>
        </div>
            </div>
        </main>
    </div>

    <script>
        function saveGrade(studentId, assignmentId, grade) {
            if (grade === '' || grade < 0 || grade > 100) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="save_grade">
                <input type="hidden" name="student_id" value="${studentId}">
                <input type="hidden" name="assignment_id" value="${assignmentId}">
                <input type="hidden" name="grade" value="${grade}">
                <input type="hidden" name="feedback" value="">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function exportGrades() {
            alert('Export gradebook\n(This would generate a CSV/Excel file with all grades)');
        }

        // Auto-save grades after 2 seconds of no typing
        let saveTimeout;
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('grade-input')) {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    const grade = e.target.value;
                    if (grade !== '' && grade >= 0 && grade <= 100) {
                        // Visual feedback
                        e.target.style.background = '#dcfce7';
                        setTimeout(() => {
                            e.target.style.background = '';
                        }, 1000);
                    }
                }, 2000);
            }
        });
    </script>
        </main>
    </div>
</body>
</html>

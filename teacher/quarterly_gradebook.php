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

// Get current school year
$current_year = date('Y') . '-' . (date('Y') + 1);

// Get teacher's assigned classes
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY s.name, c.section
");
$stmt->execute([$teacher['id']]);
$classes = $stmt->fetchAll();

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
    <title>Quarterly Gradebook - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .grade-status { font-size: 0.75rem; color: #6b7280; }
        .grade-A { background: #dcfce7; }
        .grade-B { background: #dbeafe; }
        .grade-C { background: #fef3c7; }
        .grade-D { background: #fee2e2; }
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
                    <h1>Quarterly Gradebook</h1>
                    <p>Manage student grades by quarter (<?= $current_year ?>)</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Class Selector -->
        <div class="card class-selector">
            <h3>Select Class</h3>
            <div class="form-group">
                <select class="form-control" onchange="selectClass(this.value)">
                    <option value="">Choose a class...</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $selectedClassId == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['subject_name']) ?> - Section <?= htmlspecialchars($class['section']) ?> (<?= $class['student_count'] ?> students)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($selectedClass): ?>
            <!-- Quarter Tabs -->
            <div class="card">
                <h3><?= htmlspecialchars($selectedClass['subject_name']) ?> - Section <?= htmlspecialchars($selectedClass['section']) ?></h3>
                <div class="quarter-tabs">
                    <?php foreach (['1st', '2nd', '3rd', '4th'] as $quarter): ?>
                        <div class="quarter-tab <?= $selectedQuarter === $quarter ? 'active' : '' ?>" 
                             onclick="selectQuarter('<?= $quarter ?>')">
                            <?= $quarter ?> Quarter
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Students Grade Table -->
                <?php if (!empty($students)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr class="<?= $student['grade'] ? (
                                    $student['grade'] >= 90 ? 'grade-A' : (
                                        $student['grade'] >= 80 ? 'grade-B' : (
                                            $student['grade'] >= 70 ? 'grade-C' : 'grade-D'
                                        )
                                    )
                                ) : '' ?>">
                                    <td><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                    <td><?= htmlspecialchars($student['student_id']) ?></td>
                                    <td>
                                        <input type="number" 
                                               class="grade-input" 
                                               min="0" 
                                               max="100" 
                                               step="0.01"
                                               value="<?= $student['grade'] ?? '' ?>"
                                               id="grade_<?= $student['id'] ?>"
                                               placeholder="0.00">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="remarks-input" 
                                               value="<?= htmlspecialchars($student['remarks'] ?? '') ?>"
                                               id="remarks_<?= $student['id'] ?>"
                                               placeholder="Optional remarks">
                                    </td>
                                    <td>
                                        <?php if ($student['grade']): ?>
                                            <div class="grade-status">
                                                Last updated: <?= date('M j, Y', strtotime($student['grade_date'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="grade-status">Not graded</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="save-btn" 
                                                onclick="saveGrade(<?= $student['id'] ?>)">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #6b7280; padding: 2rem;">No students enrolled in this class.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <p style="text-align: center; color: #6b7280; padding: 2rem;">Please select a class to view and manage grades.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function selectClass(classId) {
            if (classId) {
                window.location.href = 'quarterly_gradebook.php?class_id=' + classId + '&quarter=<?= $selectedQuarter ?>';
            }
        }
        
        function selectQuarter(quarter) {
            const classId = '<?= $selectedClassId ?>';
            if (classId) {
                window.location.href = 'quarterly_gradebook.php?class_id=' + classId + '&quarter=' + quarter;
            }
        }
        
        function saveGrade(studentId) {
            const grade = document.getElementById('grade_' + studentId).value;
            const remarks = document.getElementById('remarks_' + studentId).value;
            
            if (!grade || grade < 0 || grade > 100) {
                alert('Please enter a valid grade between 0 and 100.');
                return;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'save_grade');
            formData.append('student_id', studentId);
            formData.append('class_id', '<?= $selectedClassId ?>');
            formData.append('quarter', '<?= $selectedQuarter ?>');
            formData.append('grade', grade);
            formData.append('remarks', remarks);
            formData.append('school_year', '<?= $current_year ?>');
            
            // Submit via fetch
            fetch('quarterly_gradebook.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Reload page to show updated data
                location.reload();
            })
            .catch(error => {
                alert('Error saving grade: ' + error);
            });
        }
    </script>
        </main>
    </div>
</body>
</html>

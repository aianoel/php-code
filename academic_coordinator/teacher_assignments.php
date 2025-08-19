<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('academic_coordinator');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign_teacher_subject':
                try {
                    // Get subject info to create class name
                    $subjectStmt = $pdo->prepare("SELECT name, code FROM subjects WHERE id = ?");
                    $subjectStmt->execute([$_POST['subject_id']]);
                    $subject = $subjectStmt->fetch();
                    
                    // Create class name
                    $className = $subject['name'] . ' - Section ' . $_POST['section'];
                    
                    // Create class assignment
                    $stmt = $pdo->prepare("INSERT INTO classes (subject_id, teacher_id, name, section, grade_level, school_year, semester, max_students, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    $result = $stmt->execute([
                        $_POST['subject_id'],
                        $_POST['teacher_id'],
                        $className,
                        $_POST['section'],
                        $_POST['grade_level'] ?? '7',
                        $_POST['school_year'],
                        $_POST['semester'],
                        $_POST['max_students'] ?? 40
                    ]);
                    $message = $result ? "Teacher assigned successfully!" : "Error assigning teacher.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'remove_assignment':
                try {
                    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                    $result = $stmt->execute([$_POST['class_id']]);
                    $message = $result ? "Assignment removed successfully!" : "Error removing assignment.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all teachers
$stmt = $pdo->prepare("SELECT t.id, u.first_name, u.last_name, t.employee_id, t.department FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.role = 'teacher' AND u.status = 'active' ORDER BY u.last_name, u.first_name");
$stmt->execute();
$teachers = $stmt->fetchAll();

// Get all subjects
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE status = 'active' ORDER BY department, name");
$stmt->execute();
$subjects = $stmt->fetchAll();

// Get current assignments
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department,
           u.first_name, u.last_name, t.employee_id,
           COALESCE(COUNT(DISTINCT ce.student_id), 0) as student_count
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    JOIN users u ON c.teacher_id = u.id
    JOIN teachers t ON u.id = t.user_id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY s.department, s.name, c.section
");
$stmt->execute();
$assignments = $stmt->fetchAll();

// Debug: Check if we have any classes at all
$debugStmt = $pdo->prepare("SELECT COUNT(*) as total_classes FROM classes");
$debugStmt->execute();
$debugResult = $debugStmt->fetch();
$totalClasses = $debugResult['total_classes'];

// Group assignments by department
$departmentAssignments = [];
foreach ($assignments as $assignment) {
    $dept = $assignment['department'] ?? 'General';
    $departmentAssignments[$dept][] = $assignment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Assignments - Academic Coordinator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .assignment-table { width: 100%; border-collapse: collapse; }
        .assignment-table th, .assignment-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .assignment-table th { background: #f8fafc; font-weight: 600; }
        .department-header { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .teacher-badge { background: #e0e7ff; color: #3730a3; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem; }
        .section-badge { background: #f3f4f6; color: #374151; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
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
                    <h1>Teacher Subject Assignments</h1>
                    <p>Assign teachers to subjects and manage class sections</p>
                </div>
                <div>
                    <button onclick="openModal('assignModal')" class="btn btn-success"><i class="fas fa-plus"></i> New Assignment</button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Current Teacher Assignments</h3>
            <p style="color: #6b7280; margin-bottom: 1rem;">Total classes in database: <?= $totalClasses ?></p>
            <?php if (empty($assignments)): ?>
                <div style="text-align: center; padding: 3rem; color: #64748b;">
                    <i class="fas fa-chalkboard-teacher" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    <h3>No Assignments Yet</h3>
                    <p>Start by assigning teachers to subjects and sections</p>
                    <?php if ($totalClasses > 0): ?>
                        <p style="color: #dc2626; font-weight: 600;">Debug: <?= $totalClasses ?> classes exist but not displaying. Check database relationships.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($departmentAssignments as $deptName => $deptAssignments): ?>
                    <div class="department-header">
                        <h4><?= htmlspecialchars($deptName) ?> Department</h4>
                        <p><?= count($deptAssignments) ?> assignments</p>
                    </div>
                    <table class="assignment-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Section</th>
                                <th>Students</th>
                                <th>Year/Semester</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptAssignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($assignment['subject_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($assignment['subject_code']) ?></small>
                                    </td>
                                    <td>
                                        <div class="teacher-badge">
                                            <?= htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) ?><br>
                                            <small><?= htmlspecialchars($assignment['employee_id']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="section-badge"><?= htmlspecialchars($assignment['section']) ?></span>
                                    </td>
                                    <td><?= $assignment['student_count'] ?> students</td>
                                    <td>
                                        <?= htmlspecialchars($assignment['school_year'] ?? 'Current') ?><br>
                                        <small><?= htmlspecialchars($assignment['semester'] ?? 'All') ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this assignment?')">
                                            <input type="hidden" name="action" value="remove_assignment">
                                            <input type="hidden" name="class_id" value="<?= $assignment['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 0.5rem 1rem;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <h3>Assign Teacher to Subject</h3>
            <form method="POST">
                <input type="hidden" name="action" value="assign_teacher_subject">
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>">
                                <?= htmlspecialchars($subject['code'] . ' - ' . $subject['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" class="form-control" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>">
                                <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name'] . ' (' . $teacher['employee_id'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" class="form-control" required placeholder="e.g., A, B, 1-A">
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_level" class="form-control" required>
                            <option value="7">Grade 7</option>
                            <option value="8">Grade 8</option>
                            <option value="9">Grade 9</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>School Year</label>
                        <input type="text" name="school_year" class="form-control" value="<?= date('Y') . '-' . (date('Y') + 1) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Max Students</label>
                        <input type="number" name="max_students" class="form-control" value="40" min="1" max="50" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" class="form-control" required>
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                        <option value="summer">Summer</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('assignModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Assign Teacher</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
        </main>
    </div>
</body>
</html>

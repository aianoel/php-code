<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('teacher');
$user = get_logged_in_user();

// Get teacher ID - create if doesn't exist
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    // Create teacher record
    $employeeId = 'T' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, specialization, hire_date, status) VALUES (?, ?, 'General', 'Teaching', CURDATE(), 'active')");
    $stmt->execute([$user['id'], $employeeId]);
    $teacherId = $pdo->lastInsertId();
    $teacher = ['id' => $teacherId];
}

// Get all classes taught by this teacher
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.section, c.subject_id, s.name as subject_name, s.code as subject_code
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    WHERE c.teacher_id = ? AND c.status = 'active'
");
$stmt->execute([$teacher['id']]);
$teacherClasses = $stmt->fetchAll();

// Get all enrollments for these classes
$students = [];
if (!empty($teacherClasses)) {
    $classIds = array_column($teacherClasses, 'id');
    $classPlaceholders = implode(',', array_fill(0, count($classIds), '?'));
    
    // Get all enrollments for these classes
    $stmt = $pdo->prepare("
        SELECT ce.id, ce.student_id, ce.class_id, ce.status,
               c.section, c.name as class_name, 
               s.name as subject_name, s.code as subject_code
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        WHERE ce.class_id IN ($classPlaceholders) AND ce.status = 'active'
    ");
    $stmt->execute($classIds);
    $enrollments = $stmt->fetchAll();
    
    // Process enrollments to get student information
    if (!empty($enrollments)) {
        // First try to get students from regular student records
        $studentIds = array_column($enrollments, 'student_id');
        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        
        // Get students from regular student table
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name, u.last_name, u.email, u.phone,
                   'student' as source_type
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.id IN ($studentPlaceholders) AND s.status = 'enrolled'
        ");
        $stmt->execute($studentIds);
        $regularStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also check enrollment applications for these IDs
        $stmt = $pdo->prepare("
            SELECT ea.*, u.first_name, u.last_name, u.email, u.phone,
                   'application' as source_type
            FROM enrollment_applications ea
            JOIN users u ON ea.user_id = u.id
            WHERE ea.id IN ($studentPlaceholders) AND ea.status IN ('approved', 'enrolled')
        ");
        $stmt->execute($studentIds);
        $appStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine both sources
        $allStudents = array_merge($regularStudents, $appStudents);
        
        // Match students with their class information
        foreach ($allStudents as $student) {
            foreach ($enrollments as $enrollment) {
                if ($enrollment['student_id'] == $student['id']) {
                    // Add class info to student record
                    $student['class_section'] = $enrollment['section'];
                    $student['subject_name'] = $enrollment['subject_name'];
                    $student['subject_code'] = $enrollment['subject_code'];
                    $student['section'] = $enrollment['section'];
                    $students[] = $student;
                    break;
                }
            }
        }
    }
}

// Debug information - disabled for production
$debug = false;
if ($debug) {
    echo '<div style="background: #f8f9fa; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
    echo '<h3>Debug Information</h3>';
    echo '<pre style="background: #eee; padding: 10px; border-radius: 3px;">';
    echo "Teacher ID: {$teacher['id']}\n";
    echo "Number of teacher classes: " . count($teacherClasses) . "\n";
    echo "Number of enrollments found: " . (isset($enrollments) ? count($enrollments) : 0) . "\n";
    echo "Number of students found: " . count($students) . "\n";
    
    // Check if there are any classes assigned to this teacher
    $stmt = $pdo->prepare("SELECT id, name, section FROM classes WHERE teacher_id = ?");
    $stmt->execute([$teacher['id']]);
    $teacherClasses = $stmt->fetchAll();
    echo "\nClasses assigned to this teacher (" . count($teacherClasses) . "):\n";
    foreach ($teacherClasses as $class) {
        echo "- Class ID: {$class['id']}, Name: {$class['name']}, Section: {$class['section']}\n";
    }
    
    // Show enrollment details
    echo "\nEnrollments found (" . count($enrollments) . "):\n";
    foreach ($enrollments as $index => $enrollment) {
        if ($index < 10) { // Limit to first 10 for brevity
            echo "- Enrollment ID: {$enrollment['id']}, Student ID: {$enrollment['student_id']}, Class ID: {$enrollment['class_id']}, Status: {$enrollment['status']}\n";
        } else {
            echo "... and " . (count($enrollments) - 10) . " more\n";
            break;
        }
    }
    
    // Check if the student IDs in enrollments exist in the students table
    if (!empty($enrollments)) {
        $studentIds = array_column($enrollments, 'student_id');
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE id IN ($placeholders)");
        $stmt->execute($studentIds);
        $validStudentCount = $stmt->fetchColumn();
        
        echo "\nValid student records found: $validStudentCount out of " . count($studentIds) . " enrollment student IDs\n";
        
        // Check if any enrollments point to enrollment_applications instead
        if (!empty($studentIds)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_applications WHERE id IN ($placeholders)");
            $stmt->execute($studentIds);
            $appStudentCount = $stmt->fetchColumn();
            
            echo "Student IDs found in enrollment_applications: $appStudentCount\n";
        }
    }
    
    echo '</pre>';
    echo '</div>';
}

// Get classes for filter
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    WHERE c.teacher_id = ?
    ORDER BY s.name
");
$stmt->execute([$teacher['id']]);
$classes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; margin-bottom: 1rem; }
        .filter-section { display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
        .student-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .student-info { display: flex; align-items: center; gap: 1rem; }
        .contact-info { color: #64748b; font-size: 0.875rem; }
        .class-badge { background: #e0f2fe; color: #0277bd; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
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
                    <h1>My Students</h1>
                    <p>Manage and view your students across all classes</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="filter-section">
                <div>
                    <label>Filter by Class:</label>
                    <select class="form-control" style="width: auto; margin: 0;" onchange="filterStudents(this.value)">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['subject_name']) ?> - Section <?= htmlspecialchars($class['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <input type="text" class="form-control" placeholder="Search students..." style="width: 300px; margin: 0;" onkeyup="searchStudents(this.value)">
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Students List (<?= count($students) ?> total)</h3>
                <button class="btn btn-success" onclick="exportStudents()">
                    <i class="fas fa-download"></i> Export List
                </button>
            </div>

            <table class="table" id="studentsTable">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>Class</th>
                        <th>Contact</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">
                                <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                No students found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr data-class="<?= $student['section'] ?>" data-name="<?= strtolower($student['first_name'] . ' ' . $student['last_name']) ?>">
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                            <div class="contact-info">Grade <?= htmlspecialchars($student['grade_level'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($student['student_id'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="class-badge"><?= htmlspecialchars($student['subject_code']) ?> - Sec <?= htmlspecialchars($student['section']) ?></span>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($student['email']) ?></div>
                                    <div class="contact-info"><?= htmlspecialchars($student['phone'] ?? 'No phone') ?></div>
                                </td>
                                <td>
                                    <button onclick="viewStudent(<?= $student['id'] ?>)" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #3b82f6; color: white;">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button onclick="viewGrades(<?= $student['id'] ?>)" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #16a34a; color: white;">
                                        <i class="fas fa-chart-bar"></i> Grades
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function filterStudents(classId) {
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            rows.forEach(row => {
                if (!classId || row.dataset.class === classId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function searchStudents(query) {
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            const searchTerm = query.toLowerCase();
            
            rows.forEach(row => {
                const name = row.dataset.name || '';
                if (name.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function viewStudent(studentId) {
            alert('View student details for ID: ' + studentId + '\n(This would open a detailed student profile)');
        }

        function viewGrades(studentId) {
            alert('View grades for student ID: ' + studentId + '\n(This would open the gradebook for this student)');
        }

        function exportStudents() {
            alert('Export students list\n(This would generate a CSV/PDF export)');
        }
    </script>
        </main>
    </div>
</body>
</html>

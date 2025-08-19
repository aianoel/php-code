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
            case 'assign_student_section':
                try {
                    // Validate that the student exists
                    $checkStmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
                    $checkStmt->execute([$_POST['student_id']]);
                    $student = $checkStmt->fetch();
                    
                    if (!$student) {
                        $message = "Error: Student not found.";
                        break;
                    }
                    
                    // Validate that the class exists
                    $checkStmt = $pdo->prepare("SELECT id FROM classes WHERE id = ?");
                    $checkStmt->execute([$_POST['class_id']]);
                    $class = $checkStmt->fetch();
                    
                    if (!$class) {
                        $message = "Error: Class section not found.";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO class_enrollments (student_id, class_id, enrollment_date, status) 
                                          VALUES (?, ?, CURDATE(), 'active') 
                                          ON DUPLICATE KEY UPDATE status = 'active'");
                    $result = $stmt->execute([
                        $_POST['student_id'],
                        $_POST['class_id']
                    ]);
                    $message = $result ? "Student assigned to section successfully!" : "Error assigning student.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'remove_student_assignment':
                try {
                    // First, get the student ID from the student_id field
                    $studentIdValue = $_POST['student_id'];
                    
                    // Check if it's a student record ID or a student ID string
                    if (is_numeric($studentIdValue)) {
                        // It's already a student record ID
                        $studentId = $studentIdValue;
                    } else {
                        // It's a student ID string, need to look up the record ID
                        $lookupStmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
                        $lookupStmt->execute([$studentIdValue]);
                        $studentRecord = $lookupStmt->fetch();
                        if (!$studentRecord) {
                            $message = "Error: Student not found with ID {$studentIdValue}.";
                            break;
                        }
                        $studentId = $studentRecord['id'];
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM class_enrollments WHERE student_id = ? AND class_id = ?");
                    $result = $stmt->execute([$studentId, $_POST['class_id']]);
                    $message = $result ? "Student removed from section successfully!" : "Error removing student.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// No longer creating sample students - use actual enrolled students only

// Get actual enrolled students from enrollment applications
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        ea.id as enrollment_id,
        ea.first_name, 
        ea.last_name, 
        ea.email,
        ea.application_number,
        ea.grade_level,
        ea.strand,
        ea.status as enrollment_status,
        ea.created_at as enrollment_date
    FROM enrollment_applications ea
    WHERE ea.status IN ('approved', 'enrolled')
    ORDER BY ea.last_name, ea.first_name
");
$stmt->execute();
$enrolled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also get students who already have user accounts (legacy students)
$stmt = $pdo->prepare("
    SELECT s.id, s.student_id, u.first_name, u.last_name, u.email, s.grade_level, s.strand
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.status = 'active' AND s.status = 'enrolled'
    ORDER BY u.last_name, u.first_name
");
$stmt->execute();
$existing_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine both sets of students
$students = [];

// Add enrolled students from applications
foreach ($enrolled_students as $student) {
    $students[] = [
        'id' => 'app_' . $student['enrollment_id'], // Prefix to identify as application
        'student_id' => $student['application_number'],
        'first_name' => $student['first_name'],
        'last_name' => $student['last_name'],
        'email' => $student['email'],
        'grade_level' => $student['grade_level'],
        'strand' => $student['strand'],
        'source' => 'enrollment_application'
    ];
}

// Add existing students with user accounts
foreach ($existing_students as $student) {
    $students[] = [
        'id' => $student['id'],
        'student_id' => $student['student_id'],
        'first_name' => $student['first_name'],
        'last_name' => $student['last_name'],
        'email' => $student['email'],
        'grade_level' => $student['grade_level'],
        'strand' => $student['strand'],
        'source' => 'student_record'
    ];
}

$totalStudents = count($students);

// Create basic subjects and teachers if none exist
$checkSubjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
if ($checkSubjects == 0) {
    try {
        $pdo->exec("INSERT INTO subjects (code, name, description, credits, units, department, grade_level, strand, status) VALUES 
            ('MATH7', 'Mathematics 7', 'Basic Mathematics for Grade 7', 3, 3, 'Mathematics', '7', 'Core', 'active'),
            ('ENG7', 'English 7', 'English Language Arts for Grade 7', 3, 3, 'English', '7', 'Core', 'active'),
            ('SCI7', 'Science 7', 'General Science for Grade 7', 3, 3, 'Science', '7', 'Core', 'active')");
    } catch (Exception $e) {}
}

$checkTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
if ($checkTeachers == 0) {
    try {
        $teacherPassword = password_hash('teacher123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES 
            ('Sarah', 'Wilson', 'sarah.wilson@teacher.com', '$teacherPassword', 'teacher', 'active'),
            ('Robert', 'Brown', 'robert.brown@teacher.com', '$teacherPassword', 'teacher', 'active')");
        
        $teacher1Id = $pdo->lastInsertId() - 1;
        $teacher2Id = $pdo->lastInsertId();
        
        $pdo->exec("INSERT INTO teachers (user_id, employee_id, department, hire_date, status) VALUES 
            ($teacher1Id, 'TCH" . str_pad($teacher1Id, 6, '0', STR_PAD_LEFT) . "', 'Mathematics', CURDATE(), 'active'),
            ($teacher2Id, 'TCH" . str_pad($teacher2Id, 6, '0', STR_PAD_LEFT) . "', 'English', CURDATE(), 'active')");
    } catch (Exception $e) {}
}

// Create sample classes if none exist
$checkClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
if ($checkClasses == 0) {
    try {
        // Get first subject and teacher
        $subject = $pdo->query("SELECT id FROM subjects LIMIT 1")->fetch();
        $teacher = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetch();
        
        if ($subject && $teacher) {
            $pdo->exec("INSERT INTO classes (subject_id, teacher_id, name, section, grade_level, school_year, semester, max_students, status) VALUES 
                ({$subject['id']}, {$teacher['id']}, 'Mathematics 7 - Section A', 'A', '7', '2024-2025', '1st', 40, 'active')");
        }
    } catch (Exception $e) {}
}

// Get all class sections
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department,
           u.first_name as teacher_first, u.last_name as teacher_last
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    JOIN users u ON c.teacher_id = u.id
    ORDER BY s.department, s.name, c.section
");
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if we have any classes
$totalClasses = count($classes);
if ($totalClasses == 0) {
    // Try to create a sample class if none exist
    $checkSubjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $checkTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    
    if ($checkSubjects > 0 && $checkTeachers > 0) {
        try {
            // Get a subject
            $subjectStmt = $pdo->query("SELECT id FROM subjects LIMIT 1");
            $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get a teacher
            $teacherStmt = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
            $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subject && $teacher) {
                // Create a sample class
                $pdo->exec("INSERT INTO classes (subject_id, teacher_id, name, section, grade_level, school_year, semester, max_students, status) 
                           VALUES ({$subject['id']}, {$teacher['id']}, 'Mathematics 7 - Section A', 'A', '7', '2024-2025', '1st', 40, 'active')");
                
                // Refresh class list
                $stmt = $pdo->prepare("
                    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department,
                           u.first_name as teacher_first, u.last_name as teacher_last
                    FROM classes c
                    JOIN subjects s ON c.subject_id = s.id
                    JOIN users u ON c.teacher_id = u.id
                    ORDER BY s.department, s.name, c.section
                ");
                $stmt->execute();
                $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            // Silently continue if this fails
        }
    }
}

// Get all available sections (classes)
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department,
           u.first_name as teacher_first, u.last_name as teacher_last
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    JOIN users u ON c.teacher_id = u.id
    JOIN teachers t ON u.id = t.user_id
    WHERE c.status = 'active'
    ORDER BY s.department, s.name, c.section
");
$stmt->execute();
$sections = $stmt->fetchAll();

// Get current student enrollments - updated to work with actual enrolled students
$stmt = $pdo->prepare("
    SELECT ce.*, s.name as subject_name, s.code as subject_code, s.department,
           c.section, c.id as class_id,
           COALESCE(u.first_name, ea.first_name) as first_name,
           COALESCE(u.last_name, ea.last_name) as last_name,
           COALESCE(st.student_id, ea.application_number) as student_id,
           ut.first_name as teacher_first, ut.last_name as teacher_last,
           ce.enrollment_date
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    JOIN subjects s ON c.subject_id = s.id
    LEFT JOIN students st ON ce.student_id = st.id
    LEFT JOIN users u ON st.user_id = u.id
    LEFT JOIN enrollment_applications ea ON ce.student_id = CAST(SUBSTRING(ce.student_id, 5) AS UNSIGNED) AND SUBSTRING(ce.student_id, 1, 4) = 'app_'
    JOIN users ut ON c.teacher_id = ut.id
    WHERE ce.status = 'active'
    ORDER BY s.department, s.name, c.section, 
             COALESCE(u.last_name, ea.last_name),
             COALESCE(u.first_name, ea.first_name)
");
$stmt->execute();
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if we have any enrollments
$totalEnrollments = count($enrollments);

// Remove sample enrollment creation - only show actual enrollments

// Group enrollments by department
$departmentEnrollments = [];
foreach ($enrollments as $enrollment) {
    $dept = $enrollment['department'] ?? 'General';
    $departmentEnrollments[$dept][] = $enrollment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Section Assignments - Academic Coordinator</title>
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
        .enrollment-table { width: 100%; border-collapse: collapse; }
        .enrollment-table th, .enrollment-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .enrollment-table th { background: #f8fafc; font-weight: 600; }
        .department-header { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .student-badge { background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem; }
        .section-badge { background: #f3f4f6; color: #374151; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .teacher-info { font-size: 0.875rem; color: #64748b; }
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
                    <h1>Student Section Assignments</h1>
                    <p>Assign students to class sections and manage enrollments</p>
                </div>
                <div>
                    <button onclick="openModal('assignModal')" class="btn btn-success"><i class="fas fa-plus"></i> Assign Student</button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Current Student Enrollments</h3>
            <?php if (empty($enrollments)): ?>
                <div style="text-align: center; padding: 3rem; color: #64748b;">
                    <i class="fas fa-user-graduate" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    <h3>No Student Enrollments Yet</h3>
                    <p>Start by assigning students to class sections</p>
                </div>
            <?php else: ?>
                <?php foreach ($departmentEnrollments as $deptName => $deptEnrollments): ?>
                    <div class="department-header">
                        <h4><?= htmlspecialchars($deptName) ?> Department</h4>
                        <p><?= count($deptEnrollments) ?> enrollments</p>
                    </div>
                    <table class="enrollment-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Section</th>
                                <th>Teacher</th>
                                <th>Enrollment Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptEnrollments as $enrollment): ?>
                                <tr>
                                    <td>
                                        <div class="student-badge">
                                            <?= htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']) ?><br>
                                            <small><?= htmlspecialchars($enrollment['student_id']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($enrollment['subject_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($enrollment['subject_code']) ?></small>
                                    </td>
                                    <td>
                                        <span class="section-badge"><?= htmlspecialchars($enrollment['section']) ?></span>
                                    </td>
                                    <td>
                                        <div class="teacher-info">
                                            <?= htmlspecialchars($enrollment['teacher_first'] . ' ' . $enrollment['teacher_last']) ?>
                                        </div>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($enrollment['enrollment_date'])) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this student from section?')">
                                            <input type="hidden" name="action" value="remove_student_assignment">
                                            <input type="hidden" name="student_id" value="<?= $enrollment['student_id'] ?>">
                                            <input type="hidden" name="class_id" value="<?= $enrollment['class_id'] ?>">
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
            <h3>Assign Student to Section</h3>
            <form method="POST">
                <input type="hidden" name="action" value="assign_student_section">
                <div class="form-group">
                    <label>Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= htmlspecialchars($student['id']) ?>">
                                    <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?> 
                                    (<?= htmlspecialchars($student['student_id']) ?>) 
                                    - Grade <?= htmlspecialchars($student['grade_level']) ?>
                                    <?php if (!empty($student['strand'])): ?>
                                        - <?= htmlspecialchars($student['strand']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No enrolled students available</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($students) && $totalStudents > 0): ?>
                        <small style="color: #dc2626;">Debug: <?= $totalStudents ?> students exist but not loading. Check role assignments.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Section (Subject & Teacher)</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">Select Section</option>
                        <?php if (!empty($classes)): ?>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>">
                                    <?= htmlspecialchars($class['subject_name']) ?> (<?= htmlspecialchars($class['subject_code']) ?>) - 
                                    Section <?= htmlspecialchars($class['section']) ?> - 
                                    <?= htmlspecialchars($class['teacher_first'] . ' ' . $class['teacher_last']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No class sections available</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('assignModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Assign Student</button>
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

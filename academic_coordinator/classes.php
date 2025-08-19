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
            case 'create_class':
                try {
                    $stmt = $pdo->prepare("INSERT INTO classes (subject_id, teacher_id, name, section, grade_level, school_year, semester, max_students, room, schedule_days, schedule_time_start, schedule_time_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $_POST['subject_id'],
                        $_POST['teacher_id'],
                        $_POST['name'],
                        $_POST['section'],
                        $_POST['grade_level'],
                        $_POST['school_year'],
                        $_POST['semester'],
                        $_POST['max_students'],
                        $_POST['room'],
                        $_POST['schedule_day'], // Using schedule_day form field for schedule_days column
                        $_POST['start_time'], // Using start_time form field for schedule_time_start column
                        $_POST['schedule_time_end'] // Using schedule_time_end form field
                    ]);
                    $message = $result ? "Class created successfully!" : "Error creating class.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'update_class':
                try {
                    $stmt = $pdo->prepare("UPDATE classes SET name = ?, section = ?, grade_level = ?, school_year = ?, semester = ?, max_students = ?, room = ?, schedule_days = ?, schedule_time_start = ?, schedule_time_end = ?, status = ? WHERE id = ?");
                    $result = $stmt->execute([
                        $_POST['name'],
                        $_POST['section'],
                        $_POST['grade_level'],
                        $_POST['school_year'],
                        $_POST['semester'],
                        $_POST['max_students'],
                        $_POST['room'],
                        $_POST['schedule_day'], // Using schedule_day form field for schedule_days column
                        $_POST['start_time'], // Using start_time form field for schedule_time_start column
                        $_POST['schedule_time_end'], // Using schedule_time_end form field
                        $_POST['status'],
                        $_POST['class_id']
                    ]);
                    $message = $result ? "Class updated successfully!" : "Error updating class.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'delete_class':
                try {
                    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                    $result = $stmt->execute([$_POST['class_id']]);
                    $message = $result ? "Class deleted successfully!" : "Error deleting class.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all classes with related information
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department,
           u.first_name as teacher_first, u.last_name as teacher_last,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    GROUP BY c.id
    ORDER BY s.department, s.name, c.section
");
$stmt->execute();
$classes = $stmt->fetchAll();

// Get subjects for dropdown
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE status = 'active' ORDER BY department, name");
$stmt->execute();
$subjects = $stmt->fetchAll();

// Get teachers for dropdown
$stmt = $pdo->prepare("
    SELECT t.*, u.first_name, u.last_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'active' 
    ORDER BY u.last_name, u.first_name
");
$stmt->execute();
$teachers = $stmt->fetchAll();

// Group classes by department
$departments = [];
foreach ($classes as $class) {
    $dept = $class['department'] ?: 'General';
    $departments[$dept][] = $class;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - Academic Coordinator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .department-section { margin-bottom: 2rem; }
        .department-header { background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .classes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 1rem; }
        .class-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; transition: all 0.3s; }
        .class-card:hover { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .class-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem; }
        .class-name { font-weight: 600; margin-bottom: 0.5rem; }
        .class-details { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem; }
        .class-stats { display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 2% auto; padding: 2rem; border-radius: 1rem; max-width: 800px; max-height: 90vh; overflow-y: auto; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .status-badge { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-inactive { background: #fee2e2; color: #dc2626; }
        .schedule-info { background: #f3f4f6; padding: 0.5rem; border-radius: 0.25rem; font-size: 0.875rem; }
    </style>
    <link rel="stylesheet" href="../includes/sidebar.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Class Management</h1>
                    <p>Manage class sections and schedules</p>
                </div>
                <div>
                    <button onclick="openModal('createClassModal')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Class
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Classes by Department -->
        <?php foreach ($departments as $department => $dept_classes): ?>
            <div class="card department-section">
                <div class="department-header">
                    <h3><?= htmlspecialchars($department) ?> Department</h3>
                    <p><?= count($dept_classes) ?> classes</p>
                </div>
                
                <div class="classes-grid">
                    <?php foreach ($dept_classes as $class): ?>
                        <div class="class-card">
                            <div class="class-header">
                                <div>
                                    <div class="class-name"><?= htmlspecialchars($class['subject_name']) ?> - Section <?= htmlspecialchars($class['section']) ?></div>
                                    <div style="font-size: 0.875rem; color: #3b82f6;"><?= htmlspecialchars($class['subject_code']) ?></div>
                                </div>
                                <span class="status-badge status-<?= $class['status'] ?>">
                                    <?= ucfirst($class['status']) ?>
                                </span>
                            </div>
                            
                            <div class="class-details">
                                <strong>Teacher:</strong> <?= htmlspecialchars($class['teacher_first'] . ' ' . $class['teacher_last']) ?>
                            </div>
                            
                            <div class="class-details">
                                <strong>Grade Level:</strong> <?= htmlspecialchars($class['grade_level']) ?> | 
                                <strong>School Year:</strong> <?= htmlspecialchars($class['school_year']) ?> | 
                                <strong>Semester:</strong> <?= htmlspecialchars($class['semester']) ?>
                            </div>
                            
                            <?php if ($class['schedule_days'] && $class['schedule_time_start']): ?>
                                <div class="schedule-info">
                                    <i class="fas fa-calendar"></i> <?= htmlspecialchars($class['schedule_days']) ?> 
                                    <?= date('g:i A', strtotime($class['schedule_time_start'])) ?> - <?= date('g:i A', strtotime($class['schedule_time_end'])) ?>
                                    <?php if ($class['room']): ?>
                                        | <i class="fas fa-door-open"></i> <?= htmlspecialchars($class['room']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="class-stats">
                                <span><i class="fas fa-users"></i> <?= $class['student_count'] ?>/<?= $class['max_students'] ?> students</span>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                <button onclick="editClass(<?= htmlspecialchars(json_encode($class)) ?>)" class="btn btn-warning" style="padding: 0.5rem; font-size: 0.75rem;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteClass(<?= $class['id'] ?>, '<?= htmlspecialchars($class['subject_name'] . ' - ' . $class['section']) ?>')" class="btn btn-danger" style="padding: 0.5rem; font-size: 0.75rem;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($classes)): ?>
            <div class="card">
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-chalkboard" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No Classes Found</h3>
                    <p>Start by creating your first class section.</p>
                    <button onclick="openModal('createClassModal')" class="btn btn-success" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Create First Class
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Class Modal -->
    <div id="createClassModal" class="modal">
        <div class="modal-content">
            <h3>Create New Class</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_class">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['code'] . ' - ' . $subject['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Teacher</label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Class Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Mathematics 7A">
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" class="form-control" required placeholder="e.g., A, B, 1, 2">
                    </div>
                </div>
                
                <div class="form-grid-3">
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
                    <div class="form-group">
                        <label>School Year</label>
                        <input type="text" name="school_year" class="form-control" value="<?= date('Y') . '-' . (date('Y') + 1) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester" class="form-control" required>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Max Students</label>
                        <input type="number" name="max_students" class="form-control" min="1" max="50" value="30" required>
                    </div>
                    <div class="form-group">
                        <label>Room</label>
                        <input type="text" name="room" class="form-control" placeholder="e.g., Room 101">
                    </div>
                </div>
                
                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Schedule Day</label>
                        <select name="schedule_days" class="form-control">
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="schedule_time_start" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="schedule_time_end" class="form-control">
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('createClassModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Class</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Class Modal -->
    <div id="editClassModal" class="modal">
        <div class="modal-content">
            <h3>Edit Class</h3>
            <form method="POST" id="editClassForm">
                <input type="hidden" name="action" value="update_class">
                <input type="hidden" name="class_id" id="edit_class_id">
                
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" id="edit_subject_display" class="form-control" readonly style="background: #f3f4f6;">
                </div>
                
                <div class="form-group">
                    <label>Teacher</label>
                    <input type="text" id="edit_teacher_display" class="form-control" readonly style="background: #f3f4f6;">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Class Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" id="edit_section" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_level" id="edit_grade_level" class="form-control" required>
                            <option value="7">Grade 7</option>
                            <option value="8">Grade 8</option>
                            <option value="9">Grade 9</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Year</label>
                        <input type="text" name="school_year" id="edit_school_year" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester" id="edit_semester" class="form-control" required>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Max Students</label>
                        <input type="number" name="max_students" id="edit_max_students" class="form-control" min="1" max="50" required>
                    </div>
                    <div class="form-group">
                        <label>Room</label>
                        <input type="text" name="room" id="edit_room" class="form-control">
                    </div>
                </div>
                
                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Schedule Day</label>
                        <select name="schedule_days" id="edit_schedule_day" class="form-control">
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="schedule_time_start" id="edit_start_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="schedule_time_end" id="edit_end_time" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('editClassModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Class</button>
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
        
        function editClass(classData) {
            document.getElementById('edit_class_id').value = classData.id;
            document.getElementById('edit_subject_display').value = classData.subject_code + ' - ' + classData.subject_name;
            document.getElementById('edit_teacher_display').value = classData.teacher_first + ' ' + classData.teacher_last;
            document.getElementById('edit_name').value = classData.name;
            document.getElementById('edit_section').value = classData.section;
            document.getElementById('edit_grade_level').value = classData.grade_level;
            document.getElementById('edit_school_year').value = classData.school_year;
            document.getElementById('edit_semester').value = classData.semester;
            document.getElementById('edit_max_students').value = classData.max_students;
            document.getElementById('edit_room').value = classData.room || '';
            document.getElementById('edit_schedule_day').value = classData.schedule_days || '';
            document.getElementById('edit_start_time').value = classData.schedule_time_start || '';
            document.getElementById('edit_end_time').value = classData.schedule_time_end || '';
            document.getElementById('edit_status').value = classData.status;
            
            openModal('editClassModal');
        }
        
        function deleteClass(classId, className) {
            if (confirm('Are you sure you want to delete "' + className + '"? This action cannot be undone and will remove all student enrollments.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_class">
                    <input type="hidden" name="class_id" value="${classId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
        </main>
    </div>
</body>
</html>

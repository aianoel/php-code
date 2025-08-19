<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('admin');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_subject':
                $stmt = $pdo->prepare("INSERT INTO subjects (name, code, description, units, department) VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$_POST['name'], $_POST['code'], $_POST['description'], $_POST['units'], $_POST['department']]);
                $message = $result ? "Subject created successfully!" : "Error creating subject.";
                break;
            case 'create_class':
                $stmt = $pdo->prepare("INSERT INTO classes (subject_id, teacher_id, section, schedule, room, capacity) VALUES (?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$_POST['subject_id'], $_POST['teacher_id'], $_POST['section'], $_POST['schedule'], $_POST['room'], $_POST['capacity']]);
                $message = $result ? "Class created successfully!" : "Error creating class.";
                break;
            case 'delete_subject':
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                $result = $stmt->execute([$_POST['subject_id']]);
                $message = $result ? "Subject deleted successfully!" : "Error deleting subject.";
                break;
            case 'delete_class':
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $result = $stmt->execute([$_POST['class_id']]);
                $message = $result ? "Class deleted successfully!" : "Error deleting class.";
                break;
        }
    }
}

// Get subjects
try {
    // First check if units column exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'units'");
    $unitsExists = $checkStmt->rowCount() > 0;
    
    if ($unitsExists) {
        $stmt = $pdo->prepare("SELECT id, code, name, units, department, grade_level, strand, status FROM subjects ORDER BY name");
    } else {
        $stmt = $pdo->prepare("SELECT id, code, name, 1 AS units, department, grade_level, strand, status FROM subjects ORDER BY name");
    }
    $stmt->execute();
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $subjects = [];
}

// Get classes with subject and teacher info
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, 
           u.first_name, u.last_name
    FROM classes c
    LEFT JOIN subjects s ON c.subject_id = s.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY s.name, c.section
");
$stmt->execute();
$classes = $stmt->fetchAll();

// Get teachers for dropdown
$stmt = $pdo->prepare("
    SELECT t.id, u.first_name, u.last_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.status = 'active'
    ORDER BY u.last_name, u.first_name
");
$stmt->execute();
$teachers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Setup - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color: white; padding: 2rem 0; position: fixed; height: 100vh; overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 0 2rem 2rem; border-bottom: 1px solid #334155; }
        .logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .logo i { width: 40px; height: 40px; background: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .user-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .user-info p { font-size: 0.85rem; color: #94a3b8; }
        .nav-menu { padding: 1rem 0; }
        .nav-item { display: block; padding: 0.875rem 2rem; color: #cbd5e1; text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { background: rgba(220, 38, 38, 0.1); color: #dc2626; border-left-color: #dc2626; }
        .nav-item i { width: 20px; margin-right: 0.75rem; }
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 500px; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 2rem; }
        .tab { padding: 1rem 2rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #dc2626; color: #dc2626; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include_sidebar(); ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1>Academic Setup</h1>
                            <p>Manage subjects, classes, and academic structure</p>
                        </div>
                    </div>
                </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('subjects')">
                <i class="fas fa-book"></i> Subjects
            </div>
            <div class="tab" onclick="switchTab('classes')">
                <i class="fas fa-chalkboard"></i> Classes
            </div>
        </div>

        <!-- Subjects Tab -->
        <div id="subjects" class="tab-content active">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Subjects</h3>
                    <button onclick="openModal('createSubjectModal')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Subject
                    </button>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Units</th>
                            <th>Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">
                                    <i class="fas fa-book" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    No subjects found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?= isset($subject['code']) ? htmlspecialchars($subject['code']) : '' ?></td>
                                    <td><?= isset($subject['name']) ? htmlspecialchars($subject['name']) : '' ?></td>
                                    <td><?= isset($subject['units']) ? htmlspecialchars($subject['units']) : 'N/A' ?></td>
                                    <td><?= isset($subject['department']) ? htmlspecialchars($subject['department']) : 'N/A' ?></td>
                                    <td>
                                        <button onclick="deleteSubject(<?= $subject['id'] ?>)" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Classes Tab -->
        <div id="classes" class="tab-content">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Classes</h3>
                    <button onclick="openModal('createClassModal')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Class
                    </button>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Section</th>
                            <th>Teacher</th>
                            <th>Schedule</th>
                            <th>Room</th>
                            <th>Capacity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                    <i class="fas fa-chalkboard" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    No classes found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($class['subject_code']) ?><br>
                                        <small><?= htmlspecialchars($class['subject_name']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($class['section']) ?></td>
                                    <td>
                                        <?php if ($class['first_name']): ?>
                                            <?= htmlspecialchars($class['first_name'] . ' ' . $class['last_name']) ?>
                                        <?php else: ?>
                                            <em>No teacher assigned</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= isset($class['schedule']) ? htmlspecialchars($class['schedule']) : '' ?></td>
                                    <td><?= isset($class['room']) ? htmlspecialchars($class['room']) : '' ?></td>
                                    <td><?= isset($class['capacity']) ? htmlspecialchars($class['capacity']) : '' ?></td>
                                    <td>
                                        <button onclick="deleteClass(<?= $class['id'] ?>)" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Subject Modal -->
    <div id="createSubjectModal" class="modal">
        <div class="modal-content">
            <h3>Create New Subject</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_subject">
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="code" class="form-control" required placeholder="e.g., MATH101">
                </div>
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Basic Mathematics">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Subject description..."></textarea>
                </div>
                <div class="form-group">
                    <label>Units</label>
                    <input type="number" name="units" class="form-control" required min="1" max="6" value="3">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" class="form-control" required>
                        <option value="">Select Department</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science</option>
                        <option value="English">English</option>
                        <option value="Filipino">Filipino</option>
                        <option value="Social Studies">Social Studies</option>
                        <option value="Physical Education">Physical Education</option>
                        <option value="Arts">Arts</option>
                        <option value="Technology">Technology</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal('createSubjectModal')" class="btn btn-warning">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Class Modal -->
    <div id="createClassModal" class="modal">
        <div class="modal-content">
            <h3>Create New Class</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_class">
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
                    <select name="teacher_id" class="form-control">
                        <option value="">Select Teacher (Optional)</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section" class="form-control" required placeholder="e.g., A, B, 1, 2">
                </div>
                <div class="form-group">
                    <label>Schedule</label>
                    <input type="text" name="schedule" class="form-control" required placeholder="e.g., MWF 8:00-9:00 AM">
                </div>
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" class="form-control" required placeholder="e.g., Room 101">
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" class="form-control" required min="1" max="100" value="30">
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal('createClassModal')" class="btn btn-warning">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Class</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function deleteSubject(subjectId) {
            if (confirm('Are you sure you want to delete this subject? This will also delete all associated classes.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_subject">
                    <input type="hidden" name="subject_id" value="${subjectId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteClass(classId) {
            if (confirm('Are you sure you want to delete this class?')) {
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
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
            </div>
        </main>
    </div>
</body>
</html>

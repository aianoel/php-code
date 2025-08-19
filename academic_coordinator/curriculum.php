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
            case 'create_subject':
                try {
                    $stmt = $pdo->prepare("INSERT INTO subjects (code, name, description, units, department, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $result = $stmt->execute([
                        $_POST['code'],
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['units'],
                        $_POST['department']
                    ]);
                    $message = $result ? "Subject created successfully!" : "Error creating subject.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get curriculum data
$stmt = $pdo->prepare("
    SELECT s.*, COUNT(c.id) as class_count, COUNT(DISTINCT c.teacher_id) as teacher_count
    FROM subjects s
    LEFT JOIN classes c ON s.id = c.subject_id
    GROUP BY s.id
    ORDER BY s.department, s.name
");
$stmt->execute();
$subjects = $stmt->fetchAll();

// Group by department
$departments = [];
foreach ($subjects as $subject) {
    $dept = $subject['department'] ?? 'General';
    $departments[$dept][] = $subject;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Management - Academic Coordinator</title>
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
        .department-section { margin-bottom: 2rem; }
        .department-header { background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .subject-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
        .subject-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; }
        .subject-header { display: flex; justify-content: between; align-items: start; margin-bottom: 0.5rem; }
        .subject-code { background: #f3f4f6; color: #6b7280; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .subject-stats { display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.875rem; color: #64748b; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
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
                    <h1>Curriculum Management</h1>
                    <p>Manage subjects, courses, and academic programs</p>
                </div>
                <div>
                    <button onclick="openModal('createModal')" class="btn btn-success"><i class="fas fa-plus"></i> Add Subject</button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php foreach ($departments as $deptName => $deptSubjects): ?>
            <div class="department-section">
                <div class="department-header">
                    <h3><?= htmlspecialchars($deptName) ?> Department</h3>
                    <p><?= count($deptSubjects) ?> subjects</p>
                </div>
                <div class="subject-grid">
                    <?php foreach ($deptSubjects as $subject): ?>
                        <div class="subject-card">
                            <div class="subject-header">
                                <div>
                                    <h4><?= htmlspecialchars($subject['name']) ?></h4>
                                    <span class="subject-code"><?= htmlspecialchars($subject['code']) ?></span>
                                </div>
                            </div>
                            <p style="color: #64748b; font-size: 0.875rem; margin: 0.5rem 0;">
                                <?= htmlspecialchars($subject['description'] ?? 'No description') ?>
                            </p>
                            <div class="subject-stats">
                                <span><i class="fas fa-credit-card"></i> <?= $subject['units'] ?? 'N/A' ?> units</span>
                                <span><i class="fas fa-chalkboard"></i> <?= $subject['class_count'] ?> classes</span>
                                <span><i class="fas fa-users"></i> <?= $subject['teacher_count'] ?> teachers</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Create Subject Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <h3>Add New Subject</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_subject">
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="code" class="form-control" required placeholder="e.g., MATH101">
                </div>
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., College Algebra">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Subject description..."></textarea>
                </div>
                <div class="form-group">
                    <label>Units</label>
                    <input type="number" name="units" class="form-control" min="1" max="10" placeholder="3">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" class="form-control" required>
                        <option value="">Select Department</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science</option>
                        <option value="English">English</option>
                        <option value="Social Studies">Social Studies</option>
                        <option value="Physical Education">Physical Education</option>
                        <option value="Arts">Arts</option>
                        <option value="Technology">Technology</option>
                        <option value="General">General</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('createModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Subject</button>
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

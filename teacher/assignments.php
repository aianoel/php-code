<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('teacher');
$user = get_logged_in_user();

// Define constant to allow sidebar inclusion
define('ALLOW_ACCESS', true);

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

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_assignment':
                try {
                    $stmt = $pdo->prepare("INSERT INTO assignments (class_id, title, description, due_date, points, assignment_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $_POST['class_id'],
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['due_date'],
                        $_POST['points'],
                        $_POST['assignment_type'],
                        $user['id']
                    ]);
                    $message = $result ? "Assignment created successfully!" : "Error creating assignment.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'delete_assignment':
                $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ? AND created_by = ?");
                $result = $stmt->execute([$_POST['assignment_id'], $user['id']]);
                $message = $result ? "Assignment deleted successfully!" : "Error deleting assignment.";
                break;
        }
    }
}

// Get teacher's classes
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    WHERE c.teacher_id = ?
    ORDER BY s.name
");
$stmt->execute([$teacher['id']]);
$classes = $stmt->fetchAll();

// Get assignments
$stmt = $pdo->prepare("
    SELECT a.*, c.section, s.name as subject_name, s.code as subject_code,
           COUNT(sub.id) as submission_count
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN subjects s ON c.subject_id = s.id
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id
    WHERE c.teacher_id = ?
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute([$teacher['id']]);
$assignments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .assignment-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
        .assignment-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem; }
        .assignment-title { font-weight: 600; font-size: 1.1rem; }
        .assignment-meta { color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-due { background: #fef3c7; color: #d97706; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
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
                    <h1>Assignments</h1>
                    <p>Create and manage assignments for your classes</p>
                </div>
                <div>
                    <button onclick="openModal('createAssignmentModal')" class="btn btn-success"><i class="fas fa-plus"></i> Create Assignment</button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>My Assignments (<?= count($assignments) ?> total)</h3>
            
            <?php if (empty($assignments)): ?>
                <div style="text-align: center; padding: 2rem; color: #64748b;">
                    <i class="fas fa-tasks" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No assignments created yet. Click "Create Assignment" to get started.
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card">
                        <div class="assignment-header">
                            <div>
                                <div class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></div>
                                <div class="assignment-meta">
                                    <?= htmlspecialchars($assignment['subject_code']) ?> - Section <?= htmlspecialchars($assignment['section']) ?> | 
                                    Due: <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?> | 
                                    <?= htmlspecialchars($assignment['points']) ?> points
                                </div>
                            </div>
                            <div>
                                <?php
                                $now = new DateTime();
                                $due = new DateTime($assignment['due_date']);
                                if ($due < $now) {
                                    echo '<span class="status-badge status-overdue">Overdue</span>';
                                } elseif ($due->diff($now)->days <= 3) {
                                    echo '<span class="status-badge status-due">Due Soon</span>';
                                } else {
                                    echo '<span class="status-badge status-active">Active</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <div style="margin-bottom: 1rem; color: #64748b;">
                            <?= htmlspecialchars($assignment['description']) ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="color: #64748b; font-size: 0.875rem;">
                                <i class="fas fa-file-alt"></i> <?= $assignment['submission_count'] ?> submissions
                            </div>
                            <div>
                                <button onclick="viewSubmissions(<?= $assignment['id'] ?>)" class="btn" style="padding: 0.5rem 1rem; background: #3b82f6; color: white;">
                                    <i class="fas fa-eye"></i> View Submissions
                                </button>
                                <button onclick="editAssignment(<?= $assignment['id'] ?>)" class="btn" style="padding: 0.5rem 1rem; background: #d97706; color: white;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteAssignment(<?= $assignment['id'] ?>)" class="btn" style="padding: 0.5rem 1rem; background: #dc2626; color: white;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
            </div>
        </main>
    </div>

    <!-- Create Assignment Modal -->
    <div id="createAssignmentModal" class="modal">
        <div class="modal-content">
            <h3>Create New Assignment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_assignment">
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>">
                                <?= htmlspecialchars($class['subject_name']) ?> - Section <?= htmlspecialchars($class['section']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assignment Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Due Date & Time</label>
                        <input type="datetime-local" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Points</label>
                        <input type="number" name="points" class="form-control" min="1" value="100" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Assignment Type</label>
                    <select name="assignment_type" class="form-control" required>
                        <option value="homework">Homework</option>
                        <option value="quiz">Quiz</option>
                        <option value="exam">Exam</option>
                        <option value="project">Project</option>
                        <option value="essay">Essay</option>
                        <option value="lab">Lab Work</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeModal('createAssignmentModal')" class="btn btn-warning">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Assignment
                    </button>
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

        function viewSubmissions(assignmentId) {
            alert('View submissions for assignment ID: ' + assignmentId + '\n(This would show all student submissions)');
        }

        function editAssignment(assignmentId) {
            alert('Edit assignment ID: ' + assignmentId + '\n(This would open edit form)');
        }

        function deleteAssignment(assignmentId) {
            if (confirm('Are you sure you want to delete this assignment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_assignment">
                    <input type="hidden" name="assignment_id" value="${assignmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const dateInput = document.querySelector('input[name="due_date"]');
            if (dateInput) {
                dateInput.min = now.toISOString().slice(0, 16);
            }
        });
    </script>
        </main>
    </div>
</body>
</html>

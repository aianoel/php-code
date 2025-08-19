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
                    $stmt = $pdo->prepare("INSERT INTO subjects (code, name, description, credits, units, department, grade_level, strand) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $_POST['code'],
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['credits'],
                        $_POST['units'],
                        $_POST['department'],
                        $_POST['grade_level'],
                        $_POST['strand']
                    ]);
                    $message = $result ? "Subject created successfully!" : "Error creating subject.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'update_subject':
                try {
                    $stmt = $pdo->prepare("UPDATE subjects SET name = ?, description = ?, credits = ?, units = ?, department = ?, grade_level = ?, strand = ?, status = ? WHERE id = ?");
                    $result = $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['credits'],
                        $_POST['units'],
                        $_POST['department'],
                        $_POST['grade_level'],
                        $_POST['strand'],
                        $_POST['status'],
                        $_POST['subject_id']
                    ]);
                    $message = $result ? "Subject updated successfully!" : "Error updating subject.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'delete_subject':
                try {
                    $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                    $result = $stmt->execute([$_POST['subject_id']]);
                    $message = $result ? "Subject deleted successfully!" : "Error deleting subject.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all subjects
$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(DISTINCT c.id) as class_count,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM subjects s
    LEFT JOIN classes c ON s.id = c.subject_id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    GROUP BY s.id
    ORDER BY s.department, s.name
");
$stmt->execute();
$subjects = $stmt->fetchAll();

// Group subjects by department
$departments = [];
foreach ($subjects as $subject) {
    $dept = $subject['department'] ?: 'General';
    $departments[$dept][] = $subject;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - Academic Coordinator</title>
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
        .subjects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; max-width: 100%; overflow-x: hidden; }
        .subject-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; transition: all 0.3s; }
        .subject-card:hover { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .subject-header { display: flex; justify-content: between; align-items: start; margin-bottom: 0.5rem; }
        .subject-code { font-weight: bold; color: #3b82f6; }
        .subject-name { font-weight: 600; margin-bottom: 0.5rem; }
        .subject-stats { display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .status-badge { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-inactive { background: #fee2e2; color: #dc2626; }
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
                    <h1>Subject Management</h1>
                    <p>Manage academic subjects and curriculum</p>
                </div>
                <div>
                    <button onclick="openModal('createSubjectModal')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Subject
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Subjects by Department -->
        <?php foreach ($departments as $department => $dept_subjects): ?>
            <div class="card department-section">
                <div class="department-header">
                    <h3><?= htmlspecialchars($department) ?> Department</h3>
                    <p><?= count($dept_subjects) ?> subjects</p>
                </div>
                
                <div class="subjects-grid">
                    <?php foreach ($dept_subjects as $subject): ?>
                        <div class="subject-card">
                            <div class="subject-header">
                                <div>
                                    <div class="subject-code"><?= htmlspecialchars($subject['code']) ?></div>
                                    <div class="subject-name"><?= htmlspecialchars($subject['name']) ?></div>
                                </div>
                                <span class="status-badge status-<?= $subject['status'] ?>">
                                    <?= ucfirst($subject['status']) ?>
                                </span>
                            </div>
                            
                            <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($subject['description']) ?>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; font-size: 0.875rem; margin-bottom: 1rem;">
                                <span><strong>Credits:</strong> <?= $subject['credits'] ?></span>
                                <span><strong>Units:</strong> <?= isset($subject['units']) ? $subject['units'] : '' ?></span>
                                <?php if ($subject['grade_level']): ?>
                                    <span><strong>Grade:</strong> <?= htmlspecialchars($subject['grade_level']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="subject-stats">
                                <span><i class="fas fa-chalkboard"></i> <?= $subject['class_count'] ?> classes</span>
                                <span><i class="fas fa-users"></i> <?= $subject['student_count'] ?> students</span>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                <button onclick="editSubject(<?= htmlspecialchars(json_encode($subject)) ?>)" class="btn btn-warning" style="padding: 0.5rem; font-size: 0.75rem;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteSubject(<?= $subject['id'] ?>, '<?= htmlspecialchars($subject['name']) ?>')" class="btn btn-danger" style="padding: 0.5rem; font-size: 0.75rem;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($subjects)): ?>
            <div class="card">
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-book" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No Subjects Found</h3>
                    <p>Start by adding your first subject to the curriculum.</p>
                    <button onclick="openModal('createSubjectModal')" class="btn btn-success" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Add First Subject
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Subject Modal -->
    <div id="createSubjectModal" class="modal">
        <div class="modal-content">
            <h3>Add New Subject</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_subject">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Subject Code</label>
                        <input type="text" name="code" class="form-control" required placeholder="e.g., MATH101">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" class="form-control" required>
                            <option value="Mathematics">Mathematics</option>
                            <option value="English">English</option>
                            <option value="Science">Science</option>
                            <option value="Social Studies">Social Studies</option>
                            <option value="Physical Education">Physical Education</option>
                            <option value="Arts">Arts</option>
                            <option value="Technology">Technology</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Algebra I">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the subject"></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Credits</label>
                        <input type="number" name="credits" class="form-control" min="1" max="6" value="3" required>
                    </div>
                    <div class="form-group">
                        <label>Units</label>
                        <input type="number" name="units" class="form-control" min="1" max="6" value="3" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_level" class="form-control">
                            <option value="">All Grades</option>
                            <option value="7">Grade 7</option>
                            <option value="8">Grade 8</option>
                            <option value="9">Grade 9</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Strand</label>
                        <select name="strand" class="form-control">
                            <option value="">All Strands</option>
                            <option value="STEM">STEM</option>
                            <option value="ABM">ABM</option>
                            <option value="HUMSS">HUMSS</option>
                            <option value="GAS">GAS</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('createSubjectModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div id="editSubjectModal" class="modal">
        <div class="modal-content">
            <h3>Edit Subject</h3>
            <form method="POST" id="editSubjectForm">
                <input type="hidden" name="action" value="update_subject">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" id="edit_code" class="form-control" readonly style="background: #f3f4f6;">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Subject Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="edit_department" class="form-control" required>
                            <option value="Mathematics">Mathematics</option>
                            <option value="English">English</option>
                            <option value="Science">Science</option>
                            <option value="Social Studies">Social Studies</option>
                            <option value="Physical Education">Physical Education</option>
                            <option value="Arts">Arts</option>
                            <option value="Technology">Technology</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Credits</label>
                        <input type="number" name="credits" id="edit_credits" class="form-control" min="1" max="6" required>
                    </div>
                    <div class="form-group">
                        <label>Units</label>
                        <input type="number" name="units" id="edit_units" class="form-control" min="1" max="6" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_level" id="edit_grade_level" class="form-control">
                            <option value="">All Grades</option>
                            <option value="7">Grade 7</option>
                            <option value="8">Grade 8</option>
                            <option value="9">Grade 9</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Strand</label>
                        <select name="strand" id="edit_strand" class="form-control">
                            <option value="">All Strands</option>
                            <option value="STEM">STEM</option>
                            <option value="ABM">ABM</option>
                            <option value="HUMSS">HUMSS</option>
                            <option value="GAS">GAS</option>
                        </select>
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
                    <button type="button" onclick="closeModal('editSubjectModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Subject</button>
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
        
        function editSubject(subject) {
            document.getElementById('edit_subject_id').value = subject.id;
            document.getElementById('edit_code').value = subject.code;
            document.getElementById('edit_name').value = subject.name;
            document.getElementById('edit_description').value = subject.description || '';
            document.getElementById('edit_credits').value = subject.credits;
            document.getElementById('edit_units').value = subject.units;
            document.getElementById('edit_department').value = subject.department || 'General';
            document.getElementById('edit_grade_level').value = subject.grade_level || '';
            document.getElementById('edit_strand').value = subject.strand || '';
            document.getElementById('edit_status').value = subject.status;
            
            openModal('editSubjectModal');
        }
        
        function deleteSubject(subjectId, subjectName) {
            if (confirm('Are you sure you want to delete "' + subjectName + '"? This action cannot be undone.')) {
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
    </script>
        </main>
    </div>
</body>
</html>

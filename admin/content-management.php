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
            case 'create_announcement':
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, target_audience, priority, created_by) VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$_POST['title'], $_POST['content'], $_POST['target_audience'], $_POST['priority'], $user['id']]);
                $message = $result ? "Announcement created successfully!" : "Error creating announcement.";
                break;
            case 'delete_announcement':
                $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                $result = $stmt->execute([$_POST['announcement_id']]);
                $message = $result ? "Announcement deleted successfully!" : "Error deleting announcement.";
                break;
            case 'create_module':
                $stmt = $pdo->prepare("INSERT INTO learning_modules (title, description, content, class_id, module_type, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$_POST['title'], $_POST['description'], $_POST['content'], $_POST['class_id'], $_POST['module_type'], $user['id']]);
                $message = $result ? "Learning module created successfully!" : "Error creating module.";
                break;
            case 'delete_module':
                $stmt = $pdo->prepare("DELETE FROM learning_modules WHERE id = ?");
                $result = $stmt->execute([$_POST['module_id']]);
                $message = $result ? "Learning module deleted successfully!" : "Error deleting module.";
                break;
        }
    }
}

// Get announcements
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC
");
$stmt->execute();
$announcements = $stmt->fetchAll();

// Get learning modules
$stmt = $pdo->prepare("
    SELECT lm.*, c.section, s.name as subject_name, u.first_name, u.last_name 
    FROM learning_modules lm 
    LEFT JOIN classes c ON lm.class_id = c.id
    LEFT JOIN subjects s ON c.subject_id = s.id
    LEFT JOIN users u ON lm.created_by = u.id 
    ORDER BY lm.created_at DESC
");
$stmt->execute();
$modules = $stmt->fetchAll();

// Get classes for dropdown
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code 
    FROM classes c 
    LEFT JOIN subjects s ON c.subject_id = s.id 
    ORDER BY s.name, c.section
");
$stmt->execute();
$classes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color: white; padding: 2rem 0; position: fixed; height: 100vh; overflow-y: auto; z-index: 1000; }
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
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 2% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 2rem; }
        .tab { padding: 1rem 2rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #dc2626; color: #dc2626; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .priority-badge { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #dcfce7; color: #16a34a; }
        .content-preview { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
                            <h1>Content Management</h1>
                            <p>Manage announcements and learning materials</p>
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
            <div class="tab active" onclick="switchTab('announcements')">
                <i class="fas fa-bullhorn"></i> Announcements
            </div>
            <div class="tab" onclick="switchTab('modules')">
                <i class="fas fa-book-open"></i> Learning Modules
            </div>
        </div>

        <!-- Announcements Tab -->
        <div id="announcements" class="tab-content active">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Announcements</h3>
                    <button onclick="openModal('createAnnouncementModal')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Announcement
                    </button>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Target Audience</th>
                            <th>Priority</th>
                            <th>Created By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($announcements)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                                    <i class="fas fa-bullhorn" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    No announcements found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($announcement['title']) ?></strong><br>
                                        <small class="content-preview"><?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>...</small>
                                    </td>
                                    <td><?= ucfirst($announcement['target_audience']) ?></td>
                                    <td><span class="priority-badge priority-<?= $announcement['priority'] ?>"><?= ucfirst($announcement['priority']) ?></span></td>
                                    <td>
                                        <?php if ($announcement['first_name']): ?>
                                            <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']) ?>
                                        <?php else: ?>
                                            <em>System</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= format_date($announcement['created_at']) ?></td>
                                    <td>
                                        <button onclick="deleteAnnouncement(<?= $announcement['id'] ?>)" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
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

        <!-- Learning Modules Tab -->
        <div id="modules" class="tab-content">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Learning Modules</h3>
                    <button onclick="openModal('createModuleModal')" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Module
                    </button>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Grade Level</th>
                            <th>Created By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modules)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                                    <i class="fas fa-book-open" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    No learning modules found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($module['title']) ?></strong><br>
                                        <small class="content-preview"><?= htmlspecialchars(substr($module['description'], 0, 100)) ?>...</small>
                                    </td>
                                    <td><?= htmlspecialchars($module['subject_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($module['grade_level']) ?></td>
                                    <td>
                                        <?php if ($module['first_name']): ?>
                                            <?= htmlspecialchars($module['first_name'] . ' ' . $module['last_name']) ?>
                                        <?php else: ?>
                                            <em>System</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= format_date($module['created_at']) ?></td>
                                    <td>
                                        <button onclick="deleteModule(<?= $module['id'] ?>)" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
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

    <!-- Create Announcement Modal -->
    <div id="createAnnouncementModal" class="modal">
        <div class="modal-content">
            <h3>Create New Announcement</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_announcement">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" class="form-control" rows="6" required></textarea>
                </div>
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_audience" class="form-control" required>
                        <option value="all">All Users</option>
                        <option value="students">Students</option>
                        <option value="teachers">Teachers</option>
                        <option value="parents">Parents</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal('createAnnouncementModal')" class="btn btn-warning">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Module Modal -->
    <div id="createModuleModal" class="modal">
        <div class="modal-content">
            <h3>Create Learning Module</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_module">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" class="form-control" rows="8" required placeholder="Enter the module content, instructions, or materials..."></textarea>
                </div>
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>">
                                <?= htmlspecialchars($class['subject_name'] ?? 'Unknown Subject') ?> - Section <?= htmlspecialchars($class['section']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Module Type</label>
                    <select name="module_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="lesson">Lesson</option>
                        <option value="video">Video</option>
                        <option value="document">Document</option>
                        <option value="quiz">Quiz</option>
                        <option value="assignment">Assignment</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal('createModuleModal')" class="btn btn-warning">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Module</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function deleteAnnouncement(announcementId) {
            if (confirm('Are you sure you want to delete this announcement?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_announcement">
                    <input type="hidden" name="announcement_id" value="${announcementId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteModule(moduleId) {
            if (confirm('Are you sure you want to delete this learning module?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_module">
                    <input type="hidden" name="module_id" value="${moduleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
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

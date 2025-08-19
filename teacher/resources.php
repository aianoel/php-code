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

// Handle file upload and resource management
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_resource':
                try {
                    $stmt = $pdo->prepare("INSERT INTO learning_modules (class_id, title, description, content, module_type, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $_POST['class_id'],
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['content'],
                        $_POST['resource_type'],
                        $user['id']
                    ]);
                    $message = $result ? "Resource created successfully!" : "Error creating resource.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'delete_resource':
                $stmt = $pdo->prepare("DELETE FROM learning_modules WHERE id = ? AND created_by = ?");
                $result = $stmt->execute([$_POST['resource_id'], $user['id']]);
                $message = $result ? "Resource deleted successfully!" : "Error deleting resource.";
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

// Get resources/learning modules
$stmt = $pdo->prepare("
    SELECT lm.*, c.section, s.name as subject_name, s.code as subject_code
    FROM learning_modules lm
    JOIN classes c ON lm.class_id = c.id
    JOIN subjects s ON c.subject_id = s.id
    WHERE lm.created_by = ?
    ORDER BY lm.created_at DESC
");
$stmt->execute([$user['id']]);
$resources = $stmt->fetchAll();

// Organize resources by type
$resourcesByType = [
    'lesson' => [],
    'video' => [],
    'document' => [],
    'quiz' => [],
    'assignment' => []
];

foreach ($resources as $resource) {
    $type = $resource['module_type'] ?? 'document';
    if (isset($resourcesByType[$type])) {
        $resourcesByType[$type][] = $resource;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 2rem; }
        .tab { padding: 1rem 2rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #dc2626; color: #dc2626; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .resource-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .resource-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; transition: all 0.3s; }
        .resource-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        .resource-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .resource-title { font-weight: 600; font-size: 1.1rem; margin-bottom: 0.5rem; }
        .resource-meta { color: #64748b; font-size: 0.875rem; margin-bottom: 1rem; }
        .resource-actions { display: flex; gap: 0.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .type-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
        .type-lesson { background: #dbeafe; color: #2563eb; }
        .type-video { background: #fecaca; color: #dc2626; }
        .type-document { background: #dcfce7; color: #16a34a; }
        .type-quiz { background: #fef3c7; color: #d97706; }
        .type-assignment { background: #e0e7ff; color: #6366f1; }
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
                    <h1>Teaching Resources</h1>
                    <p>Manage and organize your teaching materials</p>
                </div>
                <div>
                    <button onclick="openModal('createResourceModal')" class="btn btn-success"><i class="fas fa-plus"></i> Add Resource</button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Resource Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($resources) ?></h3>
                <p>Total Resources</p>
            </div>
            <div class="stat-card">
                <h3><?= count($resourcesByType['lesson']) ?></h3>
                <p>Lessons</p>
            </div>
            <div class="stat-card">
                <h3><?= count($resourcesByType['video']) ?></h3>
                <p>Videos</p>
            </div>
            <div class="stat-card">
                <h3><?= count($resourcesByType['document']) ?></h3>
                <p>Documents</p>
            </div>
        </div>

        <div class="card">
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('all')">
                    <i class="fas fa-th-large"></i> All Resources
                </div>
                <div class="tab" onclick="switchTab('lessons')">
                    <i class="fas fa-book"></i> Lessons
                </div>
                <div class="tab" onclick="switchTab('videos')">
                    <i class="fas fa-video"></i> Videos
                </div>
                <div class="tab" onclick="switchTab('documents')">
                    <i class="fas fa-file-alt"></i> Documents
                </div>
                <div class="tab" onclick="switchTab('quizzes')">
                    <i class="fas fa-question-circle"></i> Quizzes
                </div>
            </div>

            <!-- All Resources Tab -->
            <div id="all" class="tab-content active">
                <?php if (empty($resources)): ?>
                    <div style="text-align: center; padding: 3rem; color: #64748b;">
                        <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        <h3>No resources yet</h3>
                        <p>Start by adding your first teaching resource</p>
                    </div>
                <?php else: ?>
                    <div class="resource-grid">
                        <?php foreach ($resources as $resource): ?>
                            <div class="resource-card">
                                <div class="resource-header">
                                    <div class="type-icon type-<?= $resource['module_type'] ?>">
                                        <i class="fas fa-<?= $resource['module_type'] === 'lesson' ? 'book' : ($resource['module_type'] === 'video' ? 'video' : ($resource['module_type'] === 'quiz' ? 'question-circle' : 'file-alt')) ?>"></i>
                                    </div>
                                    <div class="resource-actions">
                                        <button onclick="editResource(<?= $resource['id'] ?>)" class="btn" style="padding: 0.25rem 0.5rem; background: #d97706; color: white;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteResource(<?= $resource['id'] ?>)" class="btn" style="padding: 0.25rem 0.5rem; background: #dc2626; color: white;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                                <div class="resource-meta">
                                    <?= htmlspecialchars($resource['subject_code']) ?> - Section <?= htmlspecialchars($resource['section']) ?><br>
                                    <?= ucfirst($resource['module_type']) ?> • <?= date('M j, Y', strtotime($resource['created_at'])) ?>
                                </div>
                                <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">
                                    <?= htmlspecialchars(substr($resource['description'], 0, 100)) ?><?= strlen($resource['description']) > 100 ? '...' : '' ?>
                                </div>
                                <button onclick="viewResource(<?= $resource['id'] ?>)" class="btn" style="width: 100%; background: #3b82f6; color: white;">
                                    <i class="fas fa-eye"></i> View Resource
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Individual Type Tabs -->
            <?php foreach (['lessons' => 'lesson', 'videos' => 'video', 'documents' => 'document', 'quizzes' => 'quiz'] as $tabName => $type): ?>
                <div id="<?= $tabName ?>" class="tab-content">
                    <?php if (empty($resourcesByType[$type])): ?>
                        <div style="text-align: center; padding: 3rem; color: #64748b;">
                            <i class="fas fa-<?= $type === 'lesson' ? 'book' : ($type === 'video' ? 'video' : ($type === 'quiz' ? 'question-circle' : 'file-alt')) ?>" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            <h3>No <?= $tabName ?> yet</h3>
                            <p>Add your first <?= rtrim($tabName, 's') ?> resource</p>
                        </div>
                    <?php else: ?>
                        <div class="resource-grid">
                            <?php foreach ($resourcesByType[$type] as $resource): ?>
                                <div class="resource-card">
                                    <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                                    <div class="resource-meta">
                                        <?= htmlspecialchars($resource['subject_code']) ?> - Section <?= htmlspecialchars($resource['section']) ?><br>
                                        <?= date('M j, Y', strtotime($resource['created_at'])) ?>
                                    </div>
                                    <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">
                                        <?= htmlspecialchars(substr($resource['description'], 0, 100)) ?><?= strlen($resource['description']) > 100 ? '...' : '' ?>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button onclick="viewResource(<?= $resource['id'] ?>)" class="btn" style="flex: 1; background: #3b82f6; color: white;">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button onclick="deleteResource(<?= $resource['id'] ?>)" class="btn" style="background: #dc2626; color: white;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create Resource Modal -->
    <div id="createResourceModal" class="modal">
        <div class="modal-content">
            <h3>Add New Resource</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_resource">
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
                    <label>Resource Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Resource Type</label>
                    <select name="resource_type" class="form-control" required>
                        <option value="lesson">Lesson</option>
                        <option value="video">Video</option>
                        <option value="document">Document</option>
                        <option value="quiz">Quiz</option>
                        <option value="assignment">Assignment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" class="form-control" rows="8" placeholder="Enter the resource content, links, or instructions..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeModal('createResourceModal')" class="btn btn-warning">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Resource
                    </button>
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

        function viewResource(resourceId) {
            alert('View resource ID: ' + resourceId + '\n(This would open the full resource content)');
        }

        function editResource(resourceId) {
            alert('Edit resource ID: ' + resourceId + '\n(This would open edit form)');
        }

        function deleteResource(resourceId) {
            if (confirm('Are you sure you want to delete this resource? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_resource">
                    <input type="hidden" name="resource_id" value="${resourceId}">
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

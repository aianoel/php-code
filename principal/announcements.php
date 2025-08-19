<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('principal');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_announcement':
                try {
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, author_id, target_audience, priority, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $result = $stmt->execute([
                        $_POST['title'],
                        $_POST['content'],
                        $user['id'],
                        $_POST['target_audience'],
                        $_POST['priority']
                    ]);
                    $message = $result ? "Announcement created successfully!" : "Error creating announcement.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get announcements
try {
    $stmt = $pdo->prepare("
        SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as author_name
        FROM announcements a
        LEFT JOIN users u ON a.author_id = u.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
} catch (Exception $e) {
    $announcements = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Principal Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .announcement-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .announcement-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .announcement-title { font-size: 1.25rem; font-weight: 600; color: #1f2937; }
        .announcement-meta { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .priority-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #dcfce7; color: #16a34a; }
        .announcement-content { color: #4b5563; line-height: 1.6; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .modal.show { display: block; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1><i class="fas fa-bullhorn"></i> School Announcements</h1>
                            <p>Create and manage school-wide announcements</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal()">
                            <i class="fas fa-plus"></i> New Announcement
                        </button>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($announcements)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-bullhorn" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No announcements</h3>
                            <p>No announcements have been created yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-item">
                                <div class="announcement-header">
                                    <div class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></div>
                                    <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                        <?= ucfirst($announcement['priority']) ?> Priority
                                    </span>
                                </div>
                                
                                <div class="announcement-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?= htmlspecialchars($announcement['author_name']) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-users"></i>
                                        <span><?= ucfirst(htmlspecialchars($announcement['target_audience'])) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?></span>
                                    </div>
                                </div>
                                
                                <div class="announcement-content">
                                    <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Create New Announcement</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_announcement">
                
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" class="form-control" rows="6" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="target_audience">Target Audience</label>
                    <select id="target_audience" name="target_audience" class="form-control" required>
                        <option value="">Select audience...</option>
                        <option value="all">All Users</option>
                        <option value="students">Students</option>
                        <option value="teachers">Teachers</option>
                        <option value="parents">Parents</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control" required>
                        <option value="">Select priority...</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="hideModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal() {
            document.getElementById('announcementModal').classList.add('show');
        }
        
        function hideModal() {
            document.getElementById('announcementModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('announcementModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>

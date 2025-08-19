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

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_announcement':
                try {
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, target_audience, priority, created_by) VALUES (?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $_POST['title'],
                        $_POST['content'],
                        $_POST['target_audience'],
                        $_POST['priority'],
                        $user['id']
                    ]);
                    $message = $result ? "Announcement created successfully!" : "Error creating announcement.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get announcements created by this teacher
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id 
    WHERE a.created_by = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$user['id']]);
$myAnnouncements = $stmt->fetchAll();

// Get all announcements visible to teachers
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id 
    WHERE a.target_audience IN ('all', 'teachers', 'staff')
    ORDER BY a.created_at DESC
    LIMIT 20
");
$stmt->execute();
$allAnnouncements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Teacher</title>
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
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 2rem; }
        .tab { padding: 1rem 2rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #dc2626; color: #dc2626; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .announcement-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .announcement-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .announcement-title { font-weight: 600; font-size: 1.1rem; margin-bottom: 0.5rem; }
        .announcement-meta { color: #64748b; font-size: 0.875rem; }
        .priority-badge { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #dcfce7; color: #16a34a; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 90vh; overflow-y: auto; }
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
                    <h1>Announcements</h1>
                    <p>View and create announcements for your students</p>
                </div>
                <div>
                    <button onclick="openModal('createAnnouncementModal')" class="btn btn-success"><i class="fas fa-plus"></i> Create Announcement</button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('all')">
                    <i class="fas fa-bullhorn"></i> All Announcements
                </div>
                <div class="tab" onclick="switchTab('my')">
                    <i class="fas fa-user"></i> My Announcements (<?= count($myAnnouncements) ?>)
                </div>
            </div>

            <!-- All Announcements Tab -->
            <div id="all" class="tab-content active">
                <h3>Recent Announcements</h3>
                <?php if (empty($allAnnouncements)): ?>
                    <div style="text-align: center; padding: 3rem; color: #64748b;">
                        <i class="fas fa-bullhorn" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        <h3>No announcements yet</h3>
                        <p>Check back later for updates</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($allAnnouncements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <div>
                                    <div class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></div>
                                    <div class="announcement-meta">
                                        By <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']) ?> • 
                                        <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?> • 
                                        Target: <?= ucfirst($announcement['target_audience']) ?>
                                    </div>
                                </div>
                                <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                    <?= ucfirst($announcement['priority']) ?> Priority
                                </span>
                            </div>
                            <div style="color: #374151; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- My Announcements Tab -->
            <div id="my" class="tab-content">
                <h3>My Announcements</h3>
                <?php if (empty($myAnnouncements)): ?>
                    <div style="text-align: center; padding: 3rem; color: #64748b;">
                        <i class="fas fa-plus-circle" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        <h3>No announcements created yet</h3>
                        <p>Click "Create Announcement" to get started</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($myAnnouncements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <div>
                                    <div class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></div>
                                    <div class="announcement-meta">
                                        Created <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?> • 
                                        Target: <?= ucfirst($announcement['target_audience']) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                        <?= ucfirst($announcement['priority']) ?> Priority
                                    </span>
                                </div>
                            </div>
                            <div style="color: #374151; line-height: 1.6; margin-bottom: 1rem;">
                                <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button onclick="editAnnouncement(<?= $announcement['id'] ?>)" class="btn" style="padding: 0.5rem 1rem; background: #d97706; color: white;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteAnnouncement(<?= $announcement['id'] ?>)" class="btn" style="padding: 0.5rem 1rem; background: #dc2626; color: white;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                    <textarea name="content" class="form-control" rows="6" required placeholder="Enter your announcement content..."></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Target Audience</label>
                        <select name="target_audience" class="form-control" required>
                            <option value="students">Students</option>
                            <option value="parents">Parents</option>
                            <option value="teachers">Teachers</option>
                            <option value="staff">Staff</option>
                            <option value="all">Everyone</option>
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
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeModal('createAnnouncementModal')" class="btn btn-warning">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Announcement
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

        function editAnnouncement(announcementId) {
            alert('Edit announcement ID: ' + announcementId + '\n(This would open edit form)');
        }

        function deleteAnnouncement(announcementId) {
            if (confirm('Are you sure you want to delete this announcement?')) {
                alert('Delete announcement ID: ' + announcementId + '\n(This would delete the announcement)');
            }
        }
    </script>
        </main>
    </div>
</body>
</html>

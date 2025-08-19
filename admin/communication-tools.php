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
            case 'send_message':
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([$user['id'], $_POST['recipient_id'], $_POST['subject'], $_POST['content']]);
                $message = $result ? "Message sent successfully!" : "Error sending message.";
                break;
            case 'send_bulk_notification':
                $recipients = $_POST['recipients'];
                $title = $_POST['title'];
                $content = $_POST['content'];
                $type = $_POST['type'];
                
                if ($recipients === 'all') {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE status = 'active'");
                    $stmt->execute();
                    $users = $stmt->fetchAll();
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
                    $stmt->execute([$recipients]);
                    $users = $stmt->fetchAll();
                }
                
                $sent_count = 0;
                foreach ($users as $recipient) {
                    if (send_notification($recipient['id'], $title, $content, $type)) {
                        $sent_count++;
                    }
                }
                $message = "Notification sent to {$sent_count} users.";
                break;
        }
    }
}

// Get all users for messaging
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE status = 'active' ORDER BY role, last_name");
$stmt->execute();
$all_users = $stmt->fetchAll();

// Get recent messages
$stmt = $pdo->prepare("
    SELECT m.*, 
           s.first_name as sender_first, s.last_name as sender_last,
           r.first_name as recipient_first, r.last_name as recipient_last
    FROM messages m
    LEFT JOIN users s ON m.sender_id = s.id
    LEFT JOIN users r ON m.recipient_id = r.id
    ORDER BY m.created_at DESC
    LIMIT 20
");
$stmt->execute();
$recent_messages = $stmt->fetchAll();

// Get notifications
$stmt = $pdo->prepare("
    SELECT n.*, u.first_name, u.last_name
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
    LIMIT 20
");
$stmt->execute();
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Tools - Admin</title>
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
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 80vh; overflow-y: auto; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 2rem; }
        .tab { padding: 1rem 2rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #dc2626; color: #dc2626; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .message-item { border-left: 4px solid #e5e7eb; padding: 1rem; margin-bottom: 1rem; background: #f8fafc; border-radius: 0.5rem; }
        .message-item.unread { border-left-color: #dc2626; }
        .notification-type { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .type-info { background: #dbeafe; color: #1e40af; }
        .type-warning { background: #fef3c7; color: #d97706; }
        .type-success { background: #dcfce7; color: #16a34a; }
        .type-error { background: #fee2e2; color: #dc2626; }
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
                            <h1>Communication Tools</h1>
                            <p>Manage messages and notifications</p>
                        </div>
                    </div>
                </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <button onclick="openModal('sendMessageModal')" class="btn btn-success">
                <i class="fas fa-envelope"></i> Send Message
            </button>
            <button onclick="openModal('bulkNotificationModal')" class="btn btn-warning">
                <i class="fas fa-bullhorn"></i> Send Notification
            </button>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('messages')">
                <i class="fas fa-envelope"></i> Messages
            </div>
            <div class="tab" onclick="switchTab('notifications')">
                <i class="fas fa-bell"></i> Notifications
            </div>
        </div>

        <!-- Messages Tab -->
        <div id="messages" class="tab-content active">
            <div class="card">
                <h3>Recent Messages</h3>
                <?php if (empty($recent_messages)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-envelope" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        No messages found
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_messages as $msg): ?>
                        <div class="message-item">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong><?= htmlspecialchars($msg['subject']) ?></strong><br>
                                    <small>From: <?= htmlspecialchars($msg['sender_first'] . ' ' . $msg['sender_last']) ?></small><br>
                                    <small>To: <?= htmlspecialchars($msg['recipient_first'] . ' ' . $msg['recipient_last']) ?></small><br>
                                    <p style="margin-top: 0.5rem;"><?= htmlspecialchars(substr($msg['content'], 0, 150)) ?>...</p>
                                </div>
                                <small><?= format_date($msg['created_at']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications" class="tab-content">
            <div class="card">
                <h3>Recent Notifications</h3>
                <?php if (empty($notifications)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-bell" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        No notifications found
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="message-item">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                        <strong><?= htmlspecialchars($notif['title']) ?></strong>
                                        <span class="notification-type type-<?= $notif['type'] ?>"><?= ucfirst($notif['type']) ?></span>
                                    </div>
                                    <small>To: <?= htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']) ?></small><br>
                                    <p style="margin-top: 0.5rem;"><?= htmlspecialchars($notif['message']) ?></p>
                                </div>
                                <small><?= format_date($notif['created_at']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div id="sendMessageModal" class="modal">
        <div class="modal-content">
            <h3>Send Message</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                <div class="form-group">
                    <label>Recipient</label>
                    <select name="recipient_id" class="form-control" required>
                        <option value="">Select Recipient</option>
                        <?php 
                        $current_role = '';
                        foreach ($all_users as $u): 
                            if ($current_role !== $u['role']):
                                if ($current_role !== '') echo '</optgroup>';
                                echo '<optgroup label="' . ucfirst($u['role']) . 's">';
                                $current_role = $u['role'];
                            endif;
                        ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ')') ?></option>
                        <?php endforeach; ?>
                        <?php if ($current_role !== '') echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="content" class="form-control" rows="6" required></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal('sendMessageModal')" class="btn btn-warning">Cancel</button>
                    <button type="submit" class="btn btn-success">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Notification Modal -->
    <div id="bulkNotificationModal" class="modal">
        <div class="modal-content">
            <h3>Send Bulk Notification</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send_bulk_notification">
                <div class="form-group">
                    <label>Recipients</label>
                    <select name="recipients" class="form-control" required>
                        <option value="all">All Users</option>
                        <option value="student">All Students</option>
                        <option value="teacher">All Teachers</option>
                        <option value="parent">All Parents</option>
                        <option value="staff">All Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" class="form-control" rows="6" required></textarea>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control" required>
                        <option value="info">Information</option>
                        <option value="warning">Warning</option>
                        <option value="success">Success</option>
                        <option value="error">Error</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal('bulkNotificationModal')" class="btn btn-warning">Cancel</button>
                    <button type="submit" class="btn btn-success">Send Notification</button>
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

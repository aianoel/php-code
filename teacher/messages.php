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
            case 'send_message':
                try {
                    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content) VALUES (?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $user['id'],
                        $_POST['recipient_id'],
                        $_POST['subject'],
                        $_POST['content']
                    ]);
                    $message = $result ? "Message sent successfully!" : "Error sending message.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get received messages
$stmt = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.recipient_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$user['id']]);
$receivedMessages = $stmt->fetchAll();

// Get sent messages
$stmt = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.role
    FROM messages m
    JOIN users u ON m.recipient_id = u.id
    WHERE m.sender_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$user['id']]);
$sentMessages = $stmt->fetchAll();

// Get users for messaging (students, parents, other teachers, admin)
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.role
    FROM users u
    WHERE u.id != ? AND u.status = 'active'
    ORDER BY u.role, u.last_name, u.first_name
");
$stmt->execute([$user['id']]);
$users = $stmt->fetchAll();

// Organize users by role
$usersByRole = [];
foreach ($users as $userItem) {
    $usersByRole[$userItem['role']][] = $userItem;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Teacher</title>
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
        .message-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .message-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .message-sender { font-weight: 600; }
        .message-meta { color: #64748b; font-size: 0.875rem; }
        .message-subject { font-weight: 600; margin-bottom: 0.5rem; }
        .message-content { color: #374151; line-height: 1.6; }
        .unread { border-left: 4px solid #dc2626; background: #fef2f2; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .role-badge { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .role-admin { background: #fee2e2; color: #dc2626; }
        .role-teacher { background: #dbeafe; color: #2563eb; }
        .role-student { background: #dcfce7; color: #16a34a; }
        .role-parent { background: #fef3c7; color: #d97706; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
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
                    <h1>Messages</h1>
                    <p>Communicate with students, parents, and colleagues</p>
                </div>
                <div>
                    <button onclick="openModal('composeModal')" class="btn btn-success"><i class="fas fa-plus"></i> Compose Message</button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Message Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($receivedMessages) ?></h3>
                <p>Received Messages</p>
            </div>
            <div class="stat-card">
                <h3><?= count($sentMessages) ?></h3>
                <p>Sent Messages</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_filter($receivedMessages, function($msg) { return !$msg['is_read']; })) ?></h3>
                <p>Unread Messages</p>
            </div>
            <div class="stat-card">
                <h3><?= count($users) ?></h3>
                <p>Available Contacts</p>
            </div>
        </div>

        <div class="card">
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('inbox')">
                    <i class="fas fa-inbox"></i> Inbox (<?= count($receivedMessages) ?>)
                </div>
                <div class="tab" onclick="switchTab('sent')">
                    <i class="fas fa-paper-plane"></i> Sent (<?= count($sentMessages) ?>)
                </div>
            </div>

            <!-- Inbox Tab -->
            <div id="inbox" class="tab-content active">
                <h3>Received Messages</h3>
                <?php if (empty($receivedMessages)): ?>
                    <div style="text-align: center; padding: 3rem; color: #64748b;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        <h3>No messages yet</h3>
                        <p>Your inbox is empty</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($receivedMessages as $msg): ?>
                        <div class="message-card <?= !$msg['is_read'] ? 'unread' : '' ?>">
                            <div class="message-header">
                                <div>
                                    <div class="message-sender">
                                        <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                                        <span class="role-badge role-<?= $msg['role'] ?>"><?= ucfirst($msg['role']) ?></span>
                                    </div>
                                    <div class="message-meta">
                                        <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?>
                                        <?= !$msg['is_read'] ? '• <strong>Unread</strong>' : '' ?>
                                    </div>
                                </div>
                                <div>
                                    <button onclick="replyToMessage(<?= $msg['id'] ?>, '<?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>')" class="btn" style="padding: 0.5rem 1rem; background: #3b82f6; color: white;">
                                        <i class="fas fa-reply"></i> Reply
                                    </button>
                                </div>
                            </div>
                            <div class="message-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                            <div class="message-content"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sent Tab -->
            <div id="sent" class="tab-content">
                <h3>Sent Messages</h3>
                <?php if (empty($sentMessages)): ?>
                    <div style="text-align: center; padding: 3rem; color: #64748b;">
                        <i class="fas fa-paper-plane" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        <h3>No sent messages</h3>
                        <p>Messages you send will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sentMessages as $msg): ?>
                        <div class="message-card">
                            <div class="message-header">
                                <div>
                                    <div class="message-sender">
                                        To: <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                                        <span class="role-badge role-<?= $msg['role'] ?>"><?= ucfirst($msg['role']) ?></span>
                                    </div>
                                    <div class="message-meta">
                                        Sent <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="message-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                            <div class="message-content"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Compose Message Modal -->
    <div id="composeModal" class="modal">
        <div class="modal-content">
            <h3>Compose New Message</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                <div class="form-group">
                    <label>Recipient</label>
                    <select name="recipient_id" class="form-control" required>
                        <option value="">Select recipient...</option>
                        <?php foreach ($usersByRole as $role => $roleUsers): ?>
                            <optgroup label="<?= ucfirst($role) ?>s">
                                <?php foreach ($roleUsers as $userItem): ?>
                                    <option value="<?= $userItem['id'] ?>">
                                        <?= htmlspecialchars($userItem['first_name'] . ' ' . $userItem['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="content" class="form-control" rows="8" required placeholder="Type your message here..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeModal('composeModal')" class="btn btn-warning">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Send Message
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

        function replyToMessage(messageId, senderName) {
            openModal('composeModal');
            document.querySelector('input[name="subject"]').value = 'Re: ';
            // You could pre-fill more details here
            alert('Reply to message from ' + senderName + '\n(Subject field has been pre-filled)');
        }
    </script>
        </main>
    </div>
</body>
</html>

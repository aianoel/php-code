<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('parent');
$user = get_logged_in_user();

// Get messages for parent
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               CONCAT(sender.first_name, ' ', sender.last_name) as sender_name,
               sender.role as sender_role
        FROM messages m
        LEFT JOIN users sender ON m.sender_id = sender.id
        WHERE m.recipient_id = ? OR m.sender_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Parent Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .message-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .message-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .sender-info { display: flex; align-items: center; gap: 0.75rem; }
        .sender-avatar { width: 40px; height: 40px; border-radius: 50%; background: #3b82f6; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .sender-details h4 { color: #1f2937; margin-bottom: 0.25rem; }
        .sender-details p { color: #6b7280; font-size: 0.875rem; }
        .message-time { color: #6b7280; font-size: 0.875rem; }
        .message-subject { font-size: 1.125rem; font-weight: 600; color: #1f2937; margin-bottom: 0.75rem; }
        .message-content { color: #4b5563; line-height: 1.6; }
        .status-unread { border-left: 4px solid #3b82f6; background: #eff6ff; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-comments"></i> Messages</h1>
                    <p>Communication with teachers and school staff</p>
                </div>

                <div class="card">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No messages</h3>
                            <p>You don't have any messages at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message-item <?= $message['is_read'] ? '' : 'status-unread' ?>">
                                <div class="message-header">
                                    <div class="sender-info">
                                        <div class="sender-avatar">
                                            <?= strtoupper(substr($message['sender_name'], 0, 1)) ?>
                                        </div>
                                        <div class="sender-details">
                                            <h4><?= htmlspecialchars($message['sender_name']) ?></h4>
                                            <p><?= ucfirst(htmlspecialchars($message['sender_role'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="message-time">
                                        <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>
                                    </div>
                                </div>
                                
                                <div class="message-subject"><?= htmlspecialchars($message['subject']) ?></div>
                                <div class="message-content"><?= nl2br(htmlspecialchars($message['content'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

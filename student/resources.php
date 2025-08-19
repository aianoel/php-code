<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('student');
$user = get_logged_in_user();

// Get learning resources
try {
    $stmt = $pdo->prepare("
        SELECT r.*, sub.name as subject_name, sub.code as subject_code,
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM learning_resources r
        LEFT JOIN subjects sub ON r.subject_id = sub.id
        LEFT JOIN users u ON r.teacher_id = u.id
        WHERE r.subject_id IN (
            SELECT DISTINCT s.subject_id FROM student_schedules s WHERE s.student_id = ?
        )
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $resources = $stmt->fetchAll();
} catch (Exception $e) {
    $resources = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources - Student Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .resources-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .resource-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; transition: all 0.3s; }
        .resource-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        .resource-icon { width: 50px; height: 50px; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 1.5rem; color: white; }
        .icon-pdf { background: #dc2626; }
        .icon-video { background: #7c3aed; }
        .icon-link { background: #059669; }
        .icon-doc { background: #2563eb; }
        .resource-title { font-size: 1.125rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem; }
        .resource-meta { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .resource-description { color: #4b5563; margin-bottom: 1rem; font-size: 0.875rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-book"></i> Learning Resources</h1>
                    <p>Access study materials and resources for your subjects</p>
                </div>

                <div class="card">
                    <?php if (empty($resources)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No resources available</h3>
                            <p>Your teachers haven't uploaded any learning resources yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="resources-grid">
                            <?php foreach ($resources as $resource): ?>
                                <div class="resource-card">
                                    <?php
                                    $icon_class = 'icon-doc';
                                    $icon = 'fas fa-file';
                                    if (strpos($resource['file_type'], 'pdf') !== false) {
                                        $icon_class = 'icon-pdf';
                                        $icon = 'fas fa-file-pdf';
                                    } elseif (strpos($resource['file_type'], 'video') !== false) {
                                        $icon_class = 'icon-video';
                                        $icon = 'fas fa-play';
                                    } elseif ($resource['resource_type'] === 'link') {
                                        $icon_class = 'icon-link';
                                        $icon = 'fas fa-link';
                                    }
                                    ?>
                                    <div class="resource-icon <?= $icon_class ?>">
                                        <i class="<?= $icon ?>"></i>
                                    </div>
                                    
                                    <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                                    
                                    <div class="resource-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-book"></i>
                                            <span><?= htmlspecialchars($resource['subject_name']) ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                            <span><?= htmlspecialchars($resource['teacher_name']) ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <span><?= date('M j, Y', strtotime($resource['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($resource['description'])): ?>
                                        <div class="resource-description">
                                            <?= htmlspecialchars($resource['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="<?= htmlspecialchars($resource['file_path'] ?? '#') ?>" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-download"></i> Access Resource
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

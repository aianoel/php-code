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
            case 'create_document_type':
                try {
                    $stmt = $pdo->prepare("INSERT INTO document_types (name, description, required_for_role, is_active) VALUES (?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['required_for_role'],
                        isset($_POST['is_active']) ? 1 : 0
                    ]);
                    $message = $result ? "Document type created successfully!" : "Error creating document type.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'update_document_status':
                try {
                    $stmt = $pdo->prepare("UPDATE document_submissions SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?");
                    $result = $stmt->execute([
                        $_POST['status'],
                        $user['id'],
                        $_POST['review_notes'],
                        $_POST['document_id']
                    ]);
                    $message = $result ? "Document status updated successfully!" : "Error updating document.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'add_comment':
                try {
                    $stmt = $pdo->prepare("INSERT INTO document_review_comments (document_id, user_id, comment, is_internal) VALUES (?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $_POST['document_id'],
                        $user['id'],
                        $_POST['comment'],
                        isset($_POST['is_internal']) ? 1 : 0
                    ]);
                    $message = $result ? "Comment added successfully!" : "Error adding comment.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get document types
try {
    $stmt = $pdo->prepare("SELECT * FROM document_types ORDER BY name");
    $stmt->execute();
    $document_types = $stmt->fetchAll();
} catch (Exception $e) {
    $document_types = [];
}

// Get pending documents for review
try {
    $stmt = $pdo->prepare("
        SELECT ds.*, dt.name as document_type_name, 
               CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
               CONCAT(r.first_name, ' ', r.last_name) as reviewed_by_name
        FROM document_submissions ds
        JOIN document_types dt ON ds.document_type_id = dt.id
        JOIN users u ON ds.submitted_by = u.id
        LEFT JOIN users r ON ds.reviewed_by = r.id
        ORDER BY ds.priority DESC, ds.submitted_at ASC
    ");
    $stmt->execute();
    $documents = $stmt->fetchAll();
} catch (Exception $e) {
    $documents = [];
}

// Get statistics
try {
    $stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM document_submissions WHERE status = 'pending'")->fetchColumn(),
        'under_review' => $pdo->query("SELECT COUNT(*) FROM document_submissions WHERE status = 'under_review'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM document_submissions WHERE status = 'approved'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM document_submissions WHERE status = 'rejected'")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Review Management - Admin Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 2rem; 
        }
        
        .header { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #1f2937;
            padding: 2.5rem;
            border-radius: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .header h1 {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #6b7280;
            font-size: 1.125rem;
            font-weight: 500;
        }
        
        .card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .btn { 
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        
        .btn-primary:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.6);
        }
        
        .btn-sm { 
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }
        
        .stats-grid { 
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        
        .stat-card.warning::before { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.success::before { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.danger::before { background: linear-gradient(135deg, #ef4444, #dc2626); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .stat-value { 
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #1f2937, #374151);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card.warning .stat-value {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card.success .stat-value {
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card.danger .stat-value {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label { 
            color: #6b7280;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .form-group { 
            margin-bottom: 1.5rem;
        }
        
        .form-group label { 
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }
        
        .form-control { 
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus { 
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background: rgba(255, 255, 255, 1);
        }
        
        .document-item { 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .document-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .document-header { 
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .document-title { 
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .document-meta { 
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .meta-item { 
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-badge, .priority-badge { 
            padding: 0.375rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .status-pending { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
        .status-under_review { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; }
        .status-approved { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; }
        .status-rejected { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; }
        
        .priority-low { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #374151; }
        .priority-medium { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
        .priority-high { background: linear-gradient(135deg, #fecaca, #fca5a5); color: #991b1b; }
        .priority-urgent { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        
        .alert { 
            padding: 1.25rem 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 1px solid;
        }
        
        .alert-success { 
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border-color: #bbf7d0;
        }
        
        .alert-error { 
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-color: #fecaca;
        }
        
        .modal { 
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
        }
        
        .modal-content { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            margin: 5% auto;
            padding: 2.5rem;
            border-radius: 1.5rem;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .modal.show { display: block; }
        
        .tabs { 
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2.5rem;
            background: rgba(243, 244, 246, 0.8);
            border-radius: 1rem;
            padding: 0.5rem;
        }
        
        .tab { 
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            color: #6b7280;
        }
        
        .tab.active { 
            background: white;
            color: #3b82f6;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .document-actions { 
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .document-actions .btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        .empty-state p {
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .header { padding: 1.5rem; }
            .header h1 { font-size: 2rem; }
            .card { padding: 1.5rem; }
            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .document-header { flex-direction: column; gap: 1rem; }
            .document-meta { flex-direction: column; gap: 0.75rem; }
            .document-actions { justify-content: center; }
        }
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
                            <h1><i class="fas fa-file-alt"></i> Document Review Management</h1>
                            <p>Manage document types, review submissions, and oversee approval workflows</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal('documentTypeModal')">
                            <i class="fas fa-plus"></i> Add Document Type
                        </button>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card warning">
                        <div class="stat-value"><?= $stats['pending'] ?></div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['under_review'] ?></div>
                        <div class="stat-label">Under Review</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= $stats['approved'] ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value"><?= $stats['rejected'] ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>

                <div class="card">
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('documents')">
                            <i class="fas fa-file-alt"></i> Document Submissions
                        </div>
                        <div class="tab" onclick="switchTab('types')">
                            <i class="fas fa-cog"></i> Document Types
                        </div>
                    </div>

                    <div id="documents-tab" class="tab-content active">
                        <?php if (empty($documents)): ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <h3>No document submissions</h3>
                                <p>No documents have been submitted for review yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-item">
                                    <div class="document-header">
                                        <div>
                                            <div class="document-title"><?= htmlspecialchars($doc['title']) ?></div>
                                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;">
                                                <?= htmlspecialchars($doc['document_type_name']) ?>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <span class="priority-badge priority-<?= $doc['priority'] ?>">
                                                <?= ucfirst($doc['priority']) ?>
                                            </span>
                                            <span class="status-badge status-<?= $doc['status'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $doc['status'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="document-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-user"></i>
                                            <span>Submitted by: <?= htmlspecialchars($doc['submitted_by_name']) ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <span><?= date('M j, Y g:i A', strtotime($doc['submitted_at'])) ?></span>
                                        </div>
                                        <?php if ($doc['due_date']): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-clock"></i>
                                                <span>Due: <?= date('M j, Y', strtotime($doc['due_date'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($doc['file_name']): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-paperclip"></i>
                                                <span><?= htmlspecialchars($doc['file_name']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($doc['description']): ?>
                                        <div style="margin-bottom: 1rem; color: #4b5563;">
                                            <?= nl2br(htmlspecialchars($doc['description'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($doc['review_notes']): ?>
                                        <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                                            <strong>Review Notes:</strong>
                                            <div style="margin-top: 0.5rem;"><?= nl2br(htmlspecialchars($doc['review_notes'])) ?></div>
                                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                                Reviewed by: <?= htmlspecialchars($doc['reviewed_by_name']) ?> on <?= date('M j, Y', strtotime($doc['reviewed_at'])) ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="document-actions">
                                        <?php if ($doc['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-primary" onclick="reviewDocument(<?= $doc['id'] ?>, 'under_review')">
                                                Start Review
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($doc['status'], ['pending', 'under_review'])): ?>
                                            <button class="btn btn-sm" style="background: #16a34a; color: white;" onclick="reviewDocument(<?= $doc['id'] ?>, 'approved')">
                                                Approve
                                            </button>
                                            <button class="btn btn-sm" style="background: #dc2626; color: white;" onclick="reviewDocument(<?= $doc['id'] ?>, 'rejected')">
                                                Reject
                                            </button>
                                            <button class="btn btn-sm" style="background: #d97706; color: white;" onclick="reviewDocument(<?= $doc['id'] ?>, 'revision_required')">
                                                Request Revision
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm" style="background: #6b7280; color: white;" onclick="addComment(<?= $doc['id'] ?>)">
                                            <i class="fas fa-comment"></i> Comment
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="types-tab" class="tab-content">
                        <div style="margin-bottom: 2rem;">
                            <h3>Document Types Configuration</h3>
                            <p style="color: #6b7280;">Manage the types of documents that can be submitted for review.</p>
                        </div>
                        
                        <?php if (empty($document_types)): ?>
                            <div class="empty-state">
                                <i class="fas fa-cog"></i>
                                <h3>No document types configured</h3>
                                <p>Create document types to enable document submissions.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Name</th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Description</th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Required For Role</th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($document_types as $type): ?>
                                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                                <td style="padding: 1rem; font-weight: 600;"><?= htmlspecialchars($type['name']) ?></td>
                                                <td style="padding: 1rem; color: #6b7280;"><?= htmlspecialchars($type['description']) ?></td>
                                                <td style="padding: 1rem;">
                                                    <span style="background: #f3f4f6; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem;">
                                                        <?= ucfirst($type['required_for_role']) ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <span class="status-badge <?= $type['is_active'] ? 'status-approved' : 'status-rejected' ?>">
                                                        <?= $type['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Document Type Modal -->
    <div id="documentTypeModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Add Document Type</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_document_type">
                
                <div class="form-group">
                    <label for="name">Document Type Name</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="required_for_role">Required For Role</label>
                    <select id="required_for_role" name="required_for_role" class="form-control" required>
                        <option value="">Select role...</option>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                        <option value="parent">Parent</option>
                        <option value="registrar">Registrar</option>
                        <option value="accounting">Accounting</option>
                        <option value="principal">Principal</option>
                        <option value="guidance">Guidance</option>
                        <option value="academic_coordinator">Academic Coordinator</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="hideModal('documentTypeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Document Type</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Review Document</h3>
            <form method="POST" id="reviewForm">
                <input type="hidden" name="action" value="update_document_status">
                <input type="hidden" name="document_id" id="review_document_id">
                <input type="hidden" name="status" id="review_status">
                
                <div class="form-group">
                    <label for="review_notes">Review Notes</label>
                    <textarea id="review_notes" name="review_notes" class="form-control" rows="4" placeholder="Add your review comments..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="hideModal('reviewModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
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
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.closest('.tab').classList.add('active');
        }

        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        
        function hideModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function reviewDocument(documentId, status) {
            document.getElementById('review_document_id').value = documentId;
            document.getElementById('review_status').value = status;
            showModal('reviewModal');
        }
        
        function addComment(documentId) {
            // This would open a comment modal - simplified for now
            const comment = prompt('Add a comment:');
            if (comment) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="document_id" value="${documentId}">
                    <input type="hidden" name="comment" value="${comment}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideModal(this.id);
                }
            });
        });
    </script>
</body>
</html>

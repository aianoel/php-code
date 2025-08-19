<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('student');
$user = get_logged_in_user();

// Get student record
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $student = $stmt->fetch();
} catch (Exception $e) {
    $student = null;
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'submit_document') {
        try {
            // Handle file upload
            $file_path = null;
            $file_name = null;
            $file_size = null;
            $mime_type = null;
            
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = $_FILES['document_file']['name'];
                $file_size = $_FILES['document_file']['size'];
                $mime_type = $_FILES['document_file']['type'];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $unique_name;
                
                if (move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
                    $file_path = 'uploads/documents/' . $unique_name;
                } else {
                    throw new Exception("Failed to upload file");
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO document_submissions (document_type_id, submitted_by, student_id, title, description, file_path, file_name, file_size, mime_type, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $_POST['document_type_id'],
                $user['id'],
                $student ? $student['id'] : null,
                $_POST['title'],
                $_POST['description'],
                $file_path,
                $file_name,
                $file_size,
                $mime_type,
                $_POST['priority']
            ]);
            $message = $result ? "Document submitted successfully!" : "Error submitting document.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Get student document types
try {
    $stmt = $pdo->prepare("SELECT * FROM document_types WHERE required_for_role = 'student' AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $document_types = $stmt->fetchAll();
} catch (Exception $e) {
    $document_types = [];
}

// Get student's submitted documents
try {
    $stmt = $pdo->prepare("
        SELECT ds.*, dt.name as document_type_name,
               CONCAT(r.first_name, ' ', r.last_name) as reviewed_by_name
        FROM document_submissions ds
        JOIN document_types dt ON ds.document_type_id = dt.id
        LEFT JOIN users r ON ds.reviewed_by = r.id
        WHERE ds.submitted_by = ?
        ORDER BY ds.submitted_at DESC
    ");
    $stmt->execute([$user['id']]);
    $my_documents = $stmt->fetchAll();
} catch (Exception $e) {
    $my_documents = [];
}

// Get statistics
try {
    $stats = [
        'total_submitted' => 0,
        'pending' => 0,
        'approved' => 0,
        'revision_required' => 0
    ];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_submissions WHERE submitted_by = ?");
    $stmt->execute([$user['id']]);
    $stats['total_submitted'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_submissions WHERE submitted_by = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    $stats['pending'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_submissions WHERE submitted_by = ? AND status = 'approved'");
    $stmt->execute([$user['id']]);
    $stats['approved'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_submissions WHERE submitted_by = ? AND status = 'revision_required'");
    $stmt->execute([$user['id']]);
    $stats['revision_required'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $stats = ['total_submitted' => 0, 'pending' => 0, 'approved' => 0, 'revision_required' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Submission - Student Portal</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; }
        .stat-card.warning { background: linear-gradient(135deg, #d97706, #b45309); }
        .stat-card.success { background: linear-gradient(135deg, #16a34a, #15803d); }
        .stat-card.info { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { opacity: 0.9; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .document-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .document-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .document-title { font-size: 1.125rem; font-weight: 600; color: #1f2937; }
        .document-meta { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-under_review { background: #dbeafe; color: #2563eb; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .status-revision_required { background: #fef3c7; color: #d97706; }
        .priority-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .priority-low { background: #f3f4f6; color: #6b7280; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-high { background: #fecaca; color: #dc2626; }
        .priority-urgent { background: #dc2626; color: white; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 80vh; overflow-y: auto; }
        .modal.show { display: block; }
        .file-upload { border: 2px dashed #d1d5db; border-radius: 0.5rem; padding: 2rem; text-align: center; transition: all 0.3s; cursor: pointer; }
        .file-upload:hover { border-color: #3b82f6; background: #f8fafc; }
        .file-upload.dragover { border-color: #3b82f6; background: #eff6ff; }
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
                            <h1><i class="fas fa-file-upload"></i> Document Submission</h1>
                            <p>Submit your assignments and projects for review</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal()">
                            <i class="fas fa-plus"></i> Submit Document
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
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_submitted'] ?></div>
                        <div class="stat-label">Total Submitted</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= $stats['pending'] ?></div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= $stats['approved'] ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value"><?= $stats['revision_required'] ?></div>
                        <div class="stat-label">Needs Revision</div>
                    </div>
                </div>

                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">My Document Submissions</h3>
                    
                    <?php if (empty($my_documents)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-file-upload" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No documents submitted</h3>
                            <p>You haven't submitted any documents yet. Click "Submit Document" to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_documents as $doc): ?>
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
                                        <i class="fas fa-calendar"></i>
                                        <span>Submitted: <?= date('M j, Y g:i A', strtotime($doc['submitted_at'])) ?></span>
                                    </div>
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
                                    <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem;">
                                        <strong>Teacher Feedback:</strong>
                                        <div style="margin-top: 0.5rem;"><?= nl2br(htmlspecialchars($doc['review_notes'])) ?></div>
                                        <?php if ($doc['reviewed_by_name']): ?>
                                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                                Reviewed by: <?= htmlspecialchars($doc['reviewed_by_name']) ?> on <?= date('M j, Y', strtotime($doc['reviewed_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Submit Document Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Submit Document</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_document">
                
                <div class="form-group">
                    <label for="document_type_id">Document Type</label>
                    <select id="document_type_id" name="document_type_id" class="form-control" required>
                        <option value="">Select document type...</option>
                        <?php foreach ($document_types as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Document Title</label>
                    <input type="text" id="title" name="title" class="form-control" required placeholder="Enter document title">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe your submission..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control" required>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="document_file">Upload Document</label>
                    <div class="file-upload" onclick="document.getElementById('document_file').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #6b7280; margin-bottom: 1rem;"></i>
                        <p>Click to select file or drag and drop</p>
                        <p style="font-size: 0.875rem; color: #6b7280;">Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX</p>
                    </div>
                    <input type="file" id="document_file" name="document_file" style="display: none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
                    <div id="file-info" style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;"></div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="hideModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Document</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal() {
            document.getElementById('documentModal').classList.add('show');
        }
        
        function hideModal() {
            document.getElementById('documentModal').classList.remove('show');
        }
        
        // File upload handling
        document.getElementById('document_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileInfo = document.getElementById('file-info');
            if (file) {
                fileInfo.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            } else {
                fileInfo.textContent = '';
            }
        });
        
        // Drag and drop functionality
        const fileUpload = document.querySelector('.file-upload');
        
        fileUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        fileUpload.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        fileUpload.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('document_file').files = files;
                const event = new Event('change');
                document.getElementById('document_file').dispatchEvent(event);
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('documentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>

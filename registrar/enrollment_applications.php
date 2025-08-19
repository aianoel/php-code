<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('registrar');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start_review':
                try {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = 'registrar_review' WHERE id = ? AND status = 'pending'");
                    $result = $stmt->execute([$_POST['application_id']]);
                    $message = $result ? "Application marked for review!" : "Error updating application.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'review_application':
                try {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = 'accounting_review', registrar_reviewed_at = NOW(), registrar_reviewed_by = ?, registrar_notes = ? WHERE id = ?");
                    $result = $stmt->execute([$user['id'], $_POST['notes'], $_POST['application_id']]);
                    $message = $result ? "Application approved and forwarded to accounting review!" : "Error updating application.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'reject_application':
                try {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = 'rejected', registrar_reviewed_at = NOW(), registrar_reviewed_by = ?, rejection_reason = ? WHERE id = ?");
                    $result = $stmt->execute([$user['id'], $_POST['rejection_reason'], $_POST['application_id']]);
                    $message = $result ? "Application rejected!" : "Error rejecting application.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get pending applications for registrar review
try {
    $stmt = $pdo->prepare("
        SELECT * FROM enrollment_applications 
        WHERE status IN ('pending', 'registrar_review') 
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $applications = $stmt->fetchAll();
} catch (Exception $e) {
    $applications = [];
}

// Get statistics
try {
    $stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'pending'")->fetchColumn(),
        'in_review' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'registrar_review'")->fetchColumn(),
        'forwarded' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
        'total_today' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE DATE(created_at) = CURDATE()")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['pending' => 0, 'in_review' => 0, 'forwarded' => 0, 'total_today' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Applications - Registrar</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 1rem; padding: 1.5rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { color: #6b7280; font-size: 0.875rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .application-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1rem; }
        .application-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .application-info { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .info-group { }
        .info-label { font-weight: 600; color: #374151; margin-bottom: 0.25rem; }
        .info-value { color: #6b7280; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-review { background: #dbeafe; color: #1e40af; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
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
                    <h1>Enrollment Applications</h1>
                    <p>Review and process new student enrollment applications</p>
                </div>
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
                <div class="stat-number" style="color: #f59e0b;"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #3b82f6;"><?= $stats['in_review'] ?></div>
                <div class="stat-label">In Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #16a34a;"><?= $stats['forwarded'] ?></div>
                <div class="stat-label">Forwarded to Accounting</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #8b5cf6;"><?= $stats['total_today'] ?></div>
                <div class="stat-label">Applications Today</div>
            </div>
        </div>

        <!-- Applications List -->
        <div class="card">
            <h3>Applications for Review</h3>
            <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No Applications Pending</h3>
                    <p>All enrollment applications have been processed.</p>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div>
                                <h4><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></h4>
                                <p style="color: #6b7280;">Application #<?= htmlspecialchars($app['application_number']) ?></p>
                            </div>
                            <span class="status-badge status-<?= $app['status'] == 'pending' ? 'pending' : 'review' ?>">
                                <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                            </span>
                        </div>
                        
                        <div class="application-info">
                            <div class="info-group">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?= htmlspecialchars($app['email']) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?= htmlspecialchars($app['phone'] ?: 'Not provided') ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Grade Level</div>
                                <div class="info-value">Grade <?= htmlspecialchars($app['grade_level']) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Strand</div>
                                <div class="info-value"><?= htmlspecialchars($app['strand'] ?: 'Not specified') ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Previous School</div>
                                <div class="info-value"><?= htmlspecialchars($app['previous_school'] ?: 'Not provided') ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Submitted</div>
                                <div class="info-value"><?= date('M j, Y g:i A', strtotime($app['submitted_at'])) ?></div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <?php if ($app['status'] == 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="start_review">
                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-clipboard-check"></i> Start Review
                                    </button>
                                </form>
                                <button onclick="rejectApplication(<?= $app['id'] ?>, '<?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>')" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            <?php elseif ($app['status'] == 'registrar_review'): ?>
                                <button onclick="reviewApplication(<?= htmlspecialchars(json_encode($app)) ?>)" class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve & Forward
                                </button>
                                <button onclick="rejectApplication(<?= $app['id'] ?>, '<?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>')" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <h3>Review Application</h3>
            <form method="POST" id="reviewForm">
                <input type="hidden" name="action" value="review_application">
                <input type="hidden" name="application_id" id="review_application_id">
                
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="review_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Registrar Notes</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Add any notes about this application review..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('reviewModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve & Forward to Accounting</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>Reject Application</h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject_application">
                <input type="hidden" name="application_id" id="reject_application_id">
                
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="reject_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" placeholder="Please provide a reason for rejection..." required></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('rejectModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function reviewApplication(app) {
            document.getElementById('review_application_id').value = app.id;
            document.getElementById('review_student_name').value = app.first_name + ' ' + app.last_name;
            openModal('reviewModal');
        }
        
        function rejectApplication(appId, studentName) {
            document.getElementById('reject_application_id').value = appId;
            document.getElementById('reject_student_name').value = studentName;
            openModal('rejectModal');
        }
    </script>
        </main>
    </div>
</body>
</html>

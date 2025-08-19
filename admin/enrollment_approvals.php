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
            case 'approve_enrollment':
                try {
                    $pdo->beginTransaction();
                    
                    // Get application details
                    $stmt = $pdo->prepare("SELECT * FROM enrollment_applications WHERE id = ?");
                    $stmt->execute([$_POST['application_id']]);
                    $app = $stmt->fetch();
                    
                    if ($app) {
                        // Create user account
                        $hashedPassword = password_hash('student123', PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status, phone, address, date_of_birth, gender) VALUES (?, ?, ?, ?, 'student', 'active', ?, ?, ?, ?)");
                        $stmt->execute([
                            $app['first_name'],
                            $app['last_name'],
                            $app['email'],
                            $hashedPassword,
                            $app['phone'],
                            $app['address'],
                            $app['date_of_birth'],
                            $app['gender']
                        ]);
                        $userId = $pdo->lastInsertId();
                        
                        // Create student record
                        $studentId = 'STU' . str_pad($userId, 6, '0', STR_PAD_LEFT);
                        $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, strand, enrollment_date, status) VALUES (?, ?, ?, ?, CURDATE(), 'enrolled')");
                        $stmt->execute([
                            $userId,
                            $studentId,
                            $app['grade_level'],
                            $app['strand']
                        ]);
                        
                        // Update application status
                        $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = 'approved', admin_approved_at = NOW(), admin_approved_by = ?, admin_notes = ? WHERE id = ?");
                        $stmt->execute([$user['id'], $_POST['notes'], $_POST['application_id']]);
                        
                        $pdo->commit();
                        $message = "Enrollment approved! Student account created with ID: $studentId";
                    }
                } catch (Exception $e) {
                    $pdo->rollback();
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'reject_enrollment':
                try {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = 'rejected', admin_approved_at = NOW(), admin_approved_by = ?, rejection_reason = ? WHERE id = ?");
                    $result = $stmt->execute([$user['id'], $_POST['rejection_reason'], $_POST['application_id']]);
                    $message = $result ? "Enrollment application rejected!" : "Error rejecting application.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get applications for admin approval
$stmt = $pdo->prepare("
    SELECT ea.*, 
           ur.first_name as registrar_name, ur.last_name as registrar_last,
           ua.first_name as accounting_name, ua.last_name as accounting_last
    FROM enrollment_applications ea
    LEFT JOIN users ur ON ea.registrar_reviewed_by = ur.id
    LEFT JOIN users ua ON ea.accounting_reviewed_by = ua.id
    WHERE ea.status IN ('admin_approval', 'approved', 'rejected')
    ORDER BY ea.admin_approved_at DESC, ea.accounting_reviewed_at ASC
");
$stmt->execute();
$applications = $stmt->fetchAll();

// Get approval statistics
$stats = [
    'pending_approval' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'admin_approval'")->fetchColumn(),
    'approved_today' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'approved' AND DATE(admin_approved_at) = CURDATE()")->fetchColumn(),
    'rejected_today' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'rejected' AND DATE(admin_approved_at) = CURDATE()")->fetchColumn(),
    'total_enrolled' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'approved'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Approvals - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
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
        .application-info { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .info-group { }
        .info-label { font-weight: 600; color: #374151; margin-bottom: 0.25rem; }
        .info-value { color: #6b7280; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
        .status-admin { background: #fef3c7; color: #92400e; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .workflow-timeline { background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .timeline-step { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .timeline-step:last-child { margin-bottom: 0; }
        .timeline-icon { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .timeline-completed { background: #16a34a; color: white; }
        .timeline-pending { background: #f59e0b; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
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
                            <h1>Enrollment Approvals</h1>
                            <p>Review and approve enrollment applications</p>
                        </div>
                    </div>
                </div>
                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Approval Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" style="color: #f59e0b;"><?= $stats['pending_approval'] ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #16a34a;"><?= $stats['approved_today'] ?></div>
                        <div class="stat-label">Approved Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #dc2626;"><?= $stats['rejected_today'] ?></div>
                        <div class="stat-label">Rejected Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #8b5cf6;"><?= $stats['total_enrolled'] ?></div>
                        <div class="stat-label">Total Enrolled</div>
                    </div>
                </div>

                <!-- Applications for Final Approval -->
                <div class="card">
                    <h3>Applications for Final Approval</h3>
                    <?php if (empty($applications)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-user-graduate" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <h3>No Applications for Approval</h3>
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
                                    <span class="status-badge status-<?= str_replace('_', '-', $app['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                                    </span>
                                </div>
                                
                                <!-- Workflow Timeline -->
                                <div class="workflow-timeline">
                                    <div class="timeline-step">
                                        <div class="timeline-icon timeline-completed">
                                            <i class="fas fa-check" style="font-size: 10px;"></i>
                                        </div>
                                        <span>Registrar Review - <?= $app['registrar_name'] ? htmlspecialchars($app['registrar_name'] . ' ' . $app['registrar_last']) : 'Completed' ?></span>
                                    </div>
                                    <div class="timeline-step">
                                        <div class="timeline-icon timeline-completed">
                                            <i class="fas fa-check" style="font-size: 10px;"></i>
                                        </div>
                                        <span>Accounting Review - <?= $app['accounting_name'] ? htmlspecialchars($app['accounting_name'] . ' ' . $app['accounting_last']) : 'Completed' ?></span>
                                    </div>
                                    <div class="timeline-step">
                                        <div class="timeline-icon <?= $app['status'] == 'admin_approval' ? 'timeline-pending' : 'timeline-completed' ?>">
                                            <i class="fas fa-<?= $app['status'] == 'admin_approval' ? 'clock' : 'check' ?>" style="font-size: 10px;"></i>
                                        </div>
                                        <span>Admin Approval - <?= $app['status'] == 'admin_approval' ? 'Pending' : 'Completed' ?></span>
                                    </div>
                                </div>
                                
                                <div class="application-info">
                                    <div class="info-group">
                                        <div class="info-label">Grade Level</div>
                                        <div class="info-value">Grade <?= htmlspecialchars($app['grade_level']) ?></div>
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">Payment Status</div>
                                        <div class="info-value"><?= ucfirst($app['payment_status']) ?> (₱<?= number_format($app['paid_amount'], 2) ?>)</div>
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">Email</div>
                                        <div class="info-value"><?= htmlspecialchars($app['email']) ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($app['registrar_notes']): ?>
                                    <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                                        <strong>Registrar Notes:</strong> <?= htmlspecialchars($app['registrar_notes']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($app['accounting_notes']): ?>
                                    <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                                        <strong>Accounting Notes:</strong> <?= htmlspecialchars($app['accounting_notes']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                                    <?php if ($app['status'] == 'admin_approval'): ?>
                                        <button onclick="approveEnrollment(<?= htmlspecialchars(json_encode($app)) ?>)" class="btn btn-success">
                                            <i class="fas fa-user-plus"></i> Approve & Create Student Account
                                        </button>
                                        <button onclick="rejectEnrollment(<?= $app['id'] ?>, '<?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>')" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($app['status'] == 'approved'): ?>
                                        <span style="color: #16a34a; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> Approved on <?= date('M j, Y', strtotime($app['admin_approved_at'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #dc2626; font-weight: 600;">
                                            <i class="fas fa-times-circle"></i> Rejected on <?= date('M j, Y', strtotime($app['admin_approved_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <h3>Approve Enrollment</h3>
            <form method="POST" id="approveForm">
                <input type="hidden" name="action" value="approve_enrollment">
                <input type="hidden" name="application_id" id="approve_application_id">
                
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="approve_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Admin Approval Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Final approval notes and welcome message..."></textarea>
                </div>
                
                <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <strong>Note:</strong> Approving this application will:
                    <ul style="margin-top: 0.5rem; margin-left: 1rem;">
                        <li>Create a student user account</li>
                        <li>Generate a student ID</li>
                        <li>Set default password: student123</li>
                        <li>Send welcome email (if configured)</li>
                    </ul>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('approveModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve & Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>Reject Enrollment</h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject_enrollment">
                <input type="hidden" name="application_id" id="reject_application_id">
                
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="reject_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" placeholder="Please provide a detailed reason for rejection..." required></textarea>
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
        
        function approveEnrollment(app) {
            document.getElementById('approve_application_id').value = app.id;
            document.getElementById('approve_student_name').value = app.first_name + ' ' + app.last_name;
            openModal('approveModal');
        }
        
        function rejectEnrollment(appId, studentName) {
            document.getElementById('reject_application_id').value = appId;
            document.getElementById('reject_student_name').value = studentName;
            openModal('rejectModal');
        }
    </script>
</body>
</html>

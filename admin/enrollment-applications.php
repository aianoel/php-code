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
            case 'approve_application':
                try {
                    // First check if the application is in the correct status
                    $checkStmt = $pdo->prepare("SELECT status FROM enrollment_applications WHERE id = ?");
                    $checkStmt->execute([$_POST['application_id']]);
                    $currentStatus = $checkStmt->fetchColumn();
                    
                    if ($currentStatus != 'admin_approval') {
                        $message = "Error: Application is not in the correct status for final approval.";
                    } else {
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Update application status
                        $stmt = $pdo->prepare("UPDATE enrollment_applications SET 
                            status = 'approved', 
                            admin_approved_at = NOW(), 
                            admin_approved_by = ?, 
                            admin_notes = ? 
                            WHERE id = ?");
                        $result = $stmt->execute([$user['id'], $_POST['notes'], $_POST['application_id']]);
                        
                        if ($result) {
                            // Get application details for creating user account
                            $appStmt = $pdo->prepare("SELECT * FROM enrollment_applications WHERE id = ?");
                            $appStmt->execute([$_POST['application_id']]);
                            $app = $appStmt->fetch();
                            
                            // Create user account
                            $username = strtolower(substr($app['first_name'], 0, 1) . $app['last_name']) . rand(100, 999);
                            $password = generate_random_password();
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            
                            $userStmt = $pdo->prepare("INSERT INTO users 
                                (username, password, first_name, last_name, email, phone, role, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, 'student', NOW())");
                            $userResult = $userStmt->execute([
                                $username, 
                                $hashedPassword, 
                                $app['first_name'], 
                                $app['last_name'], 
                                $app['email'], 
                                $app['phone']
                            ]);
                            
                            if ($userResult) {
                                $userId = $pdo->lastInsertId();
                                
                                // Create student record
                                $studentId = generate_student_id($app['grade_level']);
                                $studentStmt = $pdo->prepare("INSERT INTO students 
                                    (user_id, student_id, grade_level, strand, previous_school, gpa, enrollment_date) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())");
                                $studentResult = $studentStmt->execute([
                                    $userId,
                                    $studentId,
                                    $app['grade_level'],
                                    $app['strand'],
                                    $app['previous_school'],
                                    $app['gpa']
                                ]);
                                
                                if ($studentResult) {
                                    // Update application status to enrolled
                                    $finalStmt = $pdo->prepare("UPDATE enrollment_applications SET 
                                        status = 'enrolled', 
                                        student_user_id = ? 
                                        WHERE id = ?");
                                    $finalResult = $finalStmt->execute([$userId, $app['id']]);
                                    
                                    if ($finalResult) {
                                        // Log the activity
                                        log_activity($user['id'], 'admin', 'Approved and enrolled application #' . $app['application_number']);
                                        $message = "Application approved and student account created! Username: $username, Password: $password";
                                        $pdo->commit();
                                    } else {
                                        $pdo->rollBack();
                                        $message = "Error updating application status to enrolled.";
                                    }
                                } else {
                                    $pdo->rollBack();
                                    $message = "Error creating student record.";
                                }
                            } else {
                                $pdo->rollBack();
                                $message = "Error creating user account.";
                            }
                        } else {
                            $pdo->rollBack();
                            $message = "Error approving application.";
                        }
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'reject_application':
                try {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET 
                        status = 'rejected', 
                        admin_approved_at = NOW(), 
                        admin_approved_by = ?, 
                        rejection_reason = ? 
                        WHERE id = ?");
                    $result = $stmt->execute([$user['id'], $_POST['rejection_reason'], $_POST['application_id']]);
                    
                    if ($result) {
                        // Get application number for logging
                        $appNumStmt = $pdo->prepare("SELECT application_number FROM enrollment_applications WHERE id = ?");
                        $appNumStmt->execute([$_POST['application_id']]);
                        $appNum = $appNumStmt->fetchColumn();
                        
                        // Log the activity
                        log_activity($user['id'], 'admin', 'Rejected application #' . $appNum);
                        $message = "Application rejected.";
                    } else {
                        $message = "Error rejecting application.";
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get applications for admin approval
try {
    // First check if registrar_reviewed_by column exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'registrar_reviewed_by'");
    $registrarReviewedByExists = $checkStmt->rowCount() > 0;
    
    // Check if accounting_reviewed_by column exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'accounting_reviewed_by'");
    $accountingReviewedByExists = $checkStmt->rowCount() > 0;
    
    $query = "SELECT ea.*";
    
    if ($registrarReviewedByExists) {
        $query .= ", CONCAT(ru.first_name, ' ', ru.last_name) as registrar_name";
    } else {
        $query .= ", 'Not Assigned' as registrar_name";
    }
    
    if ($accountingReviewedByExists) {
        $query .= ", CONCAT(au.first_name, ' ', au.last_name) as accounting_name";
    } else {
        $query .= ", 'Not Assigned' as accounting_name";
    }
    
    $query .= " FROM enrollment_applications ea";
    
    if ($registrarReviewedByExists) {
        $query .= " LEFT JOIN users ru ON ea.registrar_reviewed_by = ru.id";
    }
    
    if ($accountingReviewedByExists) {
        $query .= " LEFT JOIN users au ON ea.accounting_reviewed_by = au.id";
    }
    
    $query .= " WHERE ea.status IN ('admin_approval', 'approved', 'rejected', 'enrolled')
    ORDER BY 
        CASE 
            WHEN ea.status = 'admin_approval' THEN 1
            WHEN ea.status = 'approved' THEN 2
            WHEN ea.status = 'enrolled' THEN 3
            WHEN ea.status = 'rejected' THEN 4
        END,
        ea.submitted_at DESC";
    
    $stmt = $pdo->prepare($query);
$stmt->execute();
$applications = $stmt->fetchAll();

    // Get application statistics
    $stats = [
        'pending_admin' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'admin_approval'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'approved'")->fetchColumn(),
        'enrolled' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'rejected'")->fetchColumn(),
        'total' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications")->fetchColumn()
    ];

} catch (PDOException $e) {
    $applications = [];
    $stats = [
        'pending_admin' => 0,
        'approved' => 0,
        'enrolled' => 0,
        'rejected' => 0,
        'total' => 0
    ];
    error_log("Database error in enrollment-applications.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Applications - Admin</title>
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
        .application-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .info-group { }
        .info-label { font-weight: 600; color: #374151; margin-bottom: 0.25rem; }
        .info-value { color: #6b7280; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
        .status-admin_approval { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-enrolled { background: #c7d2fe; color: #4338ca; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 80vh; overflow-y: auto; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .approval-info { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
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
                            <h1>Enrollment Applications</h1>
                            <p>Review and approve enrollment applications</p>
                        </div>
                    </div>
                </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Application Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #3b82f6;"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #1e40af;"><?= $stats['pending_admin'] ?></div>
                <div class="stat-label">Pending Admin Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #16a34a;"><?= $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #4338ca;"><?= $stats['enrolled'] ?></div>
                <div class="stat-label">Enrolled</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc2626;"><?= $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Applications for Review -->
        <div class="card">
            <h3>Enrollment Applications</h3>
            <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No Applications Found</h3>
                    <p>There are no enrollment applications requiring your attention.</p>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div>
                                <h4><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></h4>
                                <p style="color: #6b7280;">Application #<?= htmlspecialchars($app['application_number']) ?></p>
                            </div>
                            <span class="status-badge status-<?= $app['status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                            </span>
                        </div>
                        
                        <div class="application-info">
                            <div class="info-group">
                                <div class="info-label">Grade Level</div>
                                <div class="info-value">Grade <?= htmlspecialchars($app['grade_level']) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Strand</div>
                                <div class="info-value"><?= htmlspecialchars($app['strand'] ?: 'Not applicable') ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?= htmlspecialchars($app['email']) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?= htmlspecialchars($app['phone']) ?></div>
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
                        
                        <!-- Approval Information -->
                        <div class="approval-info">
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Registrar Approval:</strong> 
                                <?= $app['registrar_name'] ? htmlspecialchars($app['registrar_name']) : 'Not yet reviewed' ?> 
                                (<?= $app['registrar_reviewed_at'] ? date('M j, Y g:i A', strtotime($app['registrar_reviewed_at'])) : 'Pending' ?>)
                            </div>
                            <?php if ($app['registrar_notes']): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Registrar Notes:</strong> <?= htmlspecialchars($app['registrar_notes']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Accounting Approval:</strong> 
                                <?= $app['accounting_name'] ? htmlspecialchars($app['accounting_name']) : 'Not yet reviewed' ?> 
                                (<?= $app['accounting_reviewed_at'] ? date('M j, Y g:i A', strtotime($app['accounting_reviewed_at'])) : 'Pending' ?>)
                            </div>
                            <?php if ($app['accounting_notes']): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Accounting Notes:</strong> <?= htmlspecialchars($app['accounting_notes']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($app['payment_status']): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Payment Status:</strong> 
                                    <span style="
                                        padding: 0.25rem 0.5rem; 
                                        border-radius: 0.25rem; 
                                        font-size: 0.75rem;
                                        background: <?= $app['payment_status'] == 'paid' ? '#dcfce7' : ($app['payment_status'] == 'partial' ? '#fef3c7' : '#fee2e2') ?>;
                                        color: <?= $app['payment_status'] == 'paid' ? '#16a34a' : ($app['payment_status'] == 'partial' ? '#92400e' : '#dc2626') ?>;
                                    ">
                                        <?= ucfirst($app['payment_status']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($app['total_fees'] > 0): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Total Fees:</strong> ₱<?= number_format($app['total_fees'], 2) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($app['paid_amount'] > 0): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Paid Amount:</strong> ₱<?= number_format($app['paid_amount'], 2) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($app['status'] == 'admin_approval'): ?>
                            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                                <button onclick="approveApplication(<?= htmlspecialchars(json_encode($app)) ?>)" class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve & Create Account
                                </button>
                                <button onclick="rejectApplication(<?= $app['id'] ?>, '<?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>')" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        <?php elseif ($app['status'] == 'approved' || $app['status'] == 'enrolled'): ?>
                            <div style="color: #16a34a; font-weight: 600; margin-top: 1rem;">
                                <i class="fas fa-check-circle"></i> 
                                <?= $app['status'] == 'enrolled' ? 'Student account created and enrolled' : 'Application approved' ?>
                                <?php if ($app['admin_approved_at']): ?>
                                    on <?= date('M j, Y', strtotime($app['admin_approved_at'])) ?>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($app['status'] == 'rejected'): ?>
                            <div style="color: #dc2626; font-weight: 600; margin-top: 1rem;">
                                <i class="fas fa-times-circle"></i> Application rejected
                                <?php if ($app['rejection_reason']): ?>
                                    <div style="font-weight: normal; margin-top: 0.5rem;">
                                        <strong>Reason:</strong> <?= htmlspecialchars($app['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approve Application Modal -->
    <div id="approveApplicationModal" class="modal">
        <div class="modal-content">
            <h3>Approve Application</h3>
            <form method="POST" id="approveApplicationForm">
                <input type="hidden" name="action" value="approve_application">
                <input type="hidden" name="application_id" id="approve_application_id">
                
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="approve_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Admin Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes about this approval..."></textarea>
                </div>
                
                <div class="alert alert-success">
                    <strong>Note:</strong> Approving this application will create a student account and complete the enrollment process.
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('approveApplicationModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve & Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Application Modal -->
    <div id="rejectApplicationModal" class="modal">
        <div class="modal-content">
            <h3>Reject Application</h3>
            <form method="POST" id="rejectApplicationForm">
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
                    <button type="button" onclick="closeModal('rejectApplicationModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
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
        
        function approveApplication(app) {
            document.getElementById('approve_application_id').value = app.id;
            document.getElementById('approve_student_name').value = app.first_name + ' ' + app.last_name;
            openModal('approveApplicationModal');
        }
        
        function rejectApplication(applicationId, studentName) {
            document.getElementById('reject_application_id').value = applicationId;
            document.getElementById('reject_student_name').value = studentName;
            openModal('rejectApplicationModal');
        }
        
        // Close modal when clicking outside
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

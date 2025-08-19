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
                $stmt = $pdo->prepare("UPDATE enrollments SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$user['id'], $_POST['enrollment_id']]);
                $message = $result ? "Enrollment approved successfully!" : "Error approving enrollment.";
                break;
            case 'reject_enrollment':
                $stmt = $pdo->prepare("UPDATE enrollments SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
                $result = $stmt->execute([$user['id'], $_POST['rejection_reason'] ?? 'Not specified', $_POST['enrollment_id']]);
                $message = $result ? "Enrollment rejected." : "Error rejecting enrollment.";
                break;
        }
    }
}

// Get enrollment statistics
$stmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count 
    FROM enrollments 
    GROUP BY status
");
$stmt->execute();
$enrollment_stats = $stmt->fetchAll();

// Get all enrollments with student details
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        s.student_id,
        s.grade_level,
        s.strand,
        u.first_name,
        u.last_name,
        u.email,
        u.phone
    FROM enrollments e
    LEFT JOIN students s ON e.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY e.created_at DESC
");
$stmt->execute();
$enrollments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 80vh; overflow-y: auto; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .enrollment-details { background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .detail-label { font-weight: 600; }
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
                            <h1>Enrollment Management</h1>
                            <p>Manage student enrollment and class assignments</p>
                        </div>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Enrollment Statistics -->
                <div class="stats-grid">
                    <?php 
                    $total = 0;
                    foreach ($enrollment_stats as $stat) $total += $stat['count'];
                    ?>
                    <div class="stat-card">
                        <h3><?= $total ?></h3>
                        <p>Total Applications</p>
                    </div>
                    <?php foreach ($enrollment_stats as $stat): ?>
                        <div class="stat-card">
                            <h3><?= $stat['count'] ?></h3>
                            <p><?= ucfirst($stat['status']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Enrollments Table -->
                <div class="card">
                    <h3>Enrollment Applications</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Grade Level</th>
                                <th>Strand</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrollments)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                        No enrollment applications found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <tr>
                                        <td>
                                            <?php if ($enrollment['first_name']): ?>
                                                <?= htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']) ?><br>
                                                <small><?= htmlspecialchars($enrollment['email']) ?></small>
                                            <?php else: ?>
                                                <em>Student data not available</em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($enrollment['grade_level'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($enrollment['strand'] ?? 'N/A') ?></td>
                                        <td><?= format_date($enrollment['created_at']) ?></td>
                                        <td><span class="status-badge status-<?= $enrollment['status'] ?>"><?= ucfirst($enrollment['status']) ?></span></td>
                                        <td>
                                            <button onclick="viewEnrollment(<?= $enrollment['id'] ?>)" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if ($enrollment['status'] === 'pending'): ?>
                                                <button onclick="approveEnrollment(<?= $enrollment['id'] ?>)" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button onclick="rejectEnrollment(<?= $enrollment['id'] ?>)" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Enrollment Modal -->
    <div id="viewEnrollmentModal" class="modal">
        <div class="modal-content">
            <h3>Enrollment Details</h3>
            <div id="enrollmentDetails"></div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button onclick="closeModal('viewEnrollmentModal')" class="btn btn-warning">Close</button>
            </div>
        </div>
    </div>

    <!-- Reject Enrollment Modal -->
    <div id="rejectEnrollmentModal" class="modal">
        <div class="modal-content">
            <h3>Reject Enrollment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="reject_enrollment">
                <input type="hidden" name="enrollment_id" id="rejectEnrollmentId">
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" placeholder="Please provide a reason for rejection..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal('rejectEnrollmentModal')" class="btn btn-warning">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Enrollment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const enrollments = <?= json_encode($enrollments) ?>;
        
        function viewEnrollment(enrollmentId) {
            const enrollment = enrollments.find(e => e.id == enrollmentId);
            if (!enrollment) return;
            
            const details = `
                <div class="enrollment-details">
                    <div class="detail-row">
                        <span class="detail-label">Student Name:</span>
                        <span>${enrollment.first_name || 'N/A'} ${enrollment.last_name || ''}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span>${enrollment.email || 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span>${enrollment.phone || 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Student ID:</span>
                        <span>${enrollment.student_id || 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Grade Level:</span>
                        <span>${enrollment.grade_level || 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Strand:</span>
                        <span>${enrollment.strand || 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Application Date:</span>
                        <span>${new Date(enrollment.created_at).toLocaleDateString()}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="status-badge status-${enrollment.status}">${enrollment.status.charAt(0).toUpperCase() + enrollment.status.slice(1)}</span>
                    </div>
                    ${enrollment.rejection_reason ? `
                        <div class="detail-row">
                            <span class="detail-label">Rejection Reason:</span>
                            <span>${enrollment.rejection_reason}</span>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('enrollmentDetails').innerHTML = details;
            openModal('viewEnrollmentModal');
        }
        
        function approveEnrollment(enrollmentId) {
            if (confirm('Are you sure you want to approve this enrollment?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_enrollment">
                    <input type="hidden" name="enrollment_id" value="${enrollmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectEnrollment(enrollmentId) {
            document.getElementById('rejectEnrollmentId').value = enrollmentId;
            openModal('rejectEnrollmentModal');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

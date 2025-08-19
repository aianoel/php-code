<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('accounting');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'process_payment':
                try {
                    // First check if the application is in the correct status
                    $checkStmt = $pdo->prepare("SELECT status FROM enrollment_applications WHERE id = ?");
                    $checkStmt->execute([$_POST['application_id']]);
                    $currentStatus = $checkStmt->fetchColumn();
                    
                    if ($currentStatus != 'accounting_review') {
                        $message = "Error: Application is not in the correct status for payment processing.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = 'admin_approval', accounting_reviewed_at = NOW(), accounting_reviewed_by = ?, accounting_notes = ?, payment_status = ?, paid_amount = ? WHERE id = ?");
                        $result = $stmt->execute([$user['id'], $_POST['notes'], $_POST['payment_status'], $_POST['paid_amount'], $_POST['application_id']]);
                        
                        if ($result) {
                            // Get application number for logging
                            $appNumStmt = $pdo->prepare("SELECT application_number FROM enrollment_applications WHERE id = ?");
                            $appNumStmt->execute([$_POST['application_id']]);
                            $appNum = $appNumStmt->fetchColumn();
                            
                            // Log the activity
                            log_activity($user['id'], 'accounting', 'Processed payment and forwarded application #' . $appNum . ' to admin approval');
                            $message = "Payment processed and forwarded to admin approval!";
                        } else {
                            $message = "Error processing payment.";
                        }
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'request_payment':
                try {
                    // First check if the application is in the correct status
                    $checkStmt = $pdo->prepare("SELECT status FROM enrollment_applications WHERE id = ?");
                    $checkStmt->execute([$_POST['application_id']]);
                    $currentStatus = $checkStmt->fetchColumn();
                    
                    if ($currentStatus != 'accounting_review') {
                        $message = "Error: Application is not in the correct status for payment request.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE enrollment_applications SET accounting_notes = ?, total_fees = ? WHERE id = ?");
                        $result = $stmt->execute([$_POST['notes'], $_POST['total_fees'], $_POST['application_id']]);
                        
                        if ($result) {
                            // Get application number for logging
                            $appNumStmt = $pdo->prepare("SELECT application_number FROM enrollment_applications WHERE id = ?");
                            $appNumStmt->execute([$_POST['application_id']]);
                            $appNum = $appNumStmt->fetchColumn();
                            
                            // Log the activity
                            log_activity($user['id'], 'accounting', 'Sent payment request for application #' . $appNum);
                            $message = "Payment request sent to student!";
                        } else {
                            $message = "Error sending payment request.";
                        }
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get applications for accounting review with payment information
$stmt = $pdo->prepare("
    SELECT ea.*, 
    (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ea.registrar_reviewed_by) as registrar_name 
    FROM enrollment_applications ea
    WHERE ea.status IN ('accounting_review', 'admin_approval') 
    ORDER BY ea.accounting_reviewed_at ASC, ea.submitted_at ASC
");
$stmt->execute();
$applications = $stmt->fetchAll();

// Get payment statistics
$stats = [
    'pending_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
    'payment_received' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'paid'")->fetchColumn(),
    'partial_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'partial'")->fetchColumn(),
    'total_collected' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM enrollment_applications")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Payments - Accounting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            display: flex; 
        }
        .main-content { flex: 1; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* Enhanced Header */
        .header { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(10px);
            border-radius: 1.5rem; 
            padding: 2.5rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Enhanced Stats Grid */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 2.5rem; 
        }
        .stat-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem; 
            padding: 2rem; 
            text-align: center; 
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #06b6d4, #10b981);
        }
        .stat-card:hover { 
            transform: translateY(-8px) scale(1.02); 
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        .stat-number { 
            font-size: 3rem; 
            font-weight: 800; 
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label { 
            color: #64748b; 
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Enhanced Buttons */
        .btn { 
            padding: 1rem 2rem; 
            border: none; 
            border-radius: 0.75rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.75rem;
            font-size: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary { 
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); 
            color: white; 
        }
        .btn-success { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: white; 
        }
        .btn-warning { 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); 
            color: white; 
        }
        .btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Enhanced Card */
        .card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem; 
            padding: 2.5rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
        }
        
        /* Enhanced Application Cards */
        .application-card { 
            background: rgba(248, 250, 252, 0.8);
            border: 1px solid rgba(203, 213, 225, 0.5); 
            border-radius: 1rem; 
            padding: 2rem; 
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .application-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.9);
        }
        .application-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: start; 
            margin-bottom: 1.5rem; 
        }
        .application-header h4 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        .application-info { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 1.5rem; 
        }
        .info-group { }
        .info-label { 
            font-weight: 700; 
            color: #374151; 
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-value { 
            color: #1e293b;
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        /* Enhanced Status Badges */
        .status-badge { 
            padding: 0.5rem 1rem; 
            border-radius: 2rem; 
            font-size: 0.875rem; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .status-accounting { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); 
            color: #d97706;
            border: 1px solid #fcd34d;
        }
        .status-admin { 
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); 
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        .payment-badge { 
            padding: 0.5rem 1rem; 
            border-radius: 2rem; 
            font-size: 0.875rem; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .payment-pending { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); 
            color: #dc2626;
            border: 1px solid #f87171;
        }
        .payment-partial { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); 
            color: #d97706;
            border: 1px solid #fcd34d;
        }
        .payment-paid { 
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); 
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        /* Enhanced Modal */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0, 0, 0, 0.6); 
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        .modal-content { 
            background: white; 
            margin: 5% auto; 
            padding: 2.5rem; 
            border-radius: 1.5rem; 
            max-width: 600px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }
        .modal-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
        }
        
        /* Enhanced Form Elements */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { 
            display: block; 
            margin-bottom: 0.75rem; 
            font-weight: 600;
            color: #374151;
        }
        .form-control { 
            width: 100%; 
            padding: 1rem; 
            border: 2px solid #e5e7eb; 
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1.5rem; 
        }
        
        /* Enhanced Alerts */
        .alert { 
            padding: 1.25rem; 
            border-radius: 1rem; 
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .alert-success { 
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); 
            color: #059669; 
            border: 1px solid #a7f3d0; 
        }
        .alert-error { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); 
            color: #dc2626; 
            border: 1px solid #f87171; 
        }
        
        /* Registrar Notes Section */
        .registrar-notes {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #d1d5db;
        }
        .registrar-notes strong {
            color: #374151;
        }
    </style>
    <link rel="stylesheet" href="../includes/sidebar.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content" style="margin-left: 280px; padding: 2rem;">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="color: #1e293b; font-size: 2rem; margin-bottom: 0.5rem;">Enrollment Payments</h1>
                    <p style="color: #64748b; font-size: 1.1rem;">Process enrollment fees and payment verification</p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button class="btn btn-primary">
                        <i class="fas fa-download"></i>
                        Export Report
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Payment Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?= $stats['pending_payment'] ?></div>
                <div class="stat-label">Pending Payment Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #16a34a;"><?= $stats['payment_received'] ?></div>
                <div class="stat-label">Payments Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?= $stats['partial_payment'] ?></div>
                <div class="stat-label">Partial Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #8b5cf6;">₱<?= number_format($stats['total_collected'], 2) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
        </div>

        <!-- Applications for Payment Processing -->
        <div class="card">
            <h3>Applications for Payment Processing</h3>
            <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-money-bill-wave" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No Payment Processing Required</h3>
                    <p>All enrollment payments have been processed.</p>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div>
                                <h4><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></h4>
                                <p style="color: #6b7280;">Application #<?= htmlspecialchars($app['application_number']) ?></p>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <span class="status-badge status-<?= $app['status'] == 'accounting_review' ? 'accounting' : 'admin' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                                </span>
                                <span class="payment-badge payment-<?= $app['payment_status'] ?>">
                                    <?= ucfirst($app['payment_status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="application-info">
                            <div class="info-group">
                                <div class="info-label">Grade Level</div>
                                <div class="info-value">Grade <?= htmlspecialchars($app['grade_level']) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Total Fees</div>
                                <div class="info-value">₱<?= number_format($app['total_fees'], 2) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Paid Amount</div>
                                <div class="info-value">₱<?= number_format($app['paid_amount'], 2) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Balance</div>
                                <div class="info-value">₱<?= number_format($app['total_fees'] - $app['paid_amount'], 2) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?= htmlspecialchars($app['email']) ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Submitted</div>
                                <div class="info-value"><?= date('M j, Y', strtotime($app['submitted_at'])) ?></div>
                            </div>
                        </div>
                        
                        <div class="registrar-notes">
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Registrar Approval:</strong> 
                                <?= $app['registrar_name'] ? htmlspecialchars($app['registrar_name']) : 'Unknown' ?> 
                                (<?= $app['registrar_reviewed_at'] ? date('M j, Y g:i A', strtotime($app['registrar_reviewed_at'])) : 'Date not recorded' ?>)
                            </div>
                            <?php if ($app['registrar_notes']): ?>
                                <div>
                                    <strong>Registrar Notes:</strong> <?= htmlspecialchars($app['registrar_notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; gap: 1.5rem; margin-top: 1.5rem; flex-wrap: wrap;">
                            <?php if ($app['status'] == 'accounting_review'): ?>
                                <button onclick="processPayment(<?= htmlspecialchars(json_encode($app)) ?>)" class="btn btn-success">
                                    <i class="fas fa-check"></i> Process Payment
                                </button>
                                <button onclick="requestPayment(<?= htmlspecialchars(json_encode($app)) ?>)" class="btn btn-warning">
                                    <i class="fas fa-money-bill"></i> Request Payment
                                </button>
                            <?php else: ?>
                                <span style="color: #16a34a; font-weight: 600;">
                                    <i class="fas fa-check-circle"></i> Forwarded to Admin Approval
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Process Payment Modal -->
    <div id="processPaymentModal" class="modal">
        <div class="modal-content">
            <h3>Process Payment</h3>
            <form method="POST" id="processPaymentForm">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="application_id" id="process_application_id">
                
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="process_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" class="form-control" required>
                            <option value="paid">Fully Paid</option>
                            <option value="partial">Partial Payment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount Paid (₱)</label>
                        <input type="number" name="paid_amount" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Accounting Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Payment verification notes..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('processPaymentModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Process & Forward to Admin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Payment Modal -->
    <div id="requestPaymentModal" class="modal">
        <div class="modal-content">
            <h3>Request Payment</h3>
            <form method="POST" id="requestPaymentForm">
                <input type="hidden" name="action" value="request_payment">
                <input type="hidden" name="application_id" id="request_application_id">
                
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="request_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Total Enrollment Fees (₱)</label>
                    <input type="number" name="total_fees" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Instructions</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Payment instructions and deadline..." required></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('requestPaymentModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-warning">Send Payment Request</button>
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
        
        function processPayment(app) {
            document.getElementById('process_application_id').value = app.id;
            document.getElementById('process_student_name').value = app.first_name + ' ' + app.last_name;
            openModal('processPaymentModal');
        }
        
        function requestPayment(app) {
            document.getElementById('request_application_id').value = app.id;
            document.getElementById('request_student_name').value = app.first_name + ' ' + app.last_name;
            openModal('requestPaymentModal');
        }
    </script>
        </main>
    </div>
</body>
</html>

<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('parent');
$user = get_logged_in_user();

// Get payment records
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id
        FROM payments p
        LEFT JOIN students s ON p.student_id = s.id
        WHERE s.parent_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$user['id']]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    $payments = [];
}

// Get pending invoices
try {
    $stmt = $pdo->prepare("
        SELECT i.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id
        FROM invoices i
        LEFT JOIN students s ON i.student_id = s.id
        WHERE s.parent_id = ? AND i.status = 'pending'
        ORDER BY i.due_date ASC
    ");
    $stmt->execute([$user['id']]);
    $pending_invoices = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_invoices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Parent Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .payments-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .payments-table th, .payments-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .payments-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .status-paid { background: #dcfce7; color: #16a34a; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .amount { font-weight: 600; color: #1f2937; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-money-bill"></i> Payment Records</h1>
                    <p>View payment history and pending invoices</p>
                </div>

                <?php if (!empty($pending_invoices)): ?>
                    <div class="card">
                        <div class="alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            You have <?= count($pending_invoices) ?> pending invoice(s) that require payment.
                        </div>
                        
                        <h3 style="margin-bottom: 1rem;">Pending Invoices</h3>
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($invoice['student_name']) ?></strong><br>
                                            <small>ID: <?= htmlspecialchars($invoice['student_id']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($invoice['description']) ?></td>
                                        <td class="amount">₱<?= number_format($invoice['amount'], 2) ?></td>
                                        <td><?= date('M j, Y', strtotime($invoice['due_date'])) ?></td>
                                        <td>
                                            <?php
                                            $status = 'pending';
                                            if (strtotime($invoice['due_date']) < time()) {
                                                $status = 'overdue';
                                            }
                                            ?>
                                            <span class="status-badge status-<?= $status ?>">
                                                <?= ucfirst($status) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="#" class="btn btn-primary">
                                                <i class="fas fa-credit-card"></i> Pay Now
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h3 style="margin-bottom: 1rem;">Payment History</h3>
                    
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No payment records</h3>
                            <p>No payments have been recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($payment['student_name']) ?></strong><br>
                                            <small>ID: <?= htmlspecialchars($payment['student_id']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($payment['description']) ?></td>
                                        <td class="amount">₱<?= number_format($payment['amount'], 2) ?></td>
                                        <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                                        <td><?= htmlspecialchars($payment['payment_method'] ?? 'Cash') ?></td>
                                        <td><?= htmlspecialchars($payment['reference_number'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="status-badge status-paid">Paid</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

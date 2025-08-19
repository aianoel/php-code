<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('accounting');
$user = get_logged_in_user();

// Get invoices from actual enrolled students
try {
    $stmt = $pdo->prepare("
        SELECT 
            ea.id,
            ea.application_number as invoice_number,
            ea.first_name,
            ea.last_name,
            ea.grade_level,
            ea.total_fees as amount,
            ea.enrolled_at as created_at,
            DATE_ADD(ea.enrolled_at, INTERVAL 30 DAY) as due_date,
            CASE 
                WHEN ea.payment_status = 'paid' THEN 'paid'
                WHEN ea.payment_status = 'partial' THEN 'pending'
                WHEN DATE_ADD(ea.enrolled_at, INTERVAL 30 DAY) < NOW() AND ea.payment_status != 'paid' THEN 'overdue'
                ELSE 'pending'
            END as status
        FROM enrollment_applications ea
        WHERE ea.status = 'enrolled' AND ea.total_fees > 0
        ORDER BY ea.enrolled_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $invoices = $stmt->fetchAll();
    
    // Get invoice statistics from enrolled students
    $stats = [
        'total_invoices' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled' AND total_fees > 0")->fetchColumn(),
        'paid_invoices' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled' AND payment_status = 'paid'")->fetchColumn(),
        'pending_invoices' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled' AND payment_status IN ('pending', 'partial')")->fetchColumn(),
        'overdue_invoices' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled' AND payment_status != 'paid' AND DATE_ADD(enrolled_at, INTERVAL 30 DAY) < NOW()")->fetchColumn()
    ];
} catch (PDOException $e) {
    // If there's an error, show empty state
    $invoices = [];
    $stats = [
        'total_invoices' => 0,
        'paid_invoices' => 0,
        'pending_invoices' => 0,
        'overdue_invoices' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - Accounting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
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
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .header p {
            color: #64748b;
            font-size: 1.125rem;
            font-weight: 500;
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
        .btn-danger { 
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); 
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
        
        /* Enhanced Table */
        .table { 
            width: 100%; 
            border-collapse: collapse;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .table th, .table td { 
            padding: 1.25rem; 
            text-align: left; 
            border-bottom: 1px solid #f1f5f9; 
        }
        .table th { 
            font-weight: 700; 
            color: #1e293b;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .table tbody tr {
            transition: all 0.2s ease;
        }
        .table tbody tr:hover { 
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: scale(1.01);
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
        .status-paid { 
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); 
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .status-pending { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); 
            color: #d97706;
            border: 1px solid #fcd34d;
        }
        .status-overdue { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); 
            color: #dc2626;
            border: 1px solid #f87171;
        }
        
        /* Enhanced Empty State */
        .empty-state { 
            text-align: center; 
            padding: 4rem 2rem;
            color: #64748b;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 1.5rem;
            border: 2px dashed #cbd5e1;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #94a3b8;
        }
        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.75rem;
        }
        .empty-state p {
            font-size: 1.125rem;
            color: #64748b;
        }
        
        /* Action Button Improvements */
        .action-btn {
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content" style="margin-left: 280px; padding: 2rem;">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Invoices</h1>
                    <p>Manage student invoices and payment records</p>
                </div>
                <button class="btn btn-primary"><i class="fas fa-plus"></i> Create New Invoice</button>
            </div>
        </div>

        <!-- Invoice Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_invoices'] ?></div>
                <div class="stat-label">Total Invoices</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #16a34a;"><?= $stats['paid_invoices'] ?></div>
                <div class="stat-label">Paid Invoices</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?= $stats['pending_invoices'] ?></div>
                <div class="stat-label">Pending Invoices</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc2626;"><?= $stats['overdue_invoices'] ?></div>
                <div class="stat-label">Overdue Invoices</div>
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="card">
            <h3>Recent Invoices</h3>
            <?php if (empty($invoices)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3>No Invoices Found</h3>
                    <p>There are no invoices in the system yet. Invoices will appear here once students are enrolled and fees are processed.</p>
                    <div style="margin-top: 2rem;">
                        <a href="enrollment_payments.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i>
                            Go to Enrollment Payments
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Student</th>
                            <th>Grade</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                <td><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></td>
                                <td>Grade <?= htmlspecialchars($invoice['grade_level']) ?></td>
                                <td>₱<?= number_format($invoice['amount'], 2) ?></td>
                                <td><?= $invoice['created_at'] ? date('M j, Y', strtotime($invoice['created_at'])) : 'N/A' ?></td>
                                <td><?= $invoice['due_date'] ? date('M j, Y', strtotime($invoice['due_date'])) : 'N/A' ?></td>
                                <td>
                                    <span class="status-badge status-<?= $invoice['status'] ?>">
                                        <?= ucfirst($invoice['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.75rem;">
                                        <a href="#" class="btn btn-primary action-btn" title="View Invoice"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="btn btn-success action-btn" title="Edit Invoice"><i class="fas fa-edit"></i></a>
                                        <a href="#" class="btn btn-warning action-btn" title="Send Reminder"><i class="fas fa-envelope"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        </main>
    </div>
</body>
</html>

<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require accounting login
require_role('accounting');

$user = get_logged_in_user();

// Get payment statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_payments,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_revenue
    FROM payments
");
$stmt->execute();
$payment_stats = $stmt->fetch();

// Get recent payments
$stmt = $pdo->prepare("
    SELECT p.*, u.first_name, u.last_name, s.student_id
    FROM payments p
    JOIN students s ON p.student_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_payments = $stmt->fetchAll();

// Get monthly revenue data for the last 6 months
$stmt = $pdo->prepare("
    SELECT 
        MONTHNAME(created_at) as month,
        YEAR(created_at) as year,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as amount,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as payment_count
    FROM payments 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC
    LIMIT 6
");
$stmt->execute();
$monthly_revenue_raw = $stmt->fetchAll();

// If no data exists, create sample data for demonstration
if (empty($monthly_revenue_raw)) {
    $monthly_revenue = [
        ['month' => 'January', 'year' => 2024, 'amount' => 0, 'payment_count' => 0],
        ['month' => 'February', 'year' => 2024, 'amount' => 0, 'payment_count' => 0],
        ['month' => 'March', 'year' => 2024, 'amount' => 0, 'payment_count' => 0]
    ];
} else {
    $monthly_revenue = array_reverse($monthly_revenue_raw); // Show oldest to newest
}

// Get overdue payments (assuming payments have a due_date field, or use created_at + 30 days)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as overdue_count
    FROM payments 
    WHERE status = 'pending' 
    AND DATE_ADD(created_at, INTERVAL 30 DAY) < NOW()
");
$stmt->execute();
$overdue_result = $stmt->fetch();

// Get additional statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_payments,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'paid' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) THEN 1 ELSE 0 END) as paid_this_month,
        SUM(CASE WHEN status = 'paid' AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) THEN 1 ELSE 0 END) as paid_last_month,
        SUM(CASE WHEN status = 'paid' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) THEN amount ELSE 0 END) as revenue_this_month,
        SUM(CASE WHEN status = 'paid' AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) THEN amount ELSE 0 END) as revenue_last_month
    FROM payments
");
$stmt->execute();
$payment_stats = $stmt->fetch();

$stats = [
    'total_revenue' => $payment_stats['total_revenue'] ?? 0,
    'pending_payments' => $payment_stats['pending_payments'] ?? 0,
    'paid_payments' => $payment_stats['paid_this_month'] ?? 0,
    'overdue_payments' => $overdue_result['overdue_count'] ?? 0,
    'paid_last_month' => $payment_stats['paid_last_month'] ?? 0,
    'revenue_this_month' => $payment_stats['revenue_this_month'] ?? 0,
    'revenue_last_month' => $payment_stats['revenue_last_month'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard - EduManage</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); 
            min-height: 100vh; 
            color: #1e293b;
            line-height: 1.6;
        }
        
        .dashboard-container { display: flex; min-height: 100vh; }
        
        .sidebar { 
            width: 280px; 
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); 
            color: white; 
            padding: 2rem 0; 
            position: fixed; 
            height: 100vh; 
            overflow-y: auto; 
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header { padding: 0 2rem 2rem; border-bottom: 1px solid #334155; }
        .logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .logo i { 
            width: 40px; 
            height: 40px; 
            background: linear-gradient(135deg, #059669 0%, #10b981 100%); 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .user-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; font-weight: 600; }
        .user-info p { font-size: 0.85rem; color: #94a3b8; }
        .nav-menu { padding: 1rem 0; }
        .nav-item { 
            display: block; 
            padding: 0.875rem 2rem; 
            color: #cbd5e1; 
            text-decoration: none; 
            transition: all 0.3s ease; 
            border-left: 3px solid transparent;
            font-weight: 500;
        }
        .nav-item:hover, .nav-item.active { 
            background: rgba(16, 185, 129, 0.1); 
            color: #10b981; 
            border-left-color: #10b981;
            transform: translateX(4px);
        }
        .nav-item i { width: 20px; margin-right: 0.75rem; }
        
        .main-content { 
            flex: 1; 
            margin-left: 280px; 
            padding: 2rem; 
            background: transparent;
        }
        
        .header { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(20px); 
            border-radius: 20px; 
            padding: 2rem 2.5rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .welcome h1 { 
            font-size: 2.25rem; 
            color: #1e293b; 
            margin-bottom: 0.5rem; 
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .welcome p { color: #64748b; font-size: 1.1rem; font-weight: 500; }
        
        .btn { 
            padding: 0.875rem 1.75rem; 
            border: none; 
            border-radius: 12px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem;
            font-size: 0.95rem;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, #059669 0%, #10b981 100%); 
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #047857 0%, #059669 100%);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #1e293b;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .btn-secondary:hover {
            background: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 2.5rem; 
        }
        
        .stat-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(20px);
            border-radius: 20px; 
            padding: 2rem; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06); 
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            background: var(--accent-color);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before { opacity: 1; }
        .stat-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.revenue { --accent-color: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .stat-card.pending { --accent-color: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }
        .stat-card.paid { --accent-color: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); }
        .stat-card.overdue { --accent-color: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        
        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .stat-content { flex: 1; }
        
        .stat-icon { 
            width: 60px; 
            height: 60px; 
            border-radius: 16px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon.green { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }
        .stat-icon.blue { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); }
        .stat-icon.red { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        
        .stat-value { 
            font-size: 2.5rem; 
            font-weight: 800; 
            color: #1e293b; 
            margin: 1rem 0 0.5rem 0;
            line-height: 1;
        }
        
        .stat-label { 
            color: #64748b; 
            font-size: 0.95rem; 
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .trend-up { color: #059669; }
        .trend-down { color: #dc2626; }
        .trend-neutral { color: #64748b; }
        
        .content-grid { 
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 2rem; 
        }
        
        .content-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(20px);
            border-radius: 20px; 
            padding: 2rem; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .content-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
        }
        
        .content-title { 
            font-size: 1.4rem; 
            font-weight: 700; 
            color: #1e293b; 
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .content-title i {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #64748b;
        }
        
        .list-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 1.25rem 0; 
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
        }
        
        .list-item:last-child { border-bottom: none; }
        .list-item:hover {
            background: rgba(248, 250, 252, 0.5);
            margin: 0 -1rem;
            padding-left: 1rem;
            padding-right: 1rem;
            border-radius: 12px;
        }
        
        .item-info h4 { 
            color: #1e293b; 
            margin-bottom: 0.25rem; 
            font-size: 1rem;
            font-weight: 600;
        }
        .item-info p { 
            color: #64748b; 
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-badge { 
            padding: 0.5rem 1rem; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            border: 1px solid #fca5a5;
        }
        
        .empty-state { 
            text-align: center; 
            padding: 3rem 2rem; 
            color: #64748b; 
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .chart-container {
            height: 200px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1rem;
            border: 2px dashed #cbd5e1;
        }
        
        .chart-placeholder {
            color: #64748b;
            font-weight: 500;
            text-align: center;
        }
        
        @media (max-width: 1024px) { 
            .sidebar { transform: translateX(-100%); } 
            .main-content { margin-left: 0; padding: 1rem; } 
            .content-grid { grid-template-columns: 1fr; } 
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
            .header { padding: 1.5rem; }
            .welcome h1 { font-size: 1.75rem; }
        }
        
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 1rem; align-items: flex-start; }
            .header-actions { width: 100%; justify-content: flex-start; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include_sidebar(); ?>

        <main class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="welcome">
                        <h1>Accounting Dashboard</h1>
                        <p>Comprehensive financial management and payment tracking</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary"><i class="fas fa-download"></i> Export Report</button>
                        <button class="btn btn-primary"><i class="fas fa-plus"></i> New Invoice</button>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card revenue">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-value">₱<?= number_format($stats['total_revenue']) ?></div>
                            <?php 
                            $revenue_change = 0;
                            if ($stats['revenue_last_month'] > 0) {
                                $revenue_change = (($stats['revenue_this_month'] - $stats['revenue_last_month']) / $stats['revenue_last_month']) * 100;
                            }
                            $revenue_trend_class = $revenue_change > 0 ? 'trend-up' : ($revenue_change < 0 ? 'trend-down' : 'trend-neutral');
                            $revenue_trend_icon = $revenue_change > 0 ? 'fa-arrow-up' : ($revenue_change < 0 ? 'fa-arrow-down' : 'fa-minus');
                            ?>
                            <div class="stat-trend <?= $revenue_trend_class ?>">
                                <i class="fas <?= $revenue_trend_icon ?>"></i>
                                <span><?= $revenue_change == 0 ? 'No change' : (($revenue_change > 0 ? '+' : '') . number_format($revenue_change, 1) . '% from last month') ?></span>
                            </div>
                        </div>
                        <div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Pending Payments</div>
                            <div class="stat-value"><?= $stats['pending_payments'] ?></div>
                            <div class="stat-trend trend-neutral">
                                <i class="fas fa-clock"></i>
                                <span>Awaiting payment</span>
                            </div>
                        </div>
                        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="stat-card paid">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Paid This Month</div>
                            <div class="stat-value"><?= $stats['paid_payments'] ?></div>
                            <?php 
                            $paid_change = 0;
                            if ($stats['paid_last_month'] > 0) {
                                $paid_change = (($stats['paid_payments'] - $stats['paid_last_month']) / $stats['paid_last_month']) * 100;
                            }
                            $paid_trend_class = $paid_change > 0 ? 'trend-up' : ($paid_change < 0 ? 'trend-down' : 'trend-neutral');
                            $paid_trend_icon = $paid_change > 0 ? 'fa-arrow-up' : ($paid_change < 0 ? 'fa-arrow-down' : 'fa-minus');
                            ?>
                            <div class="stat-trend <?= $paid_trend_class ?>">
                                <i class="fas <?= $paid_trend_icon ?>"></i>
                                <span><?= $paid_change == 0 ? 'No change' : (($paid_change > 0 ? '+' : '') . number_format($paid_change, 1) . '% from last month') ?></span>
                            </div>
                        </div>
                        <div class="stat-icon blue"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="stat-card overdue">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Overdue Payments</div>
                            <div class="stat-value"><?= $stats['overdue_payments'] ?></div>
                            <div class="stat-trend trend-neutral">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span><?= $stats['overdue_payments'] > 0 ? 'Requires attention' : 'All up to date' ?></span>
                            </div>
                        </div>
                        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="content-card">
                    <h3 class="content-title">
                        <i class="fas fa-credit-card"></i>
                        Recent Payments
                    </h3>
                    <?php if (empty($recent_payments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-credit-card"></i>
                            <p>No recent payments found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($recent_payments, 0, 5) as $payment): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></h4>
                                    <p>₱<?= number_format($payment['amount']) ?> • <?= htmlspecialchars($payment['payment_type']) ?></p>
                                </div>
                                <div class="status-badge status-<?= $payment['status'] ?>">
                                    <?= ucfirst($payment['status']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="text-align: center; margin-top: 1.5rem;">
                            <button class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                                <i class="fas fa-eye"></i> View All Payments
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h3 class="content-title">
                        <i class="fas fa-chart-line"></i>
                        Revenue Analytics
                    </h3>
                    <?php if (empty($monthly_revenue) || array_sum(array_column($monthly_revenue, 'amount')) == 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <p>No revenue data available</p>
                            <small style="color: #94a3b8;">Revenue will appear here once payments are processed</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($monthly_revenue as $index => $month): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($month['month']) ?> <?= $month['year'] ?></h4>
                                    <p><?= $month['payment_count'] ?> payment<?= $month['payment_count'] != 1 ? 's' : '' ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem;">
                                        ₱<?= number_format($month['amount']) ?>
                                    </div>
                                    <?php if ($index > 0 && $monthly_revenue[$index - 1]['amount'] > 0): ?>
                                        <?php 
                                        $prev_amount = $monthly_revenue[$index - 1]['amount'];
                                        $change = (($month['amount'] - $prev_amount) / $prev_amount) * 100;
                                        $trend_class = $change > 0 ? 'trend-up' : ($change < 0 ? 'trend-down' : 'trend-neutral');
                                        $trend_icon = $change > 0 ? 'fa-arrow-up' : ($change < 0 ? 'fa-arrow-down' : 'fa-minus');
                                        ?>
                                        <div class="stat-trend <?= $trend_class ?>" style="font-size: 0.75rem; margin-top: 0.25rem;">
                                            <i class="fas <?= $trend_icon ?>"></i>
                                            <span><?= $change == 0 ? '0' : abs(round($change, 1)) ?>%</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="chart-container">
                        <div class="chart-placeholder">
                            <i class="fas fa-chart-area" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                            <p>Revenue Chart Visualization</p>
                            <small style="color: #94a3b8;">Chart integration coming soon</small>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

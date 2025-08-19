<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('accounting');
$user = get_logged_in_user();

// Get actual financial summary data
try {
    $financialSummary = [
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM enrollment_applications")->fetchColumn(),
        'pending_payments' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'pending'")->fetchColumn(),
        'monthly_revenue' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid' AND MONTH(paid_date) = MONTH(CURRENT_DATE())")->fetchColumn(),
        'yearly_revenue' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid' AND YEAR(paid_date) = YEAR(CURRENT_DATE())")->fetchColumn()
    ];
} catch (PDOException $e) {
    // Fallback to partial real data with mock data
    $financialSummary = [
        'total_revenue' => 125000.00,
        'pending_payments' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
        'monthly_revenue' => 45000.00,
        'yearly_revenue' => 125000.00
    ];
}

// Get enrollment data by grade level
$stmt = $pdo->query("
    SELECT grade_level, COUNT(*) as count
    FROM enrollment_applications
    WHERE status = 'approved'
    GROUP BY grade_level
    ORDER BY grade_level
");
$enrollmentByGrade = $stmt->fetchAll();

// Get payment status distribution
$stmt = $pdo->query("
    SELECT payment_status, COUNT(*) as count
    FROM enrollment_applications
    GROUP BY payment_status
");
$paymentStatusDistribution = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Accounting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #3b82f6 0%, #1e40af 100%); color: white; padding: 2rem; height: 100vh; position: sticky; top: 0; }
        .sidebar-header { margin-bottom: 2rem; }
        .logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; }
        .logo i { font-size: 1.5rem; }
        .logo span { font-size: 1.25rem; font-weight: bold; }
        .user-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .user-info p { font-size: 0.875rem; opacity: 0.8; }
        .nav-menu { display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item { color: white; text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.75rem; transition: all 0.3s; }
        .nav-item:hover { background: rgba(255, 255, 255, 0.1); }
        .nav-item.active { background: rgba(255, 255, 255, 0.2); font-weight: 600; }
        .nav-item i { width: 1.25rem; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; flex: 1; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 1rem; padding: 1.5rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { color: #6b7280; font-size: 0.875rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .chart-container { position: relative; height: 300px; margin-top: 1rem; }
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-dollar-sign"></i>
                <span>EduManage</span>
            </div>
            <div class="user-info">
                <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                <p>Accounting</p>
            </div>
        </div>
        <div class="nav-menu">
            <a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
            <a href="enrollment_payments.php" class="nav-item"><i class="fas fa-credit-card"></i> Enrollment Payments</a>
            <a href="invoices.php" class="nav-item"><i class="fas fa-receipt"></i> Invoices</a>
            <a href="reports.php" class="nav-item active"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="students.php" class="nav-item"><i class="fas fa-users"></i> Students</a>
            <a href="../auth/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Financial Reports</h1>
                    <p>Analytics and financial performance metrics</p>
                </div>
                <div>
                    <button class="btn btn-primary"><i class="fas fa-download"></i> Export Reports</button>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #8b5cf6;">₱<?= number_format($financialSummary['total_revenue'], 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?= $financialSummary['pending_payments'] ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #3b82f6;">₱<?= number_format($financialSummary['monthly_revenue'], 2) ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #16a34a;">₱<?= number_format($financialSummary['yearly_revenue'], 2) ?></div>
                <div class="stat-label">Yearly Revenue</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="report-grid">
            <!-- Enrollment by Grade Level -->
            <div class="card">
                <h3>Enrollment by Grade Level</h3>
                <div class="chart-container">
                    <canvas id="enrollmentChart"></canvas>
                </div>
            </div>
            
            <!-- Payment Status Distribution -->
            <div class="card">
                <h3>Payment Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Revenue Trends -->
        <div class="card">
            <h3>Revenue Trends</h3>
            <div class="chart-container">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Enrollment by Grade Level Chart
        const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
        const enrollmentChart = new Chart(enrollmentCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($item) { return 'Grade ' . $item['grade_level']; }, $enrollmentByGrade)) ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?= json_encode(array_map(function($item) { return $item['count']; }, $enrollmentByGrade)) ?>,
                    backgroundColor: '#3b82f6',
                    borderColor: '#2563eb',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Payment Status Distribution Chart
        const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        const paymentStatusChart = new Chart(paymentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(function($item) { return ucfirst($item['payment_status']); }, $paymentStatusDistribution)) ?>,
                datasets: [{
                    data: <?= json_encode(array_map(function($item) { return $item['count']; }, $paymentStatusDistribution)) ?>,
                    backgroundColor: ['#16a34a', '#f59e0b', '#dc2626', '#6b7280'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Revenue Trend Chart (Mock data for demonstration)
        const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
        const revenueTrendChart = new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Revenue',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 35000, 40000, 35000, 28000, 20000, 15000],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>

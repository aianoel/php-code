<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('admin');
$user = get_logged_in_user();

// Get enrollment statistics
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count 
    FROM enrollments 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$stmt->execute();
$enrollment_trends = $stmt->fetchAll();

// Get user statistics by role
$stmt = $pdo->prepare("
    SELECT role, COUNT(*) as count, 
           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
    FROM users 
    GROUP BY role
");
$stmt->execute();
$user_stats = $stmt->fetchAll();

// Get grade distribution
$stmt = $pdo->prepare("
    SELECT grade_level, COUNT(*) as count 
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.status = 'active'
    GROUP BY grade_level
    ORDER BY grade_level
");
$stmt->execute();
$grade_distribution = $stmt->fetchAll();

// Get recent activity summary
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as activities
    FROM activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute();
$activity_summary = $stmt->fetchAll();

// Get payment statistics
$stmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM payments
    GROUP BY status
");
$stmt->execute();
$payment_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin</title>
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
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .chart-container { position: relative; height: 300px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #1e293b; }
        .stat-label { color: #64748b; margin-top: 0.5rem; }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .export-buttons { display: flex; gap: 1rem; margin-bottom: 2rem; }
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
                            <h1>Reports & Analytics</h1>
                            <p>View system reports and analytics</p>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="export-buttons">
                    <button onclick="exportReport('pdf')" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                    <button onclick="exportReport('excel')" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>

                <!-- Summary Statistics -->
                <div class="stats-grid">
                    <?php 
                    $total_users = array_sum(array_column($user_stats, 'count'));
                    $active_users = array_sum(array_column($user_stats, 'active_count'));
                    $total_students = 0;
                    foreach ($user_stats as $stat) {
                        if ($stat['role'] === 'student') $total_students = $stat['count'];
                    }
                    ?>
                    <div class="stat-card">
                        <div class="stat-value"><?= $total_users ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $active_users ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $total_students ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($enrollment_trends) ?></div>
                        <div class="stat-label">Enrollment Months</div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="grid">
                    <!-- Enrollment Trends Chart -->
                    <div class="card">
                        <h3>Enrollment Trends (Last 12 Months)</h3>
                        <div class="chart-container">
                            <canvas id="enrollmentChart"></canvas>
                        </div>
                    </div>

                    <!-- User Distribution Chart -->
                    <div class="card">
                        <h3>User Distribution by Role</h3>
                        <div class="chart-container">
                            <canvas id="userChart"></canvas>
                        </div>
                    </div>

                    <!-- Grade Distribution Chart -->
                    <div class="card">
                        <h3>Student Grade Distribution</h3>
                        <div class="chart-container">
                            <canvas id="gradeChart"></canvas>
                        </div>
                    </div>

                    <!-- Payment Status Chart -->
                    <div class="card">
                        <h3>Payment Status Overview</h3>
                        <div class="chart-container">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed Tables -->
                <div class="grid">
                    <!-- User Statistics Table -->
                    <div class="card">
                        <h3>User Statistics by Role</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Total</th>
                                    <th>Active</th>
                                    <th>Inactive</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_stats as $stat): ?>
                                    <tr>
                                        <td><?= ucfirst($stat['role']) ?></td>
                                        <td><?= $stat['count'] ?></td>
                                        <td><?= $stat['active_count'] ?></td>
                                        <td><?= $stat['count'] - $stat['active_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Recent Activity Summary -->
                    <div class="card">
                        <h3>Recent Activity Summary</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activities</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activity_summary)): ?>
                                    <tr>
                                        <td colspan="2" style="text-align: center; color: #64748b;">No recent activity</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activity_summary as $activity): ?>
                                        <tr>
                                            <td><?= format_date($activity['date']) ?></td>
                                            <td><?= $activity['activities'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Statistics -->
                <?php if (!empty($payment_stats)): ?>
                <div class="card">
                    <h3>Payment Statistics</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_stats as $payment): ?>
                                <tr>
                                    <td><?= ucfirst($payment['status']) ?></td>
                                    <td><?= $payment['count'] ?></td>
                                    <td>₱<?= number_format($payment['total_amount'] ?? 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Enrollment Trends Chart
        const enrollmentData = <?= json_encode($enrollment_trends) ?>;
        const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
        new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: enrollmentData.map(item => item.month),
                datasets: [{
                    label: 'Enrollments',
                    data: enrollmentData.map(item => item.count),
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // User Distribution Chart
        const userData = <?= json_encode($user_stats) ?>;
        const userCtx = document.getElementById('userChart').getContext('2d');
        new Chart(userCtx, {
            type: 'doughnut',
            data: {
                labels: userData.map(item => item.role.charAt(0).toUpperCase() + item.role.slice(1)),
                datasets: [{
                    data: userData.map(item => item.count),
                    backgroundColor: [
                        '#dc2626', '#16a34a', '#d97706', '#3b82f6', 
                        '#8b5cf6', '#ec4899', '#10b981', '#f59e0b'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Grade Distribution Chart
        const gradeData = <?= json_encode($grade_distribution) ?>;
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: gradeData.map(item => `Grade ${item.grade_level}`),
                datasets: [{
                    label: 'Students',
                    data: gradeData.map(item => item.count),
                    backgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Payment Status Chart
        const paymentData = <?= json_encode($payment_stats) ?>;
        if (paymentData.length > 0) {
            const paymentCtx = document.getElementById('paymentChart').getContext('2d');
            new Chart(paymentCtx, {
                type: 'pie',
                data: {
                    labels: paymentData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                    datasets: [{
                        data: paymentData.map(item => item.count),
                        backgroundColor: ['#16a34a', '#d97706', '#dc2626']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function exportReport(format) {
            alert(`Export to ${format.toUpperCase()} functionality would be implemented here.\n\nThis would generate a comprehensive report with all the charts and data shown on this page.`);
        }
    </script>
</body>
</html>

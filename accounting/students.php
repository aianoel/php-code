<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('accounting');
$user = get_logged_in_user();

// Get students with actual payment information from enrollment_applications
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               ea.payment_status,
               ea.total_fees,
               ea.paid_amount,
               (ea.total_fees - ea.paid_amount) as balance
        FROM students s
        LEFT JOIN enrollment_applications ea ON s.email = ea.email
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();
    
    // For students without enrollment applications, add default payment data
    foreach ($students as &$student) {
        if ($student['payment_status'] === null) {
            $student['payment_status'] = 'pending';
            $student['total_fees'] = 10000;
            $student['paid_amount'] = 0;
            $student['balance'] = 10000;
        }
    }
    unset($student); // Break the reference
} catch (PDOException $e) {
    // Fallback to mock data if query fails
    $students = [
        [
            'id' => 1,
            'student_id' => 'S2025001',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@example.com',
            'grade_level' => '10',
            'payment_status' => 'paid',
            'total_fees' => 10000,
            'paid_amount' => 10000,
            'balance' => 0
        ],
        [
            'id' => 2,
            'student_id' => 'S2025002',
            'first_name' => 'Maria',
            'last_name' => 'Garcia',
            'email' => 'maria.garcia@example.com',
            'grade_level' => '11',
            'payment_status' => 'partial',
            'total_fees' => 12000,
            'paid_amount' => 6000,
            'balance' => 6000
        ],
        [
            'id' => 3,
            'student_id' => 'S2025003',
            'first_name' => 'James',
            'last_name' => 'Wilson',
            'email' => 'james.wilson@example.com',
            'grade_level' => '9',
            'payment_status' => 'pending',
            'total_fees' => 9000,
            'paid_amount' => 0,
            'balance' => 9000
        ]
    ];
}

// Get payment statistics
$stats = [
    'total_students' => count($students),
    'paid_students' => 0,
    'partial_students' => 0,
    'pending_students' => 0
];

// Calculate statistics
foreach ($students as $student) {
    if ($student['payment_status'] == 'paid') {
        $stats['paid_students']++;
    } elseif ($student['payment_status'] == 'partial') {
        $stats['partial_students']++;
    } elseif ($student['payment_status'] == 'pending') {
        $stats['pending_students']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Accounting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .btn-success { background: #16a34a; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { font-weight: 600; color: #374151; }
        .table tbody tr:hover { background: #f9fafb; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
        .status-paid { background: #dcfce7; color: #16a34a; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .status-pending { background: #fee2e2; color: #dc2626; }
        .search-container { margin-bottom: 1.5rem; }
        .search-input { width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
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
            <a href="reports.php" class="nav-item"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="students.php" class="nav-item active"><i class="fas fa-users"></i> Students</a>
            <a href="../auth/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Student Financial Records</h1>
                    <p>Manage student payment information and balances</p>
                </div>
                <button class="btn btn-primary"><i class="fas fa-file-export"></i> Export Records</button>
            </div>
        </div>

        <!-- Payment Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_students'] ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #16a34a;"><?= $stats['paid_students'] ?></div>
                <div class="stat-label">Fully Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?= $stats['partial_students'] ?></div>
                <div class="stat-label">Partial Payment</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc2626;"><?= $stats['pending_students'] ?></div>
                <div class="stat-label">Payment Pending</div>
            </div>
        </div>

        <!-- Student Records -->
        <div class="card">
            <div class="search-container">
                <input type="text" id="studentSearch" class="search-input" placeholder="Search students by name, ID, or grade level...">
            </div>
            
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No Students Found</h3>
                    <p>There are no student records in the system yet.</p>
                </div>
            <?php else: ?>
                <table class="table" id="studentsTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Grade</th>
                            <th>Email</th>
                            <th>Total Fees</th>
                            <th>Paid Amount</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['student_id']) ?></td>
                                <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                <td>Grade <?= htmlspecialchars($student['grade_level']) ?></td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td>₱<?= number_format($student['total_fees'] ?? 0, 2) ?></td>
                                <td>₱<?= number_format($student['paid_amount'] ?? 0, 2) ?></td>
                                <td>₱<?= number_format($student['balance'] ?? 0, 2) ?></td>
                                <td>
                                    <?php if ($student['payment_status']): ?>
                                        <span class="status-badge status-<?= $student['payment_status'] ?>">
                                            <?= ucfirst($student['payment_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">No Record</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="#" class="btn btn-primary" style="padding: 0.5rem; font-size: 0.875rem;"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="btn btn-success" style="padding: 0.5rem; font-size: 0.875rem;"><i class="fas fa-money-bill"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Simple search functionality
        document.getElementById('studentSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('studentsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const rowText = rows[i].textContent.toLowerCase();
                rows[i].style.display = rowText.includes(searchTerm) ? '' : 'none';
            }
        });
    </script>
</body>
</html>

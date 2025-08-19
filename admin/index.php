<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin login
require_role('admin');

$user = get_logged_in_user();
$stats = $dataManager->getDashboardStats('admin');

// Get recent activities
$stmt = $pdo->prepare("
    SELECT al.*, u.first_name, u.last_name 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentActivities = $stmt->fetchAll();

// Get enrollment applications
$enrollmentApps = $dataManager->getEnrollmentApplications('pending');
$enrollmentApps = array_slice($enrollmentApps, 0, 5);

// Get system stats
$systemStats = [
    'total_users' => 0,
    'active_students' => 0,
    'active_teachers' => 0,
    'pending_enrollments' => count($enrollmentApps)
];

foreach ($stats['users_by_role'] ?? [] as $roleData) {
    $systemStats['total_users'] += $roleData['count'];
    if ($roleData['role'] === 'student') {
        $systemStats['active_students'] = $roleData['count'];
    } elseif ($roleData['role'] === 'teacher') {
        $systemStats['active_teachers'] = $roleData['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EduManage</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid #334155;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .logo i {
            width: 40px;
            height: 40px;
            background: #dc2626;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .user-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .user-info p {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .nav-menu {
            padding: 1rem 0;
        }

        .nav-item {
            display: block;
            padding: 0.875rem 2rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
            border-left-color: #dc2626;
        }

        .nav-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .welcome p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e5e7eb;
            color: #6b7280;
        }

        .btn-outline:hover {
            background: #f3f4f6;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.red { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); }
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }

        .stat-trend {
            font-size: 0.8rem;
            color: #10b981;
        }

        /* Management Cards */
        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .management-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .management-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #1e293b;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .item-info h4 {
            color: #1e293b;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .item-info p {
            color: #64748b;
            font-size: 0.8rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .management-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include_sidebar(); ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="welcome">
                        <h1>Admin Dashboard</h1>
                        <p>Manage your school system efficiently</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            System Alerts
                        </button>
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Quick Add
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $systemStats['total_users'] ?></div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-trend">↗ Active system users</div>
                        </div>
                        <div class="stat-icon red">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $systemStats['active_students'] ?></div>
                            <div class="stat-label">Active Students</div>
                            <div class="stat-trend">Current enrollment</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $systemStats['active_teachers'] ?></div>
                            <div class="stat-label">Active Teachers</div>
                            <div class="stat-trend">Faculty members</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $systemStats['pending_enrollments'] ?></div>
                            <div class="stat-label">Pending Enrollments</div>
                            <div class="stat-trend">Awaiting review</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Management Cards -->
            <div class="management-grid">
                <!-- Pending Enrollments -->
                <div class="management-card">
                    <div class="card-header">
                        <h3 class="card-title">Pending Enrollments</h3>
                        <div class="card-icon orange">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    
                    <?php if (empty($enrollmentApps)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-plus"></i>
                            <p>No pending enrollments</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($enrollmentApps as $app): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></h4>
                                    <p><?= htmlspecialchars($app['grade_level']) ?> - <?= format_date($app['created_at']) ?></p>
                                </div>
                                <div class="status-badge status-pending">Pending</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="management-card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activities</h3>
                        <div class="card-icon blue">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                    
                    <?php if (empty($recentActivities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($recentActivities, 0, 5) as $activity): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($activity['action']) ?></h4>
                                    <p><?= htmlspecialchars(($activity['first_name'] ?? 'System') . ' ' . ($activity['last_name'] ?? '')) ?></p>
                                </div>
                                <div class="item-meta">
                                    <p style="font-size: 0.75rem; color: #64748b;">
                                        <?= format_datetime($activity['created_at']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        // Add mobile menu button if needed
        if (window.innerWidth <= 1024) {
            const header = document.querySelector('.header-content');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.className = 'btn btn-outline';
            menuBtn.onclick = toggleSidebar;
            header.insertBefore(menuBtn, header.firstChild);
        }

        // Smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .management-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Active nav item handling
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#') {
                    e.preventDefault();
                }
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>

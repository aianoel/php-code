<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require principal login
require_role('principal');

$user = get_logged_in_user();

// Get school statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_students FROM students WHERE status = 'active'");
$stmt->execute();
$total_students = $stmt->fetch()['total_students'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_teachers FROM users WHERE role = 'teacher' AND status = 'active'");
$stmt->execute();
$total_teachers = $stmt->fetch()['total_teachers'];

$stmt = $pdo->prepare("SELECT COUNT(*) as pending_enrollments FROM enrollments WHERE status = 'pending'");
$stmt->execute();
$pending_enrollments = $stmt->fetch()['pending_enrollments'];

// Get recent activities
$stmt = $pdo->prepare("
    SELECT al.*, u.first_name, u.last_name 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();

// Mock data for school performance
$performance_data = [
    ['metric' => 'Overall GPA', 'value' => '3.8', 'trend' => 'up'],
    ['metric' => 'Attendance Rate', 'value' => '94%', 'trend' => 'up'],
    ['metric' => 'Graduation Rate', 'value' => '98%', 'trend' => 'stable'],
    ['metric' => 'Teacher Satisfaction', 'value' => '4.2/5', 'trend' => 'up']
];

$stats = [
    'total_students' => $total_students,
    'total_teachers' => $total_teachers,
    'pending_enrollments' => $pending_enrollments,
    'active_classes' => 45
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard - EduManage</title>
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
            background: #8b5cf6;
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
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
            border-left-color: #8b5cf6;
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
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
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

        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
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

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .content-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #1e293b;
        }

        .view-all {
            color: #8b5cf6;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .item-info h4 {
            color: #1e293b;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .item-info p {
            color: #64748b;
            font-size: 0.85rem;
        }

        .item-meta {
            text-align: right;
            font-size: 0.85rem;
            color: #64748b;
        }

        .performance-metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .performance-metric:last-child {
            border-bottom: none;
        }

        .metric-info h4 {
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .metric-value {
            font-size: 1.25rem;
            font-weight: bold;
            color: #1e293b;
        }

        .trend-indicator {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }

        .trend-up {
            background: #dcfce7;
            color: #16a34a;
        }

        .trend-stable {
            background: #fef3c7;
            color: #d97706;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Full width section */
        .full-width-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
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

            .content-grid {
                grid-template-columns: 1fr;
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
                        <h1>Principal Dashboard</h1>
                        <p>School leadership and administrative oversight</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-chart-bar"></i>
                            View Reports
                        </button>
                        <button class="btn btn-primary">
                            <i class="fas fa-bullhorn"></i>
                            New Announcement
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['total_students'] ?></div>
                            <div class="stat-label">Total Students</div>
                            <div class="stat-trend">Active enrollment</div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['total_teachers'] ?></div>
                            <div class="stat-label">Teaching Staff</div>
                            <div class="stat-trend">Active faculty</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['pending_enrollments'] ?></div>
                            <div class="stat-label">Pending Enrollments</div>
                            <div class="stat-trend">Awaiting approval</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['active_classes'] ?></div>
                            <div class="stat-label">Active Classes</div>
                            <div class="stat-trend">This semester</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-door-open"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- School Performance -->
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">School Performance</h3>
                        <a href="#" class="view-all">View Details</a>
                    </div>
                    
                    <?php foreach ($performance_data as $metric): ?>
                        <div class="performance-metric">
                            <div class="metric-info">
                                <h4><?= htmlspecialchars($metric['metric']) ?></h4>
                            </div>
                            <div style="text-align: right;">
                                <div class="metric-value"><?= htmlspecialchars($metric['value']) ?></div>
                                <div class="trend-indicator trend-<?= $metric['trend'] ?>">
                                    <?= $metric['trend'] === 'up' ? '↗ Improving' : ($metric['trend'] === 'stable' ? '→ Stable' : '↘ Declining') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent Activities -->
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Recent Activities</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($recent_activities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?></h4>
                                    <p><?= htmlspecialchars($activity['action']) ?></p>
                                </div>
                                <div class="item-meta">
                                    <p><?= format_date($activity['created_at']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="full-width-card">
                <div class="content-header">
                    <h3 class="content-title">Quick Actions</h3>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card" style="cursor: pointer;">
                        <div class="stat-header">
                            <div>
                                <h4>Staff Directory</h4>
                                <p>Manage teaching and administrative staff</p>
                            </div>
                            <div class="stat-icon blue">
                                <i class="fas fa-users-cog"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" style="cursor: pointer;">
                        <div class="stat-header">
                            <div>
                                <h4>Academic Calendar</h4>
                                <p>View and manage school events</p>
                            </div>
                            <div class="stat-icon green">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" style="cursor: pointer;">
                        <div class="stat-header">
                            <div>
                                <h4>Financial Reports</h4>
                                <p>Review school budget and expenses</p>
                            </div>
                            <div class="stat-icon orange">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" style="cursor: pointer;">
                        <div class="stat-header">
                            <div>
                                <h4>System Settings</h4>
                                <p>Configure school management system</p>
                            </div>
                            <div class="stat-icon purple">
                                <i class="fas fa-cogs"></i>
                            </div>
                        </div>
                    </div>
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
            const cards = document.querySelectorAll('.stat-card, .content-card, .full-width-card');
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

        // Quick action card click handlers
        document.querySelectorAll('.stat-card[style*="cursor"]').forEach(card => {
            card.addEventListener('click', function() {
                // Add functionality for quick actions
                console.log('Quick action clicked');
            });
        });
    </script>
</body>
</html>

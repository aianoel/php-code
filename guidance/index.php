<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require guidance login
require_role('guidance');

$user = get_logged_in_user();

// Get students for guidance counseling
$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE u.status = 'active'
    ORDER BY u.last_name, u.first_name
    LIMIT 20
");
$stmt->execute();
$students = $stmt->fetchAll();

// Get recent counseling sessions (mock data for now)
$counselingSessions = [
    ['student_name' => 'John Doe', 'date' => '2024-01-15', 'type' => 'Academic Counseling', 'status' => 'completed'],
    ['student_name' => 'Jane Smith', 'date' => '2024-01-14', 'type' => 'Career Guidance', 'status' => 'scheduled'],
    ['student_name' => 'Mike Johnson', 'date' => '2024-01-13', 'type' => 'Personal Counseling', 'status' => 'completed']
];

// Get announcements
$announcements = $dataManager->getAnnouncements(5, 'guidance');

// Stats
$stats = [
    'total_students' => count($students),
    'sessions_this_week' => 12,
    'pending_appointments' => 5,
    'completed_sessions' => 45
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guidance Dashboard - EduManage</title>
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
            background: #f59e0b;
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
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border-left-color: #f59e0b;
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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);
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

        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

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
            color: #f59e0b;
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
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .status-completed {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-scheduled {
            background: #fef3c7;
            color: #d97706;
        }

        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .student-card {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .student-card:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin: 0 auto 0.75rem;
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

            .student-grid {
                grid-template-columns: repeat(2, 1fr);
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
                        <h1>Guidance Dashboard</h1>
                        <p>Supporting student well-being and academic success</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            Alerts
                        </button>
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Schedule Session
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
                            <div class="stat-trend">Under guidance</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['sessions_this_week'] ?></div>
                            <div class="stat-label">Sessions This Week</div>
                            <div class="stat-trend">↗ +3 from last week</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['pending_appointments'] ?></div>
                            <div class="stat-label">Pending Appointments</div>
                            <div class="stat-trend">This week</div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['completed_sessions'] ?></div>
                            <div class="stat-label">Completed Sessions</div>
                            <div class="stat-trend">This semester</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Counseling Sessions -->
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Recent Sessions</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($counselingSessions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <p>No recent sessions</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($counselingSessions as $session): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($session['student_name']) ?></h4>
                                    <p><?= htmlspecialchars($session['type']) ?></p>
                                </div>
                                <div class="item-meta">
                                    <div class="status-badge status-<?= $session['status'] ?>">
                                        <?= ucfirst($session['status']) ?>
                                    </div>
                                    <p><?= format_date($session['date']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Student Directory -->
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Student Directory</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($students)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No students found</p>
                        </div>
                    <?php else: ?>
                        <div class="student-grid">
                            <?php foreach (array_slice($students, 0, 8) as $student): ?>
                                <div class="student-card">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) ?>
                                    </div>
                                    <h4><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h4>
                                    <p><?= htmlspecialchars($student['student_id']) ?></p>
                                    <p><?= htmlspecialchars($student['grade_level']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
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
            const cards = document.querySelectorAll('.stat-card, .content-card');
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

        // Student card click handler
        document.querySelectorAll('.student-card').forEach(card => {
            card.addEventListener('click', function() {
                // Add functionality to view student details
                console.log('View student details');
            });
        });
    </script>
</body>
</html>

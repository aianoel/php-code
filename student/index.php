<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require student login
require_role('student');

$user = get_logged_in_user();
$stats = $dataManager->getDashboardStats('student', $user['id']);

// Get student-specific data
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

// Get recent grades
$recentGrades = $dataManager->getStudentGrades($student['id'] ?? 0);
$recentGrades = array_slice($recentGrades, 0, 5);

// Get upcoming assignments
$upcomingAssignments = $dataManager->getAssignments(null, $student['id'] ?? 0);
$upcomingAssignments = array_filter($upcomingAssignments, function($assignment) {
    return strtotime($assignment['due_date']) >= time() && !$assignment['submitted_at'];
});
$upcomingAssignments = array_slice($upcomingAssignments, 0, 5);

// Get announcements
$announcements = $dataManager->getAnnouncements(5, 'student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduManage</title>
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
            background: #3b82f6;
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
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border-left-color: #3b82f6;
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
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
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

        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
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

        /* Action Buttons */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .action-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .action-desc {
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Content Cards */
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
            color: #3b82f6;
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

        .grade-badge {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .due-date {
            color: #f59e0b;
            font-weight: 500;
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

            .actions-grid {
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
                        <h1>Welcome back, <?= htmlspecialchars($user['first_name']) ?>! 👋</h1>
                        <p>Here's what's happening with your studies today</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            Notifications
                        </button>
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Quick Action
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= number_format($stats['current_gpa'] ?? 0, 2) ?></div>
                            <div class="stat-label">Current GPA</div>
                            <div class="stat-trend">↗ +0.2 from last semester</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['total_subjects'] ?? 0 ?></div>
                            <div class="stat-label">Total Subjects</div>
                            <div class="stat-trend">Current semester</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['assignments_due'] ?? 0 ?></div>
                            <div class="stat-label">Assignments Due</div>
                            <div class="stat-trend">This week</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['total_grades'] ?? 0 ?></div>
                            <div class="stat-label">Total Grades</div>
                            <div class="stat-trend">All time</div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-award"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="actions-grid">
                <a href="#" class="action-card">
                    <div class="action-icon blue">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="action-title">View Grades</div>
                    <div class="action-desc">Check your latest grades and performance</div>
                </a>

                <a href="#" class="action-card">
                    <div class="action-icon green">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="action-title">Assignments</div>
                    <div class="action-desc">View and submit your assignments</div>
                </a>

                <a href="#" class="action-card">
                    <div class="action-icon orange">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="action-title">Learning Modules</div>
                    <div class="action-desc">Access course materials and resources</div>
                </a>

                <a href="#" class="action-card">
                    <div class="action-icon purple">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="action-title">Join Meeting</div>
                    <div class="action-desc">Attend virtual classes and meetings</div>
                </a>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Grades -->
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Recent Grades</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($recentGrades)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <p>No grades available yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentGrades as $grade): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($grade['subject_name']) ?></h4>
                                    <p><?= htmlspecialchars($grade['grade_type']) ?> - <?= htmlspecialchars($grade['title'] ?? 'Grade') ?></p>
                                </div>
                                <div class="item-meta">
                                    <div class="grade-badge"><?= number_format($grade['percentage'], 1) ?>%</div>
                                    <p><?= format_date($grade['date_recorded']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Assignments -->
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Upcoming Assignments</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($upcomingAssignments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <p>No upcoming assignments</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingAssignments as $assignment): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($assignment['title']) ?></h4>
                                    <p><?= htmlspecialchars($assignment['subject_name']) ?></p>
                                </div>
                                <div class="item-meta">
                                    <div class="due-date">Due: <?= format_date($assignment['due_date']) ?></div>
                                    <p><?= $assignment['max_score'] ?> points</p>
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
            const cards = document.querySelectorAll('.stat-card, .action-card, .content-card');
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

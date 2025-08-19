<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require parent login
require_role('parent');

$user = get_logged_in_user();
$stats = $dataManager->getDashboardStats('parent', $user['id']);

// Get children data
$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.parent_id = ? AND u.status = 'active'
");
$stmt->execute([$user['id']]);
$children = $stmt->fetchAll();

// Get recent grades for all children
$recentGrades = [];
foreach ($children as $child) {
    $childGrades = $dataManager->getStudentGrades($child['id']);
    $recentGrades = array_merge($recentGrades, array_slice($childGrades, 0, 3));
}

// Get upcoming assignments for all children
$upcomingAssignments = [];
foreach ($children as $child) {
    $childAssignments = $dataManager->getAssignments(null, $child['id']);
    $upcomingAssignments = array_merge($upcomingAssignments, array_slice($childAssignments, 0, 3));
}

// Get announcements
$announcements = $dataManager->getAnnouncements(5, 'parent');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - EduManage</title>
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

        /* Children Cards */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .child-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .child-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1);
        }

        .child-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .child-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .child-info h3 {
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .child-info p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .child-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.8rem;
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            background: white;
            border-radius: 1rem;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .tab-btn {
            flex: 1;
            padding: 0.875rem 1rem;
            border: none;
            background: transparent;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: #64748b;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
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

            .children-grid {
                grid-template-columns: 1fr;
            }

            .tab-nav {
                flex-direction: column;
            }

            .child-stats {
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
                        <h1>Welcome, <?= htmlspecialchars($user['first_name']) ?>! 👨‍👩‍👧‍👦</h1>
                        <p>Monitor your children's academic progress and school activities</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            Notifications
                        </button>
                        <button class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i>
                            Schedule Meeting
                        </button>
                    </div>
                </div>
            </div>

            <!-- Children Cards -->
            <div class="children-grid">
                <?php if (empty($children)): ?>
                    <div class="content-card">
                        <div class="empty-state">
                            <i class="fas fa-child"></i>
                            <p>No children registered in the system</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($children as $child): ?>
                        <div class="child-card">
                            <div class="child-header">
                                <div class="child-avatar">
                                    <?= strtoupper(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1)) ?>
                                </div>
                                <div class="child-info">
                                    <h3><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></h3>
                                    <p><?= htmlspecialchars($child['student_id']) ?></p>
                                    <p><?= htmlspecialchars($child['grade_level']) ?> - <?= htmlspecialchars($child['section'] ?? 'No Section') ?></p>
                                </div>
                            </div>
                            
                            <div class="child-stats">
                                <div class="stat-item">
                                    <div class="stat-value">3.8</div>
                                    <div class="stat-label">Current GPA</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">95%</div>
                                    <div class="stat-label">Attendance</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">2</div>
                                    <div class="stat-label">Pending Tasks</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="showTab('grades')">
                    <i class="fas fa-chart-line"></i> Recent Grades
                </button>
                <button class="tab-btn" onclick="showTab('assignments')">
                    <i class="fas fa-tasks"></i> Assignments
                </button>
                <button class="tab-btn" onclick="showTab('announcements')">
                    <i class="fas fa-bullhorn"></i> Announcements
                </button>
                <button class="tab-btn" onclick="showTab('communication')">
                    <i class="fas fa-comments"></i> Communication
                </button>
            </div>

            <!-- Tab Content -->
            <div id="grades" class="tab-content active">
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Recent Grades</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($recentGrades)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <p>No recent grades available</p>
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
            </div>

            <div id="assignments" class="tab-content">
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

            <div id="announcements" class="tab-content">
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">School Announcements</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($announcements)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>No announcements available</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($announcement['title']) ?></h4>
                                    <p><?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>...</p>
                                </div>
                                <div class="item-meta">
                                    <p><?= format_date($announcement['created_at']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="communication" class="tab-content">
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Communication Tools</h3>
                    </div>
                    
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>Communication features coming soon</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Direct messaging with teachers and school staff</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

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
            const cards = document.querySelectorAll('.child-card, .content-card');
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

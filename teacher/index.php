<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require teacher login
require_role('teacher');

$user = get_logged_in_user();
$stats = $dataManager->getDashboardStats('teacher', $user['id']);

// Get teacher-specific data
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

// Get teacher's classes
$classes = $dataManager->getTeacherClasses($user['id']);

// Get teacher's students
$students = $dataManager->getTeacherStudents($user['id']);
$students = array_slice($students, 0, 8);

// Get recent assignments
$stmt = $pdo->prepare("
    SELECT a.*, c.name as class_name, s.name as subject_name
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN subjects s ON c.subject_id = s.id
    WHERE c.teacher_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentAssignments = $stmt->fetchAll();

// Get announcements
$announcements = $dataManager->getAnnouncements(5, 'teacher');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - EduManage</title>
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
            background: #10b981;
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
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-left-color: #10b981;
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
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

        .stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
            color: #10b981;
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

        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .student-card {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
        }

        .student-card:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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

            .tab-nav {
                flex-direction: column;
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
                        <h1>Welcome, <?= htmlspecialchars($user['first_name'] ?? '') ?>! 👨‍🏫</h1>
                        <p>Manage your classes and students effectively</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            Notifications
                        </button>
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Create Assignment
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['total_classes'] ?? 0 ?></div>
                            <div class="stat-label">My Classes</div>
                            <div class="stat-trend">Active this semester</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['total_students'] ?? 0 ?></div>
                            <div class="stat-label">Total Students</div>
                            <div class="stat-trend">Across all classes</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $stats['pending_assignments'] ?? 0 ?></div>
                            <div class="stat-label">Active Assignments</div>
                            <div class="stat-trend">Due this week</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">98%</div>
                            <div class="stat-label">Attendance Rate</div>
                            <div class="stat-trend">↗ +2% this month</div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="showTab('classes')">
                    <i class="fas fa-chalkboard"></i> My Classes
                </button>
                <button class="tab-btn" onclick="showTab('students')">
                    <i class="fas fa-users"></i> Students
                </button>
                <button class="tab-btn" onclick="showTab('assignments')">
                    <i class="fas fa-tasks"></i> Assignments
                </button>
                <button class="tab-btn" onclick="showTab('announcements')">
                    <i class="fas fa-bullhorn"></i> Announcements
                </button>
            </div>

            <!-- Tab Content -->
            <div id="classes" class="tab-content active">
                <div class="content-grid">
                    <div class="content-card">
                        <div class="content-header">
                            <h3 class="content-title">My Classes</h3>
                            <a href="#" class="view-all">View All</a>
                        </div>
                        
                        <?php if (empty($classes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-chalkboard"></i>
                                <p>No classes assigned yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <div class="list-item">
                                    <div class="item-info">
                                        <h4><?= htmlspecialchars($class['name'] ?? '') ?></h4>
                                        <p><?= htmlspecialchars($class['subject_name'] ?? '') ?> - <?= htmlspecialchars($class['section'] ?? 'All Sections') ?></p>
                                    </div>
                                    <div class="item-meta">
                                        <p><?= htmlspecialchars($class['grade_level'] ?? '') ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="students" class="tab-content">
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">My Students</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($students)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No students found</p>
                        </div>
                    <?php else: ?>
                        <div class="student-grid">
                            <?php foreach ($students as $student): ?>
                                <div class="student-card">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) ?>
                                    </div>
                                    <h4><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($student['student_id'] ?? '') ?></p>
                                    <p><?= htmlspecialchars($student['grade_level'] ?? '') ?> - <?= htmlspecialchars($student['section'] ?? '') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="assignments" class="tab-content">
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Recent Assignments</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($recentAssignments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <p>No assignments created yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentAssignments as $assignment): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($assignment['title'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($assignment['class_name'] ?? '') ?> - Due: <?= format_date($assignment['due_date'] ?? '') ?></p>
                                </div>
                                <div class="item-meta">
                                    <p><?= $assignment['max_score'] ?? 0 ?> pts</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="announcements" class="tab-content">
                <div class="content-card">
                    <div class="content-header">
                        <h3 class="content-title">Recent Announcements</h3>
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
                                    <h4><?= htmlspecialchars($announcement['title'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars(substr($announcement['content'] ?? '', 0, 100)) ?>...</p>
                                </div>
                                <div class="item-meta">
                                    <p><?= format_date($announcement['created_at'] ?? '') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
    </script>
</body>
</html>

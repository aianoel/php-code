<?php
/**
 * Sidebar Navigation Component
 * 
 * This file provides a reusable sidebar navigation component that can be included
 * in all user role pages throughout the application.
 * 
 * The sidebar automatically adjusts based on the user's role and displays
 * appropriate navigation links.
 */

// Ensure this file is not accessed directly
if (!defined('ALLOW_ACCESS')) {
    die("Direct access not permitted");
}

// Make sure we have the user data
if (!isset($user) || empty($user)) {
    $user = get_logged_in_user();
}

// Get user role for dynamic menu generation
$role = $user['role'] ?? '';

// Define navigation items for each role
$nav_items = [
    'student' => [
        ['url' => '../student/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../student/grades.php', 'icon' => 'fas fa-chart-line', 'text' => 'Grades'],
        ['url' => '../student/schedule.php', 'icon' => 'fas fa-calendar', 'text' => 'Schedule'],
        ['url' => '../student/assignments.php', 'icon' => 'fas fa-tasks', 'text' => 'Assignments'],
        ['url' => '../student/document-submission.php', 'icon' => 'fas fa-file-upload', 'text' => 'Submit Documents'],
        ['url' => '../student/resources.php', 'icon' => 'fas fa-book', 'text' => 'Resources'],
        ['url' => '../student/messages.php', 'icon' => 'fas fa-comments', 'text' => 'Messages'],
        ['url' => '../student/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
    'teacher' => [
        ['url' => '../teacher/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../teacher/classes.php', 'icon' => 'fas fa-chalkboard', 'text' => 'My Classes'],
        ['url' => '../teacher/students.php', 'icon' => 'fas fa-users', 'text' => 'Students'],
        ['url' => '../teacher/assignments.php', 'icon' => 'fas fa-tasks', 'text' => 'Assignments'],
        ['url' => '../teacher/document-review.php', 'icon' => 'fas fa-file-check', 'text' => 'Document Review'],
        ['url' => '../teacher/quarterly_gradebook.php', 'icon' => 'fas fa-chart-line', 'text' => 'Quarterly Gradebook'],
        ['url' => '../teacher/schedule.php', 'icon' => 'fas fa-calendar', 'text' => 'Schedule'],
        ['url' => '../teacher/resources.php', 'icon' => 'fas fa-folder', 'text' => 'Resources'],
        ['url' => '../teacher/announcements.php', 'icon' => 'fas fa-bullhorn', 'text' => 'Announcements'],
        ['url' => '../teacher/messages.php', 'icon' => 'fas fa-comments', 'text' => 'Messages'],
        ['url' => '../teacher/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
    'admin' => [
        ['url' => '../admin/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../admin/users.php', 'icon' => 'fas fa-users-cog', 'text' => 'User Management'],
        ['url' => '../admin/student-management.php', 'icon' => 'fas fa-user-graduate', 'text' => 'Student Management'],
        ['url' => '../admin/student-passwords.php', 'icon' => 'fas fa-key', 'text' => 'Student Passwords'],
        ['url' => '../admin/academic-setup.php', 'icon' => 'fas fa-school', 'text' => 'Academic Setup'],
        ['url' => '../admin/enrollment-applications.php', 'icon' => 'fas fa-user-plus', 'text' => 'Enrollment Applications'],
        ['url' => '../admin/reports.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Reports'],
        ['url' => '../admin/content-management.php', 'icon' => 'fas fa-file-alt', 'text' => 'Content Management'],
        ['url' => '../admin/document-review.php', 'icon' => 'fas fa-file-check', 'text' => 'Document Review'],
        ['url' => '../admin/landing-page-management.php', 'icon' => 'fas fa-globe', 'text' => 'Landing Page'],
        ['url' => '../admin/communication-tools.php', 'icon' => 'fas fa-envelope', 'text' => 'Communication Tools'],
        ['url' => '../admin/system-settings.php', 'icon' => 'fas fa-cogs', 'text' => 'System Settings'],
    ],
    'parent' => [
        ['url' => '../parent/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../parent/children.php', 'icon' => 'fas fa-child', 'text' => 'My Children'],
        ['url' => '../parent/grades.php', 'icon' => 'fas fa-chart-line', 'text' => 'Grades'],
        ['url' => '../parent/attendance.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Attendance'],
        ['url' => '../parent/payments.php', 'icon' => 'fas fa-money-bill', 'text' => 'Payments'],
        ['url' => '../parent/messages.php', 'icon' => 'fas fa-comments', 'text' => 'Messages'],
        ['url' => '../parent/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
    'registrar' => [
        ['url' => '../registrar/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../registrar/enrollment_applications.php', 'icon' => 'fas fa-user-plus', 'text' => 'Enrollment Applications'],
        ['url' => '../registrar/student_records.php', 'icon' => 'fas fa-user-graduate', 'text' => 'Student Records'],
        ['url' => '../registrar/class_schedules.php', 'icon' => 'fas fa-calendar', 'text' => 'Class Schedules'],
        ['url' => '../registrar/reports.php', 'icon' => 'fas fa-file-alt', 'text' => 'Reports'],
        ['url' => '../registrar/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
    'accounting' => [
        ['url' => '../accounting/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../accounting/enrollment_payments.php', 'icon' => 'fas fa-money-check', 'text' => 'Enrollment Payments'],
        ['url' => '../accounting/invoices.php', 'icon' => 'fas fa-file-invoice', 'text' => 'Invoices'],
        ['url' => '../accounting/reports.php', 'icon' => 'fas fa-chart-pie', 'text' => 'Financial Reports'],
        ['url' => '../accounting/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
    'principal' => [
        ['url' => '../principal/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../principal/teachers.php', 'icon' => 'fas fa-chalkboard-teacher', 'text' => 'Teachers'],
        ['url' => '../principal/students.php', 'icon' => 'fas fa-user-graduate', 'text' => 'Students'],
        ['url' => '../principal/academic_performance.php', 'icon' => 'fas fa-chart-line', 'text' => 'Academic Performance'],
        ['url' => '../principal/announcements.php', 'icon' => 'fas fa-bullhorn', 'text' => 'Announcements'],
        ['url' => '../principal/reports.php', 'icon' => 'fas fa-file-alt', 'text' => 'Reports'],
        ['url' => '../principal/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
    'guidance' => [
        ['url' => '../guidance/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../guidance/student_counseling.php', 'icon' => 'fas fa-hands-helping', 'text' => 'Student Counseling'],
        ['url' => '../guidance/behavior_records.php', 'icon' => 'fas fa-clipboard-list', 'text' => 'Behavior Records'],
        ['url' => '../guidance/appointments.php', 'icon' => 'fas fa-calendar-alt', 'text' => 'Appointments'],
        ['url' => '../guidance/reports.php', 'icon' => 'fas fa-file-alt', 'text' => 'Reports'],
        ['url' => '../guidance/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
    'academic_coordinator' => [
        ['url' => '../academic_coordinator/index.php', 'icon' => 'fas fa-home', 'text' => 'Dashboard'],
        ['url' => '../academic_coordinator/curriculum.php', 'icon' => 'fas fa-book-open', 'text' => 'Curriculum Management'],
        ['url' => '../academic_coordinator/subjects.php', 'icon' => 'fas fa-book', 'text' => 'Subjects'],
        ['url' => '../academic_coordinator/classes.php', 'icon' => 'fas fa-chalkboard', 'text' => 'Classes'],
        ['url' => '../academic_coordinator/schedule_management.php', 'icon' => 'fas fa-calendar-alt', 'text' => 'Schedule Management'],
        ['url' => '../academic_coordinator/teacher_assignments.php', 'icon' => 'fas fa-user-tie', 'text' => 'Teacher Assignments'],
        ['url' => '../academic_coordinator/student_assignments.php', 'icon' => 'fas fa-user-graduate', 'text' => 'Student Assignments'],
        ['url' => '../academic_coordinator/teacher_loads.php', 'icon' => 'fas fa-chalkboard-teacher', 'text' => 'Teacher Loads'],
        ['url' => '../academic_coordinator/profile.php', 'icon' => 'fas fa-user-cog', 'text' => 'Profile'],
    ],
];

// Add logout link to all roles
$logout_link = ['url' => '../auth/logout.php', 'icon' => 'fas fa-sign-out-alt', 'text' => 'Logout'];

// Get the current page filename for highlighting active link
$current_page = basename($_SERVER['PHP_SELF']);

// Role-specific titles and icons
$role_info = [
    'student' => ['title' => 'Student Portal', 'icon' => 'fas fa-graduation-cap'],
    'teacher' => ['title' => 'Teacher Portal', 'icon' => 'fas fa-chalkboard-teacher'],
    'admin' => ['title' => 'Admin Portal', 'icon' => 'fas fa-user-shield'],
    'parent' => ['title' => 'Parent Portal', 'icon' => 'fas fa-user-friends'],
    'registrar' => ['title' => 'Registrar Portal', 'icon' => 'fas fa-id-card'],
    'accounting' => ['title' => 'Accounting Portal', 'icon' => 'fas fa-calculator'],
    'principal' => ['title' => 'Principal Portal', 'icon' => 'fas fa-user-tie'],
    'guidance' => ['title' => 'Guidance Portal', 'icon' => 'fas fa-hands-helping'],
    'academic_coordinator' => ['title' => 'Academic Coordinator Portal', 'icon' => 'fas fa-sitemap'],
];

// Default if role not found
if (!isset($role_info[$role])) {
    $role_info[$role] = ['title' => 'User Portal', 'icon' => 'fas fa-user'];
}

// Add the logout link to all navigation menus
if (isset($nav_items[$role])) {
    $nav_items[$role][] = $logout_link;
}
?>

<!-- Sidebar Navigation -->
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="<?= $role_info[$role]['icon'] ?>"></i>
            <span>EduManage</span>
        </div>
        <div class="user-info">
            <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] ?? '') ?></h3>
            <p><?= htmlspecialchars($role_info[$role]['title']) ?></p>
        </div>
    </div>
    
    <div class="nav-menu">
        <?php if (isset($nav_items[$role])): ?>
            <?php foreach ($nav_items[$role] as $item): ?>
                <a href="<?= $item['url'] ?>" class="nav-item <?= (basename($item['url']) === $current_page) ? 'active' : '' ?>">
                    <i class="<?= $item['icon'] ?>"></i> <?= $item['text'] ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Default navigation if role not found -->
            <a href="../index.php" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="../auth/logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <?php endif; ?>
    </div>
</nav>

<script>
// Mobile sidebar toggle function
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
}

// Add mobile menu button if needed
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 1024 && !document.querySelector('.mobile-menu-btn')) {
        const header = document.querySelector('.header-content') || document.querySelector('.header');
        if (header) {
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.className = 'btn btn-outline mobile-menu-btn';
            menuBtn.onclick = toggleSidebar;
            header.insertBefore(menuBtn, header.firstChild);
        }
    }
    
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
});
</script>

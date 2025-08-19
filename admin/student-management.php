<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('admin');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'reset_password':
                $new_password = $_POST['new_password'];
                $student_id = $_POST['student_id'];
                $student_source = $_POST['student_source'];
                $user_id = null;
                
                // Get user_id based on the source of the student record
                if ($student_source === 'student_record') {
                    // Get user_id from student record
                    $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $user_id = $stmt->fetchColumn();
                } else if ($student_source === 'enrollment_application') {
                    // Get user_id from enrollment application
                    $stmt = $pdo->prepare("
                        SELECT u.id 
                        FROM enrollment_applications ea 
                        JOIN users u ON ea.email = u.email 
                        WHERE ea.id = ? AND u.role = 'student'
                    ");
                    $stmt->execute([$student_id]);
                    $user_id = $stmt->fetchColumn();
                }
                
                if ($user_id) {
                    // Update password
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $result = $stmt->execute([
                        password_hash($new_password, PASSWORD_DEFAULT),
                        $user_id
                    ]);
                    
                    // Log this sensitive action
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $user['id'],
                        'password_reset',
                        'Admin reset password for student ID: ' . $student_id . ' (Source: ' . $student_source . ')',
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $message = $result ? "Password reset successfully!" : "Error resetting password.";
                } else {
                    $message = "Error: Student record not found or no matching user account.";
                }
                break;
        }
    }
}

// Get all students with user details from two sources

// 1. Students from the students table (legacy students)
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.student_id,
        s.grade_level,
        s.section,
        s.strand,
        s.status as student_status,
        u.id as user_id,
        u.username,
        u.email,
        u.first_name,
        u.last_name,
        u.phone,
        u.status as user_status,
        u.created_at,
        'student_record' as source
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.role = 'student' AND s.status = 'enrolled'
    ORDER BY s.grade_level, s.section, u.last_name, u.first_name
");
$stmt->execute();
$students_from_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Students from enrollment applications with approved/enrolled status
$stmt = $pdo->prepare("
    SELECT 
        ea.id,
        ea.application_number as student_id,
        ea.grade_level,
        NULL as section,
        ea.strand,
        ea.status as student_status,
        u.id as user_id,
        u.username,
        u.email,
        ea.first_name,
        ea.last_name,
        u.phone,
        u.status as user_status,
        u.created_at,
        'enrollment_application' as source
    FROM enrollment_applications ea
    JOIN users u ON ea.email = u.email
    WHERE ea.status IN ('approved', 'enrolled') AND u.role = 'student'
    ORDER BY ea.grade_level, ea.last_name, ea.first_name
");
$stmt->execute();
$students_from_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine both sets of students
$students = array_merge($students_from_records, $students_from_applications);

// Get all users who are not students
$stmt = $pdo->prepare("
    SELECT 
        u.*
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    WHERE s.id IS NULL AND u.role != 'student'
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get student statistics from both sources
$stmt = $pdo->prepare("
    SELECT 
        grade_level,
        COUNT(*) as count 
    FROM (
        SELECT grade_level FROM students WHERE status = 'enrolled'
        UNION ALL
        SELECT grade_level FROM enrollment_applications WHERE status IN ('approved', 'enrolled')
    ) as all_students
    GROUP BY grade_level
    ORDER BY grade_level
");
$stmt->execute();
$student_stats = $stmt->fetchAll();

// Get total student count from both sources
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM students WHERE status = 'enrolled') +
        (SELECT COUNT(*) FROM enrollment_applications WHERE status IN ('approved', 'enrolled'))
    as total
");
$stmt->execute();
$total_students = $stmt->fetchColumn();

// Get total users count (non-students)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    WHERE s.id IS NULL AND u.role != 'student'
");
$stmt->execute();
$total_users = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student & User Management - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color: white; padding: 2rem 0; position: fixed; height: 100vh; overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 0 2rem 2rem; border-bottom: 1px solid #334155; }
        .logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .logo i { width: 40px; height: 40px; background: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .user-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .user-info p { font-size: 0.85rem; color: #94a3b8; }
        .nav-menu { padding: 1rem 0; }
        .nav-item { display: block; padding: 0.875rem 2rem; color: #cbd5e1; text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { background: rgba(220, 38, 38, 0.1); color: #dc2626; border-left-color: #dc2626; }
        .nav-item i { width: 20px; margin-right: 0.75rem; }
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .table th { background: #f9fafb; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-inactive { background: #fee2e2; color: #dc2626; }
        .status-suspended { background: #fef3c7; color: #d97706; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .tab-container { margin-bottom: 2rem; }
        .tabs { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .tab { padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; }
        .tab.active { background: #dc2626; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .password-toggle { position: relative; }
        .password-toggle i { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; }
        .password-field { padding-right: 2.5rem !important; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include_sidebar(); ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1>Student & User Management</h1>
                            <p>Manage students and system users separately</p>
                        </div>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?= $total_students ?></h3>
                        <p>Total Students</p>
                    </div>
                    <div class="stat-card">
                        <h3><?= $total_users ?></h3>
                        <p>Total System Users</p>
                    </div>
                    <?php foreach ($student_stats as $stat): ?>
                        <div class="stat-card">
                            <h3><?= $stat['count'] ?></h3>
                            <p>Grade <?= htmlspecialchars($stat['grade_level']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tabs -->
                <div class="tab-container">
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('students')">Students</div>
                        <div class="tab" onclick="switchTab('users')">System Users</div>
                    </div>
                    
                    <!-- Students Tab -->
                    <div id="students" class="tab-content active">
                        <div class="card">
                            <h3>All Students</h3>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Grade Level</th>
                                        <th>Section/Strand</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Password</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 2rem; color: #64748b;">
                                                <i class="fas fa-user-graduate" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                                No students found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($student['student_id']) ?></td>
                                                <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                                <td><?= htmlspecialchars($student['email']) ?></td>
                                                <td><?= htmlspecialchars($student['grade_level']) ?></td>
                                                <td>
                                                    <?php if ($student['section']): ?>
                                                        <?= htmlspecialchars($student['section']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($student['strand']): ?>
                                                        <?php if ($student['section']): ?> / <?php endif; ?>
                                                        <?= htmlspecialchars($student['strand']) ?>
                                                    <?php endif; ?>
                                                    <?php if (!$student['section'] && !$student['strand']): ?>-<?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge" style="background-color: <?= $student['source'] === 'student_record' ? '#e0f2fe' : '#fef3c7' ?>; color: <?= $student['source'] === 'student_record' ? '#0369a1' : '#92400e' ?>; font-size: 0.7rem;">
                                                        <?= $student['source'] === 'student_record' ? 'Legacy' : 'Application' ?>
                                                    </span>
                                                </td>
                                                <td><span class="status-badge status-<?= $student['user_status'] ?>"><?= ucfirst($student['user_status']) ?></span></td>
                                                <td>
                                                    <button onclick="resetPassword(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>', '<?= $student['source'] ?>')" class="btn btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                        <i class="fas fa-key"></i> Reset
                                                    </button>
                                                </td>
                                                <td>
                                                    <button onclick="viewStudent(<?= $student['id'] ?>)" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Users Tab -->
                    <div id="users" class="tab-content">
                        <div class="card">
                            <h3>All System Users</h3>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                                                <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                                No system users found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $u): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                                <td><?= htmlspecialchars($u['email']) ?></td>
                                                <td><?= ucfirst($u['role']) ?></td>
                                                <td><span class="status-badge status-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                                                <td><?= format_date($u['created_at']) ?></td>
                                                <td>
                                                    <button onclick="viewUser(<?= $u['id'] ?>)" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <h3>Reset Student Password</h3>
            <p id="resetPasswordStudentName" style="margin-bottom: 0.5rem;"></p>
            <p id="resetPasswordStudentSource" style="margin-bottom: 1rem; font-size: 0.8rem; color: #64748b;"></p>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="student_id" id="resetPasswordStudentId">
                <input type="hidden" name="student_source" id="resetPasswordStudentSource">
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-toggle">
                        <input type="password" name="new_password" id="newPassword" class="form-control password-field" required>
                        <i class="fas fa-eye" onclick="togglePasswordVisibility('newPassword')"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-toggle">
                        <input type="password" id="confirmPassword" class="form-control password-field" required>
                        <i class="fas fa-eye" onclick="togglePasswordVisibility('confirmPassword')"></i>
                    </div>
                    <div id="passwordMatchError" style="color: #dc2626; font-size: 0.8rem; margin-top: 0.5rem; display: none;">
                        Passwords do not match
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeModal('resetPasswordModal')" class="btn btn-warning">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" id="resetPasswordSubmit" class="btn btn-success">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Student Modal -->
    <div id="viewStudentModal" class="modal">
        <div class="modal-content">
            <h3>Student Details</h3>
            <div id="studentDetails"></div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button onclick="closeModal('viewStudentModal')" class="btn btn-warning">Close</button>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewUserModal" class="modal">
        <div class="modal-content">
            <h3>User Details</h3>
            <div id="userDetails"></div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button onclick="closeModal('viewUserModal')" class="btn btn-warning">Close</button>
            </div>
        </div>
    </div>

    <script>
        const students = <?= json_encode($students) ?>;
        const users = <?= json_encode($users) ?>;
        
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Activate selected tab and content
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab[onclick="switchTab('${tabId}')"]`).classList.add('active');
        }
        
        function resetPassword(studentId, studentName, studentSource) {
            document.getElementById('resetPasswordStudentId').value = studentId;
            document.getElementById('resetPasswordStudentName').textContent = `Student: ${studentName}`;
            document.getElementById('resetPasswordStudentSource').value = studentSource;
            document.getElementById('resetPasswordStudentSource').textContent = `Source: ${studentSource === 'student_record' ? 'Legacy Student Record' : 'Enrollment Application'}`;
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            document.getElementById('passwordMatchError').style.display = 'none';
            openModal('resetPasswordModal');
        }
        
        function viewStudent(studentId) {
            const student = students.find(s => s.id == studentId);
            if (!student) return;
            
            const details = `
                <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Student ID:</span>
                        <span>${student.student_id}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Name:</span>
                        <span>${student.first_name} ${student.last_name}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Email:</span>
                        <span>${student.email}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Username:</span>
                        <span>${student.username}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Source:</span>
                        <span>
                            <span class="status-badge" style="background-color: ${student.source === 'student_record' ? '#e0f2fe' : '#fef3c7'}; color: ${student.source === 'student_record' ? '#0369a1' : '#92400e'}; font-size: 0.7rem;">
                                ${student.source === 'student_record' ? 'Legacy Student Record' : 'Enrollment Application'}
                            </span>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Grade Level:</span>
                        <span>${student.grade_level || 'N/A'}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Section:</span>
                        <span>${student.section || 'N/A'}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Strand:</span>
                        <span>${student.strand || 'N/A'}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Phone:</span>
                        <span>${student.phone || 'N/A'}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Status:</span>
                        <span class="status-badge status-${student.user_status}">${student.user_status.charAt(0).toUpperCase() + student.user_status.slice(1)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Enrollment Date:</span>
                        <span>${student.enrollment_date ? new Date(student.enrollment_date).toLocaleDateString() : 'N/A'}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Created:</span>
                        <span>${new Date(student.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
            `;
            
            document.getElementById('studentDetails').innerHTML = details;
            openModal('viewStudentModal');
        }
        
        function viewUser(userId) {
            const user = users.find(u => u.id == userId);
            if (!user) return;
            
            const details = `
                <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Name:</span>
                        <span>${user.first_name} ${user.last_name}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Email:</span>
                        <span>${user.email}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Username:</span>
                        <span>${user.username}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Role:</span>
                        <span>${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Phone:</span>
                        <span>${user.phone || 'N/A'}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Status:</span>
                        <span class="status-badge status-${user.status}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Created:</span>
                        <span>${new Date(user.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
            `;
            
            document.getElementById('userDetails').innerHTML = details;
            openModal('viewUserModal');
        }
        
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Password confirmation validation
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            const errorElement = document.getElementById('passwordMatchError');
            const submitButton = document.getElementById('resetPasswordSubmit');
            
            if (newPassword !== confirmPassword) {
                errorElement.style.display = 'block';
                submitButton.disabled = true;
            } else {
                errorElement.style.display = 'none';
                submitButton.disabled = false;
            }
        });
        
        document.getElementById('newPassword').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (confirmPassword) {
                const errorElement = document.getElementById('passwordMatchError');
                const submitButton = document.getElementById('resetPasswordSubmit');
                
                if (this.value !== confirmPassword) {
                    errorElement.style.display = 'block';
                    submitButton.disabled = true;
                } else {
                    errorElement.style.display = 'none';
                    submitButton.disabled = false;
                }
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

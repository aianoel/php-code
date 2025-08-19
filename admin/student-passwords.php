<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ensure only admin can access this page
require_role('admin');
$user = get_logged_in_user();

// Log this sensitive action
$stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
$stmt->execute([
    $user['id'],
    'password_view',
    'Admin viewed student passwords',
    $_SERVER['REMOTE_ADDR']
]);

// Get all enrolled students with their credentials from both sources
// 1. Students from students table with user accounts - exclude demo/sample accounts
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.student_id,
        s.grade_level,
        s.section,
        s.strand,
        u.id as user_id,
        u.username,
        u.email,
        u.first_name,
        u.last_name,
        u.status as user_status,
        'student_record' as source
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.role = 'student' 
      AND s.status = 'enrolled'
      AND u.email NOT LIKE '%sample%' 
      AND u.email NOT LIKE '%test%'
      AND u.email NOT LIKE '%demo%'
      AND u.username NOT LIKE '%sample%'
      AND u.username NOT LIKE '%test%'
      AND u.username NOT LIKE '%demo%'
      AND s.student_id NOT LIKE '%SAMPLE%'
      AND s.student_id NOT LIKE '%TEST%'
      AND s.student_id NOT LIKE '%DEMO%'
    ORDER BY s.grade_level, s.section, u.last_name, u.first_name
");
$stmt->execute();
$students_from_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Students from enrollment applications with approved/enrolled status - exclude demo/sample accounts
$stmt = $pdo->prepare("
    SELECT 
        ea.id,
        ea.application_id as student_id,
        ea.grade_level,
        NULL as section,
        ea.strand,
        u.id as user_id,
        u.username,
        u.email,
        ea.first_name,
        ea.last_name,
        u.status as user_status,
        'enrollment_application' as source
    FROM enrollment_applications ea
    JOIN users u ON ea.email = u.email
    WHERE ea.status IN ('approved', 'enrolled') 
      AND u.role = 'student'
      AND ea.email NOT LIKE '%sample%' 
      AND ea.email NOT LIKE '%test%'
      AND ea.email NOT LIKE '%demo%'
      AND u.username NOT LIKE '%sample%'
      AND u.username NOT LIKE '%test%'
      AND u.username NOT LIKE '%demo%'
      AND ea.application_id NOT LIKE '%SAMPLE%'
      AND ea.application_id NOT LIKE '%TEST%'
      AND ea.application_id NOT LIKE '%DEMO%'
      AND ea.first_name NOT LIKE '%Sample%'
      AND ea.first_name NOT LIKE '%Test%'
      AND ea.first_name NOT LIKE '%Demo%'
    ORDER BY ea.grade_level, ea.last_name, ea.first_name
");
$stmt->execute();
$students_from_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine both sets of students
$students = array_merge($students_from_records, $students_from_applications);

// Handle password reset
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $student_id = $_POST['student_id'];
        $student_source = $_POST['student_source'];
        $new_password = $_POST['new_password'];
        $user_id = null;
        
        // Get user_id based on the source of the student record
        if ($student_source === 'student_record') {
            // Get user_id from student record
            $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $user_id = $stmt->fetchColumn();
        } else if ($student_source === 'enrollment_application') {
            // Get user_id from enrollment application
            $stmt = $pdo->prepare("SELECT u.id FROM enrollment_applications ea 
                                  JOIN users u ON ea.email = u.email 
                                  WHERE ea.id = ? AND u.role = 'student'");
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
            
            // Log password reset
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $user['id'],
                'password_reset',
                'Admin reset password for student ID: ' . $student_id . ' (Source: ' . $student_source . ')',
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $message = $result ? "Password reset successfully!" : "Error resetting password.";
        } else {
            $message = "Error: Student record not found.";
        }
    }
}

// Generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Passwords - Admin</title>
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
        .btn-warning { background: #d97706; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
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
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #d97706; border: 1px solid #fef3c7; }
        .password-field { position: relative; }
        .password-toggle { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; }
        .search-box { padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; width: 100%; margin-bottom: 1rem; }
        .filter-container { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .filter-select { padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; flex-grow: 1; }
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
                            <h1>Student Password Management</h1>
                            <p>View and reset student passwords</p>
                        </div>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <strong>Security Notice:</strong> This page allows viewing and resetting student passwords. All actions are logged for security purposes.
                </div>

                <div class="card">
                    <div class="filter-container">
                        <input type="text" id="searchInput" class="search-box" placeholder="Search by name, ID, or email...">
                        <select id="gradeFilter" class="filter-select">
                            <option value="">All Grade Levels</option>
                            <?php
                            // Get unique grade levels from both sources
                            $grade_levels = [];
                            
                            // From students table
                            $stmt = $pdo->prepare("SELECT DISTINCT grade_level FROM students WHERE grade_level IS NOT NULL ORDER BY grade_level");
                            $stmt->execute();
                            while ($row = $stmt->fetch()) {
                                $grade_levels[$row['grade_level']] = $row['grade_level'];
                            }
                            
                            // From enrollment applications
                            $stmt = $pdo->prepare("SELECT DISTINCT grade_level FROM enrollment_applications WHERE grade_level IS NOT NULL AND status IN ('approved', 'enrolled') ORDER BY grade_level");
                            $stmt->execute();
                            while ($row = $stmt->fetch()) {
                                $grade_levels[$row['grade_level']] = $row['grade_level'];
                            }
                            
                            // Output options
                            ksort($grade_levels); // Sort by grade level
                            foreach ($grade_levels as $grade) {
                                echo '<option value="' . htmlspecialchars($grade) . '">' . htmlspecialchars($grade) . '</option>';
                            }
                            ?>
                        </select>
                        <select id="sectionFilter" class="filter-select">
                            <option value="">All Sections</option>
                            <?php
                            // Get unique sections from both sources
                            $sections = [];
                            
                            // From students table
                            $stmt = $pdo->prepare("SELECT DISTINCT section FROM students WHERE section IS NOT NULL ORDER BY section");
                            $stmt->execute();
                            while ($row = $stmt->fetch()) {
                                $sections[$row['section']] = $row['section'];
                            }
                            
                            // Note: enrollment_applications table doesn't have a section column
                            // No need to query for sections from enrollment_applications
                            
                            // Output options
                            ksort($sections); // Sort alphabetically
                            foreach ($sections as $section) {
                                echo '<option value="' . htmlspecialchars($section) . '">' . htmlspecialchars($section) . '</option>';
                            }
                            ?>
                        </select>
                        <select id="strandFilter" class="filter-select">
                            <option value="">All Strands</option>
                            <?php
                            // Get unique strands from both sources
                            $strands = [];
                            
                            // From students table
                            $stmt = $pdo->prepare("SELECT DISTINCT strand FROM students WHERE strand IS NOT NULL ORDER BY strand");
                            $stmt->execute();
                            while ($row = $stmt->fetch()) {
                                $strands[$row['strand']] = $row['strand'];
                            }
                            
                            // From enrollment applications
                            $stmt = $pdo->prepare("SELECT DISTINCT strand FROM enrollment_applications WHERE strand IS NOT NULL AND status IN ('approved', 'enrolled') ORDER BY strand");
                            $stmt->execute();
                            while ($row = $stmt->fetch()) {
                                $strands[$row['strand']] = $row['strand'];
                            }
                            
                            // Output options
                            ksort($strands); // Sort alphabetically
                            foreach ($strands as $strand) {
                                echo '<option value="' . htmlspecialchars($strand) . '">' . htmlspecialchars($strand) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <h3>Student Passwords</h3>
                    <table class="table" id="studentTable">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Grade/Section</th>
                                <th>Strand</th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                        <i class="fas fa-user-graduate" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                        No students found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr data-grade="<?= htmlspecialchars($student['grade_level'] ?? '') ?>" data-section="<?= htmlspecialchars($student['section'] ?? '') ?>" data-strand="<?= htmlspecialchars($student['strand'] ?? '') ?>">
                                        <td><?= htmlspecialchars($student['student_id'] ?? '') ?></td>
                                        <td>
                                            <?= htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?>
                                            <br>
                                            <small><?= htmlspecialchars($student['email'] ?? '') ?></small>
                                            <small class="source-badge" style="<?= $student['source'] == 'student_record' ? 'background: #dbeafe; color: #1e40af;' : 'background: #fef3c7; color: #92400e;' ?> padding: 0.1rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 500; display: inline-block; margin-top: 3px;">
                                                <?= $student['source'] == 'student_record' ? 'Legacy' : 'Application' ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($student['grade_level'] ?? '') ?>
                                            <?php if (!empty($student['section'])): ?>
                                                - <?= htmlspecialchars($student['section'] ?? '') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($student['strand'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($student['username'] ?? '') ?></td>
                                        <td>
                                            <div class="password-field">
                                                <input type="password" id="password_<?= $student['id'] ?>" class="form-control" value="••••••••" readonly style="padding-right: 40px;">
                                                <span class="password-toggle" onclick="getPassword(<?= $student['user_id'] ?>, 'password_<?= $student['id'] ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <button onclick="resetPassword(<?= $student['id'] ?>, '<?= htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?>', '<?= htmlspecialchars($student['source'] ?? '') ?>')" class="btn btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                <i class="fas fa-key"></i> Reset
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <h3>Reset Student Password</h3>
            <p id="resetPasswordStudentName" style="margin-bottom: 1rem;"></p>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="student_id" id="resetPasswordStudentId">
                <input type="hidden" name="student_source" id="resetPasswordStudentSource">
                <div class="form-group">
                    <label>New Password</label>
                    <div style="position: relative;">
                        <input type="text" name="new_password" id="newPassword" class="form-control" required>
                        <button type="button" onclick="generatePassword()" class="btn btn-primary" style="position: absolute; right: 0; top: 0; padding: 0.75rem; border-radius: 0 0.5rem 0.5rem 0;">
                            Generate
                        </button>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeModal('resetPasswordModal')" class="btn btn-warning">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to get actual password from server
        function getPassword(userId, fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = passwordField.nextElementSibling.querySelector('i');
            
            if (passwordField.type === 'password') {
                // Show loading state
                passwordField.value = 'Loading...';
                toggleIcon.className = 'fas fa-spinner fa-spin';
                
                // Make AJAX request to get password
                fetch('get_student_password.php?user_id=' + userId, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        passwordField.type = 'text';
                        passwordField.value = data.password;
                        toggleIcon.className = 'fas fa-eye-slash';
                    } else {
                        passwordField.value = 'Error: ' + data.message;
                        setTimeout(() => {
                            passwordField.type = 'password';
                            passwordField.value = '••••••••';
                            toggleIcon.className = 'fas fa-eye';
                        }, 3000);
                    }
                })
                .catch(error => {
                    passwordField.value = 'Error fetching password';
                    setTimeout(() => {
                        passwordField.type = 'password';
                        passwordField.value = '••••••••';
                        toggleIcon.className = 'fas fa-eye';
                    }, 3000);
                });
            } else {
                passwordField.type = 'password';
                passwordField.value = '••••••••';
                toggleIcon.className = 'fas fa-eye';
            }
        }
        
        function resetPassword(studentId, studentName, studentSource) {
            document.getElementById('resetPasswordStudentId').value = studentId;
            document.getElementById('resetPasswordStudentName').textContent = `Student: ${studentName}`;
            document.getElementById('resetPasswordStudentSource').value = studentSource;
            document.getElementById('newPassword').value = generateRandomPassword();
            openModal('resetPasswordModal');
        }
        
        function generatePassword() {
            document.getElementById('newPassword').value = generateRandomPassword();
        }
        
        function generateRandomPassword(length = 8) {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let password = '';
            for (let i = 0; i < length; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return password;
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Search and filter functionality
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('gradeFilter').addEventListener('change', filterTable);
        document.getElementById('sectionFilter').addEventListener('change', filterTable);
        document.getElementById('strandFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const gradeFilter = document.getElementById('gradeFilter').value;
            const sectionFilter = document.getElementById('sectionFilter').value;
            const strandFilter = document.getElementById('strandFilter').value;
            
            const rows = document.querySelectorAll('#studentTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const grade = row.getAttribute('data-grade');
                const section = row.getAttribute('data-section');
                const strand = row.getAttribute('data-strand');
                
                const matchesSearch = searchValue === '' || text.includes(searchValue);
                const matchesGrade = gradeFilter === '' || grade === gradeFilter;
                const matchesSection = sectionFilter === '' || (section === sectionFilter) || (sectionFilter !== '' && section === 'null' && sectionFilter === 'null');
                const matchesStrand = strandFilter === '' || (strand === strandFilter) || (strandFilter !== '' && (strand === 'null' || strand === '') && strandFilter === 'N/A');
                
                if (matchesSearch && matchesGrade && matchesSection && matchesStrand) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

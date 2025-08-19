<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('teacher');
$user = get_logged_in_user();

// Get teacher profile
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                try {
                    // Update user table
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                    $result1 = $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['address'],
                        $user['id']
                    ]);
                    
                    // Update teacher table
                    if ($teacher) {
                        $stmt = $pdo->prepare("UPDATE teachers SET department = ?, specialization = ?, qualifications = ? WHERE user_id = ?");
                        $result2 = $stmt->execute([
                            $_POST['department'],
                            $_POST['specialization'],
                            $_POST['qualifications'],
                            $user['id']
                        ]);
                    } else {
                        // Create teacher record if doesn't exist
                        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, specialization, qualifications) VALUES (?, ?, ?, ?, ?)");
                        $result2 = $stmt->execute([
                            $user['id'],
                            'T' . str_pad($user['id'], 4, '0', STR_PAD_LEFT),
                            $_POST['department'],
                            $_POST['specialization'],
                            $_POST['qualifications']
                        ]);
                    }
                    
                    $message = ($result1 && $result2) ? "Profile updated successfully!" : "Error updating profile.";
                    
                    // Refresh data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $teacher = $stmt->fetch();
                    
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
            case 'change_password':
                try {
                    // Verify current password
                    if (password_verify($_POST['current_password'], $user['password'])) {
                        if ($_POST['new_password'] === $_POST['confirm_password']) {
                            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $result = $stmt->execute([
                                password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                                $user['id']
                            ]);
                            $message = $result ? "Password changed successfully!" : "Error changing password.";
                        } else {
                            $message = "Error: New passwords do not match.";
                        }
                    } else {
                        $message = "Error: Current password is incorrect.";
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get teacher statistics
$stats = [];
if ($teacher) {
    // Get classes count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
    $stmt->execute([$teacher['id']]);
    $stats['classes'] = $stmt->fetchColumn();
    
    // Get students count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ce.student_id) 
        FROM class_enrollments ce 
        JOIN classes c ON ce.class_id = c.id 
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$teacher['id']]);
    $stats['students'] = $stmt->fetchColumn();
    
    // Get assignments count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM assignments a 
        JOIN classes c ON a.class_id = c.id 
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$teacher['id']]);
    $stats['assignments'] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .profile-header { display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; }
        .profile-info h2 { margin-bottom: 0.5rem; }
        .profile-info p { color: #64748b; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 2rem; }
        .tab { padding: 1rem 2rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #dc2626; color: #dc2626; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #dc2626; }
        .stat-label { color: #64748b; margin-top: 0.5rem; }
    </style>
    <link rel="stylesheet" href="../includes/sidebar.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>My Profile</h1>
                    <p>Manage your personal information and account settings</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                    <p><i class="fas fa-chalkboard-teacher"></i> Teacher</p>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                    <?php if ($teacher): ?>
                        <p><i class="fas fa-id-badge"></i> Employee ID: <?= htmlspecialchars($teacher['employee_id'] ?? 'N/A') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <?php if (!empty($stats)): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['classes'] ?? 0 ?></div>
                        <div class="stat-label">Classes Teaching</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['students'] ?? 0 ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['assignments'] ?? 0 ?></div>
                        <div class="stat-label">Assignments Created</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= date('Y') - date('Y', strtotime($teacher['hire_date'] ?? date('Y-m-d'))) ?></div>
                        <div class="stat-label">Years of Service</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('personal')">
                    <i class="fas fa-user"></i> Personal Information
                </div>
                <div class="tab" onclick="switchTab('professional')">
                    <i class="fas fa-briefcase"></i> Professional Details
                </div>
                <div class="tab" onclick="switchTab('security')">
                    <i class="fas fa-lock"></i> Security Settings
                </div>
            </div>

            <!-- Personal Information Tab -->
            <div id="personal" class="tab-content active">
                <h3>Personal Information</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($teacher['department'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($teacher['specialization'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Qualifications</label>
                        <textarea name="qualifications" class="form-control" rows="4" placeholder="List your educational qualifications and certifications..."><?= htmlspecialchars($teacher['qualifications'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Professional Details Tab -->
            <div id="professional" class="tab-content">
                <h3>Professional Information</h3>
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 0.5rem;">
                    <div class="form-grid">
                        <div>
                            <label style="font-weight: 600;">Employee ID:</label>
                            <p><?= htmlspecialchars($teacher['employee_id'] ?? 'Not assigned') ?></p>
                        </div>
                        <div>
                            <label style="font-weight: 600;">Hire Date:</label>
                            <p><?= $teacher['hire_date'] ? date('F j, Y', strtotime($teacher['hire_date'])) : 'Not specified' ?></p>
                        </div>
                        <div>
                            <label style="font-weight: 600;">Status:</label>
                            <p><?= ucfirst($teacher['status'] ?? 'active') ?></p>
                        </div>
                        <div>
                            <label style="font-weight: 600;">Department:</label>
                            <p><?= htmlspecialchars($teacher['department'] ?? 'Not specified') ?></p>
                        </div>
                    </div>
                    <div style="margin-top: 1rem;">
                        <label style="font-weight: 600;">Specialization:</label>
                        <p><?= htmlspecialchars($teacher['specialization'] ?? 'Not specified') ?></p>
                    </div>
                    <div style="margin-top: 1rem;">
                        <label style="font-weight: 600;">Qualifications:</label>
                        <p><?= nl2br(htmlspecialchars($teacher['qualifications'] ?? 'Not specified')) ?></p>
                    </div>
                </div>
                <div style="margin-top: 1.5rem;">
                    <p style="color: #64748b; font-size: 0.875rem;">
                        <i class="fas fa-info-circle"></i> 
                        To update professional details, please contact the administration office.
                    </p>
                </div>
            </div>

            <!-- Security Settings Tab -->
            <div id="security" class="tab-content">
                <h3>Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>

                <div style="margin-top: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 0.5rem;">
                    <h4>Account Security</h4>
                    <div style="margin-top: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span>Last Login:</span>
                            <span><?= date('M j, Y g:i A', strtotime($user['last_login'] ?? $user['created_at'])) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span>Account Created:</span>
                            <span><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Account Status:</span>
                            <span style="color: #16a34a; font-weight: 600;"><?= ucfirst($user['status']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (newPassword && confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (this.value !== newPassword.value) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>
        </main>
    </div>
</body>
</html>

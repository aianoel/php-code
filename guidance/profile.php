<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('guidance');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                try {
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                    $result = $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['address'],
                        $user['id']
                    ]);
                    $message = $result ? "Profile updated successfully!" : "Error updating profile.";
                    $user = get_logged_in_user();
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'change_password':
                try {
                    if (password_verify($_POST['current_password'], $user['password'])) {
                        if ($_POST['new_password'] === $_POST['confirm_password']) {
                            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $result = $stmt->execute([$hashed_password, $user['id']]);
                            $message = $result ? "Password changed successfully!" : "Error changing password.";
                        } else {
                            $message = "New passwords do not match.";
                        }
                    } else {
                        $message = "Current password is incorrect.";
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Guidance Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 1px solid #e5e7eb; }
        .tab { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #3b82f6; color: #3b82f6; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .profile-header { display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; }
        .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; }
        .profile-info h2 { color: #1f2937; margin-bottom: 0.5rem; }
        .profile-info p { color: #6b7280; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                        </div>
                        <div class="profile-info">
                            <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                            <p>Guidance Counselor</p>
                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                        </div>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('profile')">
                            <i class="fas fa-user"></i> Profile Information
                        </div>
                        <div class="tab" onclick="switchTab('password')">
                            <i class="fas fa-lock"></i> Change Password
                        </div>
                    </div>

                    <div id="profile-tab" class="tab-content active">
                        <h3 style="margin-bottom: 1.5rem;">Update Profile Information</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" 
                                           value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" 
                                           value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <div id="password-tab" class="tab-content">
                        <h3 style="margin-bottom: 1.5rem;">Change Password</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" 
                                       minlength="6" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                       minlength="6" required>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.closest('.tab').classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
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
</body>
</html>

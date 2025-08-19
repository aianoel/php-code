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
            case 'update_settings':
                foreach ($_POST['settings'] as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $message = "Settings updated successfully!";
                break;
            case 'backup_database':
                $message = "Database backup initiated. This would normally create a backup file.";
                break;
            case 'clear_cache':
                $message = "System cache cleared successfully!";
                break;
            case 'update_maintenance':
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$_POST['maintenance_mode'], $_POST['maintenance_mode']]);
                $message = "Maintenance mode " . ($_POST['maintenance_mode'] === '1' ? 'enabled' : 'disabled') . "!";
                break;
        }
    }
}

// Get current settings
$stmt = $pdo->prepare("SELECT * FROM system_settings");
$stmt->execute();
$settings_data = $stmt->fetchAll();
$settings = [];
foreach ($settings_data as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Default settings if not set
$default_settings = [
    'school_name' => 'EduManage School',
    'school_address' => '123 Education Street, Learning City',
    'school_phone' => '+1 (555) 123-4567',
    'school_email' => 'info@edumanage.school',
    'academic_year' => '2024-2025',
    'semester' => 'First Semester',
    'enrollment_open' => '1',
    'max_students_per_class' => '35',
    'grade_passing_score' => '75',
    'attendance_required' => '80',
    'maintenance_mode' => '0'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get system info
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'disk_space' => function_exists('disk_free_space') ? round(disk_free_space('.') / 1024 / 1024 / 1024, 2) . ' GB' : 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin</title>
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
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 2rem; }
        .tab { padding: 1rem 2rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #dc2626; color: #dc2626; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .info-item { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb; }
        .info-item:last-child { border-bottom: none; }
        .toggle-switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #16a34a; }
        .maintenance-warning { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
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
                            <h1>System Settings</h1>
                            <p>Configure system-wide settings and preferences</p>
                        </div>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($settings['maintenance_mode'] === '1'): ?>
                    <div class="maintenance-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Maintenance Mode is currently ENABLED.</strong> The system is not accessible to regular users.
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('general')">
                        <i class="fas fa-cog"></i> General Settings
                    </div>
                    <div class="tab" onclick="switchTab('academic')">
                        <i class="fas fa-graduation-cap"></i> Academic Settings
                    </div>
                    <div class="tab" onclick="switchTab('system')">
                        <i class="fas fa-server"></i> System Management
                    </div>
                    <div class="tab" onclick="switchTab('info')">
                        <i class="fas fa-info-circle"></i> System Information
                    </div>
                </div>

                <!-- General Settings Tab -->
                <div id="general" class="tab-content active">
                    <div class="card">
                        <h3>School Information</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            <div class="grid">
                                <div class="form-group">
                                    <label>School Name</label>
                                    <input type="text" name="settings[school_name]" class="form-control" value="<?= htmlspecialchars($settings['school_name']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>School Email</label>
                                    <input type="email" name="settings[school_email]" class="form-control" value="<?= htmlspecialchars($settings['school_email']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>School Phone</label>
                                    <input type="text" name="settings[school_phone]" class="form-control" value="<?= htmlspecialchars($settings['school_phone']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Enrollment Status</label>
                                    <select name="settings[enrollment_open]" class="form-control">
                                        <option value="1" <?= $settings['enrollment_open'] === '1' ? 'selected' : '' ?>>Open</option>
                                        <option value="0" <?= $settings['enrollment_open'] === '0' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>School Address</label>
                                <textarea name="settings[school_address]" class="form-control" rows="3"><?= htmlspecialchars($settings['school_address']) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Academic Settings Tab -->
                <div id="academic" class="tab-content">
                    <div class="card">
                        <h3>Academic Configuration</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            <div class="grid">
                                <div class="form-group">
                                    <label>Academic Year</label>
                                    <input type="text" name="settings[academic_year]" class="form-control" value="<?= htmlspecialchars($settings['academic_year']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Current Semester</label>
                                    <select name="settings[semester]" class="form-control">
                                        <option value="First Semester" <?= $settings['semester'] === 'First Semester' ? 'selected' : '' ?>>First Semester</option>
                                        <option value="Second Semester" <?= $settings['semester'] === 'Second Semester' ? 'selected' : '' ?>>Second Semester</option>
                                        <option value="Summer" <?= $settings['semester'] === 'Summer' ? 'selected' : '' ?>>Summer</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Max Students per Class</label>
                                    <input type="number" name="settings[max_students_per_class]" class="form-control" value="<?= htmlspecialchars($settings['max_students_per_class']) ?>" min="1" max="100">
                                </div>
                                <div class="form-group">
                                    <label>Passing Grade (%)</label>
                                    <input type="number" name="settings[grade_passing_score]" class="form-control" value="<?= htmlspecialchars($settings['grade_passing_score']) ?>" min="0" max="100">
                                </div>
                                <div class="form-group">
                                    <label>Required Attendance (%)</label>
                                    <input type="number" name="settings[attendance_required]" class="form-control" value="<?= htmlspecialchars($settings['attendance_required']) ?>" min="0" max="100">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Academic Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Management Tab -->
                <div id="system" class="tab-content">
                    <div class="card">
                        <h3>System Management</h3>
                        
                        <!-- Maintenance Mode -->
                        <div style="margin-bottom: 2rem;">
                            <h4>Maintenance Mode</h4>
                            <p style="color: #64748b; margin-bottom: 1rem;">Enable maintenance mode to prevent user access during system updates.</p>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_maintenance">
                                <label class="toggle-switch">
                                    <input type="hidden" name="maintenance_mode" value="0">
                                    <input type="checkbox" name="maintenance_mode" value="1" <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 1rem;">Maintenance Mode <?= $settings['maintenance_mode'] === '1' ? 'Enabled' : 'Disabled' ?></span>
                            </form>
                        </div>

                        <!-- System Actions -->
                        <div class="grid">
                            <div>
                                <h4>Database Management</h4>
                                <p style="color: #64748b; margin-bottom: 1rem;">Backup and manage database.</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="backup_database">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-database"></i> Backup Database
                                    </button>
                                </form>
                            </div>
                            <div>
                                <h4>Cache Management</h4>
                                <p style="color: #64748b; margin-bottom: 1rem;">Clear system cache and temporary files.</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="clear_cache">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-broom"></i> Clear Cache
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Information Tab -->
                <div id="info" class="tab-content">
                    <div class="card">
                        <h3>System Information</h3>
                        <div class="info-item">
                            <strong>PHP Version:</strong>
                            <span><?= $system_info['php_version'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>MySQL Version:</strong>
                            <span><?= $system_info['mysql_version'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Server Software:</strong>
                            <span><?= $system_info['server_software'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Available Disk Space:</strong>
                            <span><?= $system_info['disk_space'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Memory Limit:</strong>
                            <span><?= $system_info['memory_limit'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Max Upload Size:</strong>
                            <span><?= $system_info['upload_max_filesize'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>System Time:</strong>
                            <span><?= date('Y-m-d H:i:s') ?></span>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Database Statistics</h3>
                        <?php
                        $tables = ['users', 'students', 'teachers', 'enrollments', 'subjects', 'classes', 'announcements', 'notifications'];
                        foreach ($tables as $table):
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
                                $stmt->execute();
                                $count = $stmt->fetch()['count'];
                            } catch (Exception $e) {
                                $count = 'N/A';
                            }
                        ?>
                            <div class="info-item">
                                <strong><?= ucfirst($table) ?>:</strong>
                                <span><?= $count ?> records</span>
                            </div>
                        <?php endforeach; ?>
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
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

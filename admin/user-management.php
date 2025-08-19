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
            case 'create_user':
                try {
                    // Check if username already exists
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                    $check_stmt->execute([$_POST['username'], $_POST['email']]);
                    $exists = $check_stmt->fetchColumn();
                    
                    if ($exists > 0) {
                        $message = "Error: Username or email already exists. Please choose different credentials.";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $result = $stmt->execute([
                            $_POST['username'],
                            $_POST['email'],
                            password_hash($_POST['password'], PASSWORD_DEFAULT),
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_POST['role'],
                            $_POST['phone'] ?? '',
                            $_POST['address'] ?? ''
                        ]);
                        $message = $result ? "User created successfully!" : "Error creating user.";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = "Error: Username or email already exists. Please choose different credentials.";
                    } else {
                        $message = "Error creating user: " . $e->getMessage();
                    }
                }
                break;
            case 'update_status':
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $result = $stmt->execute([$_POST['status'], $_POST['user_id']]);
                $message = $result ? "User status updated!" : "Error updating status.";
                break;
            case 'delete_user':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $result = $stmt->execute([$_POST['user_id']]);
                $message = $result ? "User deleted successfully!" : "Error deleting user.";
                break;
        }
    }
}

// Get all users
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt->execute();
$user_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
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
                            <h1>User Management</h1>
                            <p>Manage system users and their permissions</p>
                        </div>
                        <div>
                            <button onclick="openModal('createUserModal')" class="btn btn-success"><i class="fas fa-plus"></i> Add User</button>
                        </div>
                    </div>
                </div>

        <?php if (isset($message)): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="stats-grid">
            <?php foreach ($user_stats as $stat): ?>
                <div class="stat-card">
                    <h3><?= $stat['count'] ?></h3>
                    <p><?= ucfirst($stat['role']) ?>s</p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h3>All Users</h3>
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
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= ucfirst($u['role']) ?></td>
                            <td><span class="status-badge status-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                            <td><?= format_date($u['created_at']) ?></td>
                            <td>
                                <?php if ($u['role'] !== 'admin'): ?>
                                    <button onclick="updateStatus(<?= $u['id'] ?>, '<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>')" 
                                            class="btn <?= $u['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                    <button onclick="deleteUser(<?= $u['id'] ?>)" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <h3>Create New User</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-control" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="academic_coordinator">Academic Coordinator</option>
                        <option value="parent">Parent</option>
                        <option value="guidance">Guidance</option>
                        <option value="registrar">Registrar</option>
                        <option value="accounting">Accounting</option>
                        <option value="principal">Principal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeModal('createUserModal')" class="btn btn-warning">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function updateStatus(userId, status) {
            if (confirm('Are you sure you want to change this user\'s status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
            </div>
        </main>
    </div>
</body>
</html>

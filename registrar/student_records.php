<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('registrar');
$user = get_logged_in_user();

// Get student records
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               CONCAT(s.first_name, ' ', s.last_name) as full_name,
               c.name as class_name, c.grade_level,
               CONCAT(p.first_name, ' ', p.last_name) as parent_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users p ON s.parent_id = p.id
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    $students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - Registrar Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .students-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .students-table th, .students-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .students-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; margin-right: 0.5rem; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; }
        .search-box { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; margin-bottom: 1rem; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-user-graduate"></i> Student Records</h1>
                    <p>Manage and view all student records</p>
                </div>

                <div class="card">
                    <input type="text" class="search-box" placeholder="Search students by name or ID..." id="searchBox">
                    
                    <?php if (empty($students)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No student records</h3>
                            <p>No students are currently enrolled.</p>
                        </div>
                    <?php else: ?>
                        <table class="students-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Class</th>
                                    <th>Parent/Guardian</th>
                                    <th>Enrollment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['student_id']) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($student['full_name']) ?></strong><br>
                                            <small><?= htmlspecialchars($student['email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($student['grade_level']) ?></td>
                                        <td><?= htmlspecialchars($student['class_name'] ?? 'Not assigned') ?></td>
                                        <td><?= htmlspecialchars($student['parent_name'] ?? 'Not assigned') ?></td>
                                        <td><?= date('M j, Y', strtotime($student['enrollment_date'])) ?></td>
                                        <td>
                                            <a href="#" class="btn btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="#" class="btn btn-success">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('searchBox').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('studentsTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            }
        });
    </script>
</body>
</html>

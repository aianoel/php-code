<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('principal');
$user = get_logged_in_user();

// Get student data with statistics
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               CONCAT(s.first_name, ' ', s.last_name) as full_name,
               c.name as class_name, c.grade_level,
               CONCAT(p.first_name, ' ', p.last_name) as parent_name,
               AVG(g.grade) as average_grade
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users p ON s.parent_id = p.id
        LEFT JOIN grades g ON s.id = g.student_id
        GROUP BY s.id
        ORDER BY s.grade_level, s.last_name, s.first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();
    
    // Get grade level statistics
    $stmt = $pdo->prepare("SELECT grade_level, COUNT(*) as count FROM students GROUP BY grade_level ORDER BY grade_level");
    $stmt->execute();
    $grade_stats = $stmt->fetchAll();
    
} catch (Exception $e) {
    $students = [];
    $grade_stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Principal Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #f8fafc; border-radius: 0.75rem; padding: 1rem; text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #3b82f6; }
        .stat-label { color: #64748b; font-size: 0.875rem; }
        .students-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .students-table th, .students-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .students-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .grade-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .grade-excellent { background: #dcfce7; color: #16a34a; }
        .grade-good { background: #dbeafe; color: #2563eb; }
        .grade-average { background: #fef3c7; color: #d97706; }
        .grade-poor { background: #fee2e2; color: #dc2626; }
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
                    <h1><i class="fas fa-user-graduate"></i> Student Body</h1>
                    <p>Overview of all enrolled students and their academic performance</p>
                </div>

                <?php if (!empty($grade_stats)): ?>
                    <div class="card">
                        <h3 style="margin-bottom: 1rem;">Enrollment by Grade Level</h3>
                        <div class="stats-grid">
                            <?php foreach ($grade_stats as $stat): ?>
                                <div class="stat-card">
                                    <div class="stat-value"><?= $stat['count'] ?></div>
                                    <div class="stat-label">Grade <?= htmlspecialchars($stat['grade_level']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <input type="text" class="search-box" placeholder="Search students by name or ID..." id="searchBox">
                    
                    <?php if (empty($students)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No students found</h3>
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
                                    <th>Average Grade</th>
                                    <th>Parent/Guardian</th>
                                    <th>Enrollment Date</th>
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
                                        <td>Grade <?= htmlspecialchars($student['grade_level']) ?></td>
                                        <td><?= htmlspecialchars($student['class_name'] ?? 'Not assigned') ?></td>
                                        <td>
                                            <?php if ($student['average_grade']): ?>
                                                <?php
                                                $avg = round($student['average_grade'], 1);
                                                $grade_class = 'grade-poor';
                                                if ($avg >= 90) $grade_class = 'grade-excellent';
                                                elseif ($avg >= 80) $grade_class = 'grade-good';
                                                elseif ($avg >= 70) $grade_class = 'grade-average';
                                                ?>
                                                <span class="grade-badge <?= $grade_class ?>">
                                                    <?= $avg ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="grade-badge" style="background: #f3f4f6; color: #6b7280;">No grades</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($student['parent_name'] ?? 'Not assigned') ?></td>
                                        <td><?= date('M j, Y', strtotime($student['enrollment_date'])) ?></td>
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

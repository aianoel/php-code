<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('academic_coordinator');
$user = get_logged_in_user();

// Check if units column exists in subjects table
$checkStmt = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'units'");
$unitsExists = $checkStmt->rowCount() > 0;

// Get all teachers with their teaching load
$query = "
    SELECT 
        u.id as user_id,
        u.first_name,
        u.last_name,
        t.employee_id,
        t.department,
        COUNT(DISTINCT c.id) as class_count,
        SUM(" . ($unitsExists ? "COALESCE(s.units, 1)" : "1") . ") as total_units,
        COUNT(DISTINCT ce.student_id) as total_students
    FROM 
        users u
    JOIN 
        teachers t ON u.id = t.user_id
    LEFT JOIN 
        classes c ON u.id = c.teacher_id
    LEFT JOIN 
        subjects s ON c.subject_id = s.id
    LEFT JOIN 
        class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    WHERE 
        u.role = 'teacher' AND u.status = 'active'
    GROUP BY 
        u.id, u.first_name, u.last_name, t.employee_id, t.department
    ORDER BY 
        u.last_name, u.first_name
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$teachers = $stmt->fetchAll();

// Get detailed class assignments for each teacher
$teacherClasses = [];
if (!empty($teachers)) {
    foreach ($teachers as $teacher) {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.section,
                CONCAT(c.schedule_days, ' ', TIME_FORMAT(c.schedule_time_start, '%h:%i %p'), '-', TIME_FORMAT(c.schedule_time_end, '%h:%i %p')) as schedule,
                c.room,
                c.max_students as capacity,
                c.school_year,
                c.semester,
                s.name as subject_name,
                s.code as subject_code,
                " . ($unitsExists ? "s.units" : "1 AS units") . ",
                s.department,
                COUNT(ce.student_id) as enrolled_students
            FROM 
                classes c
            JOIN 
                subjects s ON c.subject_id = s.id
            LEFT JOIN 
                class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
            WHERE 
                c.teacher_id = ? AND c.status = 'active'
            GROUP BY 
                c.id, c.section, c.schedule_days, c.schedule_time_start, c.schedule_time_end, c.room, c.max_students, c.school_year, c.semester,
                s.name, s.code, " . ($unitsExists ? "s.units" : "units") . ", s.department
            ORDER BY 
                s.department, s.name
        ");
        $stmt->execute([$teacher['user_id']]);
        $teacherClasses[$teacher['user_id']] = $stmt->fetchAll();
    }
}

// Group teachers by department
$departmentTeachers = [];
foreach ($teachers as $teacher) {
    $dept = $teacher['department'] ?? 'General';
    $departmentTeachers[$dept][] = $teacher;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Loads - Academic Coordinator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .department-header { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; }
        .teacher-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .teacher-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .teacher-info { display: flex; align-items: center; gap: 1rem; }
        .teacher-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
        .teacher-name h3 { color: #1f2937; margin-bottom: 0.25rem; }
        .teacher-name p { color: #6b7280; font-size: 0.875rem; }
        .teacher-stats { display: flex; gap: 2rem; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #1f2937; }
        .stat-label { color: #6b7280; font-size: 0.75rem; }
        .classes-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .classes-table th, .classes-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .classes-table th { background: #f9fafb; font-weight: 600; font-size: 0.875rem; }
        .classes-table td { font-size: 0.875rem; }
        .subject-badge { background: #e0e7ff; color: #3730a3; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .section-badge { background: #f3f4f6; color: #374151; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .capacity-badge { 
            display: inline-block;
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        .capacity-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
        }
        .overloaded {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
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
                    <h1>Teacher Loads Management</h1>
                    <p>View and analyze teaching load distribution across faculty</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Teaching Load Overview</h2>
            
            <?php if (empty($teachers)): ?>
                <div class="empty-state">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3>No Teachers Found</h3>
                    <p>There are no active teachers in the system</p>
                </div>
            <?php else: ?>
                <?php foreach ($departmentTeachers as $deptName => $deptTeachers): ?>
                    <div class="department-header">
                        <h3><?= htmlspecialchars($deptName) ?> Department</h3>
                        <p><?= count($deptTeachers) ?> teachers</p>
                    </div>
                    
                    <?php foreach ($deptTeachers as $teacher): ?>
                        <div class="teacher-card">
                            <div class="teacher-header">
                                <div class="teacher-info">
                                    <div class="teacher-avatar">
                                        <?= strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)) ?>
                                    </div>
                                    <div class="teacher-name">
                                        <h3><?= isset($teacher['first_name']) ? htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) : '' ?></h3>
                                        <p><?= isset($teacher['employee_id']) ? htmlspecialchars($teacher['employee_id']) : '' ?> • <?= isset($teacher['department']) ? htmlspecialchars($teacher['department']) : 'General' ?></p>
                                    </div>
                                </div>
                                <div class="teacher-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?= $teacher['class_count'] ?></div>
                                        <div class="stat-label">CLASSES</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= $teacher['total_units'] ?></div>
                                        <div class="stat-label">UNITS</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= $teacher['total_students'] ?></div>
                                        <div class="stat-label">STUDENTS</div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($teacherClasses[$teacher['user_id']])): ?>
                                <div style="text-align: center; padding: 1.5rem; color: #6b7280;">
                                    <i class="fas fa-info-circle"></i> No classes assigned to this teacher
                                </div>
                            <?php else: ?>
                                <table class="classes-table">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Section</th>
                                            <th>Schedule</th>
                                            <th>Room</th>
                                            <th>Enrollment</th>
                                            <th>Year/Term</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teacherClasses[$teacher['user_id']] as $class): ?>
                                            <tr>
                                                <td>
                                                    <span class="subject-badge"><?= isset($class['subject_code']) ? htmlspecialchars($class['subject_code']) : '' ?></span><br>
                                                    <small><?= isset($class['subject_name']) ? htmlspecialchars($class['subject_name']) : '' ?></small>
                                                </td>
                                                <td>
                                                    <span class="section-badge"><?= isset($class['section']) ? htmlspecialchars($class['section']) : '' ?></span>
                                                </td>
                                                <td><?= isset($class['schedule']) ? htmlspecialchars($class['schedule']) : '' ?></td>
                                                <td><?= isset($class['room']) ? htmlspecialchars($class['room']) : '' ?></td>
                                                <td>
                                                    <?php 
                                                    $enrolled = isset($class['enrolled_students']) ? $class['enrolled_students'] : 0;
                                                    $capacity = isset($class['capacity']) ? $class['capacity'] : 40;
                                                    $percentage = $capacity > 0 ? ($enrolled / $capacity) * 100 : 0;
                                                    $isOverloaded = $percentage > 90;
                                                    ?>
                                                    <?= $enrolled ?>/<?= $capacity ?> students
                                                    <div class="capacity-badge">
                                                        <div class="capacity-fill <?= $isOverloaded ? 'overloaded' : '' ?>" style="width: <?= min(100, $percentage) ?>%;"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?= isset($class['school_year']) ? htmlspecialchars($class['school_year']) : date('Y') . '-' . (date('Y') + 1) ?><br>
                                                    <small><?= isset($class['semester']) ? htmlspecialchars($class['semester']) : '1st' ?> Semester</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
        </main>
    </div>
</body>
</html>

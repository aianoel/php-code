<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('teacher');
$user = get_logged_in_user();

// Get teacher ID - create if doesn't exist
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    // Create teacher record
    $employeeId = 'T' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, specialization, hire_date, status) VALUES (?, ?, 'General', 'Teaching', CURDATE(), 'active')");
    $stmt->execute([$user['id'], $employeeId]);
    $teacherId = $pdo->lastInsertId();
    $teacher = ['id' => $teacherId];
}

// Get teacher's schedule
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code,
           c.schedule_days, c.schedule_time_start, c.schedule_time_end, c.room
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    JOIN teachers t ON c.teacher_id = t.id
    WHERE t.user_id = ? AND c.status = 'active'
    ORDER BY 
        CASE c.schedule_days 
            WHEN 'Monday' THEN 1
            WHEN 'Tuesday' THEN 2
            WHEN 'Wednesday' THEN 3
            WHEN 'Thursday' THEN 4
            WHEN 'Friday' THEN 5
            WHEN 'Saturday' THEN 6
            WHEN 'Sunday' THEN 7
        END,
        c.schedule_time_start
");
$stmt->execute([$user['id']]);
$schedule = $stmt->fetchAll();

// Organize schedule by day
$weekSchedule = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => [],
    'Saturday' => [],
    'Sunday' => []
];

foreach ($schedule as $class) {
    if (!empty($class['schedule_days'])) {
        // Handle both single day and multiple days (comma-separated)
        $scheduleDays = explode(',', $class['schedule_days']);
        foreach ($scheduleDays as $day) {
            $day = trim($day); // Remove any whitespace
            if (isset($weekSchedule[$day])) { // Make sure it's a valid day
                $weekSchedule[$day][] = $class;
            }
        }
    }
}

// Get today's classes - handle multiple schedule days
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    JOIN teachers t ON c.teacher_id = t.id
    WHERE t.user_id = ?
    AND (c.schedule_days = ? OR c.schedule_days LIKE ? OR c.schedule_days LIKE ? OR c.schedule_days LIKE ?)
    AND c.status = 'active'
    ORDER BY c.schedule_time_start
");

$today = date('l'); // Full day name
$stmt->execute([
    $user['id'], 
    $today,                    // Exact match
    $today . ',%',            // Starts with today
    '%, ' . $today . ',%',    // Contains today in the middle
    '%, ' . $today            // Ends with today
]);
$todayClasses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Teacher</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .schedule-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1rem; }
        .day-column { background: #f8fafc; border-radius: 0.5rem; padding: 1rem; min-height: 400px; }
        .day-header { font-weight: 600; text-align: center; padding: 0.5rem; background: #e2e8f0; border-radius: 0.25rem; margin-bottom: 1rem; }
        .day-header.today { background: #dc2626; color: white; }
        .class-block { background: white; border-radius: 0.5rem; padding: 1rem; margin-bottom: 0.5rem; border-left: 4px solid #3b82f6; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .class-time { font-size: 0.875rem; color: #64748b; font-weight: 600; }
        .class-subject { font-weight: 600; margin: 0.25rem 0; }
        .class-section { font-size: 0.875rem; color: #64748b; }
        .class-room { font-size: 0.875rem; color: #16a34a; }
        .today-classes { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; }
        .today-class-card { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 1rem; padding: 1.5rem; }
        .current-time { background: #fef3c7; color: #d97706; padding: 0.5rem; border-radius: 0.25rem; text-align: center; margin-bottom: 1rem; font-weight: 600; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
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
                    <h1>My Schedule</h1>
                    <p>View your weekly class schedule and today's classes</p>
                </div>
                <div>
                </div>
            </div>
        </div>

        <!-- Current Time -->
        <div class="current-time">
            <i class="fas fa-clock"></i> Current Time: <span id="currentTime"><?= date('l, F j, Y - g:i A') ?></span>
        </div>

        <!-- Today's Classes -->
        <?php if (!empty($todayClasses)): ?>
            <div class="card">
                <h3>Today's Classes (<?= date('l, F j') ?>)</h3>
                <div class="today-classes">
                    <?php foreach ($todayClasses as $class): ?>
                        <div class="today-class-card">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <div style="font-size: 1.25rem; font-weight: 600;"><?= htmlspecialchars($class['subject_name']) ?></div>
                                    <div style="opacity: 0.9;">Section <?= htmlspecialchars($class['section']) ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600;"><?= date('g:i A', strtotime($class['schedule_time_start'])) ?> - <?= date('g:i A', strtotime($class['schedule_time_end'])) ?></div>
                                    <div style="opacity: 0.9;">Room <?= htmlspecialchars($class['room'] ?? 'TBA') ?></div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn" style="background: rgba(255,255,255,0.2); color: white; padding: 0.5rem 1rem;">
                                    <i class="fas fa-users"></i> View Students
                                </button>
                                <button class="btn" style="background: rgba(255,255,255,0.2); color: white; padding: 0.5rem 1rem;">
                                    <i class="fas fa-video"></i> Start Meeting
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Weekly Schedule Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($schedule) ?></h3>
                <p>Total Classes/Week</p>
            </div>
            <div class="stat-card">
                <h3><?= count($todayClasses) ?></h3>
                <p>Classes Today</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_unique(array_column($schedule, 'room'))) ?></h3>
                <p>Different Rooms</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_filter($weekSchedule, function($day) { return !empty($day); })) ?></h3>
                <p>Teaching Days</p>
            </div>
        </div>

        <!-- Weekly Schedule Grid -->
        <div class="card">
            <h3>Weekly Schedule</h3>
            <div class="schedule-grid">
                <?php foreach ($weekSchedule as $day => $classes): ?>
                    <div class="day-column">
                        <div class="day-header <?= $day === $today ? 'today' : '' ?>">
                            <?= $day ?>
                        </div>
                        <?php if (empty($classes)): ?>
                            <div style="text-align: center; color: #64748b; padding: 2rem;">
                                <i class="fas fa-calendar-times" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
                                No classes
                            </div>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <div class="class-block">
                                    <div class="class-time">
                                        <?= date('g:i A', strtotime($class['schedule_time_start'])) ?> - <?= date('g:i A', strtotime($class['schedule_time_end'])) ?>
                                    </div>
                                    <div class="class-subject"><?= htmlspecialchars($class['subject_name']) ?></div>
                                    <div class="class-section">Section <?= htmlspecialchars($class['section']) ?></div>
                                    <div class="class-room">
                                        <i class="fas fa-map-marker-alt"></i> Room <?= htmlspecialchars($class['room'] ?? 'TBA') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h3>Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <button class="btn btn-primary" onclick="printSchedule()">
                    <i class="fas fa-print"></i> Print Schedule
                </button>
                <button class="btn btn-success" onclick="exportSchedule()">
                    <i class="fas fa-download"></i> Export to Calendar
                </button>
                <button class="btn" style="background: #3b82f6; color: white;" onclick="viewAttendance()">
                    <i class="fas fa-check-circle"></i> Mark Attendance
                </button>
                <button class="btn" style="background: #8b5cf6; color: white;" onclick="requestSubstitute()">
                    <i class="fas fa-user-plus"></i> Request Substitute
                </button>
            </div>
        </div>
    </div>

    <script>
        // Update current time every minute
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
        }

        setInterval(updateTime, 60000); // Update every minute

        function printSchedule() {
            window.print();
        }

        function exportSchedule() {
            alert('Export to Calendar\n(This would generate an .ics file for calendar import)');
        }

        function viewAttendance() {
            alert('Mark Attendance\n(This would open attendance marking interface)');
        }

        function requestSubstitute() {
            alert('Request Substitute\n(This would open substitute teacher request form)');
        }

        // Highlight current time slot
        function highlightCurrentClass() {
            const now = new Date();
            const currentTime = now.getHours() * 60 + now.getMinutes();
            
            document.querySelectorAll('.class-block').forEach(block => {
                const timeText = block.querySelector('.class-time').textContent;
                // Parse time range and highlight if current time falls within
                // This is a simplified version - you could make it more sophisticated
            });
        }

        highlightCurrentClass();
        setInterval(highlightCurrentClass, 60000); // Check every minute
    </script>
        </main>
    </div>
</body>
</html>

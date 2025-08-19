<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('academic_coordinator');
$user = get_logged_in_user();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_schedule':
                try {
                    // Validate inputs
                    if (empty($_POST['class_id'])) {
                        throw new Exception("Class ID is required");
                    }
                    
                    // Check if we're updating an existing class
                    $class_id = intval($_POST['class_id']);
                    
                    // Sanitize inputs
                    $schedule_day = isset($_POST['schedule_day']) ? trim($_POST['schedule_day']) : '';
                    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
                    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
                    $room = isset($_POST['room']) ? trim($_POST['room']) : '';
                    
                    // Update the class schedule
                    $stmt = $pdo->prepare("UPDATE classes SET schedule_days = ?, schedule_time_start = ?, schedule_time_end = ?, room = ? WHERE id = ?");
                    $result = $stmt->execute([
                        $schedule_day,
                        $start_time,
                        $end_time,
                        $room,
                        $class_id
                    ]);
                    
                    if ($result) {
                        $message = "Schedule updated successfully!";
                        // Log the activity
                        log_activity($user['id'], 'updated schedule for class ID: ' . $class_id);
                    } else {
                        $message = "Error updating schedule. No changes were made.";
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all classes with schedule information
$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name, s.code as subject_code, s.department,
           u.first_name as teacher_first, u.last_name as teacher_last,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    GROUP BY c.id
    ORDER BY c.schedule_days, c.schedule_time_start, s.name
");
$stmt->execute();
$classes = $stmt->fetchAll();

// Create schedule grid
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$timeSlots = [];
for ($hour = 7; $hour <= 18; $hour++) {
    $timeSlots[] = sprintf('%02d:00', $hour);
    $timeSlots[] = sprintf('%02d:30', $hour);
}

// Organize classes by day and time
$schedule = [];
foreach ($classes as $class) {
    if (!empty($class['schedule_days']) && !empty($class['schedule_time_start'])) {
        // Handle both single day and multiple days (comma-separated)
        $scheduleDays = explode(',', $class['schedule_days']);
        foreach ($scheduleDays as $day) {
            $day = trim($day); // Remove any whitespace
            if (in_array($day, $days)) { // Validate day is in our days array
                $time = substr($class['schedule_time_start'], 0, 5); // HH:MM format
                $schedule[$day][$time] = $class;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Academic Coordinator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        .header { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .schedule-grid { display: grid; grid-template-columns: 100px repeat(6, 1fr); gap: 1px; background: #e5e7eb; border-radius: 0.5rem; overflow: hidden; }
        .time-slot, .day-header, .schedule-cell { background: white; padding: 0.5rem; min-height: 60px; display: flex; align-items: center; justify-content: center; }
        .day-header { background: #f8fafc; font-weight: 600; color: #374151; }
        .time-slot { background: #f8fafc; font-size: 0.875rem; color: #6b7280; writing-mode: vertical-rl; text-orientation: mixed; }
        .schedule-cell { position: relative; cursor: pointer; transition: background 0.3s; }
        .schedule-cell:hover { background: #f3f4f6; }
        .class-block { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border-radius: 0.25rem; padding: 0.5rem; font-size: 0.75rem; width: 100%; text-align: center; }
        .class-info { margin-bottom: 0.25rem; }
        .class-teacher { opacity: 0.8; font-size: 0.6rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .unscheduled-classes { margin-bottom: 2rem; }
        .class-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 0.5rem; cursor: pointer; }
        .class-card:hover { background: #f1f5f9; }
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
                    <h1>Schedule Management</h1>
                    <p>Create and manage class schedules from Monday to Saturday</p>
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

        <!-- Unscheduled Classes -->
        <?php
        $unscheduledClasses = array_filter($classes, function($class) {
            return empty($class['schedule_days']) || empty($class['schedule_time_start']);
        });
        ?>
        
        <?php if (!empty($unscheduledClasses)): ?>
            <div class="card unscheduled-classes">
                <h3>Unscheduled Classes</h3>
                <p style="color: #64748b; margin-bottom: 1rem;">Click on a class to assign schedule</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
                    <?php foreach ($unscheduledClasses as $class): ?>
                        <div class="class-card" onclick="openScheduleModal(<?= $class['id'] ?>, '<?= htmlspecialchars($class['subject_name'] ?? '') ?>', '<?= htmlspecialchars($class['section'] ?? '') ?>')">
                            <strong><?= htmlspecialchars($class['subject_name'] ?? '') ?></strong> - Section <?= htmlspecialchars($class['section'] ?? '') ?><br>
                            <small><?= htmlspecialchars(($class['teacher_first'] ?? '') . ' ' . ($class['teacher_last'] ?? '')) ?> • <?= $class['student_count'] ?? 0 ?> students</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Schedule Grid -->
        <div class="card">
            <h3>Weekly Schedule</h3>
            <div class="schedule-grid">
                <!-- Header row -->
                <div class="time-slot">Time</div>
                <?php foreach ($days as $day): ?>
                    <div class="day-header"><?= $day ?></div>
                <?php endforeach; ?>

                <!-- Time slots and schedule -->
                <?php foreach ($timeSlots as $time): ?>
                    <div class="time-slot"><?= $time ?></div>
                    <?php foreach ($days as $day): ?>
                        <div class="schedule-cell" onclick="openTimeSlotModal('<?= $day ?>', '<?= $time ?>')">
                            <?php if (isset($schedule[$day][$time])): ?>
                                <?php $class = $schedule[$day][$time]; ?>
                                <div class="class-block" onclick="event.stopPropagation(); openScheduleModal(<?= $class['id'] ?>, '<?= htmlspecialchars($class['subject_name'] ?? '') ?>', '<?= htmlspecialchars($class['section'] ?? '') ?>')">
                                    <div class="class-info">
                                        <?= htmlspecialchars($class['subject_code'] ?? '') ?><br>
                                        Section <?= htmlspecialchars($class['section'] ?? '') ?>
                                    </div>
                                    <div class="class-teacher">
                                        <?= htmlspecialchars(($class['teacher_first'] ?? '') . ' ' . ($class['teacher_last'] ?? '')) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <h3>Update Class Schedule</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="class_id" id="modal_class_id">
                
                <div class="form-group">
                    <label>Class</label>
                    <input type="text" id="modal_class_info" class="form-control" readonly>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Day</label>
                        <select name="schedule_day" id="modal_day" class="form-control" required>
                            <option value="">Select Day</option>
                            <?php foreach ($days as $day): ?>
                                <option value="<?= $day ?>"><?= htmlspecialchars($day ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room</label>
                        <input type="text" name="room" id="modal_room" class="form-control" placeholder="e.g., Room 101">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="modal_start_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" id="modal_end_time" class="form-control" required>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('scheduleModal')" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openScheduleModal(classId, subjectName, section) {
            document.getElementById('modal_class_id').value = classId || '';
            document.getElementById('modal_class_info').value = subjectName || '' + ' - Section ' + (section || '');
            
            // Fetch existing schedule data for this class if available
            fetch('get_class_schedule.php?class_id=' + classId)
                .then(response => response.json())
                .then(data => {
                    if (data.schedule_days) {
                        document.getElementById('modal_day').value = data.schedule_days;
                    }
                    if (data.schedule_time_start) {
                        document.getElementById('modal_start_time').value = data.schedule_time_start.substring(0, 5);
                    }
                    if (data.schedule_time_end) {
                        document.getElementById('modal_end_time').value = data.schedule_time_end.substring(0, 5);
                    }
                    if (data.room) {
                        document.getElementById('modal_room').value = data.room;
                    }
                })
                .catch(error => {
                    console.error('Error fetching class data:', error);
                });
            
            document.getElementById('scheduleModal').style.display = 'block';
        }
        
        function openTimeSlotModal(day, time) {
            // Reset the form
            document.getElementById('modal_class_id').value = '';
            document.getElementById('modal_class_info').value = 'New Schedule';
            document.getElementById('modal_room').value = '';
            
            // Set the day and time
            document.getElementById('modal_day').value = day;
            document.getElementById('modal_start_time').value = time;
            
            // Set end time to 1 hour later
            const [hours, minutes] = time.split(':');
            let endHour = parseInt(hours) + 1;
            if (endHour > 23) endHour = 23; // Ensure we don't go past midnight
            document.getElementById('modal_end_time').value = String(endHour).padStart(2, '0') + ':' + minutes;
            
            document.getElementById('scheduleModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
        </main>
    </div>
</body>
</html>

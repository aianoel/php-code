<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('guidance');
$user = get_logged_in_user();

// Get appointments
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id, s.grade_level
        FROM appointments a
        LEFT JOIN students s ON a.student_id = s.id
        WHERE a.counselor_id = ?
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$user['id']]);
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {
    $appointments = [];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_appointment':
                try {
                    $stmt = $pdo->prepare("INSERT INTO appointments (student_id, counselor_id, appointment_date, appointment_time, purpose, status, created_at) VALUES (?, ?, ?, ?, ?, 'scheduled', NOW())");
                    $result = $stmt->execute([
                        $_POST['student_id'],
                        $user['id'],
                        $_POST['appointment_date'],
                        $_POST['appointment_time'],
                        $_POST['purpose']
                    ]);
                    $message = $result ? "Appointment scheduled successfully!" : "Error scheduling appointment.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'update_status':
                try {
                    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND counselor_id = ?");
                    $result = $stmt->execute([$_POST['status'], $_POST['appointment_id'], $user['id']]);
                    $message = $result ? "Appointment status updated!" : "Error updating status.";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get only officially enrolled students for dropdown
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            s.id, 
            CONCAT(s.first_name, ' ', s.last_name) as name, 
            s.student_id, 
            s.grade_level
        FROM students s
        WHERE s.enrollment_status = 'enrolled'
        
        UNION
        
        SELECT DISTINCT 
            ea.id as id,
            CONCAT(ea.first_name, ' ', ea.last_name) as name,
            ea.application_number as student_id,
            ea.grade_level
        FROM enrollment_applications ea
        WHERE ea.status IN ('approved', 'enrolled')
        
        ORDER BY name
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
    <title>Appointments - Guidance Portal</title>
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
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .appointment-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .appointment-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .student-info { font-size: 1.125rem; font-weight: 600; color: #1f2937; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-scheduled { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #dcfce7; color: #16a34a; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .status-no-show { background: #fef3c7; color: #d97706; }
        .appointment-meta { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .appointment-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 600px; max-height: 80vh; overflow-y: auto; }
        .modal.show { display: block; }
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
                            <h1><i class="fas fa-calendar-alt"></i> Appointments</h1>
                            <p>Schedule and manage student counseling appointments</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal()">
                            <i class="fas fa-plus"></i> Schedule Appointment
                        </button>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($appointments)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-calendar-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No appointments scheduled</h3>
                            <p>No appointments have been scheduled yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-header">
                                    <div class="student-info">
                                        <?= htmlspecialchars($appointment['student_name']) ?>
                                        <div style="font-size: 0.875rem; font-weight: normal; color: #6b7280;">
                                            ID: <?= htmlspecialchars($appointment['student_id']) ?> | Grade <?= htmlspecialchars($appointment['grade_level']) ?>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?= $appointment['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $appointment['status'])) ?>
                                    </span>
                                </div>
                                
                                <div class="appointment-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M j, Y', strtotime($appointment['appointment_date'])) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></span>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <strong>Purpose:</strong>
                                    <div style="color: #4b5563; margin-top: 0.5rem;"><?= htmlspecialchars($appointment['purpose']) ?></div>
                                </div>
                                
                                <?php if ($appointment['status'] === 'scheduled'): ?>
                                    <div class="appointment-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm" style="background: #16a34a; color: white;">Mark Completed</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                            <input type="hidden" name="status" value="no_show">
                                            <button type="submit" class="btn btn-sm" style="background: #d97706; color: white;">No Show</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-sm" style="background: #dc2626; color: white;">Cancel</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Schedule Appointment Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Schedule Appointment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_appointment">
                
                <div class="form-group">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" class="form-control" required>
                        <option value="">Select student...</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>">
                                <?= htmlspecialchars($student['name']) ?> (ID: <?= htmlspecialchars($student['student_id']) ?>, Grade <?= htmlspecialchars($student['grade_level']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="appointment_date">Date</label>
                        <input type="date" id="appointment_date" name="appointment_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment_time">Time</label>
                        <input type="time" id="appointment_time" name="appointment_time" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="purpose">Purpose</label>
                    <textarea id="purpose" name="purpose" class="form-control" rows="3" required placeholder="Reason for the appointment..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="hideModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal() {
            document.getElementById('appointmentModal').classList.add('show');
            // Set default date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('appointment_date').value = tomorrow.toISOString().split('T')[0];
        }
        
        function hideModal() {
            document.getElementById('appointmentModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('appointmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>

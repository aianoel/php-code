<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('guidance');
$user = get_logged_in_user();

// Get counseling sessions
try {
    $stmt = $pdo->prepare("
        SELECT cs.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id, s.grade_level
        FROM counseling_sessions cs
        LEFT JOIN students s ON cs.student_id = s.id
        ORDER BY cs.session_date DESC
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll();
} catch (Exception $e) {
    $sessions = [];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'create_session') {
        try {
            $stmt = $pdo->prepare("INSERT INTO counseling_sessions (student_id, counselor_id, session_date, session_type, notes, follow_up_required, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $result = $stmt->execute([
                $_POST['student_id'],
                $user['id'],
                $_POST['session_date'],
                $_POST['session_type'],
                $_POST['notes'],
                isset($_POST['follow_up_required']) ? 1 : 0
            ]);
            $message = $result ? "Counseling session recorded successfully!" : "Error recording session.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
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
    <title>Student Counseling - Guidance Portal</title>
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
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .session-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .session-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .student-info { font-size: 1.125rem; font-weight: 600; color: #1f2937; }
        .session-meta { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .session-type-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .type-academic { background: #dbeafe; color: #2563eb; }
        .type-behavioral { background: #fef3c7; color: #d97706; }
        .type-personal { background: #dcfce7; color: #16a34a; }
        .type-career { background: #f3e8ff; color: #7c3aed; }
        .session-notes { color: #4b5563; line-height: 1.6; }
        .follow-up-badge { background: #fee2e2; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
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
                            <h1><i class="fas fa-hands-helping"></i> Student Counseling</h1>
                            <p>Manage counseling sessions and student support</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal()">
                            <i class="fas fa-plus"></i> New Session
                        </button>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($sessions)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-user-friends" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No counseling sessions</h3>
                            <p>No counseling sessions have been recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                            <div class="session-item">
                                <div class="session-header">
                                    <div class="student-info">
                                        <?= htmlspecialchars($session['student_name']) ?>
                                        <div style="font-size: 0.875rem; font-weight: normal; color: #6b7280;">
                                            ID: <?= htmlspecialchars($session['student_id']) ?> | Grade <?= htmlspecialchars($session['grade_level']) ?>
                                        </div>
                                    </div>
                                    <?php if ($session['follow_up_required']): ?>
                                        <span class="follow-up-badge">Follow-up Required</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="session-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M j, Y g:i A', strtotime($session['session_date'])) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="session-type-badge type-<?= $session['session_type'] ?>">
                                            <?= ucfirst($session['session_type']) ?> Counseling
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="session-notes">
                                    <?= nl2br(htmlspecialchars($session['notes'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Session Modal -->
    <div id="sessionModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Record Counseling Session</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_session">
                
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
                        <label for="session_date">Session Date & Time</label>
                        <input type="datetime-local" id="session_date" name="session_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="session_type">Session Type</label>
                        <select id="session_type" name="session_type" class="form-control" required>
                            <option value="">Select type...</option>
                            <option value="academic">Academic Support</option>
                            <option value="behavioral">Behavioral Counseling</option>
                            <option value="personal">Personal Counseling</option>
                            <option value="career">Career Guidance</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Session Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="6" required placeholder="Record session details, observations, and recommendations..."></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="follow_up_required" value="1">
                        Follow-up session required
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="hideModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Session</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal() {
            document.getElementById('sessionModal').classList.add('show');
            // Set default date to now
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('session_date').value = now.toISOString().slice(0, 16);
        }
        
        function hideModal() {
            document.getElementById('sessionModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('sessionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>

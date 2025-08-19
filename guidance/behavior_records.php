<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('guidance');
$user = get_logged_in_user();

// Get behavior records
try {
    $stmt = $pdo->prepare("
        SELECT br.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id, s.grade_level,
               CONCAT(u.first_name, ' ', u.last_name) as reporter_name
        FROM behavior_records br
        LEFT JOIN students s ON br.student_id = s.id
        LEFT JOIN users u ON br.reported_by = u.id
        ORDER BY br.incident_date DESC
    ");
    $stmt->execute();
    $records = $stmt->fetchAll();
} catch (Exception $e) {
    $records = [];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'create_record') {
        try {
            $stmt = $pdo->prepare("INSERT INTO behavior_records (student_id, incident_date, incident_type, description, action_taken, reported_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $result = $stmt->execute([
                $_POST['student_id'],
                $_POST['incident_date'],
                $_POST['incident_type'],
                $_POST['description'],
                $_POST['action_taken'],
                $user['id']
            ]);
            $message = $result ? "Behavior record created successfully!" : "Error creating record.";
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
    <title>Behavior Records - Guidance Portal</title>
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
        .record-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .record-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .student-info { font-size: 1.125rem; font-weight: 600; color: #1f2937; }
        .incident-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .type-minor { background: #fef3c7; color: #d97706; }
        .type-major { background: #fee2e2; color: #dc2626; }
        .type-positive { background: #dcfce7; color: #16a34a; }
        .record-meta { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
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
                            <h1><i class="fas fa-clipboard-list"></i> Behavior Records</h1>
                            <p>Track and manage student behavior incidents</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal()">
                            <i class="fas fa-plus"></i> New Record
                        </button>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($records)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No behavior records</h3>
                            <p>No behavior incidents have been recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <div class="record-item">
                                <div class="record-header">
                                    <div class="student-info">
                                        <?= htmlspecialchars($record['student_name']) ?>
                                        <div style="font-size: 0.875rem; font-weight: normal; color: #6b7280;">
                                            ID: <?= htmlspecialchars($record['student_id']) ?> | Grade <?= htmlspecialchars($record['grade_level']) ?>
                                        </div>
                                    </div>
                                    <span class="incident-badge type-<?= $record['incident_type'] ?>">
                                        <?= ucfirst($record['incident_type']) ?>
                                    </span>
                                </div>
                                
                                <div class="record-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M j, Y', strtotime($record['incident_date'])) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>Reported by: <?= htmlspecialchars($record['reporter_name']) ?></span>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <strong>Description:</strong>
                                    <div style="color: #4b5563; margin-top: 0.5rem;"><?= nl2br(htmlspecialchars($record['description'])) ?></div>
                                </div>
                                
                                <div>
                                    <strong>Action Taken:</strong>
                                    <div style="color: #4b5563; margin-top: 0.5rem;"><?= nl2br(htmlspecialchars($record['action_taken'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Record Modal -->
    <div id="recordModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Create Behavior Record</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_record">
                
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
                        <label for="incident_date">Incident Date</label>
                        <input type="date" id="incident_date" name="incident_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="incident_type">Incident Type</label>
                        <select id="incident_type" name="incident_type" class="form-control" required>
                            <option value="">Select type...</option>
                            <option value="minor">Minor Infraction</option>
                            <option value="major">Major Infraction</option>
                            <option value="positive">Positive Behavior</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required placeholder="Detailed description of the incident..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="action_taken">Action Taken</label>
                    <textarea id="action_taken" name="action_taken" class="form-control" rows="3" required placeholder="What action was taken in response..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="hideModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Record</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal() {
            document.getElementById('recordModal').classList.add('show');
            document.getElementById('incident_date').value = new Date().toISOString().split('T')[0];
        }
        
        function hideModal() {
            document.getElementById('recordModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('recordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>

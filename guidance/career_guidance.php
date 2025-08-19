<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('guidance');
$user = get_logged_in_user();

// Get career guidance records
try {
    $stmt = $pdo->prepare("
        SELECT cg.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id, s.grade_level
        FROM career_guidance cg
        LEFT JOIN students s ON cg.student_id = s.id
        ORDER BY cg.created_at DESC
    ");
    $stmt->execute();
    $career_records = $stmt->fetchAll();
} catch (Exception $e) {
    $career_records = [];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'create_record') {
        try {
            $stmt = $pdo->prepare("INSERT INTO career_guidance (student_id, counselor_id, career_interest, assessment_results, recommendations, action_plan, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $result = $stmt->execute([
                $_POST['student_id'],
                $user['id'],
                $_POST['career_interest'],
                $_POST['assessment_results'],
                $_POST['recommendations'],
                $_POST['action_plan']
            ]);
            $message = $result ? "Career guidance record created successfully!" : "Error creating record.";
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
    <title>Career Guidance - Guidance Portal</title>
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
        .career-item { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .career-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .student-info { font-size: 1.125rem; font-weight: 600; color: #1f2937; }
        .career-meta { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; color: #6b7280; font-size: 0.875rem; }
        .career-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #f3e8ff; color: #7c3aed; }
        .section { margin-bottom: 1rem; }
        .section-title { font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .section-content { color: #4b5563; line-height: 1.6; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 1rem; max-width: 700px; max-height: 80vh; overflow-y: auto; }
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
                            <h1><i class="fas fa-compass"></i> Career Guidance</h1>
                            <p>Help students explore career paths and plan their future</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal()">
                            <i class="fas fa-plus"></i> New Career Record
                        </button>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($career_records)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-compass" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No career guidance records</h3>
                            <p>No career guidance sessions have been recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($career_records as $record): ?>
                            <div class="career-item">
                                <div class="career-header">
                                    <div class="student-info">
                                        <?= htmlspecialchars($record['student_name']) ?>
                                        <div style="font-size: 0.875rem; font-weight: normal; color: #6b7280;">
                                            ID: <?= htmlspecialchars($record['student_id']) ?> | Grade <?= htmlspecialchars($record['grade_level']) ?>
                                        </div>
                                    </div>
                                    <span class="career-badge"><?= htmlspecialchars($record['career_interest']) ?></span>
                                </div>
                                
                                <div class="career-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M j, Y', strtotime($record['created_at'])) ?></span>
                                    </div>
                                </div>
                                
                                <div class="section">
                                    <div class="section-title">Assessment Results</div>
                                    <div class="section-content"><?= nl2br(htmlspecialchars($record['assessment_results'])) ?></div>
                                </div>
                                
                                <div class="section">
                                    <div class="section-title">Recommendations</div>
                                    <div class="section-content"><?= nl2br(htmlspecialchars($record['recommendations'])) ?></div>
                                </div>
                                
                                <div class="section">
                                    <div class="section-title">Action Plan</div>
                                    <div class="section-content"><?= nl2br(htmlspecialchars($record['action_plan'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Career Record Modal -->
    <div id="careerModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Create Career Guidance Record</h3>
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
                
                <div class="form-group">
                    <label for="career_interest">Career Interest/Field</label>
                    <input type="text" id="career_interest" name="career_interest" class="form-control" required placeholder="e.g., Engineering, Medicine, Arts">
                </div>
                
                <div class="form-group">
                    <label for="assessment_results">Assessment Results</label>
                    <textarea id="assessment_results" name="assessment_results" class="form-control" rows="4" required placeholder="Results from career assessments, aptitude tests, personality evaluations..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="recommendations">Recommendations</label>
                    <textarea id="recommendations" name="recommendations" class="form-control" rows="4" required placeholder="Recommended courses, activities, skills to develop..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="action_plan">Action Plan</label>
                    <textarea id="action_plan" name="action_plan" class="form-control" rows="4" required placeholder="Specific steps and timeline for career preparation..."></textarea>
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
            document.getElementById('careerModal').classList.add('show');
        }
        
        function hideModal() {
            document.getElementById('careerModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('careerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>

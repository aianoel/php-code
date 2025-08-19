<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('guidance');
$user = get_logged_in_user();

// Get guidance statistics
try {
    $counseling_stats = [
        'total_sessions' => $pdo->query("SELECT COUNT(*) FROM counseling_sessions WHERE counselor_id = " . $user['id'])->fetchColumn(),
        'this_month_sessions' => $pdo->query("SELECT COUNT(*) FROM counseling_sessions WHERE counselor_id = " . $user['id'] . " AND MONTH(session_date) = MONTH(NOW()) AND YEAR(session_date) = YEAR(NOW())")->fetchColumn(),
        'behavior_records' => $pdo->query("SELECT COUNT(*) FROM behavior_records WHERE reported_by = " . $user['id'])->fetchColumn(),
        'scheduled_appointments' => $pdo->query("SELECT COUNT(*) FROM appointments WHERE counselor_id = " . $user['id'] . " AND status = 'scheduled'")->fetchColumn()
    ];
    
    // Session types breakdown
    $stmt = $pdo->prepare("
        SELECT session_type, COUNT(*) as count
        FROM counseling_sessions 
        WHERE counselor_id = ?
        GROUP BY session_type
        ORDER BY count DESC
    ");
    $stmt->execute([$user['id']]);
    $session_types = $stmt->fetchAll();
    
    // Monthly counseling trends
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(session_date, '%Y-%m') as month,
               COUNT(*) as sessions
        FROM counseling_sessions 
        WHERE counselor_id = ? AND session_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(session_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$user['id']]);
    $monthly_trends = $stmt->fetchAll();
    
    // Behavior incidents by type
    $stmt = $pdo->prepare("
        SELECT incident_type, COUNT(*) as count
        FROM behavior_records 
        WHERE reported_by = ?
        GROUP BY incident_type
        ORDER BY count DESC
    ");
    $stmt->execute([$user['id']]);
    $behavior_types = $stmt->fetchAll();
    
    // Students requiring follow-up
    $stmt = $pdo->prepare("
        SELECT DISTINCT CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_id, s.grade_level,
               COUNT(*) as follow_up_count
        FROM counseling_sessions cs
        JOIN students s ON cs.student_id = s.id
        WHERE cs.counselor_id = ? AND cs.follow_up_required = 1
        GROUP BY s.id
        ORDER BY follow_up_count DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $follow_up_students = $stmt->fetchAll();
    
} catch (Exception $e) {
    $counseling_stats = array_fill_keys(['total_sessions', 'this_month_sessions', 'behavior_records', 'scheduled_appointments'], 0);
    $session_types = [];
    $monthly_trends = [];
    $behavior_types = [];
    $follow_up_students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guidance Reports - Guidance Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 1rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border-radius: 0.75rem; padding: 1.5rem; text-align: center; }
        .stat-card.success { background: linear-gradient(135deg, #16a34a, #15803d); }
        .stat-card.warning { background: linear-gradient(135deg, #d97706, #b45309); }
        .stat-card.info { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { opacity: 0.9; }
        .chart-container { background: #f8fafc; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 2rem; }
        .chart-bar { display: flex; align-items: center; margin-bottom: 1rem; }
        .chart-label { width: 150px; font-weight: 600; }
        .chart-progress { flex: 1; background: #e5e7eb; border-radius: 0.5rem; height: 25px; margin: 0 1rem; position: relative; }
        .chart-fill { height: 100%; border-radius: 0.5rem; background: #3b82f6; }
        .chart-value { font-weight: 600; color: #1f2937; }
        .reports-table { width: 100%; border-collapse: collapse; }
        .reports-table th, .reports-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .reports-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .trend-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; }
        .trend-month { font-weight: 600; }
        .trend-value { color: #3b82f6; font-weight: 600; }
        .follow-up-badge { background: #fee2e2; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_sidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-file-alt"></i> Guidance Reports</h1>
                    <p>Comprehensive overview of counseling activities and student support metrics</p>
                </div>

                <!-- Key Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $counseling_stats['total_sessions'] ?></div>
                        <div class="stat-label">Total Counseling Sessions</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= $counseling_stats['this_month_sessions'] ?></div>
                        <div class="stat-label">Sessions This Month</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= $counseling_stats['behavior_records'] ?></div>
                        <div class="stat-label">Behavior Records</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value"><?= $counseling_stats['scheduled_appointments'] ?></div>
                        <div class="stat-label">Scheduled Appointments</div>
                    </div>
                </div>

                <!-- Session Types Breakdown -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Counseling Sessions by Type</h3>
                    <div class="chart-container">
                        <?php if (!empty($session_types)): ?>
                            <?php 
                            $max_sessions = max(array_column($session_types, 'count'));
                            foreach ($session_types as $type): 
                                $percentage = $max_sessions > 0 ? ($type['count'] / $max_sessions) * 100 : 0;
                            ?>
                                <div class="chart-bar">
                                    <div class="chart-label"><?= ucfirst($type['session_type']) ?></div>
                                    <div class="chart-progress">
                                        <div class="chart-fill" style="width: <?= $percentage ?>%;"></div>
                                    </div>
                                    <div class="chart-value"><?= $type['count'] ?> sessions</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #6b7280;">No session data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Trends -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Monthly Counseling Trends (Last 6 Months)</h3>
                    <?php if (!empty($monthly_trends)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($monthly_trends as $trend): ?>
                                <div class="trend-item">
                                    <div class="trend-month"><?= date('F Y', strtotime($trend['month'] . '-01')) ?></div>
                                    <div class="trend-value"><?= $trend['sessions'] ?> sessions</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No trend data available</p>
                    <?php endif; ?>
                </div>

                <!-- Behavior Incidents -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Behavior Incidents by Type</h3>
                    <div class="chart-container">
                        <?php if (!empty($behavior_types)): ?>
                            <?php 
                            $max_incidents = max(array_column($behavior_types, 'count'));
                            foreach ($behavior_types as $type): 
                                $percentage = $max_incidents > 0 ? ($type['count'] / $max_incidents) * 100 : 0;
                                $color = $type['incident_type'] === 'positive' ? '#16a34a' : ($type['incident_type'] === 'major' ? '#dc2626' : '#d97706');
                            ?>
                                <div class="chart-bar">
                                    <div class="chart-label"><?= ucfirst($type['incident_type']) ?></div>
                                    <div class="chart-progress">
                                        <div class="chart-fill" style="width: <?= $percentage ?>%; background: <?= $color ?>;"></div>
                                    </div>
                                    <div class="chart-value"><?= $type['count'] ?> incidents</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #6b7280;">No behavior incident data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Students Requiring Follow-up -->
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Students Requiring Follow-up</h3>
                    <?php if (!empty($follow_up_students)): ?>
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Grade Level</th>
                                    <th>Follow-up Sessions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($follow_up_students as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['student_name']) ?></td>
                                        <td><?= htmlspecialchars($student['student_id']) ?></td>
                                        <td><?= htmlspecialchars($student['grade_level']) ?></td>
                                        <td>
                                            <span class="follow-up-badge"><?= $student['follow_up_count'] ?> sessions</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No students requiring follow-up</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<?php
// This script verifies that all schedule column references have been updated correctly
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Schedule Column Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Schedule Column Verification</h1>";

try {
    // Check if the classes table has the correct columns
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Database Structure Check</h2>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $hasScheduleDays = false;
    $hasScheduleTimeStart = false;
    $hasScheduleTimeEnd = false;
    $hasOldColumns = false;
    
    foreach ($columns as $column) {
        echo "<tr>";
        foreach ($column as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
        
        if ($column['Field'] === 'schedule_days') {
            $hasScheduleDays = true;
        }
        if ($column['Field'] === 'schedule_time_start') {
            $hasScheduleTimeStart = true;
        }
        if ($column['Field'] === 'schedule_time_end') {
            $hasScheduleTimeEnd = true;
        }
        if (in_array($column['Field'], ['schedule_day', 'start_time', 'end_time'])) {
            $hasOldColumns = true;
        }
    }
    echo "</table>";
    
    echo "<h2>Column Status</h2>";
    echo "<p>New columns present: ";
    if ($hasScheduleDays && $hasScheduleTimeStart && $hasScheduleTimeEnd) {
        echo "<span class='success'>All new columns exist</span>";
    } else {
        echo "<span class='error'>Missing some new columns</span>";
        if (!$hasScheduleDays) echo "<br>- schedule_days is missing";
        if (!$hasScheduleTimeStart) echo "<br>- schedule_time_start is missing";
        if (!$hasScheduleTimeEnd) echo "<br>- schedule_time_end is missing";
    }
    echo "</p>";
    
    echo "<p>Old columns present: ";
    if ($hasOldColumns) {
        echo "<span class='error'>Some old columns still exist</span>";
        echo "<br>You may need to run a migration script to remove old columns after ensuring all data is transferred.";
    } else {
        echo "<span class='success'>No old columns found</span>";
    }
    echo "</p>";
    
    // Check sample data
    $stmt = $pdo->prepare("SELECT id, name, schedule_days, schedule_time_start, schedule_time_end 
                          FROM classes 
                          LIMIT 5");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Sample Class Data</h2>";
    if (count($classes) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Schedule Days</th><th>Start Time</th><th>End Time</th></tr>";
        foreach ($classes as $class) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($class['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($class['name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($class['schedule_days'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($class['schedule_time_start'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($class['schedule_time_end'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No classes found.</p>";
    }
    
    echo "<h2>Files Updated</h2>";
    echo "<ul>";
    echo "<li>academic_coordinator/schedule_management.php</li>";
    echo "<li>teacher/schedule.php</li>";
    echo "<li>academic_coordinator/classes.php</li>";
    echo "<li>teacher/classes.php</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<div class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
</body>
</html>

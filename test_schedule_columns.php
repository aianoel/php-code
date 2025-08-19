<?php
require_once 'includes/config.php';

try {
    echo "<h2>Schedule Column Test</h2>";
    
    // Check if the classes table has the correct columns
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes LIKE 'schedule_days'");
    $stmt->execute();
    $scheduleDaysExists = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes LIKE 'schedule_time_start'");
    $stmt->execute();
    $scheduleTimeStartExists = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes LIKE 'schedule_time_end'");
    $stmt->execute();
    $scheduleTimeEndExists = $stmt->fetch();
    
    echo "<h3>Database Column Check:</h3>";
    echo "schedule_days column exists: " . ($scheduleDaysExists ? "Yes" : "No") . "<br>";
    echo "schedule_time_start column exists: " . ($scheduleTimeStartExists ? "Yes" : "No") . "<br>";
    echo "schedule_time_end column exists: " . ($scheduleTimeEndExists ? "Yes" : "No") . "<br>";
    
    // Check if there's any class data using these columns
    $stmt = $pdo->prepare("SELECT id, name, schedule_days, schedule_time_start, schedule_time_end 
                          FROM classes 
                          WHERE schedule_days IS NOT NULL 
                          LIMIT 5");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Sample Class Data:</h3>";
    if (count($classes) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Schedule Days</th><th>Start Time</th><th>End Time</th></tr>";
        foreach ($classes as $class) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($class['id']) . "</td>";
            echo "<td>" . htmlspecialchars($class['name']) . "</td>";
            echo "<td>" . htmlspecialchars($class['schedule_days']) . "</td>";
            echo "<td>" . htmlspecialchars($class['schedule_time_start']) . "</td>";
            echo "<td>" . htmlspecialchars($class['schedule_time_end']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No classes found with schedule data.";
    }
    
    // Check for any remaining references to old column names
    echo "<h3>Checking for old column references:</h3>";
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes LIKE 'schedule_day'");
    $stmt->execute();
    $scheduleDayExists = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes LIKE 'start_time'");
    $stmt->execute();
    $startTimeExists = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes LIKE 'end_time'");
    $stmt->execute();
    $endTimeExists = $stmt->fetch();
    
    echo "Old schedule_day column exists: " . ($scheduleDayExists ? "Yes" : "No") . "<br>";
    echo "Old start_time column exists: " . ($startTimeExists ? "Yes" : "No") . "<br>";
    echo "Old end_time column exists: " . ($endTimeExists ? "Yes" : "No") . "<br>";
    
    echo "<p>If any of the old columns still exist, you may need to run a database migration script to remove them.</p>";
    
    echo "<h3>Files Updated:</h3>";
    echo "<ul>";
    echo "<li>academic_coordinator/schedule_management.php</li>";
    echo "<li>teacher/schedule.php</li>";
    echo "<li>academic_coordinator/classes.php</li>";
    echo "<li>teacher/classes.php</li>";
    echo "</ul>";
    
    echo "<p>All files have been updated to use the new column names: schedule_days, schedule_time_start, and schedule_time_end.</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

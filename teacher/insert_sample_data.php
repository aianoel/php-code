<?php
// Database setup
require_once '../includes/config.php';

try {
    // Insert a test class directly for the logged-in teacher
    $stmt = $pdo->prepare("INSERT INTO classes 
        (subject_id, teacher_id, name, section, grade_level, school_year, schedule_days, schedule_time_start, schedule_time_end, room) 
        VALUES 
        (1, 1, 'Test Physics Class', 'A', 'grade11', '2024-2025', 'MWF', '08:00:00', '09:30:00', 'Room 101')");
    $stmt->execute();
    $classId = $pdo->lastInsertId();
    
    echo "Test class created with ID: " . $classId;
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

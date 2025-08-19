<?php
require_once 'includes/config.php';

try {
    // Check if schedule_day column already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM classes LIKE 'schedule_day'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add schedule_day column
        $stmt = $pdo->prepare("ALTER TABLE classes ADD COLUMN schedule_day VARCHAR(20) AFTER schedule_days");
        $stmt->execute();
        
        // Copy data from schedule_days to schedule_day
        $stmt = $pdo->prepare("UPDATE classes SET schedule_day = schedule_days WHERE schedule_days IS NOT NULL");
        $stmt->execute();
        
        echo "Successfully added schedule_day column and copied data from schedule_days.<br>";
    } else {
        echo "The schedule_day column already exists.<br>";
    }
    
    echo "Fix completed successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

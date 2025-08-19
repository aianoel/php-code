<?php
require_once 'includes/config.php';

try {
    // Get all users with teacher role who don't have teacher records
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name 
        FROM users u 
        LEFT JOIN teachers t ON u.id = t.user_id 
        WHERE u.role = 'teacher' AND t.id IS NULL
    ");
    $stmt->execute();
    $teachersWithoutRecords = $stmt->fetchAll();
    
    echo "Found " . count($teachersWithoutRecords) . " teachers without teacher records.\n";
    
    foreach ($teachersWithoutRecords as $user) {
        // Create teacher record
        $employeeId = 'T' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("
            INSERT INTO teachers (user_id, employee_id, department, specialization, hire_date, status) 
            VALUES (?, ?, 'General', 'Teaching', CURDATE(), 'active')
        ");
        
        $result = $stmt->execute([$user['id'], $employeeId]);
        
        if ($result) {
            echo "Created teacher record for: " . $user['first_name'] . " " . $user['last_name'] . " (ID: " . $employeeId . ")\n";
        } else {
            echo "Failed to create teacher record for: " . $user['first_name'] . " " . $user['last_name'] . "\n";
        }
    }
    
    // Verify results
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers");
    $stmt->execute();
    $totalTeachers = $stmt->fetchColumn();
    
    echo "\nTotal teacher records now: " . $totalTeachers . "\n";
    echo "Teacher records creation completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
